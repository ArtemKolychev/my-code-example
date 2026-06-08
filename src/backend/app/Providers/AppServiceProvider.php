<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Providers\AuthServiceProvider;
use App\Tenants\Providers\TenantsServiceProvider;
use App\Users\Providers\UsersServiceProvider;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(TenantsServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations/auth'),
            database_path('migrations/users'),
            database_path('migrations/tenants'),
        ]);

        Scramble::configure()->routes(fn (Route $r): bool => str_starts_with($r->uri, 'api/'));
    }
}
