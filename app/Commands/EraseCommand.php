<?php

namespace App\Commands;

use Config;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;

class EraseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erase {path=.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Erase wordpress site files & database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = realpath($this->argument('path'));

        if (!File::exists($path . '/wp-config.php')) {
            $this->fail('No wordpress files detected');
            exit(1);
        }


        $this->task('Database Drop', function () use ($path) {
            $this->restartDatabase($path);
            $dbname = config('database.connections.default.database');
            try {
                return DB::statement("DROP DATABASE IF EXISTS `$dbname`");
            } catch (\Exception $e) {
                $this->fail('Error dropping database: ' . $e->getMessage());
            }
        });
        $this->task('Removing WP files', function () use ($path) {
            $this->deleteAllInDirectory($path);
            rmdir($path);
        });
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }

    protected function deleteAllInDirectory($dir)
    {
        $success = true;
        $files = array_diff(scandir($dir), array('.', '..')); // Get all files and directories except '.' and '..'
        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                // If it's a directory, recursively delete its contents
                $this->deleteAllInDirectory($filePath);
                $result = rmdir($filePath); // After cleaning up the directory, remove the directory itself
                if (!$result)
                    $success = $result;
            } else {
                // If it's a file, delete it
                $result = unlink($filePath);
                if (!$result)
                    $success = $result;
            }
        }
    }

    private function restartDatabase($path)
    {
        if (File::exists($path . '/wp-config.php')) {
            DB::purge('default');
            $file = $path . '/wp-config.php';
            $file_contents = file_get_contents($file);
            // Regex to find constant definitions
            preg_match_all('/define\s*\(\s*["\']([A-Za-z0-9_]+)["\']\s*,\s*["\']([^"\']+)["\']\s*\)/', $file_contents, $matches);
            // Define the constants dynamically
            foreach ($matches[1] as $index => $constantName) {
                $constantValue = $matches[2][$index];
                if (!defined($constantName))
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
            DB::reconnect('default');
        }
    }
}
