<?php

declare(strict_types=1);

namespace App\Presentation\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Obtener departamentos directos (con rol de la tabla pivote)
        $departamentosDirectos = $this->relationLoaded('departamentos')
            ? $this->departamentos->map(fn($d) => [
                'id' => $d->id,
                'nombre' => $d->nombre,
                'codigo_interno' => $d->codigo_interno,
                'rol' => $d->pivot->rol,
            ])
            : collect();

        // Obtener departamentos de los permisos configurados
        $departamentosPermisos = collect();
        if ($this->relationLoaded('permisos')) {
            foreach ($this->permisos as $permiso) {
                if ($permiso->departamento_id && $permiso->nivel !== 'ninguno') {
                    $d = $permiso->departamento;
                    if ($d) {
                        $rol = match ($permiso->nivel) {
                            'admin' => 'ADMIN',
                            'escritura' => 'EDITOR',
                            'lectura' => 'LECTOR',
                            default => null,
                        };

                        if ($rol) {
                            $departamentosPermisos->push([
                                'id' => $d->id,
                                'nombre' => $d->nombre,
                                'codigo_interno' => $d->codigo_interno,
                                'rol' => $rol,
                            ]);
                        }
                    }
                }
            }
        }

        // Combinar y eliminar duplicados (priorizando el rol de departamentos directos o el más alto)
        $departamentosCombinados = $departamentosDirectos->concat($departamentosPermisos)
            ->unique('id')
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'rol' => $this->rol ?? 'USER',
            'is_active' => $this->is_active ?? true,
            'perfil' => $this->whenLoaded('perfil', fn() => [
                'id' => $this->perfil->id,
                'telefono' => $this->perfil->telefono,
                'cargo' => $this->perfil->cargo,
                'avatar' => $this->perfil->avatar,
                'bio' => $this->perfil->bio,
            ]),
            'departamentos' => $this->relationLoaded('departamentos') || $this->relationLoaded('permisos')
                ? $departamentosCombinados->toArray()
                : [],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
