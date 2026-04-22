<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('osoby_kontaktowe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klient_id')->constrained('klienci')->cascadeOnDelete();
            $table->string('imie');
            $table->string('email')->nullable()->index();
            $table->string('telefon')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('osoby_kontaktowe');
    }
};
