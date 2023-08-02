<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWtjTokensTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wtj_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('wtj_token');
            $table->string('wtj_return_token')->nullable();
            $table->string('wtj_code');
            $table->string('wtj_marker')->nullable();
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
        Schema::dropIfExists('wtj_tokens');
    }
}
