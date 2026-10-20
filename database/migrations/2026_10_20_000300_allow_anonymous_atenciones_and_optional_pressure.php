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
        Schema::table('atenciones', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('valor_1')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('atenciones')->whereNull('user_id')->orWhereNull('valor_1')->exists()) {
            throw new RuntimeException('No se puede revertir mientras existan atenciones sin usuario o presión arterial.');
        }
        Schema::table('atenciones', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->string('valor_1')->nullable(false)->change();
        });
    }
};
