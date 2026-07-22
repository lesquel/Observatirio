<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel;
use App\Infrastructure\Persistence\Eloquent\Models\DatasetModel;
use App\Infrastructure\Persistence\Eloquent\Models\DepartamentoModel;
use App\Infrastructure\Persistence\Eloquent\Models\ReporteModel;
use App\Infrastructure\Persistence\Eloquent\Models\VariableMetadatoModel;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * HTTP-level authorization tests for dataset/grafico/fuente/stopwords write endpoints.
 *
 * These tests verify that Gate::authorize is invoked in the controller layer,
 * enforcing DatasetPolicy before any write operation proceeds.
 *
 * DWS-01: Dataset writes use department role via Policy
 * GFS-01: Graficos/fuentes writes authorize against parent dataset
 * DWS-03: Stopwords writes use Policy
 */
class DatasetWriteAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Users table (matching App\Models\User)
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

        // Personal access tokens for Sanctum
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

        // Departamentos
        Schema::create('departamentos', function ($table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->boolean('publico')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // Pivot: usuario_departamento
        Schema::create('usuario_departamento', function ($table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->uuid('departamento_id');
            $table->string('rol', 20)->default('LECTOR');
            $table->timestamps();
            $table->unique(['user_id', 'departamento_id']);
        });

        // Permisos table (needed by AuthorizationService even when empty)
        Schema::create('permisos', function ($table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('modulo', 50);
            $table->string('nivel', 20)->default('ninguno');
            $table->uuid('departamento_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'modulo', 'departamento_id']);
        });

        // Datasets
        Schema::create('datasets', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('departamento_id');
            $table->uuid('categoria_id')->nullable();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('nombre_archivo')->nullable();
            $table->string('estado')->nullable();
            $table->unsignedBigInteger('subido_por')->nullable();
            $table->unsignedBigInteger('total_registros')->default(0);
            $table->string('enlace_fuente')->nullable();
            $table->text('opciones')->nullable();
            $table->timestamp('fecha_carga')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Variables metadatos (needed by DatasetRepository eager-load)
        Schema::create('variables_metadatos', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->string('nombre_columna');
            $table->string('nombre_original')->nullable();
            $table->string('tipo_dato')->default('texto');
            $table->string('tipo_detectado')->nullable();
            $table->boolean('es_visible')->default(true);
            $table->integer('orden')->default(0);
            $table->json('opciones')->nullable();
            $table->timestamps();
        });

        // Graficos predeterminados
        Schema::create('graficos_predeterminados', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->text('analisis')->nullable();
            $table->string('tipo_grafico')->default('bar');
            $table->string('tipo_analisis')->default('univariable');
            $table->uuid('variable_x_id')->nullable();
            $table->uuid('variable_y_id')->nullable();
            $table->json('filtros')->nullable();
            $table->json('configuracion')->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();
        });

        // Dataset fuentes
        Schema::create('dataset_fuentes', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('url');
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Categoria datasets (needed by DatasetRepository for categorias)
        Schema::create('categoria_datasets', function ($table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->timestamps();
        });

        // Articulos (articulo/reporte authorization tests)
        Schema::create('articulos', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('departamento_id')->nullable();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('autor')->nullable();
            $table->string('fuente')->nullable();
            $table->text('estado')->nullable();
            $table->text('enlace')->nullable();
            $table->string('visibilidad')->default('publico');
            $table->date('fecha_publicacion')->nullable();
            $table->date('fecha_recepcion')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Reportes
        Schema::create('reportes', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('departamento_id')->nullable();
            $table->string('nombre_indicador');
            $table->text('descripcion_indicador')->nullable();
            $table->date('fecha_publicacion')->nullable();
            $table->string('link_url')->nullable();
            $table->string('ficha_indicador')->nullable();
            $table->string('fuente')->nullable();
            $table->string('visibilidad')->default('publico');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('reportes');
        Schema::dropIfExists('articulos');
        Schema::dropIfExists('categoria_datasets');
        Schema::dropIfExists('dataset_fuentes');
        Schema::dropIfExists('graficos_predeterminados');
        Schema::dropIfExists('variables_metadatos');
        Schema::dropIfExists('datasets');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('usuario_departamento');
        Schema::dropIfExists('departamentos');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    // ========== Helper: create test users ==========

    private function createUser(string $rol): User
    {
        return User::create([
            'name' => $rol . ' User',
            'email' => strtolower($rol) . '@test.com',
            'password' => bcrypt('password'),
            'rol' => $rol,
            'is_active' => true,
        ]);
    }

    private function addPivot(User $user, DepartamentoModel $dept, string $role): void
    {
        \DB::table('usuario_departamento')->insert([
            'id' => \Str::uuid(),
            'user_id' => $user->id,
            'departamento_id' => $dept->id,
            'rol' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ====================================================================
    // DEFECT 1 — DatasetController write endpoints
    // ====================================================================

    /** @test */
    public function dept_editor_can_update_dataset_in_own_dept(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000001', 'nombre' => 'Dept A']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000010',
            'departamento_id' => $dept->id,
            'nombre' => 'Test Dataset',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $dept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->putJson('/api/datasets/' . $dataset->id, ['nombre' => 'Updated']);

        // Gate passes; use case may return 200 or have infra limitations → least assert not 403
        $response->assertStatus(200);
    }

    /** @test */
    public function lector_cannot_update_dataset(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000002', 'nombre' => 'Dept B']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000011',
            'departamento_id' => $dept->id,
            'nombre' => 'Test Dataset',
        ]);
        $lector = $this->createUser('USER');
        $this->addPivot($lector, $dept, 'LECTOR');

        $response = $this->actingAs($lector, 'sanctum')
            ->putJson('/api/datasets/' . $dataset->id, ['nombre' => 'Hacked']);

        $response->assertStatus(403);
    }

    /** @test */
    public function no_membership_cannot_update_dataset(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000003', 'nombre' => 'Dept C']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000012',
            'departamento_id' => $dept->id,
            'nombre' => 'Test Dataset',
        ]);
        $user = $this->createUser('USER');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/datasets/' . $dataset->id, ['nombre' => 'Hacked']);

        $response->assertStatus(403);
    }

    /** @test */
    public function dept_editor_cannot_update_other_dept_dataset(): void
    {
        $ownDept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000004', 'nombre' => 'Own Dept']);
        $otherDept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000005', 'nombre' => 'Other Dept']);
        $otherDataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000013',
            'departamento_id' => $otherDept->id,
            'nombre' => 'Other Dataset',
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $ownDept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->putJson('/api/datasets/' . $otherDataset->id, ['nombre' => 'Hacked']);

        $response->assertStatus(403);
    }

    /** @test */
    public function global_admin_can_update_any_dataset(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000006', 'nombre' => 'Dept F']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000014',
            'departamento_id' => $dept->id,
            'nombre' => 'Test Dataset',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $admin = $this->createUser('ADMIN');

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/datasets/' . $dataset->id, ['nombre' => 'Admin Update']);

        $response->assertStatus(200);
    }

    /** @test */
    public function dept_editor_can_delete_dataset_in_own_dept(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000007', 'nombre' => 'Dept G']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000015',
            'departamento_id' => $dept->id,
            'nombre' => 'Delete Test',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $dept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->deleteJson('/api/datasets/' . $dataset->id);

        $response->assertStatus(200);
    }

    /** @test */
    public function lector_cannot_delete_dataset(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000008', 'nombre' => 'Dept H']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000016',
            'departamento_id' => $dept->id,
            'nombre' => 'Delete Test',
        ]);
        $lector = $this->createUser('USER');
        $this->addPivot($lector, $dept, 'LECTOR');

        $response = $this->actingAs($lector, 'sanctum')
            ->deleteJson('/api/datasets/' . $dataset->id);

        $response->assertStatus(403);
    }

    // ====================================================================
    // DEFECT 1 — GraficoPredeterminadoController write endpoints
    // ====================================================================

    /** @test */
    public function no_membership_cannot_create_grafico(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000020', 'nombre' => 'Dept Grafico']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000030',
            'departamento_id' => $dept->id,
            'nombre' => 'Grafico Test Dataset',
        ]);
        $user = $this->createUser('USER');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/datasets/' . $dataset->id . '/graficos-predeterminados', [
                'titulo' => 'Test Grafico',
                'tipo_grafico' => 'bar',
                'tipo_analisis' => 'univariable',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function dept_editor_can_create_grafico_in_own_dept(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000021', 'nombre' => 'Dept Grafico 2']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000031',
            'departamento_id' => $dept->id,
            'nombre' => 'Grafico Test Dataset 2',
            'estado' => 'COMPLETADO',
        ]);
        $var = VariableMetadatoModel::create([
            'id' => '00000000-0000-0000-0000-000000000040',
            'dataset_id' => $dataset->id,
            'nombre_columna' => 'test_var',
            'tipo_dato' => 'texto',
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $dept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson('/api/datasets/' . $dataset->id . '/graficos-predeterminados', [
                'titulo' => 'New Grafico',
                'tipo_grafico' => 'bar',
                'tipo_analisis' => 'univariable',
                'variable_x_id' => $var->id,
            ]);

        // Gate passes → should reach creation (201) or validation error (422) depending on data
        // Either way, NOT 403
        $response->assertStatus(201);
    }

    /** @test */
    public function no_membership_cannot_update_grafico(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000022', 'nombre' => 'Dept Grafico 3']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000032',
            'departamento_id' => $dept->id,
            'nombre' => 'Grafico Dataset 3',
        ]);
        $grafico = \App\Infrastructure\Persistence\Eloquent\Models\GraficoPredeterminadoModel::create([
            'id' => '00000000-0000-0000-0000-000000000050',
            'dataset_id' => $dataset->id,
            'titulo' => 'Original',
            'tipo_grafico' => 'bar',
            'tipo_analisis' => 'univariable',
            'activo' => true,
            'orden' => 1,
        ]);
        $user = $this->createUser('USER');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/graficos-predeterminados/' . $grafico->id, ['titulo' => 'Hacked']);

        $response->assertStatus(403);
    }

    // ====================================================================
    // DEFECT 1 — DatasetFuenteController write endpoints
    // ====================================================================

    /** @test */
    public function no_membership_cannot_create_fuente(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000023', 'nombre' => 'Dept Fuente']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000033',
            'departamento_id' => $dept->id,
            'nombre' => 'Fuente Test Dataset',
        ]);
        $user = $this->createUser('USER');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/datasets/' . $dataset->id . '/fuentes', [
                'titulo' => 'Test Fuente',
                'url' => 'https://example.com',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function dept_editor_can_create_fuente_in_own_dept(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000024', 'nombre' => 'Dept Fuente 2']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000034',
            'departamento_id' => $dept->id,
            'nombre' => 'Fuente Dataset 2',
            'estado' => 'COMPLETADO',
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $dept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson('/api/datasets/' . $dataset->id . '/fuentes', [
                'titulo' => 'Valid Fuente',
                'url' => 'https://example.com',
            ]);

        $response->assertStatus(201);
    }

    // ====================================================================
    // DEFECT 2 — DashboardController::updateStopwords type mismatch
    // ====================================================================

    /** @test */
    public function dept_editor_can_update_stopwords_in_own_dept(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000025', 'nombre' => 'Dept Stopwords']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000035',
            'departamento_id' => $dept->id,
            'nombre' => 'Stopwords Dataset',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
            'opciones' => ['custom_stopwords' => []],
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $dept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->putJson('/api/stats/datasets/' . $dataset->id . '/stopwords', [
                'stopwords' => ['palabra1', 'palabra2'],
            ]);

        // Gate passes (DatasetModel now matches Policy type-hint) → use case returns 200
        $response->assertStatus(200);
    }

    /** @test */
    public function lector_cannot_update_stopwords(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000026', 'nombre' => 'Dept Stopwords 2']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000036',
            'departamento_id' => $dept->id,
            'nombre' => 'Stopwords Dataset 2',
            'estado' => 'COMPLETADO',
        ]);
        $lector = $this->createUser('USER');
        $this->addPivot($lector, $dept, 'LECTOR');

        $response = $this->actingAs($lector, 'sanctum')
            ->putJson('/api/stats/datasets/' . $dataset->id . '/stopwords', [
                'stopwords' => ['hacked'],
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function global_admin_can_update_stopwords(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000027', 'nombre' => 'Dept Stopwords 3']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000037',
            'departamento_id' => $dept->id,
            'nombre' => 'Stopwords Dataset 3',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
            'opciones' => ['custom_stopwords' => []],
        ]);
        $admin = $this->createUser('ADMIN');

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/stats/datasets/' . $dataset->id . '/stopwords', [
                'stopwords' => ['admin1'],
            ]);

        $response->assertStatus(200);
    }

    // ====================================================================
    // Articulo/Reporte write authorization + suscriptor filtering
    // ====================================================================

    /** @test */
    public function no_membership_cannot_create_articulo(): void
    {
        $d = DepartamentoModel::create(['id' => 'a0000000-0000-0000-0000-000000000001', 'nombre' => 'A']);

        $this->actingAs($this->createUser('USER'), 'sanctum')
            ->postJson('/api/articulos', ['titulo' => 'T', 'departamento_id' => $d->id])
            ->assertStatus(403);
    }

    /** @test */
    public function lector_cannot_update_articulo(): void
    {
        $d = DepartamentoModel::create(['id' => 'a0000000-0000-0000-0000-000000000002', 'nombre' => 'B']);
        $a = ArticuloModel::create(['id' => 'a0000000-0000-0000-0000-000000000010', 'departamento_id' => $d->id, 'titulo' => 'O']);
        $u = $this->createUser('USER');
        $this->addPivot($u, $d, 'LECTOR');

        $this->actingAs($u, 'sanctum')
            ->putJson('/api/articulos/' . $a->id, ['titulo' => 'X'])
            ->assertStatus(403);
    }

    /** @test */
    public function dept_editor_can_create_articulo(): void
    {
        $d = DepartamentoModel::create(['id' => 'a0000000-0000-0000-0000-000000000003', 'nombre' => 'C']);
        $u = $this->createUser('USER');
        $this->addPivot($u, $d, 'EDITOR');

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/articulos', ['titulo' => 'New', 'departamento_id' => $d->id])
            ->assertStatus(201);
    }

    /** @test */
    public function no_membership_cannot_create_reporte(): void
    {
        $d = DepartamentoModel::create(['id' => 'a0000000-0000-0000-0000-000000000006', 'nombre' => 'R']);

        $this->actingAs($this->createUser('USER'), 'sanctum')
            ->postJson('/api/reportes', ['nombre_indicador' => 'T', 'departamento_id' => $d->id])
            ->assertStatus(403);
    }

    /** @test */
    public function plain_user_index_publico_only(): void
    {
        $d = DepartamentoModel::create(['id' => 'a0000000-0000-0000-0000-000000000030', 'nombre' => 'S']);
        ArticuloModel::insert([
            ['id' => 'a0000000-0000-0000-0000-000000000040', 'titulo' => 'Pub', 'departamento_id' => $d->id, 'visibilidad' => 'publico', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'a0000000-0000-0000-0000-000000000041', 'titulo' => 'Sub', 'departamento_id' => $d->id, 'visibilidad' => 'suscriptor', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $t = collect($this->actingAs($this->createUser('USER'), 'sanctum')->getJson('/api/articulos')->json())->pluck('titulo');
        $this->assertContains('Pub', $t);
        $this->assertNotContains('Sub', $t);
    }

    /** @test */
    public function subscriber_index_publico_and_suscriptor(): void
    {
        $d = DepartamentoModel::create(['id' => 'a0000000-0000-0000-0000-000000000031', 'nombre' => 'S2']);
        ArticuloModel::insert([
            ['id' => 'a0000000-0000-0000-0000-000000000050', 'titulo' => 'Pub2', 'departamento_id' => $d->id, 'visibilidad' => 'publico', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'a0000000-0000-0000-0000-000000000051', 'titulo' => 'Sub2', 'departamento_id' => $d->id, 'visibilidad' => 'suscriptor', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $t = collect($this->actingAs($this->createUser('SUBSCRIBER'), 'sanctum')->getJson('/api/articulos')->json())->pluck('titulo');
        $this->assertContains('Pub2', $t);
        $this->assertContains('Sub2', $t);
    }

    // ====================================================================
    // REMEDIATION GAP 1 — DatasetController::analyze() authorization
    // DWS-01: POST /datasets/{id}/analyze requires dept ADMIN/EDITOR
    // ====================================================================

    /** @test */
    public function lector_cannot_analyze_dataset(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000040', 'nombre' => 'Analyze Dept']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000041',
            'departamento_id' => $dept->id,
            'nombre' => 'Analyze Test',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $lector = $this->createUser('USER');
        $this->addPivot($lector, $dept, 'LECTOR');

        $response = $this->actingAs($lector, 'sanctum')
            ->postJson('/api/datasets/' . $dataset->id . '/analyze');

        $response->assertStatus(403);
    }

    /** @test */
    public function dept_editor_can_analyze_dataset_in_own_dept(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000042', 'nombre' => 'Analyze Dept 2']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000043',
            'departamento_id' => $dept->id,
            'nombre' => 'Analyze Test 2',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $dept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson('/api/datasets/' . $dataset->id . '/analyze');

        // Gate passes; use case may return 200 or have infra limitations → least assert not 403
        $response->assertStatus(200);
    }

    // ====================================================================
    // REMEDIATION GAP 2 — Variable writes authorization
    // DWS-02: PUT /variables/{id} and POST /variables/bulk-update
    // ====================================================================

    /** @test */
    public function lector_cannot_update_variable(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000050', 'nombre' => 'Var Dept']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000051',
            'departamento_id' => $dept->id,
            'nombre' => 'Var Dataset',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $variable = VariableMetadatoModel::create([
            'id' => '00000000-0000-0000-0000-000000000060',
            'dataset_id' => $dataset->id,
            'nombre_columna' => 'test_var',
            'tipo_dato' => 'texto',
        ]);
        $lector = $this->createUser('USER');
        $this->addPivot($lector, $dept, 'LECTOR');

        $response = $this->actingAs($lector, 'sanctum')
            ->putJson('/api/variables/' . $variable->id, ['tipo_dato' => 'NUMERICO']);

        $response->assertStatus(403);
    }

    /** @test */
    public function lector_cannot_bulk_update_variables(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000052', 'nombre' => 'Bulk Dept']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000053',
            'departamento_id' => $dept->id,
            'nombre' => 'Bulk Dataset',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $variable = VariableMetadatoModel::create([
            'id' => '00000000-0000-0000-0000-000000000061',
            'dataset_id' => $dataset->id,
            'nombre_columna' => 'bulk_var',
            'tipo_dato' => 'texto',
        ]);
        $lector = $this->createUser('USER');
        $this->addPivot($lector, $dept, 'LECTOR');

        $response = $this->actingAs($lector, 'sanctum')
            ->postJson('/api/variables/bulk-update', [
                'variable_ids' => [$variable->id],
                'es_visible' => false,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function editor_can_update_variable(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000070', 'nombre' => 'Editor Var Dept']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000071',
            'departamento_id' => $dept->id,
            'nombre' => 'Editor Var Dataset',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $variable = VariableMetadatoModel::create([
            'id' => '00000000-0000-0000-0000-000000000080',
            'dataset_id' => $dataset->id,
            'nombre_columna' => 'editor_var',
            'nombre_original' => 'editor_var',
            'tipo_dato' => 'texto',
            'tipo_detectado' => 'texto',
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $dept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->putJson('/api/variables/' . $variable->id, ['tipo_dato' => 'NUMERICO']);

        $response->assertStatus(200);
    }

    /** @test */
    public function editor_can_bulk_update_variables(): void
    {
        $dept = DepartamentoModel::create(['id' => '00000000-0000-0000-0000-000000000081', 'nombre' => 'Editor Bulk Dept']);
        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000082',
            'departamento_id' => $dept->id,
            'nombre' => 'Editor Bulk Dataset',
            'nombre_archivo' => 'test.csv',
            'estado' => 'COMPLETADO',
            'subido_por' => 1,
        ]);
        $variable = VariableMetadatoModel::create([
            'id' => '00000000-0000-0000-0000-000000000083',
            'dataset_id' => $dataset->id,
            'nombre_columna' => 'editor_bulk_var',
            'nombre_original' => 'editor_bulk_var',
            'tipo_dato' => 'texto',
            'tipo_detectado' => 'texto',
        ]);
        $editor = $this->createUser('USER');
        $this->addPivot($editor, $dept, 'EDITOR');

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson('/api/variables/bulk-update', [
                'variable_ids' => [$variable->id],
                'es_visible' => false,
            ]);

        $response->assertStatus(200);
    }
}
