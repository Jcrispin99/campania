<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $paciente_id
 * @property string $correo
 * @property bool $predeterminado
 */
final class PacienteCorreo extends Model
{
    protected $table = 'paciente_correos';

    protected $fillable = ['correo', 'predeterminado'];

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    protected function casts(): array
    {
        return ['paciente_id' => 'integer', 'predeterminado' => 'boolean'];
    }
}
