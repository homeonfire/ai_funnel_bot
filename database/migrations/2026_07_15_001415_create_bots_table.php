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
    Schema::create('bots', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->json('platform_settings')->nullable();
        $table->string('llm_provider')->default('openai');
        $table->string('llm_model');
        $table->text('api_key'); // Будем шифровать
        $table->boolean('is_active')->default(true);
        
        // Поля для Telegram
        $table->text('tg_token')->nullable(); // Будем шифровать
        $table->boolean('webhook_status')->default(false);
        $table->text('webhook_error')->nullable();
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
