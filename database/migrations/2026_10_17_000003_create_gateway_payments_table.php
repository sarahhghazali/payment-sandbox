<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gateway side of the sandbox. In production this table lives inside
     * the payment provider, not the merchant application.
     */
    public function up(): void
    {
        Schema::create('gateway_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('order_reference')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('callback_url');
            $table->string('return_url');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('callback_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_payment_id')->constrained()->cascadeOnDelete();
            $table->string('event_id')->index();
            $table->unsignedInteger('attempt');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error')->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_deliveries');
        Schema::dropIfExists('gateway_payments');
    }
};
