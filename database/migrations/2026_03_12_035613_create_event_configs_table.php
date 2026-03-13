<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_configs', function (Blueprint $table) {
            $table->id();
            $table->date('wedding_date')->nullable();
            $table->time('wedding_time')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('venue_map_link')->nullable();
            $table->text('reception_details')->nullable();
            $table->string('dress_code')->nullable();
            $table->text('registry_info')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_configs');
    }
};
