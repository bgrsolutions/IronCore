<?php

namespace App\Http\Controllers;

use App\Models\AccountantExportBatch;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AccountantExportDownloadController extends Controller
{
    public function __invoke(AccountantExportBatch $accountantExportBatch): Response
    {
        abort_unless(auth()->user()->companies()->where('companies.id', $accountantExportBatch->company_id)->exists(), 403);

        return Storage::disk('local')->download($accountantExportBatch->zip_path);
    }
}
