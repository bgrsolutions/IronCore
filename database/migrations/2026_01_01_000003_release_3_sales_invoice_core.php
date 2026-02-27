<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tax_id')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->json('overrides')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'customer_id']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('series_type', 2);
            $table->string('series_prefix', 20);
            $table->string('number')->nullable();
            $table->date('issue_date')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('net_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('hash', 128)->nullable();
            $table->string('previous_hash', 128)->nullable();
            $table->text('qr_payload')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('credit_note_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->unsignedBigInteger('export_batch_id')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'series_prefix', 'number']);
            $table->index(['company_id', 'series_type', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('manual');
            $table->string('external_reference')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('customer_company');
        Schema::dropIfExists('customers');
    }
};
