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
        // DAMS: temporary_uploads
        Schema::create('temporary_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('filepath');
            $table->string('tus_id')->unique()->nullable();
            $table->bigInteger('size');
            $table->string('mime_type')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // Production: maszyny
        Schema::create('maszyny', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa');
            $table->string('typ'); // np. offset, digital, plotter
            $table->string('status')->default('available'); // available, maintenance, broken
            $table->decimal('koszt_godziny', 10, 2)->default(0);
            $table->jsonb('parametry_techniczne')->nullable();
            $table->timestamps();
        });

        // Production: operatorzy_produkcji
        Schema::create('operatorzy_produkcji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('specjalizacja')->nullable(); // np. ['druk_offset', 'ciecie']
            $table->boolean('dostepny')->default(true);
            $table->timestamps();
        });

        // Production: zlecenia_produkcyjne
        Schema::create('zlecenia_produkcyjne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zamowienie_id')->unique()->constrained('zamowienia')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, in_progress, on_hold, completed
            $table->string('priorytet')->default('normal'); // normal, high, urgent
            $table->date('data_planowana')->nullable();
            $table->text('notatki')->nullable();
            $table->timestamps();
        });

        // Production: etapy_produkcji
        Schema::create('etapy_produkcji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zlecenie_id')->constrained('zlecenia_produkcyjne')->cascadeOnDelete();
            $table->string('typ'); // prepress, druk, ciecie, laminowanie, etc.
            $table->integer('kolejnosc')->default(1);
            $table->string('status')->default('pending');
            $table->foreignId('maszyna_id')->nullable()->constrained('maszyny')->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('czas_start')->nullable();
            $table->timestamp('czas_stop')->nullable();
            $table->integer('czas_normatywny_min')->nullable();
            $table->text('notatki_operatora')->nullable();
            $table->timestamps();

            $table->index('zlecenie_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etapy_produkcji');
        Schema::dropIfExists('zlecenia_produkcyjne');
        Schema::dropIfExists('operatorzy_produkcji');
        Schema::dropIfExists('maszyny');
        Schema::dropIfExists('temporary_uploads');
    }
};
