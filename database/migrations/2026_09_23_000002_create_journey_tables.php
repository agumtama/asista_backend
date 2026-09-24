<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('worker_id')->constrained();
            $t->unique(['user_id', 'worker_id']);
            $t->timestamps();
        });
        Schema::create('job_needs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('title');
            $t->string('category');
            $t->string('city');
            $t->dateTime('starts_at');
            $t->unsignedInteger('budget');
            $t->text('scope');
            $t->string('status')->default('open');
            $t->timestamps();
        });
        Schema::create('interviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained();
            $t->foreignId('proposed_by')->constrained('users');
            $t->dateTime('scheduled_at');
            $t->string('method');
            $t->string('status')->default('proposed');
            $t->timestamps();
        });
        Schema::create('agency_relationships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('worker_id')->constrained();
            $t->foreignId('agency_id')->constrained();
            $t->foreignId('requested_by')->constrained('users');
            $t->string('type');
            $t->string('status')->default('pending');
            $t->text('note');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['agency_relationships', 'interviews', 'job_needs', 'shortlists'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
