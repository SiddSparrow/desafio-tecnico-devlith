<?php

namespace App\Actions;

use App\Jobs\ExportarAlunosJob;
use App\Models\User;
use Filament\Notifications\Notification;

class ExportarAlunosAction
{
    public function execute(User $solicitante, array $userIds = [], string $label = ''): void
    {
        ExportarAlunosJob::dispatch($solicitante, $userIds, $label);

        Notification::make()
            ->title('Exportação iniciada!')
            ->body('Você será notificado quando o arquivo estiver pronto.')
            ->info()
            ->send();
    }
}
