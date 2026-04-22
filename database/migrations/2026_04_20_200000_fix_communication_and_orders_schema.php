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
        // Fix wiadomosci table
        Schema::table('wiadomosci', function (Blueprint $table) {
            if (Schema::hasColumn('wiadomosci', 'tresc_html') && ! Schema::hasColumn('wiadomosci', 'tresc')) {
                $table->renameColumn('tresc_html', 'tresc');
            }
            if (Schema::hasColumn('wiadomosci', 'zewnetrzny_message_id') && ! Schema::hasColumn('wiadomosci', 'zewnetrzny_id')) {
                $table->renameColumn('zewnetrzny_message_id', 'zewnetrzny_id');
            }
        });

        // Fix szablony_wiadomosci table
        Schema::table('szablony_wiadomosci', function (Blueprint $table) {
            if (Schema::hasColumn('szablony_wiadomosci', 'tresc_html') && ! Schema::hasColumn('szablony_wiadomosci', 'tresc_template')) {
                $table->renameColumn('tresc_html', 'tresc_template');
            }
        });

        // Expand zamowienia table
        Schema::table('zamowienia', function (Blueprint $table) {
            $table->string('numer', 20)->unique()->after('id');
            $table->foreignId('menedzer_id')->nullable()->after('klient_id')->constrained('users')->nullOnDelete();
            $table->enum('priorytet', ['normal', 'wysoki', 'pilny'])->default('normal')->after('status');
            $table->enum('channel', ['email', 'phone', 'manual', 'shop_guest', 'shop_user', 'portal_repeat', 'api'])->default('manual')->after('priorytet');
            $table->date('termin_realizacji')->nullable()->after('channel');
            $table->foreignId('parent_order_id')->nullable()->after('termin_realizacji')->constrained('zamowienia')->nullOnDelete();
            $table->string('source_email')->nullable()->after('parent_order_id');
            $table->text('uwagi_wewnetrzne')->nullable()->after('source_email');
            $table->text('uwagi_klienta')->nullable()->after('uwagi_wewnetrzne');

            $table->index('status');
            $table->index('klient_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zamowienia', function (Blueprint $table) {
            $table->dropForeign(['parent_order_id']);
            $table->dropForeign(['menedzer_id']);
            $table->dropColumn([
                'numer',
                'menedzer_id',
                'priorytet',
                'channel',
                'termin_realizacji',
                'parent_order_id',
                'source_email',
                'uwagi_wewnetrzne',
                'uwagi_klienta',
            ]);
        });

        Schema::table('szablony_wiadomosci', function (Blueprint $table) {
            $table->renameColumn('tresc_template', 'tresc_html');
        });

        Schema::table('wiadomosci', function (Blueprint $table) {
            $table->renameColumn('tresc', 'tresc_html');
            $table->renameColumn('zewnetrzny_id', 'zewnetrzny_message_id');
        });
    }
};
