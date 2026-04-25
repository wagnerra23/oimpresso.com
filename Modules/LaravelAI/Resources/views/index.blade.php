@extends('laravelai::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('laravelai.name') !!}</p>
@endsection
