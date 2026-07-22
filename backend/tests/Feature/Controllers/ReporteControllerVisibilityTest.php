<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ReporteModel;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReporteControllerVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create minimal schema for this test suite
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('rol')->default('USER');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('reportes', function ($table) {
            $table->uuid('id')->primary();
            $table->string('nombre_indicador');
            $table->text('descripcion_indicador')->nullable();
            $table->string('visibilidad')->default('publico');
            $table->uuid('departamento_id')->nullable();
            $table->uuid('categoria_id')->nullable();
            $table->string('fuente')->nullable();
            $table->string('link_url')->nullable();
            $table->string('ficha_indicador')->nullable();
            $table->date('fecha_publicacion')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function ($table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('reportes');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    private function createReporte(string $nombre, string $visibilidad): ReporteModel
    {
        $reporte = new ReporteModel();
        $reporte->nombre_indicador = $nombre;
        $reporte->visibilidad = $visibilidad;
        $reporte->save();
        return $reporte;
    }

    public function test_guest_sees_only_publico_reportes_in_index(): void
    {
        $this->createReporte('Public Report', 'publico');
        $this->createReporte('Private Report', 'privado');
        $this->createReporte('Subscriber Report', 'suscriptor');

        $response = $this->getJson('/api/reportes');
        $response->assertStatus(200);

        $data = $response->json();
        $names = array_column($data, 'nombre_indicador');
        $this->assertContains('Public Report', $names);
        $this->assertNotContains('Private Report', $names);
        $this->assertNotContains('Subscriber Report', $names);
    }

    public function test_guest_gets_404_for_privado_reporte_show(): void
    {
        $reporte = $this->createReporte('Private Report', 'privado');

        $response = $this->getJson('/api/reportes/' . $reporte->id);
        $response->assertStatus(404);
    }

    public function test_suscriptor_paywall_persists_as_403(): void
    {
        $reporte = $this->createReporte('Suscriptor Report', 'suscriptor');

        $response = $this->getJson('/api/reportes/' . $reporte->id);
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Acceso exclusivo para suscriptores.']);
    }

    public function test_subscriber_can_view_suscriptor_reporte(): void
    {
        $subscriber = User::create([
            'name' => 'Subscriber',
            'email' => 'sub@test.com',
            'password' => bcrypt('password'),
            'rol' => 'SUBSCRIBER',
        ]);

        $reporte = $this->createReporte('Suscriptor Report', 'suscriptor');

        $response = $this->actingAs($subscriber, 'sanctum')
            ->getJson('/api/reportes/' . $reporte->id);
        $response->assertStatus(200);
        $this->assertEquals('Suscriptor Report', $response->json('nombre_indicador'));
    }
}
