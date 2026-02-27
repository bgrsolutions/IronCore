<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('plan_type', ['subscription', 'service_contract'])->default('subscription');
            $table->unsignedInteger('interval_months');
            $table->decimal('price_net', 12, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->char('currency', 3)->default('EUR');
            $table->enum('default_doc_type', ['ticket', 'invoice'])->default('invoice');
            $table->string('default_series', 20)->nullable();
            $table->boolean('auto_post')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active');
            $table->dateTime('starts_at');
            $table->dateTime('next_run_at');
            $table->dateTime('ends_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->boolean('auto_post')->nullable();
            $table->enum('doc_type', ['ticket', 'invoice'])->nullable();
            $table->string('series', 20)->nullable();
            $table->decimal('price_net', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'next_run_at']);
        });

        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('tax_rate', 5, 2);
            $table->timestamps();
        });

        Schema::create('subscription_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->dateTime('run_at');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->enum('status', ['success', 'skipped', 'failed']);
            $table->text('message')->nullable();
            $table->foreignId('generated_sales_document_id')->nullable()->constrained('sales_documents')->nullOnDelete();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'run_at']);
            $table->index(['subscription_id', 'run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_runs');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
