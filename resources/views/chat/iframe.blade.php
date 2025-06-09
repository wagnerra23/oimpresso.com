@extends('layouts.app')

@section('content')
<div class="iframe-container" style="width: 100%; height: 100%;">
    <iframe src="{{ $url }}" width="100%" height="800px" frameborder="0" allowfullscreen></iframe>
</div>
@endsection
