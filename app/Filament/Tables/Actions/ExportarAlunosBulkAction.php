<?php

namespace App\Filament\Tables\Actions;

use App\Actions\ExportarAlunosAction;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Support\Facades\Auth;

class ExportarAlunosBulkAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'exportar_alunos';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Exportar Selecionados')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Exportar alunos selecionados')
            ->modalDescription(
                'A exportação será processada em segundo plano. ' .
                'Você receberá uma notificação assim que o arquivo estiver pronto.'
            )
            ->modalSubmitActionLabel('Iniciar exportação')
            ->deselectRecordsAfterCompletion()
            ->action(function ($livewire): void {
                $ids = array_map('intval', $livewire->selectedTableRecords ?? []);
                app(ExportarAlunosAction::class)->execute(Auth::user(), $ids);
            });
    }
}
