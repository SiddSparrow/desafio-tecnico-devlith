<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;


class ExportarAlunosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly User  $solicitante,
        private readonly array $userIds = [],
    ) {}

    public function middleware(): array
    {
        return [];
    }

    public function handle(): void
    {
        $inicio      = now();
        $solicitante = $this->solicitante;
        $nomeArquivo = 'exports/alunos_' . $inicio->format('Ymd_His') . '.xlsx';
        $chunkSize   = 1000;

        Log::info('Iniciando exportação em batch', [
            'solicitante_id' => $solicitante->id,
            'solicitante'    => $solicitante->email,
            'inicio'         => $inicio->toDateTimeString(),
        ]);

        // Busca apenas os IDs dos cursors — leve, sem carregar dados
        // Pega o último ID de cada chunk: [499, 999, 1499, ...]
        // e adiciona 0 na frente para o primeiro chunk (WHERE id > 0)
        $hasFilter  = !empty($this->userIds);
        $inIds      = $hasFilter ? implode(',', array_map('intval', $this->userIds)) : null;

        $totalAlunos = DB::table('users')
            ->whereExists(fn($q) => $q->select(DB::raw(1))
                ->from('matriculas')
                ->whereColumn('matriculas.user_id', 'users.id'))
            ->when($hasFilter, fn($q) => $q->whereIn('users.id', $this->userIds))
            ->count();

        // Usa ROW_NUMBER() para buscar apenas os IDs de fronteira de cada chunk
        // sem carregar todos os IDs na memória PHP
        $filterClause = $hasFilter ? "AND u.id IN ($inIds)" : '';
        $lastIds = collect([0])->merge(
            DB::select("
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS rn
                    FROM users u
                    WHERE EXISTS (
                        SELECT 1 FROM matriculas m WHERE m.user_id = u.id
                    )
                    $filterClause
                ) t
                WHERE rn % :chunk = 0
            ", ['chunk' => $chunkSize])
        )->map(fn($row) => is_object($row) ? $row->id : $row);
        $totalJobs   = $lastIds->count();

        Log::info('Batch de exportação criado', [
            'total_alunos' => $totalAlunos,
            'chunk_size'   => $chunkSize,
            'total_jobs'   => $totalJobs,
        ]);

        try {
            $batch = Bus::batch([])
                ->then(function (Batch $batch) use ($nomeArquivo, $solicitante) {
                    Log::info('Todos chunks concluídos, gerando Excel final', [
                        'batch_id' => $batch->id,
                    ]);
                    GerarExcelFinalJob::dispatch(
                        $batch->id,
                        $nomeArquivo,
                        $solicitante->id
                    )->onQueue('export');
                })
                ->catch(function (Batch $batch, \Throwable $e) {
                    Log::error('Batch falhou', [
                        'batch_id' => $batch->id,
                        'erro'     => $e->getMessage(),
                    ]);
                })
                ->name('Exportação de Alunos')
                ->dispatch();

            Log::info('Batch despachado com sucesso', ['batch_id' => $batch->id]);

            // Adiciona os jobs em grupos de 20 
            $lastIds
                ->chunk(20)
                ->each(function ($grupo) use ($batch, $chunkSize) {
                    $batch->add(
                        $grupo->map(
                            fn($lastId) => (new ProcessarChunkExportJob($lastId, $chunkSize, $this->userIds))
                            ->onQueue('export')
                        )->values()->all()
                    );

                    Log::debug('Grupo adicionado ao batch', [
                        'batch_id'   => $batch->id,
                        'total_jobs' => $batch->fresh()->totalJobs,
                    ]);
                });

            Log::info('Todos os chunks adicionados', [
                'batch_id'   => $batch->id,
                'total_jobs' => $batch->fresh()->totalJobs,
            ]);

        } catch (\Throwable $e) {
            Log::error('Erro ao despachar batch', [
                'erro'  => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}