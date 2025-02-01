@routes

@extends('statamic::layout')

@section('title', 'Content Backup')

@section('content')

    <home token="{{csrf_token()}}" ></home>

@endsection

