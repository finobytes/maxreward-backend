<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example: php artisan make:service CommunityTree
     */
    protected $signature = 'make:service {name : The name of the service class}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new service class in app/Services';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $serviceName = Str::finish($name, 'Service');
        $directory = app_path('Services');
        $path = $directory . '/' . $serviceName . '.php';

        // Ensure directory exists
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Prevent overwriting
        if (File::exists($path)) {
            $this->error("Service already exists at: {$path}");
            return Command::FAILURE;
        }

        // Build class template
        $template = <<<PHP
<?php

namespace App\Services;

class {$serviceName}
{
    public function __construct()
    {
        // Initialize dependencies if needed
    }

    public function handle()
    {
        // Write your service logic here
    }
}

PHP;

        // Write to file
        File::put($path, $template);

        $this->info("✅ Service created successfully: app/Services/{$serviceName}.php");

        return Command::SUCCESS;
    }
}
