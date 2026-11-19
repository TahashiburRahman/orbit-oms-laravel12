<?php

namespace App\Providers;

use App\Policies\WorkspacePolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::guessPolicyNamesUsing(fn ($model) => WorkspacePolicy::class);
        Paginator::defaultView('pagination.workspace');
    }
}
