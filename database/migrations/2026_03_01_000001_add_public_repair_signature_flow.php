<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('repair_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->enum('signature_type', ['intake', 'pickup']);
            $table->string('signer_name', 120)->nullable();
            $table->dateTime('signed_at');
            $table->string('signature_image_path', 255);
            $table->string('signature_hash', 128);
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['repair_id', 'signature_type']);
        });

        Schema::create('repair_pickups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->dateTime('picked_up_at');
            $table->enum('pickup_method', ['customer', 'courier', 'other'])->default('customer');
            $table->boolean('pickup_confirmed')->default(false);
            $table->text('pickup_note')->nullable();
            $table->foreignId('pickup_signature_id')->nullable()->constrained('repair_signatures')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['repair_id', 'picked_up_at']);
        });

        Schema::create('repair_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['repair_id', 'submitted_at']);
        });

        Schema::create('public_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repair_id')->constrained()->cascadeOnDelete();
            $table->enum('purpose', ['repair_intake_signature', 'repair_pickup_signature', 'repair_feedback']);
            $table->string('token', 80)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['repair_id', 'purpose']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_tokens');
        Schema::dropIfExists('repair_feedback');
        Schema::dropIfExists('repair_pickups');
        Schema::dropIfExists('repair_signatures');
    }
};
