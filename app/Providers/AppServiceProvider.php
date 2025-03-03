<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Spatie\Valuestore\Valuestore;
use function Termwind\render;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(Valuestore $config): void
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

        DB::purge('default');
        Config::set('database.connections.default.driver', $config->get('db'));
        if (File::exists(getcwd() . '/wp-config.php')) {
            include getcwd() . '/wp-config.php';
            Config::set('database.connections.default.username', DB_USER);
            Config::set('database.connections.default.password', DB_PASSWORD);
            Config::set('database.connections.default.database', DB_NAME);
            Config::set('database.connections.default.host', DB_HOST);
            Config::set('database.connections.default.charset', DB_CHARSET);
            Config::set('database.connections.default.collation', DB_COLLATE);
            Config::set('database.connections.default.prefix', $table_prefix);
        } else {
            Config::set('database.connections.default.username', $config->get('dbuser'));
            Config::set('database.connections.default.password', $config->get('dbpass'));
        }
        DB::reconnect('default');
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
