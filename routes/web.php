<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExportacaoController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/exportacao/download', [ExportacaoController::class, 'download'])
    ->name('exportacao.download');
