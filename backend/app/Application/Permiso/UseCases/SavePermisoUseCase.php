<?php

declare(strict_types=1);

namespace App\Application\Permiso\UseCases;

use App\Application\Permiso\DTOs\PermisoResponseDTO;
use App\Domain\Permiso\Entities\Permiso;
use App\Domain\Permiso\Repositories\PermisoRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use Illuminate\Validation\ValidationException;

class SavePermisoUseCase
{
    public function __construct(
        private readonly PermisoRepositoryInterface $permisoRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Guarda o actualiza un permiso para un usuario.
     * Si el nivel es 'ninguno', elimina el permiso si existe.
     */
    public function execute(int $userId, string $modulo, string $nivel, ?string $departamentoId = null): ?PermisoResponseDTO
    {
        if ($nivel === Permiso::NIVEL_NINGUNO) {
            $existing = $this->permisoRepository->findOne($userId, $modulo, $departamentoId);
            if ($existing) {
                $this->permisoRepository->delete($existing->id);
            }
            return PermisoResponseDTO::fromNivel($userId, $modulo, $nivel, $departamentoId);
        }

        $permiso = Permiso::create(
            userId: $userId,
            modulo: $modulo,
            nivel: $nivel,
            departamentoId: $departamentoId,
        );

        $saved = $this->permisoRepository->save($permiso);

        return PermisoResponseDTO::fromEntity($saved);
    }

    /**
     * Guarda múltiples permisos de una vez (reemplaza todos los del usuario).
     * @param array $permisosData - Array de [modulo, nivel, departamento_id?]
     */
    public function saveAll(int $userId, array $permisosData): array
    {
        $existing = $this->permisoRepository->findByUserId($userId);

        $incomingKeys = [];
        $results = [];
        $observatorioDepartamentoId = null;
        $observatorioNivel = Permiso::NIVEL_NINGUNO;
        $sawObservatorios = false;

        foreach ($permisosData as $data) {
            $modulo = $data['modulo'];
            $nivel = $data['nivel'];
            $departamentoId = $data['departamento_id'] ?? null;

            if ($modulo === Permiso::MODULO_OBSERVATORIOS) {
                $sawObservatorios = true;
                if ($nivel !== Permiso::NIVEL_NINGUNO && empty($departamentoId)) {
                    throw ValidationException::withMessages([
                        'permisos' => ['Debe asignar exactamente un observatorio al usuario.'],
                    ]);
                }
                $observatorioDepartamentoId = $departamentoId;
                $observatorioNivel = $nivel;
            }

            $key = $modulo.'|'.($departamentoId ?? '');
            $incomingKeys[] = $key;

            $result = $this->execute($userId, $modulo, $nivel, $departamentoId);
            if ($result) {
                $results[] = $result;
            }
        }

        foreach ($existing as $permiso) {
            $key = $permiso->modulo.'|'.($permiso->departamentoId ?? '');
            if (! in_array($key, $incomingKeys, true)) {
                $this->permisoRepository->delete($permiso->id);
            }
        }

        if ($sawObservatorios) {
            if ($observatorioNivel !== Permiso::NIVEL_NINGUNO && $observatorioDepartamentoId) {
                $pivotRol = match ($observatorioNivel) {
                    Permiso::NIVEL_ADMIN => 'ADMIN',
                    Permiso::NIVEL_ESCRITURA => 'EDITOR',
                    default => 'LECTOR',
                };
                $this->userRepository->syncSingleDepartamento($userId, $observatorioDepartamentoId, $pivotRol);
            } else {
                $this->userRepository->clearDepartamentos($userId);
            }
        }

        return $results;
    }
}
