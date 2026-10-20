<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Closure;

final class PacienteCorreoRules
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'correos' => ['sometimes', 'array', 'max:50', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && count(array_filter($value, fn ($row) => is_array($row) && ($row['predeterminado'] ?? false))) > 1) {
                    $fail('Solo puedes marcar un correo predeterminado.');
                }
            }],
            'correos.*' => ['required', 'array:correo,predeterminado'],
            'correos.*.correo' => ['required', 'string', 'email', 'max:254', 'distinct:ignore_case'],
            'correos.*.predeterminado' => ['required', 'boolean'],
        ];
    }
}
