<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Jobs\ExportarAlunosJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportar_todos')
                ->label('Exportar Todos')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar todos os alunos')
                ->modalDescription(
                    'A exportação será processada em segundo plano. ' .
                    'Você receberá uma notificação assim que o arquivo estiver pronto.'
                )
                ->modalSubmitActionLabel('Iniciar exportação')
                ->action(function (): void {
                    ExportarAlunosJob::dispatch(auth()->user());

                    Notification::make()
                        ->title('Exportação iniciada!')
                        ->body('Você será notificado quando o arquivo estiver pronto.')
                        ->info()
                        ->send();
                }),
        ];
    }
}
