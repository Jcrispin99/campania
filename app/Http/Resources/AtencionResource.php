<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Atencion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Atencion */
final class AtencionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'paciente_id' => $this->paciente_id,
            'paciente' => new PacienteResource($this->whenLoaded('paciente')),
            'valor_1' => $this->valor_1,
            'diabetes' => $this->diabetes,
            'hipertension' => $this->hipertension,
            'ayuna' => $this->ayuna,
            'otros' => $this->otros,
            'fecha' => $this->fecha->format('Y-m-d'),
            'registrado_por' => $this->whenLoaded('user', fn (): string => $this->user->name),
        ];
    }
}
