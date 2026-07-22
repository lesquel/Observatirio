<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\User\Entities\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    
    public function findByEmail(string $email): ?User;
    
    public function save(User $user): User;
    
    public function update(User $user): User;
    
    public function delete(int $id): bool;
    
    public function attachDepartamento(int $userId, string $departamentoId, string $rol): void;
    
    public function detachDepartamento(int $userId, string $departamentoId): void;

    /**
     * Asigna exactamente un departamento al usuario (reemplaza cualquier asignación previa).
     */
    public function syncSingleDepartamento(int $userId, string $departamentoId, string $rol = 'EDITOR'): void;

    /**
     * Quita todas las asignaciones de departamento del usuario.
     */
    public function clearDepartamentos(int $userId): void;
}
