@extends('layouts.app')
@section('title', 'Copiloto — Superadmin')
@section('content')
<section class="content-header"><h1>Copiloto <small>visão da plataforma</small></h1></section>
<section class="content">
    <div class="box"><div class="box-header"><h3 class="box-title">Metas da plataforma (business_id NULL)</h3></div>
        <div class="box-body">
            <table class="table"><thead><tr><th>Nome</th><th>Unidade</th><th>Origem</th></tr></thead>
            <tbody>
                @forelse($metasPlataforma as $m)
                    <tr><td>{{ $m->nome }}</td><td>{{ $m->unidade }}</td><td>{{ $m->origem }}</td></tr>
                @empty
                    <tr><td colspan="3"><em>Nenhuma meta da plataforma ainda. Rode o seeder pra materializar R$ 5mi/ano.</em></td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>

    <div class="box"><div class="box-header"><h3 class="box-title">Metas de clientes (cross-business)</h3></div>
        <div class="box-body">
            <table class="table"><thead><tr><th>Business</th><th>Nome</th><th>Unidade</th></tr></thead>
            <tbody>
                @forelse($metasDeClientes as $m)
                    <tr><td>#{{ $m->business_id }}</td><td>{{ $m->nome }}</td><td>{{ $m->unidade }}</td></tr>
                @empty
                    <tr><td colspan="3"><em>Nenhum cliente configurou metas ainda.</em></td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>
</section>
@stop
