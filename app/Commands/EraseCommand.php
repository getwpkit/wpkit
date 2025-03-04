<?php

namespace App\Commands;

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
    protected $signature = 'erase';

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
        if (!File::exists(getcwd() . '/wp-config.php')) {
            $this->fail('No wordpress files detected');
            exit(1);
        }


        $this->task('Database Drop', function () {
            $dbname = config('database.connections.default.database');
            try {
                return DB::statement("DROP DATABASE IF EXISTS `$dbname`");
            } catch (\Exception $e) {
                $this->fail('Error dropping database: ' . $e->getMessage());
            }
        });
        $this->task('Removing WP files', function () {
            $this->deleteAllInDirectory(getcwd());
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
}
