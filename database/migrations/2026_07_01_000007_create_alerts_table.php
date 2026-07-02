<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->string('source', 100);
            $table->string('fingerprint', 64);
            $table->string('title');
            $table->json('body')->nullable();
            $table->enum('severity', ['P1', 'P2', 'P3', 'P4']);
            $table->enum('status', ['new', 'deduplicated', 'linked', 'suppressed'])->default('new');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['tenant_id', 'fingerprint']);
            $table->index(['tenant_id', 'status', 'received_at']);
            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
