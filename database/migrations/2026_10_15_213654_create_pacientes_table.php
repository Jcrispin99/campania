<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('numero')->nullable();
            $table->string('nombre_completo');
            $table->string('dni', 8)->unique();
            $table->string('nombre_posicion')->nullable();
            $table->string('nombre_area')->nullable();
            $table->string('nombre_unidad_org')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo_electronico')->nullable();
            $table->string('asistencia')->nullable();
            $table->string('presion_arterial')->nullable();
            $table->date('fecha')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
