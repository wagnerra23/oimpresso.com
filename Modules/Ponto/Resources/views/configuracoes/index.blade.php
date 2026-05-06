@extends('pontowr2::layouts.module')

@section('title', __('pontowr2::ponto.menu.configuracoes'))

@section('module_content')
    @php
        $clt       = isset($config['clt'])         ? $config['clt']         : [];
        $bancoHrs  = isset($config['banco_horas']) ? $config['banco_horas'] : [];
        $rep       = isset($config['rep'])         ? $config['rep']         : [];
        $afd       = isset($config['afd'])         ? $config['afd']         : [];
        $marcacao  = isset($config['marcacao'])    ? $config['marcacao']    : [];
        $esocial   = isset($config['esocial'])     ? $config['esocial']     : [];
    @endphp

    <section class="content-header">
        <h1>
            {{ __('pontowr2::ponto.module_label') }}
            <small>{{ __('pontowr2::ponto.menu.configuracoes') }}</small>
        </h1>
    </section>

    <section class="content">
        <div class="callout callout-warning">
            <h4><i class="fa fas fa-exclamation-triangle"></i> Somente leitura</h4>
            <p>
                As configurações abaixo vêm do arquivo <code>Modules/PontoWr2/Config/config.php</code>.
                A edição via UI ainda não está implementada — para alterar, edite o arquivo e rode
                <code>php artisan config:clear</code>. Para editar os REPs cadastrados, use
                <a href="{{ route('ponto.configuracoes.reps') }}">o cadastro de REPs</a>.
            </p>
        </div>

        {{-- CLT --}}
        <div class="row">
            <div class="col-md-6">
                @component('components.widget', ['class' => 'box-primary'])
                    @slot('title')
                        <i class="fa fas fa-gavel"></i>
                        Regras CLT / Reforma Trabalhista
                    @endslot

                    <table class="table table-condensed table-striped">
                        <tr>
                            <th>Tolerância por marcação</th>
                            <td>{{ $clt['tolerancia_minutos_por_marcacao'] ?? '—' }} min <small class="text-muted">(Art. 58 §1º CLT)</small></td>
                        </tr>
                        <tr>
                            <th>Tolerância máxima diária</th>
                            <td>{{ $clt['tolerancia_maxima_diaria_minutos'] ?? '—' }} min</td>
                        </tr>
                        <tr>
                            <th>Interjornada mínima</th>
                            <td>{{ $clt['interjornada_minima_horas'] ?? '—' }} h <small class="text-muted">(Art. 66 CLT)</small></td>
                        </tr>
                        <tr>
                            <th>Intrajornada mínima</th>
                            <td>{{ $clt['intrajornada_minima_minutos'] ?? '—' }} min <small class="text-muted">(Art. 71 CLT)</small></td>
                        </tr>
                        <tr>
                            <th>Hora noturna ficta</th>
                            <td>{{ $clt['hora_noturna_ficta_segundos'] ?? '—' }} s <small class="text-muted">(Art. 73 §1º)</small></td>
                        </tr>
                        <tr>
                            <th>Adicional noturno</th>
                            <td>{{ $clt['adicional_noturno_percentual'] ?? '—' }}%</td>
                        </tr>
                        <tr>
                            <th>Limite HE diária</th>
                            <td>{{ $clt['limite_he_diaria_horas'] ?? '—' }} h <small class="text-muted">(Art. 59 CLT)</small></td>
                        </tr>
                        <tr>
                            <th>Adicional HE</th>
                            <td>{{ $clt['adicional_he_percentual'] ?? '—' }}% <small class="text-muted">(Art. 7º XVI CF/88)</small></td>
                        </tr>
                        <tr>
                            <th>Adicional DSR</th>
                            <td>{{ $clt['adicional_dsr_percentual'] ?? '—' }}% <small class="text-muted">(Lei 605/49)</small></td>
                        </tr>
                    </table>
                @endcomponent
            </div>

            <div class="col-md-6">
                @component('components.widget', ['class' => 'box-success'])
                    @slot('title')
                        <i class="fa fas fa-balance-scale"></i>
                        Banco de Horas
                    @endslot

                    <table class="table table-condensed table-striped">
                        <tr>
                            <th>Habilitado</th>
                            <td>
                                @if (!empty($bancoHrs['habilitado']))
                                    <span class="label label-success">Sim</span>
                                @else
                                    <span class="label label-default">Não</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Prazo de compensação</th>
                            <td>{{ $bancoHrs['prazo_compensacao_meses'] ?? '—' }} meses</td>
                        </tr>
                        <tr>
                            <th>Saldo máximo</th>
                            <td>{{ $bancoHrs['saldo_maximo_horas'] ?? '—' }} h</td>
                        </tr>
                        <tr>
                            <th>Saldo mínimo</th>
                            <td>{{ $bancoHrs['saldo_minimo_horas'] ?? '—' }} h</td>
                        </tr>
                        <tr>
                            <th>Multiplicador crédito</th>
                            <td>{{ $bancoHrs['multiplicador_credito'] ?? '—' }}x</td>
                        </tr>
                        <tr>
                            <th>Multiplicador débito</th>
                            <td>{{ $bancoHrs['multiplicador_debito'] ?? '—' }}x</td>
                        </tr>
                        <tr>
                            <th>Converter HE em BH automaticamente</th>
                            <td>
                                @if (!empty($bancoHrs['converter_he_em_bh_default']))
                                    <span class="label label-success">Sim</span>
                                @else
                                    <span class="label label-default">Não</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                @endcomponent
            </div>
        </div>

        {{-- REP + AFD + Marcação --}}
        <div class="row">
            <div class="col-md-6">
                @component('components.widget', ['class' => 'box-info'])
                    @slot('title')
                        <i class="fa fas fa-clock"></i>
                        REP e imutabilidade de marcações
                    @endslot

                    <table class="table table-condensed table-striped">
                        <tr>
                            <th>Tipos de REP permitidos</th>
                            <td>
                                @foreach ($rep['tipos_permitidos'] ?? [] as $t)
                                    <span class="label label-info">{{ $t }}</span>
                                @endforeach
                            </td>
                        </tr>
                        <tr>
                            <th>Verificar sequência NSR</th>
                            <td>{{ !empty($rep['nsr_verificar_sequencia']) ? 'Sim' : 'Não' }}</td>
                        </tr>
                        <tr>
                            <th>Assinar marcações (ICP-Brasil)</th>
                            <td>{{ !empty($rep['assinar_marcacoes']) ? 'Sim' : 'Não' }}</td>
                        </tr>
                        <tr>
                            <th>Certificado ICP configurado</th>
                            <td>
                                @if (!empty($rep['certificado_icp_path']))
                                    <span class="label label-success">Sim</span>
                                @else
                                    <span class="label label-warning">Não</span>
                                    <small class="text-muted">(setar PONTO_CERT_ICP_PATH no .env)</small>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Janela de correção</th>
                            <td>{{ $marcacao['janela_correcao_minutos'] ?? '—' }} min</td>
                        </tr>
                        <tr>
                            <th>Append-only forçado</th>
                            <td>{{ !empty($marcacao['forcar_append_only']) ? 'Sim' : 'Não' }}</td>
                        </tr>
                        <tr>
                            <th>Hash</th>
                            <td><code>{{ $marcacao['hash_algoritmo'] ?? '—' }}</code></td>
                        </tr>
                    </table>
                @endcomponent
            </div>

            <div class="col-md-6">
                @component('components.widget', ['class' => 'box-warning'])
                    @slot('title')
                        <i class="fa fas fa-file-import"></i>
                        AFD / Importação
                    @endslot

                    <table class="table table-condensed table-striped">
                        <tr>
                            <th>Encoding</th>
                            <td><code>{{ $afd['encoding'] ?? '—' }}</code></td>
                        </tr>
                        <tr>
                            <th>Tamanho máximo</th>
                            <td>{{ $afd['max_filesize_mb'] ?? '—' }} MB</td>
                        </tr>
                        <tr>
                            <th>Chunk de processamento</th>
                            <td>{{ $afd['chunk_size_linhas'] ?? '—' }} linhas</td>
                        </tr>
                        <tr>
                            <th>Validar hash de registros</th>
                            <td>{{ !empty($afd['validar_hash_registros']) ? 'Sim' : 'Não' }}</td>
                        </tr>
                    </table>

                    <hr>
                    <h4><i class="fa fas fa-paper-plane"></i> eSocial</h4>
                    <table class="table table-condensed table-striped">
                        <tr>
                            <th>Ambiente</th>
                            <td>
                                @php $amb = $esocial['ambiente'] ?? '—'; @endphp
                                <span class="label {{ $amb === 'producao' ? 'label-danger' : 'label-warning' }}">
                                    {{ $amb }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Eventos</th>
                            <td>
                                @foreach ($esocial['eventos'] ?? [] as $ev)
                                    <span class="label label-default">{{ $ev }}</span>
                                @endforeach
                            </td>
                        </tr>
                        <tr>
                            <th>tpAmb</th>
                            <td>{{ $esocial['tp_amb'] ?? '—' }}</td>
                        </tr>
                    </table>
                @endcomponent
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 text-center">
                <a href="{{ route('ponto.configuracoes.reps') }}" class="btn btn-info">
                    <i class="fa fas fa-microchip"></i> Gerenciar REPs cadastrados
                </a>
            </div>
        </div>
    </section>
@endsection
