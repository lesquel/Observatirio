<?php

declare(strict_types=1);

namespace App\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\User\Entities\User as DomainUser;
use App\Infrastructure\Persistence\Eloquent\Models\VariableMetadatoModel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class VariablePolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly AuthorizationService $authorizationService,
    ) {}

    public function update(User $user, VariableMetadatoModel $variable): bool
    {
        $departamentoId = $variable->dataset?->departamento_id;

        if ($departamentoId === null) {
            return false;
        }

        return $this->authorizationService->canWriteVariable(
            $this->toDomainUser($user),
            $departamentoId,
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
