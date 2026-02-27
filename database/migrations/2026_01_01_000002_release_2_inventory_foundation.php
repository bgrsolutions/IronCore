<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('barcode')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_company', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('average_cost', 14, 4)->default(0);
            $table->decimal('reorder_level', 14, 4)->default(0);
            $table->json('overrides')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
            $table->unique(['warehouse_id', 'code']);
        });

        Schema::create('stock_moves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16);
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->string('reason', 100);
            $table->morphs('source');
            $table->timestamp('moved_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'product_id', 'moved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_moves');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('product_company');
        Schema::dropIfExists('products');
    }
};
