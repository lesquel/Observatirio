<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Permiso;

use App\Domain\Permiso\Entities\Permiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePermisosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permisos' => ['required', 'array'],
            'permisos.*.modulo' => ['required', 'string', Rule::in(Permiso::MODULOS)],
            'permisos.*.nivel' => ['required', 'string', Rule::in(Permiso::NIVELES)],
            'permisos.*.departamento_id' => [
                'nullable',
                'string',
                'exists:departamentos,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $index = explode('.', $attribute)[1] ?? null;
                    if ($index === null) {
                        return;
                    }
                    $permiso = $this->input("permisos.{$index}");
                    if (!is_array($permiso)) {
                        return;
                    }
                    if (
                        ($permiso['modulo'] ?? null) === Permiso::MODULO_OBSERVATORIOS
                        && ($permiso['nivel'] ?? Permiso::NIVEL_NINGUNO) !== Permiso::NIVEL_NINGUNO
                        && empty($value)
                    ) {
                        $fail('Debe asignar exactamente un observatorio.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'permisos.required' => 'Debe enviar al menos un permiso.',
            'permisos.*.modulo.in' => 'El módulo debe ser atlas, articulos, reportes u observatorios.',
            'permisos.*.nivel.in' => 'El nivel debe ser ninguno, lectura, escritura o admin.',
        ];
    }
}
