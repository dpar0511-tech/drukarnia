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
        Schema::create('wiadomosci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watek_id')->index()->constrained('watki_komunikacji')->cascadeOnDelete();
            $table->enum('kierunek', ['przychodzacy', 'wychodzacy']);
            $table->enum('kanal', ['email', 'sms', 'system']);
            $table->longText('tresc');
            $table->string('nadawca_email')->nullable();
            $table->string('odbiorca_email')->nullable();
            $table->string('zewnetrzny_id')->unique()->nullable();
            $table->string('status_dostarczenia')->nullable(); // np. wyslane, dostarczone
            $table->boolean('przeczytana')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wiadomosci');
    }
};
