<?php
namespace spaghettijeff\chunky;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

use Symfony\Component\HttpFoundation\Response;
use spaghettijeff\chunky\UploadManager;

class Upload
{
    /**
     * The storage disk chunks are saved in
     *
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected $storage;
    /**
     * uuid for the upload
     *
     * @var string
     */
    protected $id;
    /**
     * file name reported by the client
     *
     * @var string
     */
    protected $client_name;
    /**
     * file size (in bytes) reported by the client
     *
     * @var int
     */
    protected int $size;


    public function __construct(Request $request, UploadManager $manager)
    {
        $this->storage = $manager->storage();
        $this->size = $request->header('file-size');
        $this->client_name = $request->header('file-name');
        $this->id = $request->header('upload-id');
    }

    public function getID(): string
    {
        return $this->id;
    }

    /**
     * determine if all the chunks of a file have been uploaded
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        $total_bytes_uploaded = 0;
        $uploaded_chunks = $this->storage->files('chunks/'.$this->id);
        $uploaded_chunks = array_filter($uploaded_chunks, fn($x) => str_starts_with($x, 'chunks/'.$this->id.'/part'));
        foreach($uploaded_chunks as $chunk) {
            $total_bytes_uploaded += $this->storage->size($chunk);
        }
        if ($total_bytes_uploaded > $this->size) {
            throw new \Error('too many bytes uploaded!');
        }
        return $total_bytes_uploaded === $this->size;
    }

    /**
     * get the file name of the upload as reported by the client
     *
     * @return string
     */
    public function getClientOriginalName(): string
    {
        return $this->client_name;
    }

    /**
     * save the contents of a chunk to the filesystem, returning a
     * string that is the path on success, and false on failure
     *
     * @param spaghettijeff/chunky/UploadChunk $chunk
     *
     * @return string|false
     */

    public function storeChunk(UploadChunk $chunk): string|false
    {
        $chunk_directory = $this->get_chunk_directory();
        $this->storage->makeDirectory($chunk_directory);
        $path = $this->storage->path($chunk_directory.'/part'.$chunk->getChunkNumber());
        $stream = fopen($path, 'wb');
        if (!$stream) return false;
        if (!stream_copy_to_stream($chunk->file(), $stream)) return false;
        fclose($stream);
        return $path;
    }

    /**
     * merge the chunks of an upload to one file at $directory in the filesystem $driver
     * if no directory is given the root of the filesystem is used
     * if no driver is given the filesystem that stores chunks is used
     * returns the path of the stored upload
     *
     * @param string|null $directory
     * @param \Illuminate\Contracts\Filesystem\Filesystem|null $driver
     *
     * @return string
     */
    public function mergeAndStore(string|null $directory=null, Filesystem|null $driver=null): string
    {
        $driver = $driver ? $driver : $this->storage;
        $directory = $directory ? $directory : '';
        $filename = $this->id;
        throw_if(!$this->isFinished(), new \Error('Upload not complete'));
        $chunk_dir = $this->get_chunk_directory();
        $chunks = $this->get_uploaded_chunks();
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

    /**
     * returns a response for the client to use to start/finish/resume an upload
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadResponse()
    {
        if ($this->id === null) {
        $this->init_new_upload();
        return response()->json([
            'upload_id' => $this->id,
            'max_chunksize' => config('chunky.max_chunk_size'),
            'uploaded_chunks' => [],
        ], Response::HTTP_ACCEPTED);
        }
        // attempt resume
        $directory = $this->get_chunk_directory();
        $uploaded_chunks = array_map(
            fn($path) => intval(substr($path, strlen($directory.'part'))),
            $this->get_uploaded_chunks());

        return response()->json([
            'upload_id' => $this->id,
            'max_chunksize' => config('chunky.max_chunk_size'),
            'uploaded_chunks' => $uploaded_chunks,
        ], Response::HTTP_ACCEPTED);
    }

    protected function init_new_upload() {
        $this->id = Str::uuid()->toString();
    }

    private final function get_chunk_directory() {
        return 'chunks/'.$this->id.'/';
    }

    private final function get_uploaded_chunks() {
        $directory = $this->get_chunk_directory();
        return array_filter(
            $this->storage->files($directory, false),
            fn($x) => str_starts_with($x, $directory.'part'));
    }
}
