<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\User\Entities\User as DomainUser;
use App\Infrastructure\Persistence\Eloquent\Models\DatasetModel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DatasetPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {}

    /**
     * Before hook: global ADMIN bypasses all checks.
     * This matches the AuthorizationService resolution order
     * and covers graficos/fuentes writes via the parent dataset.
     */
    public function before(User $user): ?bool
    {
        if ($this->authorizationService->isGlobalAdmin($this->toDomainUser($user))) {
            return true;
        }

        return null; // Let the method-specific gate decide
    }

    public function create(User $user, string $departamentoId): bool
    {
        return $this->authorizationService->canWriteDataset(
            $this->toDomainUser($user),
            $departamentoId,
        );
    }

    public function view(User $user, DatasetModel $dataset): bool
    {
        return true; // Read is public; scoping is done by queries
    }

    public function update(User $user, DatasetModel $dataset): bool
    {
        return $this->authorizationService->canWriteDataset(
            $this->toDomainUser($user),
            $dataset->departamento_id,
        );
    }

    public function delete(User $user, DatasetModel $dataset): bool
    {
        return $this->authorizationService->canWriteDataset(
            $this->toDomainUser($user),
            $dataset->departamento_id,
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
