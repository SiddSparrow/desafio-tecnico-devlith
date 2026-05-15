<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessarChunkExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $timeout = 120;
    public int $tries   = 1;

    public function __construct(
        public int $lastId,
        public int $chunkSize,
    ) {}

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        Log::info('Processando chunk', [
            'last_id'    => $this->lastId,
            'chunk_size' => $this->chunkSize,
        ]);

        // Passo 1: busca até $chunkSize user_ids a partir de matriculas.
        // WHERE user_id > lastId + DISTINCT + ORDER BY user_id + LIMIT usa o índice
        // (user_id, ano_letivo) sem temp table nem filesort — O(log n + chunk).
        $userIds = DB::table('matriculas')
            ->select('user_id')
            ->where('user_id', '>', $this->lastId)
            ->distinct()
            ->orderBy('user_id')
            ->limit($this->chunkSize)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            Log::info('Chunk vazio, nada a inserir', ['last_id' => $this->lastId]);
            return;
        }

        // Passo 2: agrega matriculas apenas para os user_ids do chunk.
        // GROUP BY sobre ≤500 IDs específicos usa index range scan por user_id.
        $agregados = DB::table('matriculas')
            ->select(
                'user_id',
                DB::raw('MIN(ano_letivo) as primeiro_ano'),
                DB::raw('MAX(ano_letivo) as ultimo_ano'),
                DB::raw('SUM(CASE WHEN resultado_final = "aprovado" THEN 1 ELSE 0 END) as aprovacoes'),
                DB::raw('SUM(CASE WHEN resultado_final = "reprovado" THEN 1 ELSE 0 END) as reprovacoes'),
            )
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // Passo 3: escola mais recente por user_id.
        // ORDER BY user_id, ano_letivo DESC usa o índice composto; unique() em PHP
        // descarta duplicatas mantendo a primeira ocorrência (maior ano_letivo).
        $escolaRecente = DB::table('matriculas as m')
            ->join('escolas', 'escolas.id', '=', 'm.escola_id')
            ->select('m.user_id', 'escolas.nome as escola_recente')
            ->whereIn('m.user_id', $userIds)
            ->orderBy('m.user_id')
            ->orderByDesc('m.ano_letivo')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        // Passo 4: dados cadastrais dos usuários do chunk (≤500 rows via PK lookup).
        $usuarios = DB::table('users')
            ->leftJoin('documentos', 'documentos.user_id', '=', 'users.id')
            ->leftJoin('enderecos', 'enderecos.user_id', '=', 'users.id')
            ->select(
                'users.id', 'users.name', 'users.email', 'users.data_de_nascimento',
                'documentos.cpf', 'documentos.rg',
                'enderecos.logradouro', 'enderecos.cep',
            )
            ->whereIn('users.id', $userIds)
            ->get()
            ->keyBy('id');

        // Passo 5: combina os resultados em PHP e monta o array de inserção.
        $insert = $userIds->map(function ($userId) use ($usuarios, $agregados, $escolaRecente) {
            $u   = $usuarios[$userId]      ?? null;
            $agg = $agregados[$userId]     ?? null;
            $esc = $escolaRecente[$userId] ?? null;

            if (! $u || ! $agg) {
                return null;
            }

            return [
                'batch_id'           => $this->batchId,
                'nome'               => $u->name,
                'email'              => $u->email,
                'data_de_nascimento' => $u->data_de_nascimento,
                'primeiro_ano'       => $agg->primeiro_ano,
                'ultimo_ano'         => $agg->ultimo_ano,
                'escola_recente'     => $esc?->escola_recente,
                'cpf'                => $u->cpf,
                'rg'                 => $u->rg,
                'logradouro'         => $u->logradouro,
                'cep'                => $u->cep,
                'aprovacoes'         => (int) $agg->aprovacoes,
                'reprovacoes'        => (int) $agg->reprovacoes,
            ];
        })->filter()->values()->toArray();

        if (empty($insert)) {
            Log::info('Chunk vazio após merge, nada a inserir', ['last_id' => $this->lastId]);
            return;
        }

        DB::table('exportacao_alunos_temp')->insert($insert);

        Log::info('Chunk inserido', [
            'last_id' => $this->lastId,
            'count'   => count($insert),
        ]);
    }
}
