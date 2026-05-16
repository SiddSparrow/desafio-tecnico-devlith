<?php

namespace App\Filament\Pages\Actions;

use App\Actions\ExportarAlunosAction;
use Filament\Actions\Action;

class ExportarTodosHeaderAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'exportar_todos';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
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
                app(ExportarAlunosAction::class)->execute(auth()->user());
            });
    }
}
