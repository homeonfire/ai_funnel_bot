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
    Schema::create('transitions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('from_step_id')->constrained('steps')->cascadeOnDelete();
        $table->foreignId('to_step_id')->constrained('steps')->cascadeOnDelete();
        $table->string('logical_operator')->default('AND');
        $table->json('rules');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transitions');
    }
};
