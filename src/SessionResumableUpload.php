<?php
namespace spaghettijeff\chunky;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

use spaghettijeff\chunky\Upload;
use spaghettijeff\chunky\UploadManager;

class SessionResumableUpload extends Upload {
    protected string|null $expected_hash;
    protected string|null $session_id;
    private $session;

    public function __construct(Request $request, UploadManager $manager)
    {
        $this->expected_hash = $request->header('file-hash');
        $this->session_id = $request->session()->get('chunky.upload-id');
        $this->session = $request->session();
        parent::__construct($request, $manager);
    }

    public function uploadResponse()
    {
        if ($this->session_id !== null && $this->id === null) { // attempt session recovery
            $hash = $this->get_stored_hash($this->session_id);
            #dd($hash);
            if ($hash && $this->expected_hash === $hash['hash'] && $this->client_name == $hash['filename']) { // stored hash is same as reported
                $this->id = $this->session_id;
            }
        }
        return parent::uploadResponse();
    }

    private function get_stored_hash(string $id): array|false
    {
        $contents = $this->storage->get('chunks/'.$id.'/sha512sum');
        if (!$contents) return false;
        $hash = substr($contents, 0, 128);
        $fname = substr($contents, 129);
        if (!$hash | !$fname) return false;
        return ['hash' => $hash, 'filename' => $fname];
    }

    protected function init_new_upload() {
        parent::init_new_upload();
        $hash_content = $this->expected_hash . ' ' . $this->client_name;
        $this->storage->put('/chunks/' . $this->id . '/sha512sum', $hash_content);
        $this->session->put('chunky.upload-id', $this->id);
        $this->session->save();
    }
}
