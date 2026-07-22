<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class ArticuloModel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'articulos';

    protected $fillable = [
        'categoria_id',
        'departamento_id',
        'titulo',
        'descripcion',
        'autor',
        'fuente',
        'estado',
        'enlace',
        'visibilidad',
        'fecha_publicacion',
        'fecha_recepcion',
    ];

    protected $casts = [
        'fecha_publicacion' => 'date',
        'fecha_recepcion' => 'date',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDatasetModel::class, 'categoria_id');
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(DepartamentoModel::class, 'departamento_id');
    }

    /** Scope: filtrar por departamento */
    public function scopeDelDepartamento(Builder $query, string $departamentoId): Builder
    {
        return $query->where('departamento_id', $departamentoId);
    }

    /**
     * Scope: filtrar por visibilidad según el usuario.
     *
     * - Guest (null): solo visibilidad = 'publico'
     * - ADMIN: sin filtro
     * - SUBSCRIBER: visibilidad IN ('publico', 'suscriptor')
     * - Miembro de departamento: público + suscriptor + privado de su depto
     */
    public function scopeVisibleFor(Builder $query, ?\App\Models\User $user): void
    {
        if ($user === null) {
            $query->whereIn('visibilidad', ['publico', 'suscriptor']);
            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        // Si tiene permiso global (sin depto) en atlas o articulos, ve todo
        $hasGlobalPermiso = \Illuminate\Support\Facades\DB::table('permisos')
            ->where('user_id', $user->id)
            ->whereIn('modulo', ['atlas', 'articulos'])
            ->whereNull('departamento_id')
            ->where('nivel', '!=', 'ninguno')
            ->exists();

        if ($hasGlobalPermiso) {
            return;
        }

        $query->where(function (Builder $q) use ($user): void {
            $q->whereIn('visibilidad', ['publico', 'suscriptor']);

            $q->orWhere(function (Builder $sub) use ($user): void {
                $sub->where('visibilidad', 'privado')
                    ->where(function (Builder $subQ) use ($user): void {
                        $subQ->whereIn('departamento_id', function ($q) use ($user): void {
                            $q->select('departamento_id')
                                ->from('usuario_departamento')
                                ->where('user_id', $user->id);
                        });
                        $subQ->orWhereIn('departamento_id', function ($q) use ($user): void {
                            $q->select('departamento_id')
                                ->from('permisos')
                                ->where('user_id', $user->id)
                                ->whereIn('modulo', ['atlas', 'articulos'])
                                ->where('nivel', '!=', 'ninguno');
                        });
                    });
            });
        });
    }
}
