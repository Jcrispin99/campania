<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePacienteCorreosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $rules = PacienteCorreoRules::rules();
        $rules['correos'][0] = 'present';

        return $rules;
    }
}
