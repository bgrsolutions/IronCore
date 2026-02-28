<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repair_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->string('line_type', 30)->default('labour');
            $table->string('description');
            $table->decimal('qty', 12, 4)->default(1);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(7.0);
            $table->decimal('line_net', 14, 2)->default(0);
            $table->decimal('cost_total', 14, 2)->default(0);
            $table->timestamps();
            $table->index(['repair_id', 'line_type']);
        });

        Schema::create('supplier_product_costs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('last_unit_cost', 12, 4);
            $table->char('currency', 3)->default('EUR');
            $table->dateTime('last_seen_at');
            $table->timestamps();
            $table->unique(['company_id', 'supplier_id', 'product_id'], 'supplier_product_costs_company_supplier_product_unique');
        });

        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->boolean('cost_increase_flag')->default(false)->after('gross_amount');
            $table->decimal('cost_increase_percent', 6, 2)->nullable()->after('cost_increase_flag');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->dropColumn(['cost_increase_flag', 'cost_increase_percent']);
        });

        Schema::dropIfExists('supplier_product_costs');
        Schema::dropIfExists('repair_line_items');
    }
};
