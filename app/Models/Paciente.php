<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PacienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $numero
 * @property string $nombre_completo
 * @property int|null $edad
 * @property string $dni
 * @property string|null $nombre_posicion
 * @property string|null $nombre_area
 * @property string|null $nombre_unidad_org
 * @property string|null $telefono
 * @property string|null $correo_electronico
 * @property string|null $asistencia
 * @property string|null $presion_arterial
 * @property Carbon|null $fecha
 */
final class Paciente extends Model
{
    /** @use HasFactory<PacienteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['numero', 'nombre_completo', 'dni', 'edad', 'nombre_posicion', 'nombre_area', 'nombre_unidad_org', 'telefono', 'correo_electronico', 'asistencia', 'presion_arterial', 'fecha'];

    /** @return HasMany<Atencion, $this> */
    public function atenciones(): HasMany
    {
        return $this->hasMany(Atencion::class);
    }

    /** @return HasMany<PacienteCorreo, $this> */
    public function correos(): HasMany
    {
        return $this->hasMany(PacienteCorreo::class)->orderByDesc('predeterminado')->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['edad' => 'integer', 'numero' => 'integer', 'fecha' => 'date'];
    }
}
