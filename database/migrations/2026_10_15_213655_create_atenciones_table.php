<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atenciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('valor_1');
            $table->date('fecha');
            $table->timestamps();
            $table->index(['paciente_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atenciones');
    }
};
