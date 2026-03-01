<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_reorder_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->integer('lead_time_days')->default(3);
            $table->integer('safety_days')->default(7);
            $table->integer('min_days_cover')->default(14);
            $table->integer('max_days_cover')->default(30);
            $table->decimal('min_order_qty', 12, 3)->nullable();
            $table->decimal('pack_size_qty', 12, 3)->nullable();
            $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });

        Schema::create('supplier_stock_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('warehouse_name', 100);
            $table->dateTime('snapshot_at');
            $table->string('source', 50)->default('import');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'supplier_id', 'snapshot_at'], 'sss_co_sup_snap_idx');
        });

        Schema::create('supplier_stock_snapshot_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_stock_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_sku', 80)->nullable();
            $table->string('barcode', 80)->nullable();
            $table->string('product_name')->nullable();
            $table->decimal('qty_available', 12, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->timestamp('created_at')->useCurrent();
            $table->index('supplier_stock_snapshot_id');
            $table->index('product_id');
        });

        Schema::create('reorder_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->dateTime('generated_at');
            $table->unsignedInteger('period_days');
            $table->date('from_date');
            $table->date('to_date');
            $table->json('payload');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'generated_at']);
        });

        Schema::create('reorder_suggestion_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reorder_suggestion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('suggested_qty', 12, 3);
            $table->unsignedInteger('days_cover_target');
            $table->decimal('avg_daily_sold', 12, 4);
            $table->decimal('on_hand', 12, 3);
            $table->decimal('supplier_available', 12, 3)->nullable();
            $table->decimal('negative_exposure', 12, 3)->nullable();
            $table->decimal('last_supplier_unit_cost', 12, 4)->nullable();
            $table->decimal('estimated_spend', 12, 2)->nullable();
            $table->string('reason', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->index('reorder_suggestion_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reorder_suggestion_items');
        Schema::dropIfExists('reorder_suggestions');
        Schema::dropIfExists('supplier_stock_snapshot_items');
        Schema::dropIfExists('supplier_stock_snapshots');
        Schema::dropIfExists('product_reorder_settings');
    }
};
