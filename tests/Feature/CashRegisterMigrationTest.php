<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('resumes the cash register migration after MySQL left tables without unique indexes', function (): void {
    Schema::table('cash_register_document_series', function (Blueprint $table): void {
        $table->dropUnique('cash_register_series_unique');
        $table->dropUnique(['document_series_id']);
    });

    $migration = require database_path('migrations/2026_07_22_030000_create_cash_registers_table.php');
    $migration->up();

    expect(Schema::hasIndex('cash_register_document_series', ['cash_register_id', 'document_series_id'], 'unique'))->toBeTrue();
    expect(Schema::hasIndex('cash_register_document_series', ['document_series_id'], 'unique'))->toBeTrue();
});
