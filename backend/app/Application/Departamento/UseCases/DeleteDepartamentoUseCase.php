<?php

declare(strict_types=1);

namespace App\Application\Departamento\UseCases;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Models\User;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteDepartamentoUseCase
{
    public function __construct(
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
        private readonly AuthorizationService $authorizationService,
    ) {}

    public function execute(string $departamentoId, int $userId): bool
    {
        $departamento = $this->departamentoRepository->findById($departamentoId);

        if (!$departamento) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'Departamento no encontrado');
        }

        $eloquentUser = User::find($userId);
        if (!$eloquentUser) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Usuario no encontrado');
        }

        $domainUser = new DomainUser(
            id: $eloquentUser->id,
            name: $eloquentUser->name ?? '',
            email: $eloquentUser->email ?? '',
            rol: $eloquentUser->rol,
        );

        if (!$this->authorizationService->hasDepartmentRole($domainUser, $departamentoId, 'ADMIN')) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Solo los administradores pueden eliminar el departamento');
        }

        return $this->departamentoRepository->delete($departamentoId);
    }
}
