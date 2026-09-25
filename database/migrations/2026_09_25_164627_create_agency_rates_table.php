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
        Schema::create('agency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('rate_unit');
            $table->string('arrangement');
            $table->unsignedInteger('rate');
            $table->unsignedInteger('agency_fee')->default(0);
            $table->unique(['agency_id', 'category', 'rate_unit', 'arrangement']);
            $table->timestamps();
        });
        Schema::table('workers', function (Blueprint $table) {
            $table->string('video_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agency_rates');
        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn('video_path');
        });
    }
};
