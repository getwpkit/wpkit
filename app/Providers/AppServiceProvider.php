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
        global $argv, $argc;
        $subCommand = $argc > 1 ? $argv[1] : '';
        if ($subCommand !== 'setup' && !Storage::exists('config.json')) {
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
            $file = getcwd() . '/wp-config.php';
            $file_contents = file_get_contents($file);
            // Regex to find constant definitions
            preg_match_all('/define\s*\(\s*["\']([A-Za-z0-9_]+)["\']\s*,\s*["\']([^"\']+)["\']\s*\)/', $file_contents, $matches);
            // Define the constants dynamically
            foreach ($matches[1] as $index => $constantName) {
                $constantValue = $matches[2][$index];
                define($constantName, $constantValue);
            }
            // Regex pattern to match $table_prefix definition
            $pattern = "/table_prefix\s*=\s*'(.*?)';/";
            preg_match($pattern, $file_contents, $matches);
            $table_prefix = $matches[1];

            Config::set('database.connections.default.username', DB_USER);
            Config::set('database.connections.default.password', DB_PASSWORD);
            Config::set('database.connections.default.database', DB_NAME);
            Config::set('database.connections.default.host', DB_HOST);
            Config::set('database.connections.default.charset', DB_CHARSET);
            Config::set('database.connections.default.collation', defined('DB_COLLATE') ? DB_COLLATE : null);
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
