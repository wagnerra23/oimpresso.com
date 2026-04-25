@extends('layouts.app')
@section('title', 'Copiloto — Nova meta')
@section('content')
<section class="content-header"><h1>Nova meta</h1></section>
<section class="content">
    <div class="box"><div class="box-body">
        <form method="POST" action="{{ route('copiloto.metas.store') }}">
            @csrf
            <div class="form-group"><label>Nome</label><input name="nome" class="form-control" required></div>
            <div class="form-group"><label>Slug</label><input name="slug" class="form-control" required pattern="[a-z0-9_]+"></div>
            <div class="form-group"><label>Unidade</label>
                <select name="unidade" class="form-control">
                    <option value="R$">R$</option><option value="qtd">Quantidade</option>
                    <option value="%">Percentual</option><option value="dias">Dias</option>
                </select>
            </div>
            <div class="form-group"><label>Tipo de agregação</label>
                <select name="tipo_agregacao" class="form-control">
                    <option value="soma">Soma</option><option value="media">Média</option>
                    <option value="ultimo">Último valor</option><option value="contagem">Contagem</option>
                </select>
            </div>
            <button class="btn btn-primary">Criar</button>
        </form>
    </div></div>
</section>
@stop
