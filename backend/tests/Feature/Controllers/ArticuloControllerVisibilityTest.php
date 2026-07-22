<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArticuloControllerVisibilityTest extends TestCase
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

        Schema::create('articulos', function ($table) {
            $table->uuid('id')->primary();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('visibilidad')->default('publico');
            $table->uuid('departamento_id')->nullable();
            $table->uuid('categoria_id')->nullable();
            $table->string('autor')->nullable();
            $table->string('fuente')->nullable();
            $table->string('estado')->nullable();
            $table->string('enlace')->nullable();
            $table->date('fecha_publicacion')->nullable();
            $table->date('fecha_recepcion')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('usuario_departamento', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('departamento_id');
            $table->string('rol');
            $table->timestamps();
        });

        // Sanctum personal access tokens table
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
        Schema::dropIfExists('usuario_departamento');
        Schema::dropIfExists('articulos');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    private function createArticulo(string $titulo, string $visibilidad): ArticuloModel
    {
        $articulo = new ArticuloModel();
        $articulo->titulo = $titulo;
        $articulo->visibilidad = $visibilidad;
        $articulo->save();
        return $articulo;
    }

    public function test_guest_sees_only_publico_articles_in_index(): void
    {
        $this->createArticulo('Public Article', 'publico');
        $this->createArticulo('Private Article', 'privado');
        $this->createArticulo('Subscriber Article', 'suscriptor');

        $response = $this->getJson('/api/articulos');
        $response->assertStatus(200);

        $data = $response->json();
        $titles = array_column($data, 'titulo');
        $this->assertContains('Public Article', $titles);
        $this->assertNotContains('Private Article', $titles);
        $this->assertNotContains('Subscriber Article', $titles);
    }

    public function test_guest_gets_404_for_privado_article_show(): void
    {
        $article = $this->createArticulo('Private Article', 'privado');

        $response = $this->getJson('/api/articulos/' . $article->id);
        $response->assertStatus(404);
    }

    public function test_admin_sees_all_articles(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'rol' => 'ADMIN',
        ]);

        $article = $this->createArticulo('Private Article', 'privado');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/articulos/' . $article->id);
        $response->assertStatus(200);
        $this->assertEquals('Private Article', $response->json('titulo'));
    }
}
