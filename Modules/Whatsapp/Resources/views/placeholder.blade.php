@extends('layouts.app')

@section('title', $titulo ?? 'Whatsapp')

@section('content')
<section class="content-header">
    <h1>{{ $titulo ?? 'Whatsapp' }}</h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <p class="text-muted">{{ $mensagem ?? 'Em breve.' }}</p>
            <p>
                <a href="{{ url('/home') }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </p>
        </div>
    </div>
</section>
@endsection
