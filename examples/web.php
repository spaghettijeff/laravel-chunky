<?php

use Illuminate\Support\Facades\Route;
use spaghettijeff\chunky\Upload;
use spaghettijeff\chunky\UploadChunk;


Route::get('/upload', function() {
    return view('file_selector');
});

Route::post('/upload', function(Upload $upload) {
    if ($upload->isFinished()) {
        // store following in database, etc.
        $filepath = $upload->mergeAndStore();
        $filename = $upload->getClientOriginalName();

        return response($filepath); // This response can be whatever as long as the status is 200
    }
    // this must be returned to start/resume an upload.
    // Additional elemnts may be added to this json response
    return $upload->uploadResponse();
});

Route::patch('/upload', function(Upload $upload, UploadChunk $chunk) {
    $chunkpath = $upload->storeChunk($chunk);
});
