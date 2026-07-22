<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\Permiso\Entities\Permiso;
use App\Domain\Permiso\Repositories\PermisoRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthorizationServiceTest extends TestCase
{
    private AuthorizationService $service;
    private PermisoRepositoryInterface|MockInterface $permisoRepo;
    private DepartamentoRepositoryInterface|MockInterface $deptRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permisoRepo = $this->mock(PermisoRepositoryInterface::class);
        $this->deptRepo = $this->mock(DepartamentoRepositoryInterface::class);

        $this->service = new AuthorizationService(
            $this->permisoRepo,
            $this->deptRepo,
        );
    }

    // ========== AUTH-01: Global ADMIN short-circuit ==========

    public function test_global_admin_short_circuits_all_checks(): void
    {
        $admin = new DomainUser(
            id: 1,
            name: 'Admin',
            email: 'admin@test.com',
            password: null,
            rol: 'ADMIN',
        );

        // All these should return true without touching repos
        $this->assertTrue($this->service->isGlobalAdmin($admin));
        $this->assertTrue($this->service->hasDepartmentRole($admin, 'dept-1', 'ADMIN'));
        $this->assertTrue($this->service->hasModulePermission($admin, 'atlas', 'escritura'));
        $this->assertTrue($this->service->canWriteDataset($admin, 'dept-1'));
        $this->assertTrue($this->service->canManageUsers($admin));
    }

    // ========== AUTH-02: Permiso wins over pivot ==========

    public function test_permiso_wins_over_pivot_when_both_exist(): void
    {
        $user = new DomainUser(
            id: 2,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        // Permiso says escritura (pass), pivot says LECTOR (would fail if checked first)
        $this->permisoRepo
            ->shouldReceive('findOne')
            ->with(2, 'atlas', 'dept-1')
            ->once()
            ->andReturn(new Permiso(
                id: 'perm-1',
                userId: 2,
                modulo: 'atlas',
                nivel: 'escritura',
                departamentoId: 'dept-1',
            ));

        // Should NOT call getUserRole because permiso already passed
        $this->deptRepo->shouldNotReceive('getUserRole');

        $this->assertTrue($this->service->canWriteDataset($user, 'dept-1'));
    }

    public function test_permiso_admin_level_allows_write(): void
    {
        $user = new DomainUser(
            id: 3,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        $this->permisoRepo
            ->shouldReceive('findOne')
            ->with(3, 'atlas', 'dept-1')
            ->once()
            ->andReturn(new Permiso(
                id: 'perm-2',
                userId: 3,
                modulo: 'atlas',
                nivel: 'admin',
                departamentoId: 'dept-1',
            ));

        $this->deptRepo->shouldNotReceive('getUserRole');

        $this->assertTrue($this->service->canWriteDataset($user, 'dept-1'));
    }

    // ========== AUTH-02: Pivot fallback when no permiso ==========

    public function test_pivot_fallback_when_no_permiso(): void
    {
        $user = new DomainUser(
            id: 4,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        // No permiso row exists
        $this->permisoRepo
            ->shouldReceive('findOne')
            ->with(4, 'atlas', 'dept-1')
            ->once()
            ->andReturn(null);

        // Pivot says ADMIN → should pass
        $this->deptRepo
            ->shouldReceive('getUserRole')
            ->with('dept-1', 4)
            ->once()
            ->andReturn('ADMIN');

        $this->assertTrue($this->service->canWriteDataset($user, 'dept-1'));
    }

    public function test_pivot_editor_allows_write(): void
    {
        $user = new DomainUser(
            id: 5,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        $this->permisoRepo
            ->shouldReceive('findOne')
            ->with(5, 'atlas', 'dept-1')
            ->once()
            ->andReturn(null);

        $this->deptRepo
            ->shouldReceive('getUserRole')
            ->with('dept-1', 5)
            ->once()
            ->andReturn('EDITOR');

        $this->assertTrue($this->service->canWriteDataset($user, 'dept-1'));
    }

    public function test_pivot_lector_denies_write(): void
    {
        $user = new DomainUser(
            id: 6,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        $this->permisoRepo
            ->shouldReceive('findOne')
            ->with(6, 'atlas', 'dept-1')
            ->once()
            ->andReturn(null);

        $this->deptRepo
            ->shouldReceive('getUserRole')
            ->with('dept-1', 6)
            ->once()
            ->andReturn('LECTOR');

        $this->assertFalse($this->service->canWriteDataset($user, 'dept-1'));
    }

    // ========== getDepartmentRole resolution ==========

    public function test_get_department_role_permiso_wins_over_pivot(): void
    {
        $user = new DomainUser(
            id: 7,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        $this->permisoRepo
            ->shouldReceive('findOne')
            ->with(7, 'atlas', 'dept-1')
            ->once()
            ->andReturn(new Permiso(
                id: 'perm-3',
                userId: 7,
                modulo: 'atlas',
                nivel: 'admin',
                departamentoId: 'dept-1',
            ));

        $result = $this->service->getDepartmentRole($user, 'dept-1');

        $this->assertEquals('ADMIN', $result);
    }

    // ========== canManageUsers ==========

    public function test_non_admin_cannot_manage_users(): void
    {
        $user = new DomainUser(
            id: 8,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        $this->assertFalse($this->service->canManageUsers($user));
    }

    public function test_editor_cannot_manage_users(): void
    {
        $user = new DomainUser(
            id: 9,
            name: 'Test Editor',
            email: 'editor@test.com',
            password: null,
            rol: 'EDITOR',
        );

        $this->assertFalse($this->service->canManageUsers($user));
    }

    // ========== Articulo/Reporte convenience methods ==========

    public function test_can_view_article_admin_bypass(): void
    {
        $admin = new DomainUser(
            id: 10,
            name: 'Admin',
            email: 'admin@test.com',
            password: null,
            rol: 'ADMIN',
        );

        $articuloModel = new \App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel([
            'departamento_id' => 'dept-1',
            'visibilidad' => 'privado',
        ]);

        $this->assertTrue($this->service->canViewArticle($admin, $articuloModel));
    }

    public function test_can_view_reporte_admin_bypass(): void
    {
        $admin = new DomainUser(
            id: 11,
            name: 'Admin',
            email: 'admin@test.com',
            password: null,
            rol: 'ADMIN',
        );

        $reporteModel = new \App\Infrastructure\Persistence\Eloquent\Models\ReporteModel([
            'departamento_id' => 'dept-1',
            'visibilidad' => 'privado',
        ]);

        $this->assertTrue($this->service->canViewReporte($admin, $reporteModel));
    }

    public function test_can_write_article_admin_bypass(): void
    {
        $admin = new DomainUser(
            id: 12,
            name: 'Admin',
            email: 'admin@test.com',
            password: null,
            rol: 'ADMIN',
        );

        $articuloModel = new \App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel([
            'departamento_id' => 'dept-1',
        ]);

        $this->assertTrue($this->service->canWriteArticle($admin, $articuloModel));
    }

    public function test_can_write_reporte_admin_bypass(): void
    {
        $admin = new DomainUser(
            id: 13,
            name: 'Admin',
            email: 'admin@test.com',
            password: null,
            rol: 'ADMIN',
        );

        $reporteModel = new \App\Infrastructure\Persistence\Eloquent\Models\ReporteModel([
            'departamento_id' => 'dept-1',
        ]);

        $this->assertTrue($this->service->canWriteReporte($admin, $reporteModel));
    }

    public function test_can_write_article_permiso_wins(): void
    {
        $user = new DomainUser(
            id: 14,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        $articuloModel = new \App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel([
            'departamento_id' => 'dept-1',
        ]);

        $this->permisoRepo
            ->shouldReceive('findOne')
            ->with(14, 'articulos', 'dept-1')
            ->once()
            ->andReturn(new Permiso(
                id: 'perm-4',
                userId: 14,
                modulo: 'articulos',
                nivel: 'escritura',
                departamentoId: 'dept-1',
            ));

        $this->assertTrue($this->service->canWriteArticle($user, $articuloModel));
    }

    public function test_can_write_variable_admin_bypass(): void
    {
        $admin = new DomainUser(
            id: 15,
            name: 'Admin',
            email: 'admin@test.com',
            password: null,
            rol: 'ADMIN',
        );

        $this->assertTrue($this->service->canWriteVariable($admin, 'dept-1'));
    }

    public function test_can_write_variable_denied_for_no_role(): void
    {
        $user = new DomainUser(
            id: 16,
            name: 'Test User',
            email: 'user@test.com',
            password: null,
            rol: 'USER',
        );

        $this->permisoRepo
            ->shouldReceive('findOne')
            ->with(16, 'atlas', 'dept-1')
            ->once()
            ->andReturn(null);

        $this->deptRepo
            ->shouldReceive('getUserRole')
            ->with('dept-1', 16)
            ->once()
            ->andReturn(null);

        $this->assertFalse($this->service->canWriteVariable($user, 'dept-1'));
    }
}
