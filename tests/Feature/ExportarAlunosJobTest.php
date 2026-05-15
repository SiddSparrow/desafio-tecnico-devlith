<?php

namespace Tests\Feature;

use App\Jobs\ExportarAlunosJob;
use App\Jobs\ProcessarChunkExportJob;
use App\Models\Documento;
use App\Models\Endereco;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExportarAlunosJobTest extends TestCase
{
    use RefreshDatabase;

    private function criarAlunoComMatricula(): User
    {
        $escola = Escola::factory()->create();
        $aluno  = User::factory()->create();

        Documento::factory()->create(['user_id' => $aluno->id]);
        Endereco::factory()->create(['user_id' => $aluno->id]);
        Matricula::factory()->create([
            'user_id'    => $aluno->id,
            'escola_id'  => $escola->id,
            'ano_letivo' => 2022,
        ]);

        return $aluno;
    }

    public function test_enfileira_chunk_jobs_para_alunos_com_matriculas(): void
    {
        Queue::fake();

        $this->criarAlunoComMatricula();
        $solicitante = User::factory()->create();

        (new ExportarAlunosJob($solicitante))->handle();

        Queue::assertPushed(ProcessarChunkExportJob::class);
    }

    public function test_chunk_jobs_usam_fila_export(): void
    {
        Queue::fake();

        $this->criarAlunoComMatricula();
        $solicitante = User::factory()->create();

        (new ExportarAlunosJob($solicitante))->handle();

        Queue::assertPushed(ProcessarChunkExportJob::class, function ($job) {
            return $job->queue === 'export';
        });
    }

    public function test_chunk_size_configurado_para_500(): void
    {
        Queue::fake();

        $this->criarAlunoComMatricula();
        $solicitante = User::factory()->create();

        (new ExportarAlunosJob($solicitante))->handle();

        Queue::assertPushed(ProcessarChunkExportJob::class, function ($job) {
            return $job->chunkSize === 500;
        });
    }

    public function test_middleware_impede_execucoes_sobrepostas(): void
    {
        $solicitante = User::factory()->create();
        $job         = new ExportarAlunosJob($solicitante);
        $middlewares = $job->middleware();

        $this->assertNotEmpty($middlewares);
        $this->assertInstanceOf(
            \Illuminate\Queue\Middleware\WithoutOverlapping::class,
            $middlewares[0]
        );
    }

    public function test_pipeline_completo_popula_tabela_temp_e_gera_excel(): void
    {
        // Teste de integração: roda o fluxo completo com fila síncrona
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Notification::fake();

        $solicitante = $this->criarAlunoComMatricula();

        (new ExportarAlunosJob($solicitante))->handle();

        // Com queue sync, os chunk jobs rodam, a tabela temp é populada,
        // GerarExcelFinalJob gera o Excel, limpa a temp e notifica o usuário
        \Illuminate\Support\Facades\Notification::assertSentTo(
            $solicitante,
            \App\Notifications\ExportacaoConcluida::class
        );

        $arquivos = \Illuminate\Support\Facades\Storage::disk('local')->allFiles('exports');
        $this->assertNotEmpty($arquivos);
    }
}
