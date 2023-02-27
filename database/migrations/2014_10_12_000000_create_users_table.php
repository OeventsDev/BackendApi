<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('avatar')->nullable();
            $table->string('nom');
            $table->string('prenom');
            $table->string('sexe');
            $table->string('telephone')->nullable();
            $table->text('firebase_token')->nullable();
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamp('telephone_verified_at')->nullable();
            $table->string('google_id')->nullable();
            $table->string('facebook_id')->nullable();
            $table->integer('default_auth')->default(1);
            $table->unsignedBigInteger('pays_id');
            $table->unsignedBigInteger('parrain_id')->nullable();
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
};
