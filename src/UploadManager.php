<?php
namespace spaghettijeff\chunky;

use Illuminate\Support\Facades\Storage;

Class UploadManager
{
    /**
     * The storage disk chunks are saved in
     *
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
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
