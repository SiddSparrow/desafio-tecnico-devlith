<?php

namespace Tests\Feature;

use App\Jobs\GerarExcelFinalJob;
use App\Models\User;
use App\Notifications\ExportacaoConcluida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GerarExcelFinalJobTest extends TestCase
{
    use RefreshDatabase;

    private const BATCH_ID    = 'test-batch-abc123';
    private const NOME_ARQUIVO = 'exports/alunos_teste.xlsx';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function inserirLinhasTemp(int $quantidade = 3, string $batchId = self::BATCH_ID): void
    {
        $linhas = array_map(fn ($i) => [
            'batch_id'           => $batchId,
            'nome'               => "Aluno $i",
            'email'              => "aluno$i@teste.com",
            'data_de_nascimento' => '2000-01-01',
            'primeiro_ano'       => '2020',
            'ultimo_ano'         => '2024',
            'escola_recente'     => 'Escola Teste',
            'cpf'                => '11111111111',
            'rg'                 => '11111111',
            'logradouro'         => 'Rua Teste, 100',
            'cep'                => '12345678',
            'aprovacoes'         => $i,
            'reprovacoes'        => 0,
        ], range(1, $quantidade));

        DB::table('exportacao_alunos_temp')->insert($linhas);
    }

    public function test_gera_arquivo_excel_no_storage(): void
    {
        $user = User::factory()->create();
        $this->inserirLinhasTemp();
        Notification::fake();

        $job = new GerarExcelFinalJob(self::BATCH_ID, self::NOME_ARQUIVO, $user->id);
        $job->handle();

        Storage::disk('local')->assertExists(self::NOME_ARQUIVO);
    }

    public function test_limpa_registros_da_tabela_temporaria_apos_gerar_excel(): void
    {
        $user = User::factory()->create();
        $this->inserirLinhasTemp(5);
        Notification::fake();

        $job = new GerarExcelFinalJob(self::BATCH_ID, self::NOME_ARQUIVO, $user->id);
        $job->handle();

        $this->assertDatabaseMissing('exportacao_alunos_temp', ['batch_id' => self::BATCH_ID]);
        $this->assertDatabaseCount('exportacao_alunos_temp', 0);
    }

    public function test_nao_remove_registros_de_outros_batches(): void
    {
        $user = User::factory()->create();
        $this->inserirLinhasTemp(2, self::BATCH_ID);
        $this->inserirLinhasTemp(3, 'outro-batch-xyz');
        Notification::fake();

        $job = new GerarExcelFinalJob(self::BATCH_ID, self::NOME_ARQUIVO, $user->id);
        $job->handle();

        $this->assertDatabaseMissing('exportacao_alunos_temp', ['batch_id' => self::BATCH_ID]);
        $this->assertDatabaseCount('exportacao_alunos_temp', 3);
    }

    public function test_envia_notificacao_ao_solicitante(): void
    {
        $user = User::factory()->create();
        $this->inserirLinhasTemp();
        Notification::fake();

        $job = new GerarExcelFinalJob(self::BATCH_ID, self::NOME_ARQUIVO, $user->id);
        $job->handle();

        Notification::assertSentTo($user, ExportacaoConcluida::class);
    }

    public function test_nao_lanca_excecao_quando_solicitante_nao_existe(): void
    {
        $this->inserirLinhasTemp();
        Notification::fake();

        $job = new GerarExcelFinalJob(self::BATCH_ID, self::NOME_ARQUIVO, 99999);
        $job->handle();

        Notification::assertNothingSent();
        Storage::disk('local')->assertExists(self::NOME_ARQUIVO);
    }
}
