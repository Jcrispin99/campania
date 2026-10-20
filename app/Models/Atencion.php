<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AtencionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $paciente_id
 * @property int|null $user_id
 * @property string|null $valor_1
 * @property bool $diabetes
 * @property bool $hipertension
 * @property bool $ayuna
 * @property string|null $otros
 * @property Carbon $fecha
 * @property-read Paciente $paciente
 * @property-read User|null $user
 */
final class Atencion extends Model
{
    /** @use HasFactory<AtencionFactory> */
    use HasFactory;

    protected $table = 'atenciones';

    /** @var list<string> */
    protected $fillable = ['paciente_id', 'user_id', 'valor_1', 'diabetes', 'hipertension', 'ayuna', 'otros', 'fecha'];

    /** @return BelongsTo<Paciente, $this> */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha' => 'date', 'diabetes' => 'boolean', 'hipertension' => 'boolean', 'ayuna' => 'boolean'];
    }
}
