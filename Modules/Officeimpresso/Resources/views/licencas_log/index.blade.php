@extends('layout')

@section('content')
    <h2>Logs de Licenciamento</h2>
    <a href="{{ route('licencas_log.create') }}">Registrar Novo Log</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Licença</th>
                <th>Solicitação</th>
                <th>Status</th>
                <th>Data de Criação</th>
                <th>Data de Atualização</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->licenca_id }}</td>
                    <td>{{ $log->solicitacao }}</td>
                    <td>{{ $log->status }}</td>
                    <td>{{ $log->created_at }}</td>
                    <td>{{ $log->updated_at }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
