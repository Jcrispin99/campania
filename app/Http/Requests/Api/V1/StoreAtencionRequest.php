<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'exists:pacientes,dni'],
            'edad' => ['required', 'integer', 'min:0', 'max:120'],
            'correo_electronico' => ['nullable', 'string', 'email', 'max:255'],
            'valor_1' => ['required', 'string', 'max:255'],
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
            'edad.required' => 'Ingresa la edad del paciente.',
            'edad.integer' => 'La edad debe ser un número entero.',
            'edad.min' => 'Ingresa una edad válida entre 0 y 120 años.',
            'edad.max' => 'Ingresa una edad válida entre 0 y 120 años.',
            'correo_electronico.email' => 'Ingresa un correo electrónico válido.',
            'valor_1.required' => 'Ingresa la presión arterial.',
            'valor_1.string' => 'La presión arterial debe ser un texto.',
        ];
    }
}
