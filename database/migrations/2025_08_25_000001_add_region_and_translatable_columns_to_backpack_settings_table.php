<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = config('backpack-settings.table', 'backpack_settings');

        Schema::table($tableName, function (Blueprint $table) {
            $table->boolean('is_translatable')->default(false);
            $table->string('region', 64)->nullable();
            $table->index('region', 'bpsettings_region_index');
            $table->dropUnique('bpsettings_key_unique');
            $table->unique(['key', 'region'], 'bpsettings_key_region_unique');
        });

        $this->ensureJsonCompatibleValueColumn($tableName);
    }

    public function down(): void
    {
        $tableName = config('backpack-settings.table', 'backpack_settings');

        $this->revertJsonColumn($tableName);

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropUnique('bpsettings_key_region_unique');
            $table->dropIndex('bpsettings_region_index');
            $table->dropColumn(['is_translatable', 'region']);
            $table->unique('key', 'bpsettings_key_unique');
        });
    }

    protected function ensureJsonCompatibleValueColumn(string $tableName): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        $grammar = $connection->getSchemaGrammar();

        if ($grammar === null) {
            return;
        }

        $table = $grammar->wrapTable($tableName);
        $column = $grammar->wrap('value');

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} JSON NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE JSONB USING CASE WHEN {$column} IS NULL OR {$column} = '' THEN NULL ELSE {$column}::jsonb END");
        }
    }

    protected function revertJsonColumn(string $tableName): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        $grammar = $connection->getSchemaGrammar();

        if ($grammar === null) {
            return;
        }

        $table = $grammar->wrapTable($tableName);
        $column = $grammar->wrap('value');

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} LONGTEXT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE TEXT USING {$column}::text");
        }
    }
};
