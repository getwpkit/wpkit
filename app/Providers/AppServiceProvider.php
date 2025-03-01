<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Valuestore\Valuestore;
use function Termwind\render;
use Illuminate\Support\Facades\Storage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($_SERVER['argv'][1] !== 'setup' && !Storage::exists('config.json')) {
            render(<<<'HTML'
                <div class="py-1 ml-2">
                    <div class="px-1 bg-red-300 text-black">WPkit has not been initialized yet.</div>
                    <em class="ml-1">
                        Please run `wpkit setup`.
                    </em>
                </div>
            HTML);
            exit();
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            Valuestore::class,
            fn() => Valuestore::make(Storage::path('config.json'))
        );
    }
}
