@extends('layouts.app')
@section('title', 'Voz do Cliente')

@section('content')
<section class="content-header">
    <h1>Voz do Cliente <small>o que as pessoas relataram</small></h1>
</section>

<section class="content">
    <div class="box box-solid">
        <div class="box-body">
            @if($sinais->isEmpty())
                <p class="text-muted">
                    Nenhum relato ainda. Quando alguém relatar um problema por dentro do
                    sistema, ele aparece aqui — com a tela em que aconteceu.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-condensed table-striped">
                        <thead>
                            <tr>
                                <th>Quando</th>
                                <th>Quem</th>
                                <th>O que disse</th>
                                <th>Onde</th>
                                <th>Grav.</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($sinais as $sinal)
                            <tr>
                                <td>{{ $sinal->created_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ $sinal->autor_nome ?: '—' }}</td>
                                <td>{{ $sinal->texto }}</td>
                                <td><small class="text-muted">{{ $sinal->url_vista ?: '—' }}</small></td>
                                <td>{{ $sinal->severidade ?? '—' }}</td>
                                <td>
                                    @if($sinal->status === 'pending')
                                        <span class="label label-warning">Pendente</span>
                                    @elseif($sinal->status === 'triaged')
                                        <span class="label label-info">
                                            Triado{{ $sinal->triado_para_us ? ' · '.$sinal->triado_para_us : '' }}
                                        </span>
                                    @else
                                        <span class="label label-default">Fechado</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {!! $sinais->links() !!}
            @endif
        </div>
    </div>
</section>
@endsection
