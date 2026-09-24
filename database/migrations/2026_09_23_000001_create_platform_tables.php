<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('family');
            $t->string('verification')->default('pending');
        });
        Schema::create('api_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('agencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->string('name');
            $t->string('city');
            $t->string('legal_number');
            $t->string('verification')->default('pending');
            $t->string('subscription')->default('inactive');
            $t->timestamps();
        });
        Schema::create('workers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('category');
            $t->string('city');
            $t->text('bio');
            $t->json('skills');
            $t->json('certifications')->nullable();
            $t->unsignedInteger('experience_years')->default(0);
            $t->string('arrangement')->default('live_out');
            $t->string('rate_unit')->default('daily');
            $t->unsignedInteger('rate');
            $t->unsignedInteger('agency_fee')->default(0);
            $t->string('verification')->default('pending');
            $t->boolean('available')->default(true);
            $t->timestamps();
        });
        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('family_id')->constrained('users');
            $t->foreignId('worker_id')->constrained();
            $t->foreignId('agency_id')->nullable()->constrained();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('address');
            $t->text('scope');
            $t->string('rate_unit');
            $t->unsignedInteger('units');
            $t->unsignedBigInteger('worker_pay');
            $t->unsignedBigInteger('agency_fee');
            $t->unsignedBigInteger('platform_fee');
            $t->unsignedBigInteger('total');
            $t->string('status')->default('requested');
            $t->string('payment_status')->default('unpaid');
            $t->text('contract');
            $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->text('body');
            $t->timestamps();
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained();
            $t->foreignId('author_id')->constrained('users');
            $t->foreignId('target_id')->constrained('users');
            $t->unsignedTinyInteger('rating');
            $t->text('comment');
            $t->unique(['booking_id', 'author_id', 'target_id']);
            $t->timestamps();
        });
        Schema::create('safety_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('booking_id')->constrained();
            $t->foreignId('reporter_id')->constrained('users');
            $t->string('category');
            $t->text('description');
            $t->text('evidence')->nullable();
            $t->string('status')->default('reported');
            $t->text('decision')->nullable();
            $t->text('appeal')->nullable();
            $t->timestamps();
        });
        Schema::create('verification_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('document_path');
            $t->string('status')->default('pending');
            $t->text('note')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('action');
            $t->string('subject');
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'verification_requests', 'safety_reports', 'reviews', 'messages', 'bookings', 'workers', 'agencies', 'api_tokens'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'verification']));
    }
};
