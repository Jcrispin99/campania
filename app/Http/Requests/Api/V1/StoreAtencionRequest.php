<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAtencionRequest extends FormRequest
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
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'exists:pacientes,dni'],
            'edad' => ['nullable', 'integer', 'min:0', 'max:120'],
            'correo_electronico' => ['nullable', 'string', 'email', 'max:255'],
            'valor_1' => ['nullable', 'string', 'max:255'],
            'diabetes' => ['sometimes', 'boolean'],
            'hipertension' => ['sometimes', 'boolean'],
            'ayuna' => ['sometimes', 'boolean'],
            'otros' => ['nullable', 'string', 'max:1000'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dni.exists' => 'No existe un paciente con este DNI.',
            'dni.regex' => 'El DNI debe tener 8 dígitos.',
            'edad.integer' => 'La edad debe ser un número entero.',
            'edad.min' => 'Ingresa una edad válida entre 0 y 120 años.',
            'edad.max' => 'Ingresa una edad válida entre 0 y 120 años.',
            'correo_electronico.email' => 'Ingresa un correo electrónico válido.',
            'valor_1.string' => 'La presión arterial debe ser un texto.',
        ];
    }
}
