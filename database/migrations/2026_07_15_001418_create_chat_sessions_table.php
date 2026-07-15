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
    Schema::create('chat_sessions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
        $table->string('platform')->default('telegram');
        $table->string('external_chat_id'); // ID пользователя в телеграме
        $table->foreignId('current_step_id')->nullable()->constrained('steps')->nullOnDelete();
        $table->json('context')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->timestamps();
        $table->json('messages')->nullable(); // Добавь вот это
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
