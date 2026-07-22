<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\User\Entities\User as DomainUser;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorizationService->canManageUsers($this->toDomainUser($user));
    }

    public function view(User $user, User $targetUser): bool
    {
        return $this->authorizationService->canManageUsers($this->toDomainUser($user));
    }

    public function create(User $user): bool
    {
        return $this->authorizationService->canManageUsers($this->toDomainUser($user));
    }

    public function update(User $user, User $targetUser): bool
    {
        return $this->authorizationService->canManageUsers($this->toDomainUser($user));
    }

    public function delete(User $user, User $targetUser): bool
    {
        return $this->authorizationService->canManageUsers($this->toDomainUser($user));
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
