<?php
namespace spaghettijeff\chunky;

use Illuminate\Support\Facades\Storage;

Class UploadManager
{
    protected $storage_driver;

    public function __construct()
    {
        $this->storage_driver = Storage::disk(config('chunky.disk'));
    }

    public function storage()
    {
        return $this->storage_driver;
    }
}
