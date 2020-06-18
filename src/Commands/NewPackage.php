<?php

namespace JeroenG\Packager\Commands;

use Illuminate\Support\Str;
use JeroenG\Packager\Conveyor;
use JeroenG\Packager\Wrapping;
use Illuminate\Console\Command;
use JeroenG\Packager\ProgressBar;

/**
 * Create a brand new package.
 *
 * @author JeroenG
 **/
class NewPackage extends Command
{
    use ProgressBar;

    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'packager:new {vendor} {name} {--i}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Create a new package.';

    /**
     * Packages roll off of the conveyor.
     * @var object \JeroenG\Packager\Conveyor
     */
    protected $conveyor;

    /**
     * Packages are packed in wrappings to personalise them.
     * @var object \JeroenG\Packager\Wrapping
     */
    protected $wrapping;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Conveyor $conveyor, Wrapping $wrapping)
    {
        parent::__construct();
        $this->conveyor = $conveyor;
        $this->wrapping = $wrapping;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Start the progress bar
        $this->startProgressBar(6);

        // Defining vendor/package, optionally defined interactively
        if ($this->option('i')) {
            $this->conveyor->vendor($this->ask('What will be the vendor name?', $this->argument('vendor')));
            $this->conveyor->package($this->ask('What will be the package name?', $this->argument('name')));
        } else {
            $this->conveyor->vendor($this->argument('vendor'));
            $this->conveyor->package($this->argument('name'));
        }

        // Start creating the package
        $this->info('Creating package '.$this->conveyor->vendor().'\\'.$this->conveyor->package().'...');
        $this->conveyor->checkIfPackageExists();
        $this->makeProgress();

        // Create the package directory
        $this->info('Creating packages directory...');
        $this->conveyor->makeDir($this->conveyor->packagesPath());
        $this->makeProgress();

        // Create the vendor directory
        $this->info('Creating vendor...');
        $this->conveyor->makeDir($this->conveyor->vendorPath());
        $this->makeProgress();

        // Get the packager package skeleton
        $this->info('Downloading skeleton...');
        $this->conveyor->downloadSkeleton();
        $manifest = (file_exists($this->conveyor->packagePath().'/rewriteRules.php')) ? $this->conveyor->packagePath().'/rewriteRules.php' : null;

        $this->conveyor->renameFiles($manifest);
        $this->makeProgress();

        // Replacing skeleton placeholders
        $this->info('Replacing skeleton placeholders...');
        $this->wrapping->replace([
            ':uc:vendor',
            ':uc:package',
            ':lc:vendor',
            ':lc:package',
        ], [
            Str::studly($this->conveyor->vendor()),
            Str::studly($this->conveyor->package()),
            strtolower($this->conveyor->vendor()),
            strtolower($this->conveyor->package()),
        ]);

        if ($this->option('i')) {
            $this->interactiveReplace();
        } else {
            $this->wrapping->replace([
                ':author_name',
                ':author_email',
                ':author_homepage',
                ':license',
            ], [
                config('packager.author_name'),
                config('packager.author_email'),
                config('packager.author_homepage'),
                config('packager.license'),
            ]);
        }

        // Fill all placeholders in all files with the replacements.
        $this->wrapping->fill($this->conveyor->packagePath());
        $this->makeProgress();

        // Composer dump-autoload to identify new service provider
        $this->info('Dumping autoloads and discovering package...');
        $this->wrapping->addToComposer($this->conveyor->vendor(), $this->conveyor->package());
        $this->wrapping->addToProviders($this->conveyor->vendor(), $this->conveyor->package());
        $this->conveyor->dumpAutoloads();
        $this->makeProgress();

        // Finished creating the package, end of the progress bar
        $this->finishProgress('Package created successfully!');
    }

    /**
     * Use the interactive CLI to replace certain placeholders.
     *
     * @return void
     */
    protected function interactiveReplace()
    {
        $author = $this->ask('Who is the author?', config('packager.author_name'));
        $authorEmail = $this->ask('What is the author\'s e-mail?', config('packager.author_email'));
        $authorHomepage = $this->ask('What is the author\'s website?', config('packager.author_homepage'));
        $description = $this->ask('How would you describe the package?');
        $license = $this->ask('Under which license will it be released?', config('packager.license'));

        $this->wrapping->replace([
            ':author_name',
            ':author_email',
            ':author_homepage',
            ':package_description',
            ':license',
        ], [
            $author,
            $authorEmail,
            $authorHomepage,
            $description,
            $license,
        ]);
    }
}
