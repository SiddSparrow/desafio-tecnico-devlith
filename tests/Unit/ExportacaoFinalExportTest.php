<?php

namespace Tests\Unit;

use App\Exports\ExportacaoFinalExport;
use PHPUnit\Framework\TestCase;

class ExportacaoFinalExportTest extends TestCase
{
    private ExportacaoFinalExport $export;

    protected function setUp(): void
    {
        parent::setUp();
        $this->export = new ExportacaoFinalExport('dummy-batch-id');
    }

    public function test_headings_returns_correct_columns(): void
    {
        $this->assertSame([
            'Nome', 'E-mail', 'Data de Nascimento', 'Range de Escolaridade',
            'Escola', 'CPF', 'RG', 'Logradouro', 'CEP', 'Aprovações', 'Reprovações',
        ], $this->export->headings());
    }

    public function test_chunk_size_is_1000(): void
    {
        $this->assertSame(1000, $this->export->chunkSize());
    }

    public function test_map_formats_cpf_with_mask(): void
    {
        $row = $this->makeRow(['cpf' => '11111111111']);
        $result = $this->export->map($row);
        $this->assertSame('111.111.111-11', $result[5]);
    }

    public function test_map_formats_rg_with_mask(): void
    {
        $row = $this->makeRow(['rg' => '111111111']); // 9 dígitos: XX.XXX.XXX-X
        $result = $this->export->map($row);
        $this->assertSame('11.111.111-1', $result[6]);
    }

    public function test_map_formats_cep_with_mask(): void
    {
        $row = $this->makeRow(['cep' => '12345678']);
        $result = $this->export->map($row);
        $this->assertSame('12345-678', $result[8]);
    }

    public function test_map_formats_range_escolar(): void
    {
        $row = $this->makeRow(['primeiro_ano' => '2020', 'ultimo_ano' => '2024']);
        $result = $this->export->map($row);
        $this->assertSame('2020-2024', $result[3]);
    }

    public function test_map_range_escolar_returns_dash_when_null(): void
    {
        $row = $this->makeRow(['primeiro_ano' => null, 'ultimo_ano' => null]);
        $result = $this->export->map($row);
        $this->assertSame('-', $result[3]);
    }

    public function test_map_escola_recente_returns_dash_when_null(): void
    {
        $row = $this->makeRow(['escola_recente' => null]);
        $result = $this->export->map($row);
        $this->assertSame('-', $result[4]);
    }

    public function test_map_cpf_returns_dash_when_null(): void
    {
        $row = $this->makeRow(['cpf' => null]);
        $result = $this->export->map($row);
        $this->assertSame('-', $result[5]);
    }

    public function test_map_rg_returns_dash_when_null(): void
    {
        $row = $this->makeRow(['rg' => null]);
        $result = $this->export->map($row);
        $this->assertSame('-', $result[6]);
    }

    public function test_map_cep_returns_dash_when_null(): void
    {
        $row = $this->makeRow(['cep' => null]);
        $result = $this->export->map($row);
        $this->assertSame('-', $result[8]);
    }

    public function test_map_casts_aprovacoes_to_int(): void
    {
        $row = $this->makeRow(['aprovacoes' => '3']);
        $result = $this->export->map($row);
        $this->assertSame(3, $result[9]);
        $this->assertIsInt($result[9]);
    }

    public function test_map_casts_reprovacoes_to_int(): void
    {
        $row = $this->makeRow(['reprovacoes' => '2']);
        $result = $this->export->map($row);
        $this->assertSame(2, $result[10]);
        $this->assertIsInt($result[10]);
    }

    public function test_map_returns_all_eleven_columns(): void
    {
        $result = $this->export->map($this->makeRow());
        $this->assertCount(11, $result);
    }

    // Cria um objeto linha com valores-padrão mesclados com sobreposições
    private function makeRow(array $overrides = []): object
    {
        return (object) array_merge([
            'nome'               => 'João Silva',
            'email'              => 'joao@teste.com',
            'data_de_nascimento' => '2000-01-01',
            'primeiro_ano'       => '2020',
            'ultimo_ano'         => '2024',
            'escola_recente'     => 'Escola Central',
            'cpf'                => '11111111111',
            'rg'                 => '11111111',
            'logradouro'         => 'Rua das Flores, 100',
            'cep'                => '12345678',
            'aprovacoes'         => '3',
            'reprovacoes'        => '1',
        ], $overrides);
    }
}
