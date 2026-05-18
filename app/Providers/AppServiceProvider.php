<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\ContractCreated;
use App\Listeners\GenerateInitialInvoice;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            ContractCreated::class,
            GenerateInitialInvoice::class
        );
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        // Self-healing: Automatically add the notes column to contracts table if missing
        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('contracts', 'notes')) {
                \Illuminate\Support\Facades\Schema::table('contracts', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->text('notes')->nullable()->after('contract_date');
                });
            }
        } catch (\Throwable $e) {
            // Ignore database connection/migration issues during seeding
        }

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user()->can() and @can()
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
