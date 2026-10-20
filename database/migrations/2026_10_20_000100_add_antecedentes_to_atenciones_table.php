<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atenciones', function (Blueprint $table): void {
            $table->boolean('diabetes')->default(false)->after('valor_1');
            $table->boolean('hipertension')->default(false)->after('diabetes');
            $table->boolean('ayuna')->default(false)->after('hipertension');
            $table->text('otros')->nullable()->after('ayuna');
        });
    }

    public function down(): void
    {
        Schema::table('atenciones', function (Blueprint $table): void {
            $table->dropColumn(['diabetes', 'hipertension', 'ayuna', 'otros']);
        });
    }
};
