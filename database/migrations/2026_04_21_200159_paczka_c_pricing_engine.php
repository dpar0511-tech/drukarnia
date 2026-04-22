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
        // Pricing: reguly_cenowe
        Schema::create('reguly_cenowe', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa');
            $table->string('typ'); // np. markup, discount, fixed
            $table->jsonb('warunki')->nullable();
            $table->decimal('wartosc', 12, 4);
            $table->boolean('aktywna')->default(true);
            $table->timestamps();
        });

        // Pricing: indywidualne_cenniki
        Schema::create('indywidualne_cenniki', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klient_id')->constrained('klienci')->cascadeOnDelete();
            $table->string('produkt_kod')->index();
            $table->decimal('cena_specjalna', 12, 2);
            $table->timestamps();
        });

        // Pricing: rabaty_definicje
        Schema::create('rabaty_definicje', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa');
            $table->string('kod_rabatowy')->nullable()->unique();
            $table->enum('typ', ['procentowy', 'kwotowy']);
            $table->decimal('wartosc', 12, 2);
            $table->decimal('minimalna_kwota_zamowienia', 12, 2)->default(0);
            $table->timestamp('wygasa_at')->nullable();
            $table->boolean('aktywny')->default(true);
            $table->timestamps();
        });

        // Pricing: kalkulacje_cen
        Schema::create('kalkulacje_cen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zamowienie_id')->constrained('zamowienia')->cascadeOnDelete();
            $table->foreignId('pozycja_id')->nullable()->constrained('pozycje_zamowienia')->cascadeOnDelete();

            $table->decimal('cena_bazowa', 12, 2);
            $table->jsonb('modyfikatory')->nullable(); // Array of { label, value, type }
            $table->decimal('rabat_kwota', 12, 2)->default(0);
            $table->decimal('cena_netto', 12, 2);
            $table->integer('stawka_vat')->default(23);
            $table->decimal('vat_kwota', 12, 2);
            $table->decimal('cena_brutto', 12, 2);

            $table->foreignId('zaakceptowana_przez_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('zaakceptowana_at')->nullable();
            $table->timestamps();
        });

        // Pricing: koszty_wlasne
        Schema::create('koszty_wlasne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zamowienie_id')->constrained('zamowienia')->cascadeOnDelete();
            $table->decimal('koszt_materialow', 12, 2)->default(0);
            $table->decimal('koszt_robocizny', 12, 2)->default(0);
            $table->decimal('koszty_inne', 12, 2)->default(0);
            $table->decimal('marza_realna', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('koszty_wlasne');
        Schema::dropIfExists('kalkulacje_cen');
        Schema::dropIfExists('rabaty_definicje');
        Schema::dropIfExists('indywidualne_cenniki');
        Schema::dropIfExists('reguly_cenowe');
    }
};
