<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeTraitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example: php artisan make:trait TraitName
     */
    protected $signature = 'make:trait {name : The name of the trait}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Trait class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $filesystem = new Filesystem();

        // Set path for trait
        $path = app_path('Traits/' . $name . '.php');

        // Create directory if not exists
        if (! $filesystem->isDirectory(app_path('Traits'))) {
            $filesystem->makeDirectory(app_path('Traits'), 0755, true);
        }

        // Prevent overwriting
        if ($filesystem->exists($path)) {
            $this->error('Trait already exists!');
            return;
        }

        // Create content
        $content = <<<PHP
<?php

namespace App\Traits;

trait {$name}
{
    //
}
PHP;

        // Write file
        $filesystem->put($path, $content);

        $this->info("Trait created successfully: {$path}");
    }
}
