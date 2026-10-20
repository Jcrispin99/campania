<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...PacienteCorreoRules::rules(),
            'edad' => ['nullable', 'integer', 'min:0', 'max:120'],
            'numero' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'nombre_completo' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', Rule::unique('pacientes', 'dni')->ignore($this->route('paciente'))],
            'nombre_posicion' => ['nullable', 'string', 'max:255'],
            'nombre_area' => ['nullable', 'string', 'max:255'],
            'nombre_unidad_org' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo_electronico' => ['nullable', 'string', 'email', 'max:254'],
            'asistencia' => ['nullable', 'string', 'max:255'],
            'presion_arterial' => ['nullable', 'string', 'max:255'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dni.regex' => 'El DNI debe tener 8 dígitos.',
            'dni.unique' => 'Ya existe un paciente con este DNI.',
            'nombre_completo.required' => 'Ingresa el nombre completo.',
        ];
    }
}
