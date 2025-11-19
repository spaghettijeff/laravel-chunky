<?php
namespace spaghettijeff\chunky\Tests\Unit;

use spaghettijeff\chunky\Tests\TestCase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;

class UnitTest extends TestCase
{
    public function test_upload_initialization(): void
    {
        $file = UploadedFile::fake()->image('upload.png')->size(1);
        $response = $this->init_upload($file);
        $response->assertStatus(202);
        $response->assertJson(fn (AssertableJson $json) =>
            $json->has('upload_id')
                ->has('max_chunksize')
                ->where('uploaded_chunks', [])
        );
    }

    // ---------------- HELPER FUNCTIONS ----------------

    private function init_upload(File $file): TestResponse
    {
        $response = $this->withHeaders([
            'file-name' => $file->getClientOriginalName(),
            'file-size' => $file->getSize(),
            'file-type' => $file->getType(),
        ])->post('/upload', []);
        return $response;
    }

    private function finalize_upload(File $file, array $upload_ctx): TestResponse
    {
        $response = $this->withHeaders([
            'upload-id' => $upload_ctx['upload_id'],
            'file-name' => $file->getClientOriginalName(),
            'file-size' => $file->getSize(),
            'file-type' => $file->getType(),
        ])->post('/upload', []);
        return $response;
    }

    private function chunk_upload(array $upload_ctx, File $file, string $chunk, int $chunk_num): TestResponse
    {
        $chunk_file = UploadedFile::fake()->createWithContent('blob', $chunk)->mimeType('application/octet-stream');
        $response = $this->withHeaders([
            'upload-id' => $upload_ctx['upload_id'],
            'chunk-number' => $chunk_num,
            'file-name' => $file->getClientOriginalName(),
            'file-size' => $file->getSize(),
            'file-type' => $file->getType(),
        ])->patch('/upload', unpack('C*', $chunk));
        return $response;
    }
}
