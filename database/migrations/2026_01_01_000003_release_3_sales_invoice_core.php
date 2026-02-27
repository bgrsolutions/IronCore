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
<<<<<<< codex/implement-release-1-of-ironcore-erp-dift7s
=======
            $table->string('tax_id')->nullable()->index();
>>>>>>> main
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
<<<<<<< codex/implement-release-1-of-ironcore-erp-dift7s
            $table->string('fiscal_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('province')->nullable();
            $table->boolean('wants_full_invoice')->default(false);
            $table->string('default_payment_terms')->nullable();
=======
            $table->json('overrides')->nullable();
>>>>>>> main
            $table->timestamps();
            $table->unique(['company_id', 'customer_id']);
        });

<<<<<<< codex/implement-release-1-of-ironcore-erp-dift7s
        Schema::create('sales_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('doc_type', ['ticket', 'invoice', 'credit_note']);
            $table->string('series', 20);
            $table->unsignedBigInteger('number')->nullable();
            $table->string('full_number', 50)->nullable();
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->dateTime('issue_date');
            $table->dateTime('posted_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('net_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('gross_total', 12, 2)->default(0);
            $table->json('immutable_payload')->nullable();
            $table->string('hash', 128)->nullable();
            $table->string('previous_hash', 128)->nullable();
            $table->text('qr_payload')->nullable();
            $table->foreignId('related_document_id')->nullable()->constrained('sales_documents')->nullOnDelete();
            $table->enum('source', ['pos', 'prestashop', 'manual'])->default('manual');
            $table->string('source_ref', 100)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'series', 'number']);
            $table->index(['company_id', 'doc_type', 'status']);
            $table->index(['source', 'source_ref']);
        });

        Schema::create('sales_document_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('line_net', 12, 2);
            $table->decimal('line_tax', 12, 2);
            $table->decimal('line_gross', 12, 2);
            $table->decimal('cost_unit', 12, 4)->nullable();
            $table->decimal('cost_total', 12, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'card', 'bank_transfer', 'other']);
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at');
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('integration', 60);
            $table->string('action', 60);
            $table->string('payload_hash', 64);
            $table->enum('status', ['success', 'error']);
            $table->json('result_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_on_hand', 12, 3);
            $table->string('alert_type', 60)->default('negative_stock');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'alert_type', 'created_at']);
        });
=======
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
>>>>>>> main
    }

    public function down(): void
    {
<<<<<<< codex/implement-release-1-of-ironcore-erp-dift7s
        Schema::dropIfExists('inventory_alerts');
        Schema::dropIfExists('integration_runs');
        Schema::dropIfExists('integration_api_tokens');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sales_document_lines');
        Schema::dropIfExists('sales_documents');
=======
        Schema::dropIfExists('sales');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
>>>>>>> main
        Schema::dropIfExists('customer_company');
        Schema::dropIfExists('customers');
    }
};
