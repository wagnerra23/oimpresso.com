{{-- Aba "Financeiro": régua de cobrança (US-FIN-071). Mora em common_settings[financeiro_regua_cobranca];
     quem lê é Modules\Financeiro\Services\ReguaCobrancaConfig::de(), que aceita os dias como texto "1, 5, 15". --}}
@php($regua = (array) ($common_settings['financeiro_regua_cobranca'] ?? []))
@php($reguaDias = is_array($regua['dias'] ?? null) ? implode(', ', $regua['dias']) : ($regua['dias'] ?? '1, 5, 15'))
@php($reguaDiasAntes = is_array($regua['dias_antes'] ?? null) ? implode(', ', $regua['dias_antes']) : ($regua['dias_antes'] ?? ''))
<div class="pos-tab-content">
    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        {{-- Desmarcado manda 0: sem o hidden, desligar não chegaria ao salvar. --}}
                        <input type="hidden" name="common_settings[financeiro_regua_cobranca][ativo]" value="0">
                        {!! Form::checkbox('common_settings[financeiro_regua_cobranca][ativo]', 1, ! empty($regua['ativo']), ['class' => 'input-icheck', 'id' => 'financeiro_regua_ativo']) !!}
                        Enviar lembrete de cobrança
                    </label>
                    <p class="help-block">E-mail ao cliente quando o título a receber vence e não é pago. Cliente que recusou e-mail não recebe.</p>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('financeiro_regua_dias', 'Dias após o vencimento:') !!}
                {!! Form::text('common_settings[financeiro_regua_cobranca][dias]', $reguaDias, ['class' => 'form-control', 'id' => 'financeiro_regua_dias']) !!}
                <p class="help-block">Separados por vírgula. Na última etapa o vendedor recebe uma tarefa para cobrar.</p>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {{-- US-FIN-033: vazio = não avisa antes. O objeto inteiro vai no POST, então o campo precisa estar aqui. --}}
                {!! Form::label('financeiro_regua_dias_antes', 'Dias antes do vencimento:') !!}
                {!! Form::text('common_settings[financeiro_regua_cobranca][dias_antes]', $reguaDiasAntes, ['class' => 'form-control', 'id' => 'financeiro_regua_dias_antes', 'placeholder' => 'ex.: 3']) !!}
                <p class="help-block">Vazio = não avisa antes. Ex.: 3 avisa três dias antes de vencer.</p>
            </div>
        </div>
    </div>
</div>
