<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('backpack-settings.table', 'backpack_settings'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key')->index();
            $table->text('value')->nullable();
            $table->string('cast')->nullable();
            $table->string('group')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['key'], 'bpsettings_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('backpack-settings.table', 'backpack_settings'));
    }
};
