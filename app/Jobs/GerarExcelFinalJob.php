<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ExportacaoConcluida;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GerarExcelFinalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        private readonly string $batchId,
        private readonly string $nomeArquivo,
        private readonly int    $solicitanteId,
    ) {}

    public function handle(): void
    {
        Log::info('Gerando Excel final', [
            'batch_id' => $this->batchId,
            'arquivo'  => $this->nomeArquivo,
        ]);

        Storage::disk('local')->makeDirectory('exports');
        $fullPath = Storage::disk('local')->path($this->nomeArquivo);

        $this->escreverXlsx($fullPath);

        DB::table('exportacao_alunos_temp')
            ->where('batch_id', $this->batchId)
            ->delete();

        Log::info('Excel gerado e tabela temp limpa', [
            'arquivo' => $this->nomeArquivo,
        ]);

        $solicitante = User::find($this->solicitanteId);
        $solicitante?->notify(new ExportacaoConcluida($this->nomeArquivo));
    }

    // Escreve o xlsx via XMLWriter + ZipArchive: nunca acumula mais de 500
    // linhas em memória, independente do volume total de registros.
    private function escreverXlsx(string $path): void
    {
        $tmpSheet = tempnam(sys_get_temp_dir(), 'xlsx_sheet_');

        try {
            $this->escreverSheet($tmpSheet);
            $this->empacotarZip($path, $tmpSheet);
        } finally {
            @unlink($tmpSheet);
        }
    }

    private function escreverSheet(string $tmpPath): void
    {
        $xml = new \XMLWriter();
        $xml->openURI($tmpPath);
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->startElement('sheetData');

        $this->escreverLinha($xml, 1, [
            'Nome', 'E-mail', 'Data de Nascimento', 'Range de Escolaridade',
            'Escola', 'CPF', 'RG', 'Logradouro', 'CEP', 'Aprovações', 'Reprovações',
        ], styleIndex: 1);

        $rowNum = 2;

        DB::table('exportacao_alunos_temp')
            ->where('batch_id', $this->batchId)
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($xml, &$rowNum): void {
                foreach ($rows as $row) {
                    $this->escreverLinha($xml, $rowNum++, [
                        $row->nome,
                        $row->email,
                        $row->data_de_nascimento,
                        $row->primeiro_ano && $row->ultimo_ano
                            ? "{$row->primeiro_ano}-{$row->ultimo_ano}" : '-',
                        $row->escola_recente ?? '-',
                        $this->formatCpf($row->cpf),
                        $this->formatRg($row->rg),
                        $row->logradouro,
                        $this->formatCep($row->cep),
                        (int) $row->aprovacoes,
                        (int) $row->reprovacoes,
                    ]);
                }
            });

        $xml->endElement(); // sheetData
        $xml->endElement(); // worksheet
        $xml->endDocument();
        $xml->flush();
    }

    private function escreverLinha(\XMLWriter $xml, int $row, array $values, int $styleIndex = 0): void
    {
        static $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'];

        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $row);

        foreach ($values as $i => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', $cols[$i] . $row);

            if ($styleIndex > 0) {
                $xml->writeAttribute('s', (string) $styleIndex);
            }

            if (is_int($value) || is_float($value)) {
                $xml->startElement('v');
                $xml->text((string) $value);
                $xml->endElement();
            } else {
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->startElement('t');
                $xml->text((string) ($value ?? ''));
                $xml->endElement(); // t
                $xml->endElement(); // is
            }

            $xml->endElement(); // c
        }

        $xml->endElement(); // row
    }

    private function empacotarZip(string $path, string $tmpSheet): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $zip    = new \ZipArchive();
        $result = $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException("ZipArchive::open falhou (código {$result}) ao abrir: {$path}");
        }

        $zip->addFromString('[Content_Types].xml', $this->xmlContentTypes());
        $zip->addFromString('_rels/.rels',          $this->xmlRels());
        $zip->addFromString('xl/workbook.xml',      $this->xmlWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xmlWorkbookRels());
        $zip->addFromString('xl/styles.xml',        $this->xmlStyles());
        $zip->addFile($tmpSheet, 'xl/worksheets/sheet1.xml');
        $zip->close();
    }

    private function xmlContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"           ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"  ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"             ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private function xmlRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function xmlWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Alunos" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }

    private function xmlWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>';
    }

    private function xmlStyles(): string
    {
        // xf index 0 = padrão; xf index 1 = cabeçalho (negrito branco, fundo índigo)
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF4F46E5"/></patternFill></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
  </cellXfs>
</styleSheet>';
    }

    private function formatCpf(?string $cpf): string
    {
        if (! $cpf) return '-';
        $cpf = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf) ?? '-';
    }

    private function formatRg(?string $rg): string
    {
        if (! $rg) return '-';
        $rg = preg_replace('/\D/', '', $rg);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{1})/', '$1.$2.$3-$4', $rg) ?? '-';
    }

    private function formatCep(?string $cep): string
    {
        if (! $cep) return '-';
        $cep = preg_replace('/\D/', '', $cep);
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep) ?? '-';
    }
}
