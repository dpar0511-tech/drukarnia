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
        // CRM: tagi
        Schema::create('tagi', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa')->unique();
            $table->string('slug')->unique();
            $table->string('kolor', 7)->default('#cbd5e1'); // hex
            $table->timestamps();
        });

        // CRM: klient_tag
        Schema::create('klient_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klient_id')->constrained('klienci')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tagi')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['klient_id', 'tag_id']);
        });

        // Core: status_definitions
        Schema::create('status_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 50)->unique(); // np. WAITING_PAYMENT
            $table->string('nazwa_pl');
            $table->string('nazwa_en')->nullable();
            $table->string('kolor', 7)->nullable();
            $table->string('ikona')->nullable();
            $table->string('modul')->default('order'); // order, client, production
            $table->integer('kolejnosc')->default(0);
            $table->boolean('aktywny')->default(true);
            $table->timestamps();
        });

        // Indeksy dla istniejących tabel
        Schema::table('klienci', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('klienci', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::dropIfExists('status_definitions');
        Schema::dropIfExists('klient_tag');
        Schema::dropIfExists('tagi');
    }
};
