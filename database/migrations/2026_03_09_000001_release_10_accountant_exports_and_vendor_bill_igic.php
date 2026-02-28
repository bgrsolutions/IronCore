<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('net_amount');
        });

        DB::table('vendor_bill_lines')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $net = (float) ($row->net_amount ?? 0);
                $tax = (float) ($row->tax_amount ?? 0);
                $taxRate = $net > 0 ? round(($tax / $net) * 100, 2) : 0.0;

                DB::table('vendor_bill_lines')->where('id', $row->id)->update(['tax_rate' => $taxRate]);
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE vendor_bill_lines MODIFY tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE vendor_bill_lines ALTER COLUMN tax_rate SET DEFAULT 0.00');
            DB::statement('UPDATE vendor_bill_lines SET tax_rate = 0.00 WHERE tax_rate IS NULL');
            DB::statement('ALTER TABLE vendor_bill_lines ALTER COLUMN tax_rate SET NOT NULL');
        }

        Schema::create('accountant_export_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('breakdown_by_store')->default(false);
            $table->json('summary_payload')->nullable();
            $table->string('zip_path');
            $table->string('zip_hash', 64);
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
            $table->index(['company_id', 'from_date', 'to_date']);
        });

        Schema::create('accountant_export_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('accountant_export_batch_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('rows_count')->default(0);
            $table->string('sha256', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->index('accountant_export_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accountant_export_files');
        Schema::dropIfExists('accountant_export_batches');

        Schema::table('vendor_bill_lines', function (Blueprint $table): void {
            $table->dropColumn('tax_rate');
        });
    }
};
