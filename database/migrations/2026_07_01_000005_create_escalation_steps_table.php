<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_steps', function (Blueprint $table) {
            $table->id();
            // Denormalized from escalation_policies.tenant_id so this model can
            // use BelongsToTenant like every other model (CLAUDE.md: no exceptions).
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('escalation_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step_order');
            $table->unsignedSmallInteger('delay_minutes');
            $table->foreignId('notify_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('notify_channel', ['email', 'slack', 'webhook']);
            $table->timestamps();

            $table->index(['escalation_policy_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_steps');
    }
};
