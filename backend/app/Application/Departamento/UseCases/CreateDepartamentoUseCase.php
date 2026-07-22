<?php

declare(strict_types=1);

namespace App\Application\Departamento\UseCases;

use App\Application\Departamento\DTOs\CreateDepartamentoDTO;
use App\Application\Departamento\DTOs\DepartamentoResponseDTO;
use App\Domain\Departamento\Entities\Departamento;
use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateDepartamentoUseCase
{
    public function __construct(
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function execute(CreateDepartamentoDTO $dto): DepartamentoResponseDTO
    {
        $existing = $this->departamentoRepository->findByCodigoInterno($dto->codigoInterno);
        if ($existing) {
            throw ValidationException::withMessages([
                'codigo_interno' => ['El código interno ya está en uso.'],
            ]);
        }

        $departamento = Departamento::create(
            nombre: $dto->nombre,
            codigoInterno: $dto->codigoInterno,
            descripcion: $dto->descripcion,
            publico: $dto->publico,
        );

        $savedDepartamento = $this->departamentoRepository->save($departamento);

        // ADMIN global ya tiene acceso total; no ocupar el cupo de un solo observatorio.
        $creator = User::find($dto->userId);
        if ($creator && $creator->rol !== 'ADMIN') {
            $this->userRepository->syncSingleDepartamento(
                userId: $dto->userId,
                departamentoId: $savedDepartamento->id,
                rol: 'ADMIN'
            );
        }

        return DepartamentoResponseDTO::fromEntity($savedDepartamento, 'ADMIN');
    }
}
