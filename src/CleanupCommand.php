<?php
namespace spaghettijeff\chunky;

use DateInterval;
use DateTimeImmutable;
use spaghettijeff\chunky\UploadManager;
use Illuminate\Console\Command;

class CleanupCommand extends Command {
    protected $signature = 'chunky:clean {--older-than=}';

    public function handle(UploadManager $manager): void
    {
        $older_than = DateInterval::createFromDateString($this->option('older-than'));
        $cutoff_time = (new DateTimeImmutable('now'))->sub($older_than);
        $chunk_directories = $manager->storage()->directories('chunks');
        foreach ($chunk_directories as $dir) {
            $dir_modified = $manager->storage()->lastModified($dir);
            if ($dir_modified <= $cutoff_time->getTimestamp()) {
                $manager->storage()->deleteDirectory($dir);
            }
        }
    }

}
