<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsersMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        Schema::create('perfiles', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('telefono')->nullable();
            $table->string('cargo')->nullable();
            $table->text('biografia')->nullable();
            $table->string('avatar_url')->nullable();
            $table->timestamps();
        });

        Schema::create('departamentos', function ($table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('usuario_departamento', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('departamento_id');
            $table->foreign('departamento_id')->references('id')->on('departamentos')->cascadeOnDelete();
            $table->string('rol', 20)->default('LECTOR');
            $table->timestamps();
            $table->unique(['user_id', 'departamento_id']);
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
        Schema::dropIfExists('usuario_departamento');
        Schema::dropIfExists('departamentos');
        Schema::dropIfExists('perfiles');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_editor_gets_403_on_get_users(): void
    {
        $editor = User::create([
            'name' => 'Editor',
            'email' => 'editor@test.com',
            'password' => bcrypt('password'),
            'rol' => 'EDITOR',
        ]);

        $response = $this->actingAs($editor, 'sanctum')
            ->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_admin_gets_200_on_get_users(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'rol' => 'ADMIN',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/users');

        $response->assertStatus(200);
    }
}
