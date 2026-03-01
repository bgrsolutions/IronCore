<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->string('type', 20)->nullable()->after('code');
            $table->string('address_street')->nullable()->after('type');
            $table->string('address_city')->nullable()->after('address_street');
            $table->string('address_region')->nullable()->after('address_city');
            $table->string('address_postcode', 32)->nullable()->after('address_region');
            $table->string('address_country', 2)->nullable()->after('address_postcode');
            $table->string('contact_name')->nullable()->after('address_country');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->boolean('counts_for_stock')->default(true)->after('is_default');
            $table->boolean('is_external_supplier_stock')->default(false)->after('counts_for_stock');
            $table->index('type');
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('ean', 64)->nullable()->after('barcode');
            $table->decimal('cost', 12, 4)->nullable()->after('product_type');
            $table->decimal('default_margin_percent', 8, 3)->nullable()->after('cost');
            $table->unsignedInteger('lead_time_days')->nullable()->after('default_margin_percent');
            $table->foreignId('default_warehouse_id')->nullable()->after('lead_time_days')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('default_warehouse_id')->constrained('suppliers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->after('supplier_id')->constrained('product_categories')->nullOnDelete();
            $table->string('image_url')->nullable()->after('description');
            $table->string('image_path')->nullable()->after('image_url');
            $table->unique('ean');
            $table->index('default_warehouse_id');
            $table->index('supplier_id');
            $table->index('category_id');
        });

        Schema::create('product_company_pricing', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('margin_percent', 8, 3)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'company_id']);
        });

        Schema::create('product_warehouse_stock', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('external_quantity', 14, 3)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_warehouse_stock');
        Schema::dropIfExists('product_company_pricing');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['ean']);
            $table->dropIndex(['default_warehouse_id']);
            $table->dropIndex(['supplier_id']);
            $table->dropIndex(['category_id']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('default_warehouse_id');
            $table->dropColumn(['ean', 'cost', 'default_margin_percent', 'lead_time_days', 'image_url', 'image_path']);
        });

        Schema::dropIfExists('product_categories');

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropColumn([
                'type',
                'address_street',
                'address_city',
                'address_region',
                'address_postcode',
                'address_country',
                'contact_name',
                'contact_email',
                'contact_phone',
                'counts_for_stock',
                'is_external_supplier_stock',
            ]);
        });
    }
};
