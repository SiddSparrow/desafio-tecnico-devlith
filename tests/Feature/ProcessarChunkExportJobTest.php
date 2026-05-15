<?php

namespace Tests\Feature;

use App\Jobs\ProcessarChunkExportJob;
use App\Models\Documento;
use App\Models\Endereco;
use App\Models\Escola;
use App\Models\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessarChunkExportJobTest extends TestCase
{
    use RefreshDatabase;

    // Cria um batch vazio real no banco e retorna seu ID
    private function criarBatchId(): string
    {
        return Bus::batch([])->allowFailures()->dispatch()->id;
    }

    private function criarAluno(array $atributos = []): User
    {
        $user = User::factory()->create($atributos);

        Documento::factory()->create(['user_id' => $user->id]);
        Endereco::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_insere_dados_do_aluno_na_tabela_temporaria(): void
    {
        $escola = Escola::factory()->create(['nome' => 'Escola Alfa']);
        $aluno  = $this->criarAluno();

        Matricula::factory()->aprovado()->create([
            'user_id'   => $aluno->id,
            'escola_id' => $escola->id,
            'ano_letivo' => 2022,
        ]);

        $batchId = $this->criarBatchId();
        $job     = new ProcessarChunkExportJob(0, 500);
        $job->batchId = $batchId;
        $job->handle();

        $this->assertDatabaseHas('exportacao_alunos_temp', [
            'batch_id' => $batchId,
            'nome'     => $aluno->name,
            'email'    => $aluno->email,
        ]);
    }

    public function test_contabiliza_aprovacoes_e_reprovacoes_corretamente(): void
    {
        $escola = Escola::factory()->create();
        $aluno  = $this->criarAluno();

        Matricula::factory()->aprovado()->count(3)->create([
            'user_id'   => $aluno->id,
            'escola_id' => $escola->id,
        ]);
        Matricula::factory()->reprovado()->count(2)->create([
            'user_id'   => $aluno->id,
            'escola_id' => $escola->id,
        ]);

        $batchId = $this->criarBatchId();
        $job     = new ProcessarChunkExportJob(0, 500);
        $job->batchId = $batchId;
        $job->handle();

        $linha = DB::table('exportacao_alunos_temp')
            ->where('batch_id', $batchId)
            ->where('email', $aluno->email)
            ->first();

        $this->assertNotNull($linha);
        $this->assertEquals(3, $linha->aprovacoes);
        $this->assertEquals(2, $linha->reprovacoes);
    }

    public function test_escola_recente_e_a_do_ano_mais_recente(): void
    {
        $escolaAntiga  = Escola::factory()->create(['nome' => 'Escola Antiga']);
        $escolaRecente = Escola::factory()->create(['nome' => 'Escola Recente']);
        $aluno         = $this->criarAluno();

        Matricula::factory()->create([
            'user_id'   => $aluno->id,
            'escola_id' => $escolaAntiga->id,
            'ano_letivo' => 2020,
        ]);
        Matricula::factory()->create([
            'user_id'   => $aluno->id,
            'escola_id' => $escolaRecente->id,
            'ano_letivo' => 2024,
        ]);

        $batchId = $this->criarBatchId();
        $job     = new ProcessarChunkExportJob(0, 500);
        $job->batchId = $batchId;
        $job->handle();

        $linha = DB::table('exportacao_alunos_temp')
            ->where('batch_id', $batchId)
            ->where('email', $aluno->email)
            ->first();

        $this->assertEquals('Escola Recente', $linha->escola_recente);
    }

    public function test_range_escolar_e_calculado_entre_primeiro_e_ultimo_ano(): void
    {
        $escola = Escola::factory()->create();
        $aluno  = $this->criarAluno();

        foreach ([2021, 2023, 2025] as $ano) {
            Matricula::factory()->create([
                'user_id'   => $aluno->id,
                'escola_id' => $escola->id,
                'ano_letivo' => $ano,
            ]);
        }

        $batchId = $this->criarBatchId();
        $job     = new ProcessarChunkExportJob(0, 500);
        $job->batchId = $batchId;
        $job->handle();

        $linha = DB::table('exportacao_alunos_temp')
            ->where('batch_id', $batchId)
            ->where('email', $aluno->email)
            ->first();

        $this->assertEquals(2021, $linha->primeiro_ano);
        $this->assertEquals(2025, $linha->ultimo_ano);
    }

    public function test_ignora_usuarios_sem_matriculas(): void
    {
        // Usuário sem matrículas não deve entrar na exportação
        User::factory()->create();

        $batchId = $this->criarBatchId();
        $job     = new ProcessarChunkExportJob(0, 500);
        $job->batchId = $batchId;
        $job->handle();

        $this->assertDatabaseEmpty('exportacao_alunos_temp');
    }

    public function test_nao_insere_nada_quando_range_de_ids_esta_vazio(): void
    {
        $escola = Escola::factory()->create();
        $aluno  = $this->criarAluno();

        Matricula::factory()->create([
            'user_id'   => $aluno->id,
            'escola_id' => $escola->id,
        ]);

        // lastId maior que todos os IDs existentes → chunk vazio
        $batchId = $this->criarBatchId();
        $job     = new ProcessarChunkExportJob($aluno->id, 500);
        $job->batchId = $batchId;
        $job->handle();

        $this->assertDatabaseEmpty('exportacao_alunos_temp');
    }

    public function test_processa_apenas_alunos_dentro_do_limite_do_chunk(): void
    {
        $escola = Escola::factory()->create();
        $alunos = collect();

        for ($i = 0; $i < 3; $i++) {
            $aluno = $this->criarAluno();
            Matricula::factory()->create([
                'user_id'   => $aluno->id,
                'escola_id' => $escola->id,
            ]);
            $alunos->push($aluno);
        }

        // chunkSize = 1 → apenas o primeiro aluno após o cursor deve ser inserido
        $batchId = $this->criarBatchId();
        $job     = new ProcessarChunkExportJob(0, 1);
        $job->batchId = $batchId;
        $job->handle();

        $count = DB::table('exportacao_alunos_temp')
            ->where('batch_id', $batchId)
            ->count();

        $this->assertEquals(1, $count);
    }
}
