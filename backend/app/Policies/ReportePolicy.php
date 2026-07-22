<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\User\Entities\User as DomainUser;
use App\Infrastructure\Persistence\Eloquent\Models\ReporteModel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportePolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {}

    public function view(User $user, ReporteModel $reporte): bool
    {
        return $this->authorizationService->canViewReporte(
            $this->toDomainUser($user),
            $reporte,
        );
    }

    public function create(User $user, ?string $departamentoId): bool
    {
        if ($departamentoId === null) {
            return false;
        }

        return $this->authorizationService->canWriteReporteInDepartment(
            $this->toDomainUser($user),
            $departamentoId,
        );
    }

    public function update(User $user, ReporteModel $reporte): bool
    {
        return $this->authorizationService->canWriteReporte(
            $this->toDomainUser($user),
            $reporte,
        );
    }

    public function delete(User $user, ReporteModel $reporte): bool
    {
        return $this->authorizationService->canWriteReporte(
            $this->toDomainUser($user),
            $reporte,
        );
    }

    private function toDomainUser(User $user): DomainUser
    {
        return new DomainUser(
            id: $user->id,
            name: $user->name ?? '',
            email: $user->email ?? '',
            rol: $user->rol,
        );
    }
}
