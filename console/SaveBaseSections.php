<?php namespace AmoriumCms\Amorium\Console;

use Illuminate\Console\Command;
use AmoriumCms\Amorium\Classes\BaseSections;

class SaveBaseSections extends Command
{
    protected $signature = 'amorium:sections-save-base';

    protected $description = 'Write the current sections to the base sections JSON files.';

    public function handle()
    {
        $count = BaseSections::export();

        $this->info("{$count} sections written to " . implode(', ', array_merge([BaseSections::path()], BaseSections::extraPaths())));
    }
}
