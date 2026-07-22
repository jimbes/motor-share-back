<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        JsonResource::withoutWrapping();

        // Some MySQL/MariaDB hosts (older InnoDB row format / no large-prefix
        // support) can't index a utf8mb4 varchar(255) column - it exceeds
        // the ~767-1000 byte key length limit. Capping the default string
        // length keeps auto-generated indexes (e.g. password_reset_tokens'
        // email primary key) under that limit.
        Schema::defaultStringLength(191);
    }
}
