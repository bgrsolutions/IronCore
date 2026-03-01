<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('pdf_path')->nullable()->after('currency');
        });

        Schema::table('expense_lines', function (Blueprint $table): void {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('net_amount');
        });
    }

    public function down(): void
    {
        Schema::table('expense_lines', function (Blueprint $table): void {
            $table->dropColumn('tax_rate');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn('pdf_path');
        });
    }
};
