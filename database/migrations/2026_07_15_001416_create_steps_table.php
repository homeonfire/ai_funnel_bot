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
    Schema::create('steps', function (Blueprint $table) {
        $table->id();
        $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->text('stage_prompt')->nullable();
        $table->json('variables_definition')->nullable();
        $table->integer('sort_order')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('steps');
    }
};
