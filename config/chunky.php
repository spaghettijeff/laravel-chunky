<?php

use Illuminate\Support\Facades\Storage;

return [
    'max_chunk_size' => 1048576, // 1M
    'chunk_retry_attempts' => 4,
    'disk' => 'local',
];
