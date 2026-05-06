@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.relatorios'))

@section('module_content')
    @php
        // Mapeamento chave → ícone/cor para AdminLTE info-box
        $decoracao = [
            'afd'         => ['icon' => 'fa fas fa-file-code',    'bg' => 'bg-blue'],
            'afdt'        => ['icon' => 'fa fas fa-file-invoice', 'bg' => 'bg-aqua'],
            'aej'         => ['icon' => 'fa fas fa-file-alt',     'bg' => 'bg-teal'],
            'espelho'     => ['icon' => 'fa fas fa-user-clock',   'bg' => 'bg-green'],
            'he'          => ['icon' => 'fa fas fa-hourglass-half','bg' => 'bg-purple'],
            'banco-horas' => ['icon' => 'fa fas fa-balance-scale','bg' => 'bg-yellow'],
            'atrasos'     => ['icon' => 'fa fas fa-user-slash',   'bg' => 'bg-red'],
            'esocial'     => ['icon' => 'fa fas fa-paper-plane',  'bg' => 'bg-maroon'],
        ];
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.relatorios') }}</small>
        </h1>
    </section>

    <section class="content">
        <div class="callout callout-info">
            <h4><i class="fa fas fa-info-circle"></i> Relatórios disponíveis</h4>
            <p>
                Escolha o tipo de relatório para gerar. Filtros (período, colaborador, departamento)
                serão solicitados na próxima tela. A geração de AFD/AFDT/AEJ segue o layout da
                <em>Portaria MTP 671/2021</em>.
            </p>
        </div>

        <div class="row">
            @foreach ($relatorios as $r)
                @php
                    $dec = $decoracao[$r['chave']] ?? ['icon' => 'fa fas fa-file', 'bg' => 'bg-gray'];
                @endphp
                <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon {{ $dec['bg'] }}">
                            <i class="{{ $dec['icon'] }}"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><strong>{{ $r['titulo'] }}</strong></span>
                            <span class="info-box-number" style="font-size:12px; font-weight:normal;">
                                {{ $r['descricao'] }}
                            </span>
                            <a href="{{ route('ponto.relatorios.gerar', $r['chave']) }}"
                               class="btn btn-primary btn-xs"
                               style="margin-top:5px;">
                                <i class="fa fas fa-cogs"></i> Gerar
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="text-center text-muted" style="margin:12px 0;">
            <small>
                <i class="fa fas fa-shield-alt"></i>
                Relatórios legais (AFD/AFDT/AEJ) seguem layout Portaria 671/2021 Anexo I.
                A geração efetiva depende de implementação em <code>ReportService</code>
                (fase posterior — hoje retorna HTTP 501).
            </small>
        </div>
    </section>
@endsection
