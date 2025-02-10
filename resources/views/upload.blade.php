<!-- resources/views/upload.blade.php -->

@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Upload Domains | <a href="/domains">View Domains</a></h2> 

    @if(session('success'))
    <div class="alert alert-success">{!! session('success') !!}</div>
    @endif
    <form action="{{ route('domains.upload') }}" method="POST" enctype="multipart/form-data" id="upload-form">
        @csrf
        <div class="form-group">
            <label for="registrar">Registrar</label>
            <select name="registrar" id="registrar" class="form-control">
                <option value="cosmotown">Cosmotown</option>
                <option value="dynadot">Dynadot</option>
                <option value="spaceship">Spaceship.com</option>
                <option value="namecheap">NameCheap</option>
                <option value="porkbun">Porkbun</option>
                <option value="regery">Regery</option>
                <option value="gandi">Gandi</option>
                <option value="namebright">NameBright</option>
                <option value="godaddy">GoDaddy</option>
                <option value="sav">SAV</option>
                <option value="namesilo">NameSilo</option>
                <option value="namecom">Name.com</option>
                <option value="123reg.co.uk">123Reg.co.uk</option>
            </select>
        </div>
        <div class="form-group">
            <label for="file">CSV File</label>
            <div id="drop-area" class="form-control-file">
                <p>Drag & Drop your file here or click to select</p>
                <input type="file" name="file" id="file" class="form-control-file" style="display: none;">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    console.info('Upload form initialized, ready for file drop.');

    let dropArea = document.getElementById('drop-area');
    let fileInput = document.getElementById('file');
    let dropCounter = 0;

    dropArea.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropArea.classList.add('dragging');
        console.info('File dragged over drop area.');
    });

    dropArea.addEventListener('dragleave', () => {
        dropArea.classList.remove('dragging');
        console.info('File left the drop area.');
    });

    dropArea.addEventListener('drop', (event) => {
        event.preventDefault();
        dropArea.classList.remove('dragging');
        dropCounter++;
        console.info('File dropped into drop area. Drop count: ' + dropCounter);

        fileInput.files = event.dataTransfer.files;
        if (fileInput.files.length > 0) {
            console.info('File selected: ' + fileInput.files[0].name);
            dropArea.querySelector('p').textContent = fileInput.files[0].name;
        }
    });

    dropArea.addEventListener('click', () => {
        console.info('Drop area clicked. Opening file dialog.');
        fileInput.click();
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            console.info('File selected via dialog: ' + fileInput.files[0].name);
            dropArea.querySelector('p').textContent = fileInput.files[0].name;
        }
    });
});
</script>

<style>
#drop-area {
    border: 2px dashed #007bff;
    padding: 20px;
    text-align: center;
    cursor: pointer;
}
#drop-area.dragging {
    background-color: #e9ecef;
}
</style>
@endsection