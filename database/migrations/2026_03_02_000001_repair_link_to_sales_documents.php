<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('repairs', function (Blueprint $table): void {
            if (Schema::hasColumn('repairs', 'invoice_id')) {
                $table->dropConstrainedForeignId('invoice_id');
            }
        });

        Schema::table('repairs', function (Blueprint $table): void {
            if (! Schema::hasColumn('repairs', 'linked_sales_document_id')) {
                $table->foreignId('linked_sales_document_id')->nullable()->after('customer_id')->constrained('sales_documents')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('repairs', function (Blueprint $table): void {
            if (Schema::hasColumn('repairs', 'linked_sales_document_id')) {
                $table->dropConstrainedForeignId('linked_sales_document_id');
            }
        });
    }
};
