@extends('layout')

@section('content')
    <h2>Computadores Cadastrados</h2>
    <a href="{{ route('licencas_computador.create') }}">Empresa licensiada</a>


    <input type="text" name="busimess_id" id="busimess_id" required>

    <label for="versao_minima">Versão Minima:</label>
    <input type="text" name="hd" id="hd" required>

    <label for="versao_obrigatoria">Versão Obrigatoria:</label>
    <input type="text" name="processador" id="processador" required>

    <label for="caminho_banco">Caminho do banco servidor:</label>
    <input type="text" name="memoria" id="memoria" required>


    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>HD</th>
                <th>User Win</th>
                <th>Processador</th>
                <th>Memória</th>
                <th>Versão Executável</th>
                <th>Bloqueado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($computadores as $computador)
                <tr>
                    <td>{{ $computador->id }}</td>
                    <td>{{ $computador->hd }}</td>
                    <td>{{ $computador->user_win }}</td>
                    <td>{{ $computador->processador }}</td>
                    <td>{{ $computador->memoria }}</td>
                    <td>{{ $computador->versao_exe }}</td>
                    <td>{{ $computador->bloqueado ? 'Sim' : 'Não' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
