<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAkPagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ak_pages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('template');
            $table->json('title');
            $table->string('slug');
            $table->boolean('is_active')->default(1);
            $table->json('extras')->nullable();
            $table->json('seo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('ak_pages');
    }
}
