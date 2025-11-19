
class ChunkyUploader {
    constructor(target, {csrf_token, retry_attempts}, progress_callback = null) {
        this.target = target;
        this.csrf_token = csrf_token;
        this.progress_callback = (progress_callback === null) ? () => {} : progress_callback;
        this.retry_attempts = retry_attempts;
        this.context = {
            upload_id: undefined,
            max_chunksize: undefined,
            required_chunks: undefined,
            file_name: undefined,
            file_size: undefined,
            file_type: undefined,
        };
    }

    async upload(file) {
        await this.initialize(file);
        chunk_loop:
        for (const chunk of this.split_file(this.context.max_chunksize, file)) {
            for (let retry_count = 0; retry_count < this.retry_attempts; retry_count++) {
                let response = await this.upload_chunk(chunk);
                if (response.ok) {
                    this.progress_callback({
                        event: 'chunk upload',
                        chunk_number: chunk.chunk_number,
                        response: response,
                    });
                    continue chunk_loop;
                }
                this.progress_callback({
                    event: 'chunk upload failed',
                    retry_count: retry_count,
                    chunk_number: chunk.chunk_number,
                    response: response,
                });
            }
            // all retries failed
            throw new Error(`Upload failed, chunk number ${chunk.chunk_number} failed all retry attempts(${this.num_retry})`);
        }
        const response = await this.finalize();
        this.progress_callback(response);
        return response;
    }

    async initialize(file) {
        const response = await fetch(this.target, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrf_token,
                'file-name': file.name,
                'file-size': file.size,
                'file-type': file.type,
            },
        });
        if (response.status != 202) {
            throw new Error(`Failed to initialize upload: ${response.status}`);
        }
        const result = await response.json();
        this.progress_callback(result);
        const num_chunks = Math.ceil(file.size/result.max_chunksize);
        var all_chunk_ids = new Set(function* () {
                for (let i = 1; i <= num_chunks; i++) {
                    yield i;
                }
            }());
        var uploaded_chunks = new Set(result.uploaded_chunks);
        var required_chunks = all_chunk_ids.difference(uploaded_chunks);
        this.context = {
            upload_id: result.upload_id,
            max_chunksize: result.max_chunksize,
            required_chunks: required_chunks,
            file_name: file.name,
            file_size: file.size,
            file_type: file.type,

        };
    }

    async finalize() {
        const response = await fetch(this.target, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrf_token,
                'file-name': this.context.file_name,
                'file-size': this.context.file_size,
                'file-type': this.context.file_type,
                'upload-id': this.context.upload_id,
            }});
        if (response.status != 200) {
            throw new Error(`Failed to finalize upload: ${response.status}`);
        }
        return response;
    }

    *split_file(max_chunk_size, file) {
        let byte_start = 0;
        let chunk_number = 1;
        while (byte_start < file.size) {
            const byte_end = Math.min(byte_start + max_chunk_size, file.size)
            yield {
                file: file.slice(byte_start, byte_end),
                chunk_number: chunk_number,
            }
            byte_start = byte_end;
            chunk_number += 1;
        }
    }

    async upload_chunk(file_chunk) {
        const response = await fetch(this.target, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': this.csrf_token,
                'file-name': this.context.file_name,
                'file-size': this.context.file_size,
                'file-type': this.context.file_type,
                'upload-id': this.context.upload_id,
                'chunk-number': file_chunk.chunk_number,
            },
            body: file_chunk.file,
        });
        return response;
    }
}
