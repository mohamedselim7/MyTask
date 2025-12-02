<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
             $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('qty');
            $table->enum('status', ['active', 'expired', 'used', 'cancelled'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
