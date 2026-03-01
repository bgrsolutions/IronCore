<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->decimal('purchase_tax_rate', 5, 2)->default(0)->after('tax_id');
            $table->boolean('sales_tax_enabled')->default(true)->after('purchase_tax_rate');
            $table->decimal('sales_tax_rate', 5, 2)->default(0)->after('sales_tax_enabled');
        });

        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->string('tax_mode', 20)->default('inherit_company')->after('currency');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_mode');
            $table->index('tax_mode');
        });

        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->foreignId('receiving_warehouse_id')->nullable()->after('store_location_id')->constrained('warehouses')->nullOnDelete();
            $table->string('pdf_path')->nullable()->after('currency');
        });

        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->string('ean', 64)->nullable()->after('product_id');
            $table->decimal('margin_percent', 8, 3)->nullable()->after('tax_rate');
            $table->decimal('suggested_net_sale_price', 12, 2)->nullable()->after('margin_percent');
            $table->index('ean');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->dropIndex(['ean']);
            $table->dropColumn(['ean', 'margin_percent', 'suggested_net_sale_price']);
        });

        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('receiving_warehouse_id');
            $table->dropColumn('pdf_path');
        });

        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->dropIndex(['tax_mode']);
            $table->dropColumn(['tax_mode', 'tax_rate']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['purchase_tax_rate', 'sales_tax_enabled', 'sales_tax_rate']);
        });
    }
};
