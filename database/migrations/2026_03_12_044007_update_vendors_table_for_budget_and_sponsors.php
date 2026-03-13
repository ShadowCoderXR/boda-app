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
        Schema::table('vendors', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('website');
            $table->unsignedBigInteger('sponsor_id')->nullable()->after('user_id');
            $table->foreign('sponsor_id')->references('id')->on('sponsors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['sponsor_id']);
            $table->dropColumn(['price', 'sponsor_id']);
        });
    }
};
