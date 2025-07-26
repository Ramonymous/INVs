<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inv_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('inv_receipts')->cascadeOnDelete();
            $table->foreignId('child_part_id')->constrained('master_childparts')->cascadeOnDelete();
            $table->integer('quantity');
            $table->integer('available');
            $table->string('code')->unique(); // <- QR code
            $table->timestamps();
            $table->softDeletes(); // ← tambah juga
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_receipt_items');
    }
};
