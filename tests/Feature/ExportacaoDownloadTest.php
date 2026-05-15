<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportacaoDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_download_requer_autenticacao(): void
    {
        $url = route('exportacao.download', ['file' => encrypt('exports/qualquer.xlsx')]);

        $this->get($url)
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_download_retorna_arquivo_para_caminho_valido(): void
    {
        Storage::disk('local')->put('exports/alunos_test.xlsx', 'conteudo-fake');

        $user = User::factory()->create();
        $url  = route('exportacao.download', [
            'file' => encrypt('exports/alunos_test.xlsx'),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_download_retorna_404_quando_arquivo_nao_existe(): void
    {
        $user = User::factory()->create();
        $url  = route('exportacao.download', [
            'file' => encrypt('exports/nao_existe.xlsx'),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertNotFound();
    }

    public function test_download_rejeita_token_invalido_com_erro(): void
    {
        $user = User::factory()->create();
        $url  = route('exportacao.download', ['file' => 'token-invalido-adulterado']);

        $this->withoutExceptionHandling()
            ->actingAs($user);

        $this->expectException(\Illuminate\Contracts\Encryption\DecryptException::class);

        $this->get($url);
    }

    public function test_nome_do_arquivo_baixado_contem_data_atual(): void
    {
        Storage::disk('local')->put('exports/alunos_test.xlsx', 'conteudo-fake');

        $user         = User::factory()->create();
        $dataHoje     = now()->format('d-m-Y');
        $nomeEsperado = "alunos_{$dataHoje}.xlsx";
        $url          = route('exportacao.download', [
            'file' => encrypt('exports/alunos_test.xlsx'),
        ]);

        $this->actingAs($user)
            ->get($url)
            ->assertHeader('Content-Disposition', "attachment; filename={$nomeEsperado}");
    }
}
