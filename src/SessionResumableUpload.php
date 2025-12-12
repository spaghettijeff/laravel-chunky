<?php
namespace spaghettijeff\chunky;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

use spaghettijeff\chunky\Upload;
use spaghettijeff\chunky\UploadManager;

class SessionResumableUpload extends Upload {
    /**
     * sha512 hash of the file reported by the client
     *
     * @var string|null
     */
    protected string|null $expected_hash;
    /**
     * upload id saved in the session
     *
     * @var string|null
     */
    protected string|null $session_id;
    /**
     * session of the current request
     *
     * @var \Illuminate\Contracts\Session\Session
     */
    private $session;

    public function __construct(Request $request, UploadManager $manager)
    {
        $this->expected_hash = $request->header('file-hash');
        $this->session_id = $request->session()->get('chunky.upload-id');
        $this->session = $request->session();
        parent::__construct($request, $manager);
    }

     /*
     * returns a response for the client to use to start/finish/resume an upload
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadResponse()
    {
        if ($this->session_id !== null && $this->id === null) { // attempt session recovery
            $hash = $this->get_stored_hash($this->session_id);
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
