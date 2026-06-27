<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['deposit', 'withdrawal']);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->foreignUuid('wallet_id')->constrained('wallets')->onDelete('restrict');
            $table->dateTime('created_at')->useCurrent();
            $table->index('wallet_id');
        });

        DB::statement("
                ALTER TABLE transactions
                ADD CONSTRAINT check_transaction_amount
                CHECK (amount > 0)   
            ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
