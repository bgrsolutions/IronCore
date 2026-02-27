<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAttachmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_can_be_attached_and_downloaded(): void
    {
        Storage::fake('local');
        $this->seed();

        $company = Company::first();
        $user = User::first();
        $this->actingAs($user);

        Storage::disk('local')->put('documents/sample.pdf', 'pdf-content');

        $document = Document::create([
            'company_id' => $company->id,
            'disk' => 'local',
            'path' => 'documents/sample.pdf',
            'original_name' => 'sample.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 11,
            'status' => 'active',
            'uploaded_at' => now(),
        ]);

        $expense = Expense::create([
            'company_id' => $company->id,
            'merchant' => 'Store',
            'date' => now()->toDateString(),
            'category' => 'Ops',
            'status' => 'draft',
        ]);

        DocumentAttachment::create([
            'company_id' => $company->id,
            'document_id' => $document->id,
            'attachable_type' => Expense::class,
            'attachable_id' => $expense->id,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('document_attachments', ['document_id' => $document->id, 'attachable_id' => $expense->id]);

        $this->get(route('documents.download', $document))->assertOk();
    }
}
