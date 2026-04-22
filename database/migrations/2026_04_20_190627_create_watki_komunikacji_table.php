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
        Schema::create('watki_komunikacji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zamowienie_id')->nullable()->constrained('zamowienia')->nullOnDelete();
            $table->foreignId('klient_id')->nullable()->constrained('klienci')->nullOnDelete();
            $table->string('temat');
            $table->string('status')->default('nowy'); // opcje: nowy, otwarty, zamkniety
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watki_komunikacji');
    }
};
