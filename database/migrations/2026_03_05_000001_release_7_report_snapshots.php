<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->enum('snapshot_type', ['daily', 'weekly']);
            $table->date('snapshot_date')->nullable();
            $table->date('week_start_date')->nullable();
            $table->json('payload');
            $table->dateTime('generated_at');
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['company_id', 'snapshot_type', 'snapshot_date', 'week_start_date'], 'report_snapshot_unique_scope');
            $table->index(['company_id', 'snapshot_type']);
        });

        Schema::create('report_snapshot_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 50);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_snapshot_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshot_items');
        Schema::dropIfExists('report_snapshots');
    }
};
