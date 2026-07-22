<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Domain\User\Entities\User as DomainUser;
use App\Infrastructure\Persistence\Eloquent\Models\ReporteModel;
use App\Models\User;
use App\Policies\ReportePolicy;
use Mockery\MockInterface;
use Tests\TestCase;

class ReportePolicyTest extends TestCase
{
    private ReportePolicy $policy;
    private AuthorizationService|MockInterface $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = $this->mock(AuthorizationService::class);
        $this->policy = new ReportePolicy($this->authService);
    }

    public function test_anon_cannot_view_privado_reporte(): void
    {
        $user = new User(['id' => 1, 'rol' => 'USER']);
        $reporte = new ReporteModel(['departamento_id' => 'dept-1', 'visibilidad' => 'privado']);

        $this->authService
            ->shouldReceive('canViewReporte')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->view($user, $reporte));
    }

    public function test_suscriptor_can_view_suscriptor_reporte(): void
    {
        $user = new User(['id' => 2, 'rol' => 'SUBSCRIBER']);
        $reporte = new ReporteModel(['departamento_id' => 'dept-1', 'visibilidad' => 'suscriptor']);

        $this->authService
            ->shouldReceive('canViewReporte')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->view($user, $reporte));
    }

    public function test_dept_member_can_view_privado_in_own_dept(): void
    {
        $user = new User(['id' => 3, 'rol' => 'USER']);
        $reporte = new ReporteModel(['departamento_id' => 'dept-1', 'visibilidad' => 'privado']);

        $this->authService
            ->shouldReceive('canViewReporte')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->view($user, $reporte));
    }

    public function test_admin_can_view_all_reportes(): void
    {
        $user = new User(['id' => 4, 'rol' => 'ADMIN']);
        $reporte = new ReporteModel(['departamento_id' => 'dept-1', 'visibilidad' => 'privado']);

        $this->authService
            ->shouldReceive('canViewReporte')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->view($user, $reporte));
    }

    public function test_user_role_cannot_view_suscriptor_reporte(): void
    {
        $user = new User(['id' => 5, 'rol' => 'USER']);
        $reporte = new ReporteModel(['departamento_id' => 'dept-1', 'visibilidad' => 'suscriptor']);

        $this->authService
            ->shouldReceive('canViewReporte')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->view($user, $reporte));
    }

    public function test_dept_editor_can_create_reporte_in_own_dept(): void
    {
        $user = new User(['id' => 20, 'rol' => 'USER']);

        $this->authService
            ->shouldReceive('canWriteReporteInDepartment')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->create($user, 'dept-1'));
    }

    public function test_no_membership_cannot_create_reporte(): void
    {
        $user = new User(['id' => 21, 'rol' => 'USER']);

        $this->authService
            ->shouldReceive('canWriteReporteInDepartment')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->create($user, 'dept-2'));
    }

    public function test_suscriptor_paywall_causes_403_in_controller(): void
    {
        $user = new User(['id' => 6, 'rol' => 'USER']);
        $reporte = new ReporteModel(['departamento_id' => 'dept-1', 'visibilidad' => 'suscriptor']);

        $this->authService
            ->shouldReceive('canViewReporte')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->view($user, $reporte));
    }
}
