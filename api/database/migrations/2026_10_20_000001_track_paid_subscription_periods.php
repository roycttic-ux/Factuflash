<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('paid_until')->nullable();
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('plan', 20)->nullable();
            $table->string('external_reference', 120)->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->timestamp('last_verified_at')->nullable();
        });
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('provider_payment_id', 100)->unique();
            $table->decimal('amount', 10, 2);
            $table->timestamp('paid_at');
            $table->timestamp('period_ends_at');
            $table->boolean('revoked')->default(false);
        });

        // Preserve previously granted paid periods, without granting indefinite access.
        DB::table('users')->where('plan_status', 'active')
            ->update(['paid_until' => DB::raw('trial_expires_at')]);
        DB::table('subscriptions')->orderBy('id')->each(function ($subscription) {
            DB::table('subscriptions')->where('id', $subscription->id)->update([
                'plan' => (float) $subscription->amount > 40000 ? 'annual' : 'monthly',
            ]);
        });
        DB::table('users')->where('plan_status', 'trial')->orderBy('id')->each(function ($user) {
            if (!$user->created_at) return;
            $end = Carbon::parse($user->created_at)->addDays(7);
            if (!$user->trial_expires_at || Carbon::parse($user->trial_expires_at)->greaterThan($end)) {
                DB::table('users')->where('id', $user->id)->update(['trial_expires_at' => $end]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['plan', 'external_reference', 'checkout_url', 'last_verified_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('paid_until'));
    }
};
