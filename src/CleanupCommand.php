<?php
namespace spaghettijeff\chunky;

use DateInterval;
use DateTimeImmutable;
use Exception;
use spaghettijeff\chunky\UploadManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupCommand extends Command {
    protected $signature = 'chunky:clean {--older-than=} {--q|quiet} {--d|dry-run}';

    public function handle(UploadManager $manager): int
    {
        if ($this->option('quiet')) {
            $this->setVerbosity(0);
        } else {
            $this->setVerbosity(1);
        }
        $older_than = $this->option('older-than');
        if ($older_than == null) {
            $this->error("--older-than= is a required option");
            return 1;
        }
        $delete_directory = fn ($dir) => $manager->storage()->deleteDirectory($dir);
        if ($this->option('dry-run')) {
            $delete_directory = fn ($dir) => true;
            $this->info("Dry run - no chunks will be deleted", verbosity: 1);
        }
        try {
            $older_than = DateInterval::createFromDateString($this->option('older-than'));
        } catch(Exception $e) {
            $this->error("unable to parse older-than time range: " . $e->getMessage());
            return 1;
        }
        $cutoff_time = (new DateTimeImmutable('now'))->sub($older_than);
        $chunk_directories = $manager->storage()->directories('chunks');
        foreach ($chunk_directories as $dir) {
            $dir_modified = $manager->storage()->lastModified($dir);
            if ($dir_modified <= $cutoff_time->getTimestamp()) {
                if ($delete_directory($dir)) {
                    $this->line("Deleted download ".$dir." started at ".date('Y-m-d H:i:s', $dir_modified), verbosity: 1);
                } else {
                    $this->error("Delete failed: ".$dir." started at ".date('Y-m-d H:i:s', $dir_modified), verbosity: 1);
                }
            }
        }
        return 0;
    }
}
