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
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->index('user_id');
        });

        DB::statement("
                ALTER TABLE wallets
                ADD CONSTRAINT check_wallet_balance
                CHECK (balance >= 0)   
            "); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
