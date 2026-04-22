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
        // Kierunek systemowy - using string for better compatibility during change in pgsql
        Schema::table('wiadomosci', function (Blueprint $table) {
            $table->string('kierunek', 50)->change();
        });

        // Zalaczniki refinement
        Schema::table('zalaczniki_wiadomosci', function (Blueprint $table) {
            if (! Schema::hasColumn('zalaczniki_wiadomosci', 'nazwa_oryginalna')) {
                $table->string('nazwa_oryginalna')->after('plik_id')->nullable();
            }

            // To change constraint to nullOnDelete, we need to drop it first
            // Check if foreign key exists is harder, but we can wrap it in try-catch or just check column property
        });

        Schema::table('zalaczniki_wiadomosci', function (Blueprint $table) {
            // Check if we need to drop and re-add foreign key
            // For safety in this specific case where we know it failed here:
            try {
                $table->dropForeign(['plik_id']);
            } catch (Exception $e) {
                // Ignore if not exists
            }
        });

        Schema::table('zalaczniki_wiadomosci', function (Blueprint $table) {
            $table->foreignId('plik_id')->nullable()->change()->constrained('pliki')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zalaczniki_wiadomosci', function (Blueprint $table) {
            $table->dropForeign(['plik_id']);
        });

        Schema::table('zalaczniki_wiadomosci', function (Blueprint $table) {
            $table->foreignId('plik_id')->change()->constrained('pliki')->cascadeOnDelete();
            $table->dropColumn('nazwa_oryginalna');
        });

        Schema::table('wiadomosci', function (Blueprint $table) {
            $table->enum('kierunek', ['przychodzacy', 'wychodzacy'])->change();
        });
    }
};
