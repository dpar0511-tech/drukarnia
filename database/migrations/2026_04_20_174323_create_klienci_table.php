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
        Schema::create('klienci', function (Blueprint $table) {
            $table->id();
            $table->enum('typ', ['B2B', 'B2C']);
            $table->string('imie_nazwa');
            $table->string('nip', 10)->nullable()->unique();
            $table->string('regon', 9)->nullable();
            $table->string('email_glowny')->nullable()->unique();
            $table->string('telefon_glowny', 20)->nullable();
            $table->string('adres_ulica')->nullable();
            $table->string('adres_miasto', 100)->nullable();
            $table->string('adres_kod', 10)->nullable();
            $table->string('adres_kraj', 2)->default('PL');
            $table->enum('status', ['aktywny', 'vip', 'zablokowany', 'draft'])->default('aktywny');
            // poziom_lojalnosci_id to be added in future migrations
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('klienci');
    }
};
