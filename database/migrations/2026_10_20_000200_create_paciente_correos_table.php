<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paciente_correos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->string('correo', 254)->unique();
            $table->boolean('predeterminado')->default(false);
            $table->timestamps();
        });

        // Preserve existing valid addresses without assigning a shared legacy
        // address to an arbitrary patient. Conflicting values remain in the old column.
        $addresses = [];
        foreach (DB::table('pacientes')->whereNotNull('correo_electronico')->get(['id', 'correo_electronico']) as $patient) {
            foreach (preg_split('/[;,\r\n]+/', $patient->correo_electronico) ?: [] as $value) {
                $email = mb_strtolower(mb_trim($value));
                if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                    $addresses[$email][$patient->id] = true;
                }
            }
        }
        $defaults = [];
        foreach ($addresses as $email => $owners) {
            if (count($owners) !== 1) {
                continue;
            }
            $id = array_key_first($owners);
            $default = ! isset($defaults[$id]);
            DB::table('paciente_correos')->insert([
                'paciente_id' => $id, 'correo' => $email, 'predeterminado' => $default,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($default) {
                $defaults[$id] = true;
                DB::table('pacientes')->where('id', $id)->update(['correo_electronico' => $email]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paciente_correos');
    }
};
