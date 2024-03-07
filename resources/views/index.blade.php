@extends('statamic::layout')
@section('title', 'Content Backup')

@section('content')

    <header class="mb-6">
        <h1>Backup</h1>
    </header>
    <div class="card p-4 content">
        <div class="flex justify-between flex-col md:flex-row gap-3">
            <a class="w-full md:w-1/2 hover:bg-gray-200 rounded-md group flex p-4">
                <div class="w-8 h-8 mr-4 text-gray-800 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M.752 21.751a1.5 1.5 0 0 0 1.5 1.5m0-22.5a1.5 1.5 0 0 0-1.5 1.5m22.5 0a1.5 1.5 0 0 0-1.5-1.5m0 22.5a1.5 1.5 0 0 0 1.5-1.5m0-15.75v1.5m0 3.75v1.5m0 3.75v1.5m-22.5-12v1.5m0 3.75v1.5m0 3.75v1.5m5.25 5.25h1.5m3.75 0h1.5m3.75 0h1.5m-12-22.5h1.5m3.75 0h1.5m3.75 0h1.5m-6 5.25v12m4.5-4.5-4.5 4.5-4.5-4.5"></path></svg>
                </div>
                <div>
                    <h3 class="text-blue">Download Backup</h3>
                    <p class="text-xs">Download content backup zip.</p>
                </div>
            </a>
            <a class="w-full md:w-1/2 hover:bg-gray-200 rounded-md group flex p-4">
                <div class="w-8 h-8 mr-4 text-gray-800 shrink-0 rotate-180">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M.752 21.751a1.5 1.5 0 0 0 1.5 1.5m0-22.5a1.5 1.5 0 0 0-1.5 1.5m22.5 0a1.5 1.5 0 0 0-1.5-1.5m0 22.5a1.5 1.5 0 0 0 1.5-1.5m0-15.75v1.5m0 3.75v1.5m0 3.75v1.5m-22.5-12v1.5m0 3.75v1.5m0 3.75v1.5m5.25 5.25h1.5m3.75 0h1.5m3.75 0h1.5m-12-22.5h1.5m3.75 0h1.5m3.75 0h1.5m-6 5.25v12m4.5-4.5-4.5 4.5-4.5-4.5"></path></svg>
                </div>
                <div>
                    <h3 class="text-blue">Restore Backup</h3>
                    <p class="text-xs">Restore content backup previously downloaded.</p>
                </div>
            </a>
        </div>
    </div>

@endsection
