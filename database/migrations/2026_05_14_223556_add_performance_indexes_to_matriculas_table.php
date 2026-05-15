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
        Schema::table('matriculas', function (Blueprint $table) {
            $table->index(['user_id', 'ano_letivo']);
            $table->index(['user_id', 'resultado_final']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matriculas', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'ano_letivo']);
            $table->dropIndex(['user_id', 'resultado_final']);
        });
    }
};
