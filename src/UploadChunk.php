<?php
namespace spaghettijeff\chunky;

use Illuminate\Http\Request;

class UploadChunk
{
    private mixed $file;
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
