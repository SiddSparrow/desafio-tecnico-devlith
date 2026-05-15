<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Notifications\Actions\Action;

class ExportacaoConcluida extends Notification
{
    public function __construct(
        private readonly string $caminhoArquivo
    ) {}

    // Canal do Filament (aparece no sino de notificações)
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        // Gera URL de download assinada (expira em 1h)
        $url = route('exportacao.download', [
            'file' => encrypt($this->caminhoArquivo)
        ]);

        return FilamentNotification::make()
            ->title('Exportação concluída!')
            ->success()
            ->body('A planilha de alunos está pronta para download.')
            ->actions([
                Action::make('download')
                    ->label('Baixar Excel')
                    ->url($url)
                    ->openUrlInNewTab(),
            ])
            ->getDatabaseMessage();
    }
}