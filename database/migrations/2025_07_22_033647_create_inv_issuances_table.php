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
        Schema::create('inv_issuances', function (Blueprint $table) {
            $table->id();

            // Referensi ke request
            $table->foreignId('request_id')->constrained('inv_requests')->cascadeOnDelete();

            // Referensi ke item penerimaan (kode QR)
            $table->foreignId('receipt_item_id')->constrained('inv_receipt_items')->cascadeOnDelete();

            // Jumlah yang dikeluarkan dari item tersebut
            $table->integer('issued_quantity');

            // Siapa yang melakukan issuance
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            // Waktu issuance
            $table->datetime('issued_at');

            // Flag force jika tanpa request
            $table->boolean('is_forced')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_issuances');
    }
};
