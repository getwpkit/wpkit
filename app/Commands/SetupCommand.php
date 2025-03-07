<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\text;
use function Termwind\render;
use function Laravel\Prompts\form;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initial setup for wpkit';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        render(<<<'HTML'
            <div class="py-1 ml-2">
                <div class="px-1 bg-blue-300 text-black">WPkit setup</div>
            </div>
        HTML);
        $config = form()
            ->select(
                label: 'Choose DB server',
                options: [
                    'mariadb' => 'Mariadb',
                    'mysql' => 'MySQL'
                ],
                default: 'mysql',
                required: true,
                name: 'db',
            )
            ->text('Enter DB server username', required: true, name: 'dbuser')
            ->password('Enter DB server password', required: true, name: 'dbpass')
            ->text('Enter DB prefix', default: 'wp_', name: 'prefix')
            ->select(
                label: 'Choose site url generator',
                options: [
                    'static' => 'Static',
                    'dynamic' => 'Dynamic',
                ],
                default: 'localhost',
                required: true,
                name: 'siteurl-type'
            )
            ->add(function ($responses) {
                $siteUrl = $responses['siteurl-type'] === 'static' ? 'http://localhost:8080' : 'http://{siteurl}.local';
                return text(
                    'Enter Site URL template',
                    default: $siteUrl,
                    required: true,
                    hint: '{siteurl} replaced with auto generated name'
                );

            }, name: 'siteurl')
            ->text('Enter default Admin username', default: 'admin', name: 'username', required: true)
            ->password('Enter default Admin password', name: 'password', required: true)
            ->submit();

        if (!Storage::exists('presets')) {
            Storage::makeDirectory('presets');
        }
        $this->task(
            'saving configurations ',
            fn() => Storage::put('config.json', json_encode($config, JSON_PRETTY_PRINT))
        );
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
