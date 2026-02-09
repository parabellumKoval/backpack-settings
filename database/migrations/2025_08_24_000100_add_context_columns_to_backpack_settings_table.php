<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected function tableName(): string
    {
        return config('backpack-settings.table', 'backpack_settings');
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $schema = null;
            $tableName = $table;

            if (str_contains($table, '.')) {
                [$schema, $tableName] = explode('.', $table, 2);
            }

            if ($schema === null) {
                $schema = DB::getSchemaBuilder()->getCurrentSchemaName();
            }

            $result = DB::select(
                'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ? LIMIT 1',
                [$schema, $tableName, $indexName]
            );

            return !empty($result);
        }

        if ($driver === 'mysql') {
            $result = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);
            return !empty($result);
        }

        $schemaBuilder = Schema::getConnection()->getSchemaBuilder();
        if (method_exists($schemaBuilder, 'hasIndex')) {
            return $schemaBuilder->hasIndex($table, $indexName);
        }

        return false;
    }

    public function up(): void
    {
        $table = $this->tableName();
        if (!Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            if (!Schema::hasColumn($table->getTable(), 'region')) {
                $table->string('region', 32)->nullable()->after('group');
                $table->index('region');
            }
            if (!Schema::hasColumn($table->getTable(), 'locale')) {
                $table->string('locale', 32)->nullable()->after('region');
                $table->index('locale');
            }
        });

        if ($this->indexExists($table, 'bpsettings_key_unique')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique('bpsettings_key_unique');
            });
        }

        if (! $this->indexExists($table, 'bpsettings_key_region_locale_unique')) {
            Schema::table($table, function (Blueprint $table) {
                $table->unique(['key', 'region', 'locale'], 'bpsettings_key_region_locale_unique');
            });
        }
    }

    public function down(): void
    {
        $table = $this->tableName();
        if (!Schema::hasTable($table)) {
            return;
        }

        if ($this->indexExists($table, 'bpsettings_key_region_locale_unique')) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique('bpsettings_key_region_locale_unique');
            });
        }

        Schema::table($table, function (Blueprint $table) {
            if (Schema::hasColumn($table->getTable(), 'locale')) {
                try {
                    $table->dropIndex(['locale']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('locale');
            }
            if (Schema::hasColumn($table->getTable(), 'region')) {
                try {
                    $table->dropIndex(['region']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('region');
            }
        });

        if (! $this->indexExists($table, 'bpsettings_key_unique')) {
            Schema::table($table, function (Blueprint $table) {
                $table->unique('key', 'bpsettings_key_unique');
            });
        }
    }
};
