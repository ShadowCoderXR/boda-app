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
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
            $table->foreignId('subgroup_id')->nullable()->after('name')->constrained('subgroups')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['subgroup_id']);
            $table->dropColumn('subgroup_id');
            $table->foreignId('parent_id')->nullable()->after('name')->constrained('groups')->cascadeOnDelete();
        });
    }
};
