@extends('layouts.app')
@section('title', 'Copiloto — Alertas')
@section('content')
<section class="content-header"><h1>Alertas</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <div class="alert alert-warning">STUB spec-ready — ver SPEC US-COPI-060.</div>
        <a href="{{ route('copiloto.alertas.config') }}" class="btn btn-default">Configurar</a>
    </div></div>
</section>
@stop
