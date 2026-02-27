<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('product_type', ['stock', 'service'])->default('stock');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('default_tax_profile_id')->nullable();
            $table->decimal('default_igic_rate', 6, 3)->nullable();
            $table->decimal('sale_price', 12, 4)->nullable();
            $table->decimal('reorder_min_qty', 12, 3)->nullable();
            $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('stock_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('move_type', ['receipt', 'sale', 'adjustment_in', 'adjustment_out', 'transfer_in', 'transfer_out', 'return_in', 'return_out']);
            $table->decimal('qty', 12, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('total_cost', 12, 4)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->dateTime('occurred_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'product_id']);
            $table->index(['company_id', 'warehouse_id']);
            $table->index('occurred_at');
        });

        Schema::create('product_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('avg_cost', 12, 4)->default(0);
            $table->dateTime('last_calculated_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_costs');
        Schema::dropIfExists('stock_moves');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('product_company');
        Schema::dropIfExists('products');
    }
};
