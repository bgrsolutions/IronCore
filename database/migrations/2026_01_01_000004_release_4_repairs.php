<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('intake');
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('reported_issue')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('diagnostic_fee_added')->default(false);
            $table->decimal('diagnostic_fee_net', 14, 2)->default(45.00);
            $table->decimal('diagnostic_fee_tax_rate', 8, 4)->default(0.07);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('repair_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
            $table->index(['repair_id', 'changed_at']);
        });

        Schema::create('repair_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('minutes');
            $table->string('labour_product_code', 20);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['repair_id', 'started_at']);
        });

        Schema::create('repair_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('line_cost', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['repair_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_parts');
        Schema::dropIfExists('repair_time_entries');
        Schema::dropIfExists('repair_status_history');
        Schema::dropIfExists('repairs');
    }
};
