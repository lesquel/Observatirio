<?php

declare(strict_types=1);

namespace App\Application\Permiso\UseCases;

use App\Application\Permiso\DTOs\PermisoResponseDTO;
use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\Permiso\Entities\Permiso;
use App\Domain\Permiso\Repositories\PermisoRepositoryInterface;
use Illuminate\Support\Collection;

class GetPermisosUseCase
{
    public function __construct(
        private readonly PermisoRepositoryInterface $permisoRepository,
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
    ) {}

    /**
     * Obtiene todos los permisos de un usuario.
     * @return Collection<PermisoResponseDTO>
     */
    public function execute(int $userId): Collection
    {
        $permisos = $this->permisoRepository->findByUserId($userId);

        $hasObservatorios = $permisos->contains(fn($p) => $p->modulo === Permiso::MODULO_OBSERVATORIOS);

        if (!$hasObservatorios) {
            $departments = $this->departamentoRepository->findAllByUserId($userId);
            if ($departments->isNotEmpty()) {
                $dept = $departments->first();
                $pivotRole = $this->departamentoRepository->getUserRole($dept->id, $userId);
                if ($pivotRole) {
                    $nivel = match ($pivotRole) {
                        'ADMIN' => Permiso::NIVEL_ADMIN,
                        'EDITOR' => Permiso::NIVEL_ESCRITURA,
                        default => Permiso::NIVEL_LECTURA,
                    };

                    $synthetic = Permiso::create(
                        userId: $userId,
                        modulo: Permiso::MODULO_OBSERVATORIOS,
                        nivel: $nivel,
                        departamentoId: $dept->id
                    );
                    $permisos = $permisos->concat([$synthetic]);
                }
            }
        }

        return $permisos->map(fn($p) => PermisoResponseDTO::fromEntity($p));
    }
}
