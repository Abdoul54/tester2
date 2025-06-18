<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeRepositoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name : The name of the repository}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a repository class and its interface';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fs   = new Filesystem;
        $name = Str::studly($this->argument('name'));

        // Paths
        $repoDir      = app_path('Repositories');
        $contractDir  = $repoDir . '/Contracts';
        $repoFile     = "$repoDir/{$name}Repository.php";
        $contractFile = "$contractDir/{$name}RepositoryInterface.php";

        // Ensure directories exist
        foreach ([$repoDir, $contractDir] as $dir) {
            if (! $fs->isDirectory($dir)) {
                $fs->makeDirectory($dir, 0755, true);
            }
        }

        // Load stubs and replace placeholder
        $replacements = ['{{Name}}' => $name];
        $stubRepo      = str_replace(array_keys($replacements), array_values($replacements), file_get_contents(base_path('stubs/repository.stub')));
        $stubContract  = str_replace(array_keys($replacements), array_values($replacements), file_get_contents(base_path('stubs/repository.interface.stub')));

        // Write files
        $fs->put($repoFile, $stubRepo);
        $fs->put($contractFile, $stubContract);

        $this->info("Created Repository: {$repoFile}");
        $this->info("Created Interface:  {$contractFile}");
    }
}
