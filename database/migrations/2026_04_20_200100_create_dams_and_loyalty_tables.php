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
        // Poziomy lojalnosci
        Schema::create('poziomy_lojalnosci', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa');
            $table->decimal('prog_obrotow', 12, 2);
            $table->integer('rabat_procent');
            $table->timestamps();
        });

        // Add to klienci
        Schema::table('klienci', function (Blueprint $table) {
            $table->foreignId('poziom_lojalnosci_id')->nullable()->after('status')->constrained('poziomy_lojalnosci')->nullOnDelete();
        });

        // DAMS: pliki
        Schema::create('pliki', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa_oryginalna');
            $table->string('slug')->unique();
            $table->string('mime_type', 50);
            $table->bigInteger('rozmiar_bajtow');
            $table->string('sciezka_s3')->unique();
            $table->string('bucket', 50)->default('drukarnia-files');
            $table->string('thumbnail_sm_url', 500)->nullable();
            $table->string('thumbnail_md_url', 500)->nullable();
            $table->string('thumbnail_lg_url', 500)->nullable();
            $table->foreignId('uploadowany_przez_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checksum_sha256', 64)->unique();
            $table->softDeletes();
            $table->timestamps();

            $table->index('checksum_sha256');
            $table->index('sciezka_s3');
        });

        // DAMS: wersje_plikow
        Schema::create('wersje_plikow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plik_id')->constrained('pliki')->cascadeOnDelete();
            $table->integer('numer_wersji');
            $table->string('sciezka_s3');
            $table->text('komentarz')->nullable();
            $table->foreignId('uploadowany_przez_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('aktywna')->default(false);
            $table->timestamps();

            $table->unique(['plik_id', 'numer_wersji']);
        });

        // DAMS: powiazania_plikow (Polymorphic)
        Schema::create('powiazania_plikow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plik_id')->constrained('pliki')->cascadeOnDelete();
            $table->string('fileable_type');
            $table->unsignedBigInteger('fileable_id');
            $table->string('rola', 50)->nullable(); // artwork, dokument, dowod, zdjecie
            $table->timestamps();

            $table->unique(['plik_id', 'fileable_type', 'fileable_id', 'rola'], 'idx_powiazania_unique');
            $table->index(['fileable_type', 'fileable_id']);
        });

        // Communication: zalaczniki_wiadomosci
        Schema::create('zalaczniki_wiadomosci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiadomosc_id')->constrained('wiadomosci')->cascadeOnDelete();
            $table->foreignId('plik_id')->constrained('pliki')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zalaczniki_wiadomosci');
        Schema::dropIfExists('powiazania_plikow');
        Schema::dropIfExists('wersje_plikow');
        Schema::dropIfExists('pliki');
        Schema::table('klienci', function (Blueprint $table) {
            $table->dropForeign(['poziom_lojalnosci_id']);
            $table->dropColumn('poziom_lojalnosci_id');
        });
        Schema::dropIfExists('poziomy_lojalnosci');
    }
};
