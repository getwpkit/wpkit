<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Spatie\Valuestore\Valuestore;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Termwind\render;
use PDO;
use PDOException;
class NewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'new {name}
                                {--locale=en_US : Select which language you want to download.}
                                {--skip-content : Download WP without the default themes and plugins.}
                                {--force : Overwrites existing files, if present.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new wordpress application';

    /**
     * Execute the console command.
     */
    public function handle(Valuestore $config)
    {
        $name = $this->argument('name');
        $slug = Str::slug($name);
        $locale = $this->option('locale', 'en_US');
        $skipContent = $this->option('skip-content') ? '--skip-content' : '';
        $path = getcwd() . DIRECTORY_SEPARATOR . $slug;
        $force = $this->option('force') ? '--force' : '';
        $siteUrl = Str::replace('{siteurl}', $slug, $config->get('siteurl', 'http://localhost'));
        $dbname = $config->get('prefix', 'wp_') . $slug;
        $dbuser = $config->get('dbuser', 'root');
        $dbpass = $config->get('dbpass', '');
        $dbDriver = $config->get('db', 'mysql');


        $username = $config->get('username', 'admin');
        $password = $config->get('password', 'admin');


        $siteUrl = text('Enter site URL', default: $siteUrl, required: true);
        $dbname = text('Enter database name', default: $dbname, required: true);

        $username = text('Enter Admin username', default: $username, required: true);
        $password = text('Enter Admin password', default: $password, required: true);
        $presets = array_merge(['None'], Storage::files('presets'));

        $preset = select('Choose preset', $presets);

        if ($preset !== 'None') {
            require_once Storage::path($preset);
        }

        $cmd = "wp core download --path=\"{$path}\" --locale={$locale} {$skipContent} {$force}";
        $cmdOut = '';
        $task = $this->task('wp core download', function () use (&$cmd, &$cmdOut) {
            $cmdOut = shell_exec($cmd . ' 2>&1');
            return !Str::contains($cmdOut, 'error', true);
        });
        $this->checkIfErrorOccurs($task, $cmdOut);

        $task = $this->task("Create database with name {$dbname}", function () use (&$cmdOut, $dbname, $dbuser, $dbpass, $dbDriver) {
            try {
                $pdo = new PDO("mysql:host=127.0.0.1", $dbuser, $dbpass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->prepare("SHOW DATABASES LIKE :dbname");
                $stmt->bindParam(':dbname', $dbname, PDO::PARAM_STR);
                $stmt->execute();

                if ($stmt->rowCount() == 0) {
                    $createDbStmt = $pdo->prepare("CREATE DATABASE `$dbname`");
                    $createDbStmt->execute();
                } else {
                    $this->newLine();
                    $this->info("Database already exists.");
                }
                return true;
            } catch (PDOException $e) {
                $cmdOut = "Error: " . $e->getMessage();
                return false;
            }
        });
        $this->checkIfErrorOccurs($task, $cmdOut);

        $cmd = "wp config create --dbname='$dbname' --dbuser='$dbuser' --dbpass='$dbpass' --path='$path'";
        $cmdOut = '';
        $task = $this->task("Create wordpress config file", function () use (&$cmd, &$cmdOut, $path) {
            if ($this->option('force') && File::exists($path . DIRECTORY_SEPARATOR . 'wp-config.php'))
                File::delete($path . DIRECTORY_SEPARATOR . 'wp-config.php');

            $cmdOut = shell_exec($cmd . ' 2>&1');
            return !Str::contains($cmdOut, 'error', true);
        });
        $this->checkIfErrorOccurs($task, $cmdOut);

        $cmd = "wp core install --url='$siteUrl' --title='$name' --admin_user='$username' --admin_password='$password' --admin_email='admin@example.com' --path='$path'";
        $cmdOut = '';
        $task = $this->task("Setup wordpress site", function () use (&$cmd, &$cmdOut) {
            $cmdOut = shell_exec($cmd . ' 2>&1');
            return !Str::contains($cmdOut, 'error', true);
        });
        $this->checkIfErrorOccurs($task, $cmdOut);
        $this->restartDatabase($path);

        $task = $this->task("Run wpkit_preset_do", function () use ($path) {
            if (function_exists(function: 'wpkit_preset_do')) {
                $wpkitFunction = new \ReflectionFunction('wpkit_preset_do');
                $db = DB::connection();
                $path = $path . DIRECTORY_SEPARATOR;
                $wpkitArgNames = array_column($wpkitFunction->getParameters(), 'name');
                $wpkitArgs = [];
                foreach ($wpkitArgNames as $paramName) {
                    if (in_array($paramName, ['path', 'db']))
                        $wpkitArgs[$paramName] = $$paramName;
                }
                $wpkitFunction->invokeArgs($wpkitArgs);
            }
            return true;
        });



        // Success at the end
        if ($task && Str::contains($cmdOut, 'success', true)) {
            $message = Str::substr(
                $cmdOut,
                (Str::position($cmdOut, 'Success:') + Str::length('Success:'))
            );
            render(<<<"HTML"
                <div class="py-1 ml-2">
                    <div class="px-1 bg-green-400 text-black">SUCCESS</div>
                    <span class="ml-1">
                        site created at `./{$slug}`, siteurl: {$siteUrl}
                    </span>
                </div>
            HTML);

        }
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }

    private function checkIfErrorOccurs(bool $task, string $cmdOut)
    {
        if (!$task || Str::contains($cmdOut, 'error', true)) {
            $message = Str::substr(
                $cmdOut,
                (Str::position($cmdOut, 'Error:') + Str::length('Error:'))
            );
            $message = Str::replace(['.', '\n'], '', $message);
            $this->fail($message);
            exit(1);
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
