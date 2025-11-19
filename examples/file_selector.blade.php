<script src='spaghettijeff/chunky/chunkyClient.js'></script>

<script>
var uploader = new ChunkyUploader(
    '{{ Request::url() }}',
    {csrf_token: '{{ csrf_token() }}', retry_attempts: 4},
    (e) => console.log(e) );

function upload(event) {
    event.preventDefault();
    const file = event.target.querySelector('#chunky-file').files[0];
    uploader.upload(file).then(() => console.log('Upload Finished'));
}
</script>

<div>
    <form id='upload-form' onsubmit='upload(event)'>
        <div>
            <label for="file">Choose file to upload</label>
            <input type="file" id="chunky-file" name="chunky-file"/>
        </div>
        <div>
            <input id='chunky-upload' type='submit'></input>
        </div>
    </form>
</div>
