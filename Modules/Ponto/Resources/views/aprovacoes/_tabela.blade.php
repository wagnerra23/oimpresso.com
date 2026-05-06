{{--
    Partial: tabela de intercorrências.
    Usada no Dashboard (pontowr2::dashboard.index) e na lista completa
    (pontowr2::aprovacoes.index).
    Padrão AdminLTE 2.x (table table-striped, label-*).

    Variáveis esperadas:
      - $aprovacoes : Collection|Paginator de Intercorrencia
--}}
@php
    $labelsEstado = [
        'RASCUNHO'  => 'label-default',
        'PENDENTE'  => 'label-warning',
        'APROVADA'  => 'label-success',
        'REJEITADA' => 'label-danger',
        'APLICADA'  => 'label-primary',
        'CANCELADA' => 'label-default',
    ];
@endphp
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Tipo</th>
                <th>Data / Intervalo</th>
                <th>Estado</th>
                <th>Prioridade</th>
                <th class="text-right">Ação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($aprovacoes as $a)
                <tr>
                    <td>
                        <i class="fa fas fa-user text-muted"></i>
                        {{ optional(optional($a->colaborador)->user)->first_name }} {{ optional(optional($a->colaborador)->user)->last_name }}
                    </td>
                    <td>
                        {{ __('pontowr2::ponto.intercorrencia.tipos.' . $a->tipo) }}
                    </td>
                    <td>
                        <span>{{ $a->data->format('d/m/Y') }}</span>
                        @if (!$a->dia_todo)
                            <br>
                            <small class="text-muted">
                                <i class="fa fas fa-clock"></i>
                                {{ $a->intervalo_inicio }} – {{ $a->intervalo_fim }}
                            </small>
                        @else
                            <br>
                            <small class="text-muted">
                                <i class="fa fas fa-calendar-day"></i>
                                Dia todo
                            </small>
                        @endif
                    </td>
                    <td>
                        <span class="label {{ $labelsEstado[$a->estado] ?? 'label-default' }}">
                            {{ __('pontowr2::ponto.intercorrencia.estados.' . $a->estado) }}
                        </span>
                    </td>
                    <td>
                        @if ($a->prioridade === 'URGENTE')
                            <span class="label label-danger">Urgente</span>
                        @else
                            <span class="label label-default">Normal</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($a->estado === 'PENDENTE')
                            <form method="POST"
                                  action="{{ route('ponto.aprovacoes.aprovar', $a->id) }}"
                                  style="display:inline-block;">
                                @csrf
                                <button type="submit"
                                        class="btn btn-primary btn-xs"
                                        onclick="return confirm('Aprovar esta intercorrência?');">
                                    <i class="fa fas fa-check"></i> Aprovar
                                </button>
                            </form>
                            <form method="POST"
                                  action="{{ route('ponto.aprovacoes.rejeitar', $a->id) }}"
                                  style="display:inline-block;"
                                  onsubmit="var m = prompt('Motivo da rejeição:'); if (!m) return false; this.motivo.value = m;">
                                @csrf
                                <input type="hidden" name="motivo" value="">
                                <button type="submit" class="btn btn-default btn-xs">
                                    <i class="fa fas fa-times"></i> Rejeitar
                                </button>
                            </form>
                        @else
                            <small class="text-muted">—</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding:24px;">
                        <i class="fa fas fa-check-circle" style="font-size:24px; color:#5cb85c;"></i><br>
                        Nenhuma intercorrência encontrada.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
