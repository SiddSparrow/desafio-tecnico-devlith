<?php

namespace App\Actions;

use App\Jobs\ExportarAlunosJob;
use App\Models\Escola;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ExportarAlunosDaEscolaAction
{
    public function execute(User $solicitante, Escola $escola): void
    {
        $userIds = DB::table('matriculas')
            ->where('escola_id', $escola->id)
            ->distinct()
            ->pluck('user_id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        ExportarAlunosJob::dispatch($solicitante, $userIds, $escola->nome);

        Notification::make()
            ->title('Exportação iniciada!')
            ->body("Exportando alunos de {$escola->nome}.")
            ->info()
            ->send();
    }
}
