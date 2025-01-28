@extends('statamic::layout')
@section('title', 'Content Backup')

@section('content')

    <header class="mb-6">
        <h1>Backup</h1>
    </header>
    <div class="p-4 card content">
        <div class="flex flex-col justify-between gap-3 md:flex-row">
            <a href="{{route('statamic.cp.statamic-content-backup.backup')}}" class="flex w-full p-4 rounded-md md:w-1/2 hover:bg-gray-200 group">
                <div class="w-8 h-8 mr-4 text-gray-800 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M.752 21.751a1.5 1.5 0 0 0 1.5 1.5m0-22.5a1.5 1.5 0 0 0-1.5 1.5m22.5 0a1.5 1.5 0 0 0-1.5-1.5m0 22.5a1.5 1.5 0 0 0 1.5-1.5m0-15.75v1.5m0 3.75v1.5m0 3.75v1.5m-22.5-12v1.5m0 3.75v1.5m0 3.75v1.5m5.25 5.25h1.5m3.75 0h1.5m3.75 0h1.5m-12-22.5h1.5m3.75 0h1.5m3.75 0h1.5m-6 5.25v12m4.5-4.5-4.5 4.5-4.5-4.5"></path></svg>
                </div>
                <div>
                    <h3 class="text-blue">Download Backup</h3>
                    <p class="text-xs">Download content backup zip.</p>
                </div>
            </a>
            <a id="restoreButton" class="flex w-full p-4 rounded-md md:w-1/2 hover:bg-gray-200 group">
                <div class="w-8 h-8 mr-4 text-gray-800 rotate-180 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M.752 21.751a1.5 1.5 0 0 0 1.5 1.5m0-22.5a1.5 1.5 0 0 0-1.5 1.5m22.5 0a1.5 1.5 0 0 0-1.5-1.5m0 22.5a1.5 1.5 0 0 0 1.5-1.5m0-15.75v1.5m0 3.75v1.5m0 3.75v1.5m-22.5-12v1.5m0 3.75v1.5m0 3.75v1.5m5.25 5.25h1.5m3.75 0h1.5m3.75 0h1.5m-12-22.5h1.5m3.75 0h1.5m3.75 0h1.5m-6 5.25v12m4.5-4.5-4.5 4.5-4.5-4.5"></path></svg>
                </div>
                <div>
                    <h3 class="text-blue">Restore Backup</h3>
                    <p class="text-xs">Restore content backup previously downloaded.</p>
                    <p class="text-xs">This will replace current content!</p>
                </div>
                <form
                    id="restoreForm"
                    action="{{route('statamic.cp.statamic-content-backup.restore')}}"
                    method="POST"
                    class="hidden"
                    enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="restoreInput" id="restoreInput" accept="application/zip">
                </form>
            </a>
        </div>
    </div>

    <script defer>

        window.onload = (() => {
            document.querySelector('#restoreButton').addEventListener('click', (event) => {
                document.querySelector('#restoreInput').click();
            })
            document.querySelector('#restoreInput').addEventListener('change', (event) => {
                document.querySelector('#restoreForm').submit();
            })
        })

    </script>

@endsection
