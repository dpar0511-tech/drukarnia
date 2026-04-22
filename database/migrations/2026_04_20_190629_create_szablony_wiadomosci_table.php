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
        Schema::create('szablony_wiadomosci', function (Blueprint $table) {
            $table->id();
            $table->string('nazwa')->unique();
            $table->enum('kanal', ['email', 'sms', 'system']);
            $table->string('temat')->nullable();
            $table->text('tresc_html');
            $table->json('zmienne'); // parametry interpolacyjne np. ["{{order_number}}"]
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('szablony_wiadomosci');
    }
};
