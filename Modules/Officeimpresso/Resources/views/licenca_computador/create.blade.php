@extends('layouts.app')

@section('title', __('officeimpresso::lang.officeimpresso'))

@section('content')
@include('officeimpresso::layouts.nav')
<section class="content-header">
    <h1>Cadastrar Novo Computador</h1>
</section>
<section class="content">
    <form action="{{ route('licenca_computador.store') }}" method="POST">
        @csrf
        <label for="licenca_id">Licença:</label>
        <input type="text" name="licenca_id" id="licenca_id" required>

        <label for="hd">HD:</label>
        <input type="text" name="hd" id="hd" required>

        <label for="processador">Processador:</label>
        <input type="text" name="processador" id="processador" required>

        <label for="memoria">Memória:</label>
        <input type="text" name="memoria" id="memoria" required>

        <label for="versao_exe">Versão Executável:</label>
        <input type="text" name="versao_exe" id="versao_exe" required>

        <button type="submit" class="btn btn-primary">Cadastrar</button>
    </form>
</section>
@endsection
