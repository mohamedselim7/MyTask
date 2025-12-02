<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::create('idempotency_keys', function (Blueprint $table) {
        $table->id();
        $table->string('key')->unique();
        $table->string('action')->nullable(); 
        $table->json('request')->nullable();
        $table->json('response')->nullable();
        $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
        $table->timestamp('processed_at')->nullable();
        $table->timestamps();
        $table->index(['key', 'status']);
        $table->index('processed_at');
        $table->index('created_at');
    });
}

    public function down()
    {
        Schema::dropIfExists('idempotency_keys');
    }
};