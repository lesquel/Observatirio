<?php

declare(strict_types=1);

namespace App\Application\Auth\Services;

use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\Permiso\Entities\Permiso;
use App\Domain\Permiso\Repositories\PermisoRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel;
use App\Infrastructure\Persistence\Eloquent\Models\ReporteModel;

/**
 * Central authorization service for the application.
 *
 * Resolution order for ALL permission checks:
 * 1. Global ADMIN bypass (user.rol === 'ADMIN')
 * 2. Permisos table (granular module-level permissions)
 * 3. Pivot role fallback (usuario_departamento.rol)
 * 4. Deny
 */
class AuthorizationService
{
    private const MODULO_ATLAS = 'atlas';
    private const MODULO_ARTICULOS = 'articulos';
    private const MODULO_REPORTES = 'reportes';
    private const MODULO_OBSERVATORIOS = 'observatorios';

    private const WRITE_LEVELS = ['escritura', 'admin'];

    public function __construct(
        private readonly PermisoRepositoryInterface $permisoRepository,
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
    ) {}

    /**
     * Check if the user has the global ADMIN role (short-circuit).
     */
    public function isGlobalAdmin(DomainUser $user): bool
    {
        return $user->rol === 'ADMIN';
    }

    /**
     * Get the highest effective role for a user within a department.
     * Resolution: global ADMIN bypass → permiso (observatorios / atlas) → pivot fallback.
     */
    public function getDepartmentRole(DomainUser $user, string $departamentoId): ?string
    {
        if ($this->isGlobalAdmin($user)) {
            return 'ADMIN';
        }

        // Check permiso for observatorios module first, then atlas
        $permisoObservatorios = $this->permisoRepository->findOne(
            userId: $user->id,
            modulo: self::MODULO_OBSERVATORIOS,
            departamentoId: $departamentoId,
        );

        if ($permisoObservatorios !== null && $permisoObservatorios->nivel !== 'ninguno') {
            return $this->permisoNivelToRole($permisoObservatorios->nivel);
        }

        $permisoAtlas = $this->permisoRepository->findOne(
            userId: $user->id,
            modulo: self::MODULO_ATLAS,
            departamentoId: $departamentoId,
        );

        if ($permisoAtlas !== null && $permisoAtlas->nivel !== 'ninguno') {
            return $this->permisoNivelToRole($permisoAtlas->nivel);
        }

        // Fallback to pivot role
        return $this->departamentoRepository->getUserRole($departamentoId, $user->id);
    }

    /**
     * Check if a user has at least the specified role within a department.
     */
    public function hasDepartmentRole(DomainUser $user, string $departamentoId, string $minRole): bool
    {
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        $role = $this->getDepartmentRole($user, $departamentoId);

        return $this->roleWeight($role) >= $this->roleWeight($minRole);
    }

    /**
     * Check if a user has module-level permission at or above the required nivel.
     */
    public function hasModulePermission(
        DomainUser $user,
        string $modulo,
        string $minNivel,
        ?string $departamentoId = null,
    ): bool {
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        $permiso = $this->permisoRepository->findOne(
            userId: $user->id,
            modulo: $modulo,
            departamentoId: $departamentoId,
        );

        if ($permiso !== null && $permiso->nivel !== 'ninguno') {
            return $this->nivelWeight($permiso->nivel) >= $this->nivelWeight($minNivel);
        }

        return false;
    }

    // ========== Convenience methods for Policies ==========

