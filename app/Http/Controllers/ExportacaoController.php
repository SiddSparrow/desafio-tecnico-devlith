<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class ExportacaoController extends Controller
{
    public function download(Request $request)
    {
        $caminho  = decrypt($request->query('file'));
        $fullPath = Storage::disk('local')->path($caminho);

        if (!file_exists($fullPath)) {
            abort(404);
        }

        return response()->download($fullPath, basename($caminho));
    }
}