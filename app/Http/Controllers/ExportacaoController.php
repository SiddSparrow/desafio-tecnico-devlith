<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacaoController extends Controller
{
    public function download(Request $request)
    {
        file_put_contents('/tmp/download_debug.txt', 'reached=' . date('H:i:s'));

        $caminho  = decrypt($request->query('file'));
        $fullPath = storage_path('app/private/' . $caminho);

        file_put_contents('/tmp/download_debug.txt', 'path=' . $fullPath . ' exists=' . (file_exists($fullPath) ? 'yes' : 'no'));

        if (!file_exists($fullPath)) {
            abort(404);
        }

        return response()->download($fullPath, 'alunos_' . now()->format('d-m-Y') . '.xlsx');
    }
}