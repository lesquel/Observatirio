<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\User\Entities\User as DomainUser;
use App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ArticuloPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {}

    public function view(User $user, ArticuloModel $articulo): bool
    {
        return $this->authorizationService->canViewArticle(
            $this->toDomainUser($user),
            $articulo,
        );
    }

    public function create(User $user, ?string $departamentoId): bool
    {
        if ($departamentoId === null) {
            return false;
        }

        return $this->authorizationService->canWriteArticleInDepartment(
            $this->toDomainUser($user),
            $departamentoId,
        );
    }

    public function update(User $user, ArticuloModel $articulo): bool
    {
        return $this->authorizationService->canWriteArticle(
            $this->toDomainUser($user),
            $articulo,
        );
    }

    public function delete(User $user, ArticuloModel $articulo): bool
    {
        return $this->authorizationService->canWriteArticle(
            $this->toDomainUser($user),
            $articulo,
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
