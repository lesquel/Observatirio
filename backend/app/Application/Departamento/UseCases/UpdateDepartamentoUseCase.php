<?php

declare(strict_types=1);

namespace App\Application\Departamento\UseCases;

use App\Application\Auth\Services\AuthorizationService;
use App\Application\Departamento\DTOs\DepartamentoResponseDTO;
use App\Application\Departamento\DTOs\UpdateDepartamentoDTO;
use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Models\User;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UpdateDepartamentoUseCase
{
    public function __construct(
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
        private readonly AuthorizationService $authorizationService,
    ) {}

    public function execute(UpdateDepartamentoDTO $dto): DepartamentoResponseDTO
    {
        $departamento = $this->departamentoRepository->findById($dto->id);

        if (!$departamento) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'Departamento no encontrado');
        }

        $eloquentUser = User::find($dto->userId);
        if (!$eloquentUser) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Usuario no encontrado');
        }

        $domainUser = new DomainUser(
            id: $eloquentUser->id,
            name: $eloquentUser->name ?? '',
            email: $eloquentUser->email ?? '',
            rol: $eloquentUser->rol,
        );

        if (!$this->authorizationService->canWriteObservatorio($domainUser, $dto->id) &&
            !$this->authorizationService->hasDepartmentRole($domainUser, $dto->id, 'EDITOR')) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'No tienes permisos para modificar este observatorio');
        }

        // Actualizar entidad
        $updatedDepartamento = $departamento->update(
            nombre: $dto->nombre,
            codigoInterno: $dto->codigoInterno,
            descripcion: $dto->descripcion,
            icono: $dto->icono,
            publico: $dto->publico,
        );

        // Guardar cambios
        $savedDepartamento = $this->departamentoRepository->update($updatedDepartamento);

        $userRole = $this->authorizationService->getDepartmentRole($domainUser, $dto->id) ?? 'EDITOR';

        return DepartamentoResponseDTO::fromEntity($savedDepartamento, $userRole);
    }
}
