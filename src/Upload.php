<?php
namespace spaghettijeff\chunky;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

use Symfony\Component\HttpFoundation\Response;
use spaghettijeff\chunky\UploadManager;

class Upload
{
    private $storage;
    private $id;
    protected $client_name;
    protected int $size;


    public function __construct(Request $request, UploadManager $manager)
    {
        $this->storage = $manager->storage();
        $upload_id = $request->header('upload-id');
        if ($upload_id === null) {
            $upload_id = Str::uuid()->toString();
        }
        $this->id = $upload_id;
        $this->size = $request->header('file-size');
        $this->client_name = $request->header('file-name');
    }

    public function getID(): string
    {
        return $this->id;
    }


    public function isFinished(): bool
    {
        $total_bytes_uploaded = 0;
        $uploaded_chunks = $this->storage->files('chunks/'.$this->id);
        foreach($uploaded_chunks as $chunk) {
            $total_bytes_uploaded += $this->storage->size($chunk);
        }
        if ($total_bytes_uploaded > $this->size) {
            throw new \Error('too many bytes uploaded!');
        }
        return $total_bytes_uploaded === $this->size;
    }

    public function getClientOriginalName(): string
    {
        return $this->client_name;
    }

    public function storeChunk(UploadChunk $chunk): string|false
    {
        $this->storage->makeDirectory('chunks/' . $this->id);
        $path = $this->storage->path('chunks/' . $this->id . '/part' . $chunk->getChunkNumber());
        $stream = fopen($path, 'wb');
        if (!$stream) return false;
        if (!stream_copy_to_stream($chunk->file(), $stream)) return false;
        fclose($stream);
        return $path;
    }

    public function mergeAndStore(string|null $directory=null, Filesystem|null $driver=null): string
    {
        $driver = $driver ? $driver : $this->storage;
        $directory = $directory ? $directory : '';
        $filename = $this->id;
        throw_if(!$this->isFinished(), new \Error('Upload not complete'));
        $chunk_dir = 'chunks/'.$this->id.'/';
        $chunks = $this->storage->files($chunk_dir, false);
        usort($chunks, function($a, $b) use ($chunk_dir) {
            $a = intval(substr($a, strlen($chunk_dir.'part')));
            $b = intval(substr($b, strlen($chunk_dir.'part')));
            return ($a > $b)? 1 : (($a < $b)? -1 : 0);
        });
        $out_stream = fopen($this->storage->path($this->getID()), 'ab');
        foreach ($chunks as $chunk) {
            $data = $this->storage->readStream($chunk);
            if (!stream_copy_to_stream($data, $out_stream)) {
                throw new \Error('Upload failed to merge chunks');
            }
                fclose($data);
        }
        fclose($out_stream);
        $this->storage->deleteDirectory($chunk_dir);
        return $directory.'/'.$filename;
    }
    public function uploadResponse()
    {
        if ($this->id === null) {
        return response()->json([
            'upload_id' => $this->id,
            'max_chunksize' => config('chunky.max_chunk_size'),
            'uploaded_chunks' => [],
        ], Response::HTTP_ACCEPTED);
        }
        // attempt resume
        $directory = 'chunks/'.$this->id.'/';
        $uploaded_chunks = array_map(function ($path) use ($directory) {
                return intval(substr($path, strlen($directory.'part')));
            },
            $this->storage->files($directory));

        return response()->json([
            'upload_id' => $this->id,
            'max_chunksize' => config('chunky.max_chunk_size'),
            'uploaded_chunks' => $uploaded_chunks,
        ], Response::HTTP_ACCEPTED);
    }
}
