<?php

namespace App\Http\Controllers;

use App\Models\VerifactuExport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class VerifactuExportDownloadController extends Controller
{
    public function __invoke(VerifactuExport $verifactuExport): Response
    {
        if (! auth()->user()->companies()->where('companies.id', $verifactuExport->company_id)->exists()) {
            abort(403);
        }

        $verifactuExport->update(['status' => 'downloaded']);

        return Storage::disk('local')->download($verifactuExport->file_path);
    }
}
