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
            'valor_1' => ['required', 'string', 'max:255'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'dni.exists' => 'No existe un paciente con este DNI.',
            'dni.regex' => 'El DNI debe tener 8 dígitos.',
            'valor_1.required' => 'Ingresa la medición.',
            'valor_1.string' => 'La medición debe ser un texto.',
        ];
    }
}
