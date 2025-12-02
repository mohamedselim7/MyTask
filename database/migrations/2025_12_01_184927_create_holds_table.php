<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('holds', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();
        $table->integer('qty');
        $table->enum('status', ['reserved', 'expired', 'used'])->default('reserved'); 
        $table->timestamp('expires_at');
        $table->foreignId('order_id')->nullable();
        $table->timestamps();
        $table->index(['product_id', 'status']);
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
