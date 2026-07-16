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
    Schema::table('steps', function (Blueprint $table) {
        $table->float('pos_x')->default(100)->after('name');
        $table->float('pos_y')->default(100)->after('pos_x');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table) {
            //
        });
    }
};
