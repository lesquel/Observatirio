<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel;
use App\Models\User;
use Tests\TestCase;

class ArticuloModelScopeTest extends TestCase
{
    public function test_guest_only_sees_publico_articles(): void
    {
        $query = ArticuloModel::query();
        $query->visibleFor(null);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('visibilidad', $sql);
        $this->assertContains('publico', $bindings);
    }

    public function test_admin_sees_all_articles(): void
    {
        $admin = new User(['rol' => 'ADMIN']);

        $query = ArticuloModel::query();
        $query->visibleFor($admin);

        $sql = $query->toSql();

        // ADMIN should have NO visibilidad filter in the WHERE clause
        $this->assertStringNotContainsString('visibilidad', $sql);
    }

    public function test_subscriber_sees_publico_and_suscriptor(): void
    {
        $subscriber = new User(['rol' => 'SUBSCRIBER']);

        $query = ArticuloModel::query();
        $query->visibleFor($subscriber);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('visibilidad', $sql);
        $this->assertContains('publico', $bindings);
        $this->assertContains('suscriptor', $bindings);
    }

    public function test_dept_member_includes_privado_subquery(): void
    {
        $user = new User(['rol' => 'USER']);
        $user->id = 42;

        $query = ArticuloModel::query();
        $query->visibleFor($user);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        // Should have a subquery for usuario_departamento
        $this->assertStringContainsString('usuario_departamento', $sql);
        // Should have more bindings than just publico/suscriptor (privado + user_id)
        $this->assertGreaterThan(3, count($bindings));
        // The user ID should appear in bindings
        $this->assertContains(42, $bindings);
    }
}
