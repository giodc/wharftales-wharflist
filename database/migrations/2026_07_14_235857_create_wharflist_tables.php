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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain');
            $table->foreignId('list_id')->constrained('lists')->cascadeOnDelete();
            $table->string('api_key', 64)->unique();
            $table->text('custom_fields')->nullable();
            $table->timestamps();
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->string('verification_token')->nullable()->index();
            $table->boolean('verified')->default(false);
            $table->boolean('unsubscribed')->default(false);
            $table->json('custom_data')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriber_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('subscribers')->cascadeOnDelete();
            $table->foreignId('list_id')->constrained('lists')->cascadeOnDelete();
            $table->boolean('unsubscribed')->default(false);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['subscriber_id', 'list_id']);
        });

        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->nullable()->constrained('lists')->nullOnDelete();
            $table->string('subject');
            $table->longText('body');
            $table->string('status')->default('draft'); // draft|sent|scheduled
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->foreignId('list_id')->constrained('lists')->cascadeOnDelete();
            $table->unique(['email_campaign_id', 'list_id']);
        });

        Schema::create('campaign_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->json('list_ids')->nullable();
            $table->string('status')->default('pending'); // pending|scheduled|processing|completed|failed|cancelled
            $table->unsignedInteger('progress')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt')->nullable();
            $table->timestamps();

            $table->unique(['ip_address', 'site_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_limits');
        Schema::dropIfExists('campaign_sends');
        Schema::dropIfExists('campaign_list');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('subscriber_lists');
        Schema::dropIfExists('subscribers');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('lists');
        Schema::dropIfExists('settings');
    }
};
