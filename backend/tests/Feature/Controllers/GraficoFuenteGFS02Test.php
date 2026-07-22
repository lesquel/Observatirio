<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\DatasetModel;
use App\Infrastructure\Persistence\Eloquent\Models\DepartamentoModel;
use App\Infrastructure\Persistence\Eloquent\Models\GraficoPredeterminadoModel;
use App\Infrastructure\Persistence\Eloquent\Models\DatasetFuenteModel;
use App\Infrastructure\Persistence\Eloquent\Models\VariableMetadatoModel;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GraficoFuenteGFS02Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('departamentos', function ($table) {
            $table->uuid('id')->primary();
            $table->string('nombre');
            $table->boolean('publico')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('datasets', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('departamento_id');
            $table->string('nombre');
            $table->string('estado')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('variables_metadatos', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->string('nombre_columna');
            $table->string('tipo_dato')->default('texto');
            $table->timestamps();
        });

        Schema::create('graficos_predeterminados', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->string('titulo');
            $table->string('tipo_grafico')->default('bar');
            $table->string('tipo_analisis')->default('univariable');
            $table->uuid('variable_x_id')->nullable();
            $table->uuid('variable_y_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('dataset_fuentes', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('dataset_id');
            $table->string('titulo');
            $table->string('url');
            $table->integer('orden')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dataset_fuentes');
        Schema::dropIfExists('graficos_predeterminados');
        Schema::dropIfExists('variables_metadatos');
        Schema::dropIfExists('datasets');
        Schema::dropIfExists('departamentos');
        parent::tearDown();
    }

    public function test_grafico_index_returns_empty_for_private_department_dataset(): void
    {
        $dept = DepartamentoModel::create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'nombre' => 'Private Dept',
            'publico' => false,
        ]);

        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000010',
            'departamento_id' => $dept->id,
            'nombre' => 'Private Dataset',
        ]);

        $var = VariableMetadatoModel::create([
            'id' => '00000000-0000-0000-0000-000000000020',
            'dataset_id' => $dataset->id,
            'nombre_columna' => 'test',
            'tipo_dato' => 'texto',
        ]);

        GraficoPredeterminadoModel::create([
            'id' => '00000000-0000-0000-0000-000000000030',
            'dataset_id' => $dataset->id,
            'titulo' => 'Should Not Appear',
            'tipo_grafico' => 'bar',
            'tipo_analisis' => 'univariable',
            'variable_x_id' => $var->id,
            'activo' => true,
            'orden' => 1,
        ]);

        $response = $this->getJson('/api/datasets/' . $dataset->id . '/graficos-predeterminados');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json(), 'Private-department dataset should return empty graficos');
    }

    public function test_grafico_index_returns_data_for_public_department_dataset(): void
    {
        $dept = DepartamentoModel::create([
            'id' => '00000000-0000-0000-0000-000000000002',
            'nombre' => 'Public Dept',
            'publico' => true,
        ]);

        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000011',
            'departamento_id' => $dept->id,
            'nombre' => 'Public Dataset',
        ]);

        $var = VariableMetadatoModel::create([
            'id' => '00000000-0000-0000-0000-000000000021',
            'dataset_id' => $dataset->id,
            'nombre_columna' => 'test',
            'tipo_dato' => 'texto',
        ]);

        GraficoPredeterminadoModel::create([
            'id' => '00000000-0000-0000-0000-000000000031',
            'dataset_id' => $dataset->id,
            'titulo' => 'Should Appear',
            'tipo_grafico' => 'bar',
            'tipo_analisis' => 'univariable',
            'variable_x_id' => $var->id,
            'activo' => true,
            'orden' => 1,
        ]);

        $response = $this->getJson('/api/datasets/' . $dataset->id . '/graficos-predeterminados');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('Should Appear', $data[0]['titulo']);
    }

    public function test_grafico_index_returns_empty_for_nonexistent_dataset(): void
    {
        $response = $this->getJson('/api/datasets/00000000-0000-0000-0000-000000000099/graficos-predeterminados');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json(), 'Nonexistent dataset should return empty graficos');
    }

    public function test_fuente_index_returns_empty_for_private_department_dataset(): void
    {
        $dept = DepartamentoModel::create([
            'id' => '00000000-0000-0000-0000-000000000003',
            'nombre' => 'Private Dept 2',
            'publico' => false,
        ]);

        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000012',
            'departamento_id' => $dept->id,
            'nombre' => 'Private Dataset 2',
        ]);

        DatasetFuenteModel::create([
            'id' => '00000000-0000-0000-0000-000000000040',
            'dataset_id' => $dataset->id,
            'titulo' => 'Should Not Appear',
            'url' => 'https://example.com',
            'orden' => 1,
        ]);

        $response = $this->getJson('/api/datasets/' . $dataset->id . '/fuentes');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json(), 'Private-department dataset should return empty fuentes');
    }

    public function test_fuente_index_returns_data_for_public_department_dataset(): void
    {
        $dept = DepartamentoModel::create([
            'id' => '00000000-0000-0000-0000-000000000004',
            'nombre' => 'Public Dept 2',
            'publico' => true,
        ]);

        $dataset = DatasetModel::create([
            'id' => '00000000-0000-0000-0000-000000000013',
            'departamento_id' => $dept->id,
            'nombre' => 'Public Dataset 2',
        ]);

        DatasetFuenteModel::create([
            'id' => '00000000-0000-0000-0000-000000000041',
            'dataset_id' => $dataset->id,
            'titulo' => 'Should Appear',
            'url' => 'https://example.com',
            'orden' => 1,
        ]);

        $response = $this->getJson('/api/datasets/' . $dataset->id . '/fuentes');
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('Should Appear', $data[0]['titulo']);
    }

    public function test_fuente_index_returns_empty_for_nonexistent_dataset(): void
    {
        $response = $this->getJson('/api/datasets/00000000-0000-0000-0000-000000000099/fuentes');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json(), 'Nonexistent dataset should return empty fuentes');
    }
}
