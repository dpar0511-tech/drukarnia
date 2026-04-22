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
        Schema::table('klienci', function (Blueprint $table) {
            // In PostgreSQL, changing ENUMs via Doctrine/Laravel is problematic.
            // We'll change it to string with the new option.
            $table->string('status')->default('aktywny')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('klienci', function (Blueprint $table) {
            $table->enum('status', ['aktywny', 'vip', 'zablokowany'])->default('aktywny')->change();
        });
    }
};
