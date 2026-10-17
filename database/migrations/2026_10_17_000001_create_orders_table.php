<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('description');
            // Minor units (cents). Never a float: 0.1 + 0.2 !== 0.3 in binary
            // floating point, and money must be exact.
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status')->default('pending')->index();
            $table->string('gateway_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
