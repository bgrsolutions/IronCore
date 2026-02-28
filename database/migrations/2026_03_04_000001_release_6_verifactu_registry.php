<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('verifactu_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('export_type', ['verifactu_records'])->default('verifactu_records');
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->dateTime('generated_at');
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path');
            $table->string('file_hash', 128);
            $table->unsignedInteger('record_count')->default(0);
            $table->enum('status', ['generated', 'downloaded'])->default('generated');
            $table->timestamps();

            $table->index(['company_id', 'generated_at']);
        });

        Schema::create('verifactu_export_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verifactu_export_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
            $table->dateTime('included_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['verifactu_export_id', 'sales_document_id']);
        });

        Schema::create('verifactu_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifactu_events');
        Schema::dropIfExists('verifactu_export_items');
        Schema::dropIfExists('verifactu_exports');
    }
};
