<?php
namespace spaghettijeff\chunky;

use Illuminate\Http\Request;
use spaghettijeff\chunky\Upload;
use spaghettijeff\chunky\UploadManager;

class SessionResumableUpload extends Upload {
    protected string|null $expected_hash;
    protected string|null $session_id;

    public function __construct(Request $request, UploadManager $manager)
    {
        parent::__construct($request, $manager);
        $this->expected_hash = $request->header('upload-hash');
        $this->session_id = $request->session()->get('chunky.upload-id');
    }

    public function uploadResponse()
    {
        if ($this->session_id !== null && $this->id === null) { // attempt session recovery
            [$hash, $file_name] = self::get_stored_hash($this->id);
            if ($this->expected_hash === $hash && $this->client_name == $file_name) { // stored hash is same as reported
                $this->id = $this->session_id;
            }
        }
        return parent::uploadResponse();
    }

    private static function get_stored_hash(string $id): array|false
    {
        $contents = $this->storage->get('chunks/'.$this->id.'/sha512sum');
        if (!$contents) return false;
        $hash = substr($contents, 0, 132);
        $fname = substr($contents, 132);
        if (!$hash | !$fname) return false;
        return ['hash' => $hash, 'filename' => $fname];
    }

}
