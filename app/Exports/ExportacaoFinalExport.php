<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportacaoFinalExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithChunkReading,
    WithColumnWidths,
    WithStyles
{
    public function __construct(
        private readonly string $batchId
    ) {}

    public function query()
    {
        return DB::table('exportacao_alunos_temp')
            ->where('batch_id', $this->batchId)
            ->orderBy('id');
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function headings(): array
    {
        return [
            'Nome', 'E-mail', 'Data de Nascimento', 'Range de Escolaridade',
            'Escola', 'CPF', 'RG', 'Logradouro', 'CEP', 'Aprovações', 'Reprovações',
        ];
    }

    public function map($row): array
    {
        return [
            $row->nome,
            $row->email,
            $row->data_de_nascimento,
            $row->primeiro_ano && $row->ultimo_ano
                ? "{$row->primeiro_ano}-{$row->ultimo_ano}"
                : '-',
            $row->escola_recente ?? '-',
            $this->formatCpf($row->cpf),
            $this->formatRg($row->rg),
            $row->logradouro,
            $this->formatCep($row->cep),
            (int) $row->aprovacoes,
            (int) $row->reprovacoes,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30, // Nome
            'B' => 35, // E-mail
            'C' => 20, // Data de Nascimento
            'D' => 20, // Range de Escolaridade
            'E' => 35, // Escola
            'F' => 18, // CPF
            'G' => 15, // RG
            'H' => 35, // Logradouro
            'I' => 12, // CEP
            'J' => 12, // Aprovações
            'K' => 12, // Reprovações
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
            ],
        ];
    }

    private function formatCpf(?string $cpf): string
    {
        if (! $cpf) return '-';
        $cpf = preg_replace('/\D/', '', $cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    private function formatRg(?string $rg): string
    {
        if (! $rg) return '-';
        $rg = preg_replace('/\D/', '', $rg);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{1})/', '$1.$2.$3-$4', $rg);
    }

    private function formatCep(?string $cep): string
    {
        if (! $cep) return '-';
        $cep = preg_replace('/\D/', '', $cep);
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
    }
}