    /**
     * Check if user can view an article (visibility-based).
     */
    public function canViewArticle(DomainUser $user, ArticuloModel $articulo): bool
    {
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        // Global atlas or articulos permiso check
        if ($this->hasModulePermission($user, self::MODULO_ATLAS, 'lectura') ||
            $this->hasModulePermission($user, self::MODULO_ARTICULOS, 'lectura')) {
            return true;
        }

        $visibilidad = $articulo->visibilidad ?? 'publico';

        if ($visibilidad === 'publico') {
            return true;
        }

        if ($visibilidad === 'suscriptor' && in_array($user->rol, ['ADMIN', 'EDITOR', 'SUBSCRIBER'], true)) {
            return true;
        }

        if ($visibilidad === 'privado' && $articulo->departamento_id !== null) {
            $deptRole = $this->getDepartmentRole($user, $articulo->departamento_id);
            if ($deptRole !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can write an article (department-level or global permission).
     */
    public function canWriteArticle(DomainUser $user, ArticuloModel $articulo): bool
    {
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        // Global atlas or articulos write permiso check
        if ($this->hasModulePermission($user, self::MODULO_ATLAS, 'escritura') ||
            $this->hasModulePermission($user, self::MODULO_ARTICULOS, 'escritura')) {
            return true;
        }

        $departamentoId = $articulo->departamento_id;
        if ($departamentoId === null) {
            return false;
        }

        // Check articulos or atlas module permiso for department
        $permisoArticulos = $this->permisoRepository->findOne($user->id, self::MODULO_ARTICULOS, $departamentoId);
        if ($permisoArticulos !== null && $permisoArticulos->nivel !== 'ninguno') {
            return in_array($permisoArticulos->nivel, self::WRITE_LEVELS, true);
        }

        $permisoAtlas = $this->permisoRepository->findOne($user->id, self::MODULO_ATLAS, $departamentoId);
        if ($permisoAtlas !== null && $permisoAtlas->nivel !== 'ninguno') {
            return in_array($permisoAtlas->nivel, self::WRITE_LEVELS, true);
        }

        // Fallback to department role
        return $this->hasDepartmentRole($user, $departamentoId, 'EDITOR');
    }

    /**
     * Check if user can view a reporte (visibility-based).
     */
    public function canViewReporte(DomainUser $user, ReporteModel $reporte): bool
    {
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        // Global atlas or reportes permiso check
        if ($this->hasModulePermission($user, self::MODULO_ATLAS, 'lectura') ||
            $this->hasModulePermission($user, self::MODULO_REPORTES, 'lectura')) {
            return true;
        }

        $visibilidad = $reporte->visibilidad ?? 'publico';

        if ($visibilidad === 'publico') {
            return true;
        }

        if ($visibilidad === 'suscriptor' && in_array($user->rol, ['ADMIN', 'EDITOR', 'SUBSCRIBER'], true)) {
            return true;
        }

        if ($visibilidad === 'privado' && $reporte->departamento_id !== null) {
            $deptRole = $this->getDepartmentRole($user, $reporte->departamento_id);
            if ($deptRole !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can write a reporte (department-level or global permission).
     */
    public function canWriteReporte(DomainUser $user, ReporteModel $reporte): bool
    {
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        // Global atlas or reportes write permiso check
        if ($this->hasModulePermission($user, self::MODULO_ATLAS, 'escritura') ||
            $this->hasModulePermission($user, self::MODULO_REPORTES, 'escritura')) {
            return true;
        }

        $departamentoId = $reporte->departamento_id;
        if ($departamentoId === null) {
            return false;
        }

        // Check reportes or atlas module permiso for department
        $permisoReportes = $this->permisoRepository->findOne($user->id, self::MODULO_REPORTES, $departamentoId);
        if ($permisoReportes !== null && $permisoReportes->nivel !== 'ninguno') {
            return in_array($permisoReportes->nivel, self::WRITE_LEVELS, true);
        }

        $permisoAtlas = $this->permisoRepository->findOne($user->id, self::MODULO_ATLAS, $departamentoId);
        if ($permisoAtlas !== null && $permisoAtlas->nivel !== 'ninguno') {
            return in_array($permisoAtlas->nivel, self::WRITE_LEVELS, true);
        }

        // Fallback to department role
        return $this->hasDepartmentRole($user, $departamentoId, 'EDITOR');
    }

    public function canWriteArticleInDepartment(DomainUser $user, ?string $departamentoId): bool
    {
        if ($this->hasModulePermission($user, self::MODULO_ATLAS, 'escritura') ||
            $this->hasModulePermission($user, self::MODULO_ARTICULOS, 'escritura')) {
            return true;
        }
        if ($departamentoId === null) {
            return false;
        }
        return $this->checkWritePermisoOrRole($user, self::MODULO_ARTICULOS, $departamentoId);
    }

    public function canWriteReporteInDepartment(DomainUser $user, ?string $departamentoId): bool
    {
        if ($this->hasModulePermission($user, self::MODULO_ATLAS, 'escritura') ||
            $this->hasModulePermission($user, self::MODULO_REPORTES, 'escritura')) {
            return true;
        }
        if ($departamentoId === null) {
            return false;
        }
        return $this->checkWritePermisoOrRole($user, self::MODULO_REPORTES, $departamentoId);
    }

    public function canWriteObservatorio(DomainUser $user, string $departamentoId): bool
    {
        return $this->checkWritePermisoOrRole($user, self::MODULO_OBSERVATORIOS, $departamentoId);
    }

    /**
     * Check if user can write datasets in a department.
     * Uses getDepartmentRole (permiso → pivot) to avoid double permiso lookup.
     */
    public function canWriteDataset(DomainUser $user, string $departamentoId): bool
    {
        if ($this->isGlobalAdmin($user)) {
            return true;
        }

        $role = $this->getDepartmentRole($user, $departamentoId);

        return $this->roleWeight($role) >= $this->roleWeight('EDITOR');
    }

    /**
     * Check if user can write variables in a department.
     * Same logic as canWriteDataset (variables belong to datasets).
     */
    public function canWriteVariable(DomainUser $user, string $departamentoId): bool
    {
        return $this->canWriteDataset($user, $departamentoId);
    }

    /**
     * Check if user can manage other users (global ADMIN only).
     */
    public function canManageUsers(DomainUser $user): bool
    {
        return $this->isGlobalAdmin($user);
    }

    private function checkWritePermisoOrRole(DomainUser $user, string $modulo, string $departamentoId): bool
    {
        if ($this->isGlobalAdmin($user)) { return true; }
        $permiso = $this->permisoRepository->findOne($user->id, $modulo, $departamentoId);
        if ($permiso !== null && $permiso->nivel !== 'ninguno') {
            return in_array($permiso->nivel, self::WRITE_LEVELS, true);
        }
        return $this->hasDepartmentRole($user, $departamentoId, 'EDITOR');
    }

    // ========== Helper methods ==========

    /**
     * Map permiso nivel to role string.
     */
    private function permisoNivelToRole(string $nivel): ?string
    {
        return match ($nivel) {
            'admin' => 'ADMIN',
            'escritura' => 'EDITOR',
            'lectura' => 'LECTOR',
            default => null,
        };
    }

    /**
     * Get numeric weight for a role (higher = more privileged).
     */
    private function roleWeight(?string $role): int
    {
        return match ($role) {
            'ADMIN' => 3,
            'EDITOR' => 2,
            'LECTOR' => 1,
            default => 0,
        };
    }

    /**
     * Get numeric weight for a permiso nivel.
     */
    private function nivelWeight(string $nivel): int
    {
        return match ($nivel) {
            'admin' => 3,
            'escritura' => 2,
            'lectura' => 1,
            default => 0,
        };
    }
}
