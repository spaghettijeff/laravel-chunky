<?php

namespace spaghettijeff\chunky\Tests;

use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Concerns\WithWorkbench;
use spaghettijeff\chunky\ChunkyServiceProvider;
use spaghettijeff\chunky\Upload;
use spaghettijeff\chunky\UploadChunk;

class TestCase extends \Orchestra\Testbench\Dusk\TestCase
{
    //use WithWorkbench;
    protected function getPackageProviders($app)
    {
        return [
            ChunkyServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::persistentFake('test-disk');

        config([
            'chunky.max_chunk_size' => 64,
            'chunky.chunk_retry_attempts' => 4,
            'chunky.disk' => 'test-disk',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Storage::disk('test-disk')->deleteDirectory('./');
    }

    protected function defineRoutes($router)
    {
        $router->post('/upload', function(Upload $upload) {
            if ($upload->isFinished()) {
                return response('Download complete', 200);
            }
            return $upload->uploadResponse();
        });

        $router->patch('/upload', function(Upload $upload, UploadChunk $chunk) {
            $chunkpath = $upload->storeChunk($chunk);
        });
    }
}
