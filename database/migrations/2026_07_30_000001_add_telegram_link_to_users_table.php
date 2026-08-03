<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Each subscriber links their own Telegram chat here (via a
            // one-time /LINK code, see TelegramBotService), so "send
            // signal" reaches their own phone instead of the shared
            // broadcast channel.
            $table->string('telegram_chat_id')->nullable()->after('is_admin');
            $table->string('telegram_link_code', 8)->nullable()->unique();
            $table->timestamp('telegram_link_code_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_link_code', 'telegram_link_code_expires_at']);
        });
    }
};
