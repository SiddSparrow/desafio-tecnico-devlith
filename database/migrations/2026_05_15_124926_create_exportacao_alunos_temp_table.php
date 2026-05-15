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
        Schema::create('exportacao_alunos_temp', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 36)->index(); // para limpar após export
            $table->string('nome');
            $table->string('email');
            $table->date('data_de_nascimento')->nullable();
            $table->string('primeiro_ano')->nullable();
            $table->string('ultimo_ano')->nullable();
            $table->string('escola_recente')->nullable();
            $table->string('cpf')->nullable();
            $table->string('rg')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('cep')->nullable();
            $table->integer('aprovacoes')->default(0);
            $table->integer('reprovacoes')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exportacao_alunos_temp');
    }
};
