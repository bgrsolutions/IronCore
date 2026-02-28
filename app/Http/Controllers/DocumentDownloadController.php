<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document)
    {
        abort_unless(auth()->check(), 403);
        abort_unless(auth()->user()->companies()->where('companies.id', $document->company_id)->exists(), 403);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
