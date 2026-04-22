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
        // Orders: pozycje_zamowienia
        Schema::create('pozycje_zamowienia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zamowienie_id')->constrained('zamowienia')->cascadeOnDelete();
            $table->string('nazwa');
            $table->integer('naklad')->default(1);
            $table->string('format')->nullable(); // np. A4, 100x200mm
            $table->string('material')->nullable(); // np. Kreda 350g
            $table->string('kolorystyka')->nullable(); // np. 4+4, 4+0
            $table->decimal('cena_netto', 12, 2)->nullable();
            $table->timestamps();
        });

        // Orders: specyfikacje_druku
        Schema::create('specyfikacje_druku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pozycja_id')->constrained('pozycje_zamowienia')->cascadeOnDelete();
            $table->jsonb('parametry')->nullable(); // parametry techniczne
            $table->timestamps();
        });

        // Orders: historia_statusow
        Schema::create('historia_statusow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zamowienie_id')->constrained('zamowienia')->cascadeOnDelete();
            $table->string('status_from')->nullable();
            $table->string('status_to');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('komentarz')->nullable();
            $table->timestamps();

            $table->index('zamowienie_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historia_statusow');
        Schema::dropIfExists('specyfikacje_druku');
        Schema::dropIfExists('pozycje_zamowienia');
    }
};
