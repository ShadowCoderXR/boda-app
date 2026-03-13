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
        Schema::table('guests', function (Blueprint $table) {
            $table->boolean('is_representative')->default(false);
            $table->uuid('representative_id')->nullable();
            $table->integer('extra_spots')->default(0);

            $table->foreign('representative_id')->references('id')->on('guests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['representative_id']);
            $table->dropColumn(['is_representative', 'representative_id', 'extra_spots']);
        });
    }
};
