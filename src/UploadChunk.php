<?php
namespace spaghettijeff\chunky;

use Illuminate\Http\Request;

class UploadChunk
{
    /**
     * contents of the file chunk
     *
     * @var resource
     */
    private mixed $file;
    /**
     * file chunk number (1 indexed)
     *
     * @var int
     */
    private int $chunk_number;

    public function __construct(Request $request)
    {
        $this->file = $request->getContent(asResource: true);
        $this->chunk_number = $request->header('chunk-number');
    }

    public function getChunkNumber(): int
    {
        return $this->chunk_number;
    }

    public function file(): mixed
    {
        return $this->file;
    }
}
