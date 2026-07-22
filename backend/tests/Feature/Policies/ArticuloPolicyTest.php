<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel;
use App\Infrastructure\Persistence\Eloquent\Models\ReporteModel;
use App\Models\User;
use App\Policies\ArticuloPolicy;
use App\Policies\ReportePolicy;
use Mockery\MockInterface;
use Tests\TestCase;

class ArticuloPolicyTest extends TestCase
{
    private ArticuloPolicy $policy;
    private AuthorizationService|MockInterface $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = $this->mock(AuthorizationService::class);
        $this->policy = new ArticuloPolicy($this->authService);
    }

    public function test_anon_cannot_view_privado_article(): void
    {
        $user = new User();

        $this->authService
            ->shouldReceive('canViewArticle')
            ->once()
            ->andReturn(false);

        $articulo = new ArticuloModel();
        $this->assertFalse($this->policy->view($user, $articulo));
    }

    public function test_suscriptor_can_view_suscriptor_article(): void
    {
        $user = new User();

        $this->authService
            ->shouldReceive('canViewArticle')
            ->once()
            ->andReturn(true);

        $articulo = new ArticuloModel(['visibilidad' => 'suscriptor']);
        $this->assertTrue($this->policy->view($user, $articulo));
    }

    public function test_dept_member_can_view_privado_in_own_dept(): void
    {
        $user = new User();

        $this->authService
            ->shouldReceive('canViewArticle')
            ->once()
            ->andReturn(true);

        $articulo = new ArticuloModel(['departamento_id' => 'dept-1', 'visibilidad' => 'privado']);
        $this->assertTrue($this->policy->view($user, $articulo));
    }

    public function test_admin_can_view_all_articles(): void
    {
        $user = new User();

        $this->authService
            ->shouldReceive('canViewArticle')
            ->once()
            ->andReturn(true);

        $articulo = new ArticuloModel(['departamento_id' => 'dept-1', 'visibilidad' => 'privado']);
        $this->assertTrue($this->policy->view($user, $articulo));
    }

    public function test_privado_article_returns_false_for_unauthorized(): void
    {
        $user = new User();

        $this->authService
            ->shouldReceive('canViewArticle')
            ->once()
            ->andReturn(false);

        $articulo = new ArticuloModel(['departamento_id' => 'dept-2', 'visibilidad' => 'privado']);
        $this->assertFalse($this->policy->view($user, $articulo));
    }

    public function test_dept_editor_can_create_article_in_own_dept(): void
    {
        $user = new User(['id' => 10, 'rol' => 'USER']);

        $this->authService
            ->shouldReceive('canWriteArticleInDepartment')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->create($user, 'dept-1'));
    }

    public function test_no_membership_cannot_create_article(): void
    {
        $user = new User(['id' => 11, 'rol' => 'USER']);

        $this->authService
            ->shouldReceive('canWriteArticleInDepartment')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->create($user, 'dept-2'));
    }

    public function test_suscriptor_paywall_persists_via_reporte(): void
    {
        $user = new User();

        $this->authService
            ->shouldReceive('canViewReporte')
            ->once()
            ->andReturn(false);

        $reporte = new ReporteModel(['departamento_id' => 'dept-1', 'visibilidad' => 'suscriptor']);
        $reportePolicy = new ReportePolicy($this->authService);
        $this->assertFalse($reportePolicy->view($user, $reporte));
    }
}
