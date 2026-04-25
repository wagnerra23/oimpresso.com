@extends('layouts.app')
@section('title', 'Copiloto — Config alertas')
@section('content')
<section class="content-header"><h1>Configurar alertas</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <form method="POST" action="{{ route('copiloto.alertas.config.update') }}">
            @csrf @method('PATCH')
            <div class="form-group"><label>Desvio aceitável (%)</label>
                <input type="number" name="desvio_threshold" class="form-control" value="10">
            </div>
            <div class="form-group"><label>Canais</label>
                <label><input type="checkbox" name="canais[]" value="in_app" checked> In-app</label>
                <label><input type="checkbox" name="canais[]" value="email"> Email</label>
                <label><input type="checkbox" name="canais[]" value="whatsapp"> WhatsApp (v2)</label>
            </div>
            <button class="btn btn-primary">Salvar</button>
        </form>
    </div></div>
</section>
@stop
