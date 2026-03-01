<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('store_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('user_store_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_location_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'store_location_id']);
        });

        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->foreignId('store_location_id')->nullable()->after('company_id')->constrained('store_locations')->nullOnDelete();
            $table->index(['company_id', 'store_location_id', 'posted_at'], 'sd_company_store_posted_idx');
        });

        Schema::table('repairs', function (Blueprint $table): void {
            $table->foreignId('store_location_id')->nullable()->after('company_id')->constrained('store_locations')->nullOnDelete();
            $table->foreignId('technician_user_id')->nullable()->after('linked_sales_document_id')->constrained('users')->nullOnDelete();
            $table->index(['company_id', 'store_location_id', 'status']);
            $table->index(['company_id', 'technician_user_id', 'status']);
        });

        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->foreignId('store_location_id')->nullable()->after('company_id')->constrained('store_locations')->nullOnDelete();
            $table->index(['company_id', 'store_location_id', 'status']);
        });

        Schema::create('purchase_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_location_id')->nullable()->constrained('store_locations')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'ordered', 'partially_received', 'received', 'cancelled'])->default('draft');
            $table->dateTime('planned_at');
            $table->dateTime('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['expected_at', 'status']);
        });

        Schema::create('purchase_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('suggested_qty', 12, 3);
            $table->decimal('ordered_qty', 12, 3)->nullable();
            $table->decimal('received_qty', 12, 3)->default(0);
            $table->decimal('unit_cost_estimate', 12, 4)->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->foreignId('source_reorder_suggestion_item_id')->nullable()->constrained('reorder_suggestion_items')->nullOnDelete();
            $table->enum('status', ['planned', 'ordered', 'received', 'cancelled'])->default('planned');
            $table->timestamps();
            $table->index(['purchase_plan_id', 'product_id']);
        });

        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->foreignId('purchase_plan_id')->nullable()->after('supplier_id')->constrained('purchase_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_plan_id');
            $table->dropIndex(['company_id', 'store_location_id', 'status']);
            $table->dropConstrainedForeignId('store_location_id');
        });

        Schema::table('repairs', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'store_location_id', 'status']);
            $table->dropIndex(['company_id', 'technician_user_id', 'status']);
            $table->dropConstrainedForeignId('technician_user_id');
            $table->dropConstrainedForeignId('store_location_id');
        });

        Schema::table('sales_documents', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'store_location_id', 'posted_at']);
            $table->dropConstrainedForeignId('store_location_id');
        });

        Schema::dropIfExists('purchase_plan_items');
        Schema::dropIfExists('purchase_plans');
        Schema::dropIfExists('user_store_locations');
        Schema::dropIfExists('store_locations');
    }
};
