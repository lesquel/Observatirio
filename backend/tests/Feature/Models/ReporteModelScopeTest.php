<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Infrastructure\Persistence\Eloquent\Models\ReporteModel;
use App\Models\User;
use Tests\TestCase;

class ReporteModelScopeTest extends TestCase
{
    public function test_guest_only_sees_publico_reportes(): void
    {
        $query = ReporteModel::query();
        $query->visibleFor(null);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('visibilidad', $sql);
        $this->assertContains('publico', $bindings);
    }

    public function test_admin_sees_all_reportes(): void
    {
        $admin = new User(['rol' => 'ADMIN']);

        $query = ReporteModel::query();
        $query->visibleFor($admin);

        $sql = $query->toSql();

        $this->assertStringNotContainsString('visibilidad', $sql);
    }
}
