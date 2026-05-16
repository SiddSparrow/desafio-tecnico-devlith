<?php

namespace App\Filament\Tables\Actions;

use App\Actions\ExportarAlunosDaEscolaAction;
use App\Models\Escola;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class ExportarAlunosDaEscolaTableAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'exportar_alunos';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Exportar Alunos')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn(Escola $record) => "Exportar alunos de {$record->nome}")
            ->modalDescription('A exportação será processada em segundo plano. Você receberá uma notificação assim que o arquivo estiver pronto.')
            ->modalSubmitActionLabel('Iniciar exportação')
            ->action(function (Escola $record): void {
                app(ExportarAlunosDaEscolaAction::class)->execute(Auth::user(), $record);
            });
    }
}
