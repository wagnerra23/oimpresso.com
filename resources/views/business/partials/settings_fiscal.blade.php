<div class="pos-tab-content">
    {{-- Certificado Digital A1 --}}
    <div class="row">
        <div class="col-sm-12">
            <h4 class="tw-font-bold tw-text-gray-700 tw-border-b tw-pb-2 tw-mb-4">
                <i class="fa fa-certificate"></i> Certificado Digital A1
            </h4>
        </div>

        <div class="col-sm-6">
            <div class="form-group">
                <label>Certificado A1 (.pfx) @show_tooltip('Selecione o arquivo .pfx do seu certificado digital A1')</label>
                <input type="file" name="certificado_pfx" id="certificado_pfx" accept=".pfx,.p12" class="form-control">
                @if(!empty($business->certificado))
                    <p class="help-block text-success">
                        <i class="fa fa-check-circle"></i> Certificado carregado
                        <small class="text-muted">(envie novo arquivo para substituir)</small>
                    </p>
                @else
                    <p class="help-block text-muted">Nenhum certificado carregado</p>
                @endif
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('senha_certificado', 'Senha do Certificado:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-lock"></i></span>
                    {!! Form::password('senha_certificado', ['class' => 'form-control', 'id' => 'senha_certificado', 'placeholder' => '●●●●●●●●']) !!}
                </div>
                @if(!empty($business->senha_certificado))
                    <p class="help-block text-success"><i class="fa fa-check-circle"></i> Senha salva</p>
                @endif
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('ambiente', 'Ambiente:') !!}
                {!! Form::select('ambiente', [
                    '2' => 'Homologação (Testes)',
                    '1' => 'Produção',
                ], $business->ambiente ?? '2', ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>
    </div>

    {{-- Regime Tributário e Série --}}
    <div class="row">
        <div class="col-sm-12">
            <h4 class="tw-font-bold tw-text-gray-700 tw-border-b tw-pb-2 tw-mb-4 tw-mt-4">
                <i class="fa fa-file-text"></i> Regime Tributário e Numeração
            </h4>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('regime', 'Regime Tributário:') !!}
                {!! Form::select('regime', [
                    '1' => 'Simples Nacional',
                    '2' => 'Simples Nacional — Excesso',
                    '3' => 'Regime Normal (Lucro Presumido/Real)',
                ], $business->regime ?? '1', ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('numero_serie_nfe', 'Série NF-e:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-hashtag"></i></span>
                    {!! Form::number('numero_serie_nfe', $business->numero_serie_nfe ?? 1, ['class' => 'form-control', 'min' => 1, 'max' => 999]) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('numero_serie_nfce', 'Série NFC-e:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-hashtag"></i></span>
                    {!! Form::number('numero_serie_nfce', $business->numero_serie_nfce ?? 1, ['class' => 'form-control', 'min' => 1, 'max' => 999]) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('numero_serie_cte', 'Série CT-e:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-hashtag"></i></span>
                    {!! Form::number('numero_serie_cte', $business->numero_serie_cte ?? 1, ['class' => 'form-control', 'min' => 1, 'max' => 999]) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- CST / CSOSN Padrão --}}
    <div class="row">
        <div class="col-sm-12">
            <h4 class="tw-font-bold tw-text-gray-700 tw-border-b tw-pb-2 tw-mb-4 tw-mt-4">
                <i class="fa fa-percent"></i> Tributação Padrão
            </h4>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('cst_csosn_padrao', 'CST/CSOSN ICMS Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-tag"></i></span>
                    {!! Form::text('cst_csosn_padrao', $business->cst_csosn_padrao ?? '', ['class' => 'form-control', 'placeholder' => 'Ex: 400, 102']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('perc_icms_padrao', '% ICMS Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon">%</span>
                    {!! Form::text('perc_icms_padrao', $business->perc_icms_padrao ?? '0', ['class' => 'form-control input_number', 'placeholder' => '0.00']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('cst_pis_padrao', 'CST PIS Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-tag"></i></span>
                    {!! Form::text('cst_pis_padrao', $business->cst_pis_padrao ?? '07', ['class' => 'form-control', 'placeholder' => 'Ex: 07']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('perc_pis_padrao', '% PIS Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon">%</span>
                    {!! Form::text('perc_pis_padrao', $business->perc_pis_padrao ?? '0', ['class' => 'form-control input_number', 'placeholder' => '0.00']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('cst_cofins_padrao', 'CST COFINS Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-tag"></i></span>
                    {!! Form::text('cst_cofins_padrao', $business->cst_cofins_padrao ?? '07', ['class' => 'form-control', 'placeholder' => 'Ex: 07']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('perc_cofins_padrao', '% COFINS Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon">%</span>
                    {!! Form::text('perc_cofins_padrao', $business->perc_cofins_padrao ?? '0', ['class' => 'form-control input_number', 'placeholder' => '0.00']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('cst_ipi_padrao', 'CST IPI Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-tag"></i></span>
                    {!! Form::text('cst_ipi_padrao', $business->cst_ipi_padrao ?? '99', ['class' => 'form-control', 'placeholder' => 'Ex: 99']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('perc_ipi_padrao', '% IPI Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon">%</span>
                    {!! Form::text('perc_ipi_padrao', $business->perc_ipi_padrao ?? '0', ['class' => 'form-control input_number', 'placeholder' => '0.00']) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- CFOP e NCM Padrão --}}
    <div class="row">
        <div class="col-sm-12">
            <h4 class="tw-font-bold tw-text-gray-700 tw-border-b tw-pb-2 tw-mb-4 tw-mt-4">
                <i class="fa fa-sitemap"></i> CFOP e NCM Padrão
            </h4>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('cfop_saida_estadual_padrao', 'CFOP Saída Estadual:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-exchange"></i></span>
                    {!! Form::text('cfop_saida_estadual_padrao', $business->cfop_saida_estadual_padrao ?? '5102', ['class' => 'form-control', 'placeholder' => 'Ex: 5102']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('cfop_saida_inter_estadual_padrao', 'CFOP Saída Interestadual:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-exchange"></i></span>
                    {!! Form::text('cfop_saida_inter_estadual_padrao', $business->cfop_saida_inter_estadual_padrao ?? '6102', ['class' => 'form-control', 'placeholder' => 'Ex: 6102']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('ncm_padrao', 'NCM Padrão:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-barcode"></i></span>
                    {!! Form::text('ncm_padrao', $business->ncm_padrao ?? '', ['class' => 'form-control', 'placeholder' => 'Ex: 49019900']) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- NFC-e: CSC --}}
    <div class="row">
        <div class="col-sm-12">
            <h4 class="tw-font-bold tw-text-gray-700 tw-border-b tw-pb-2 tw-mb-4 tw-mt-4">
                <i class="fa fa-shopping-cart"></i> NFC-e — Código de Segurança do Contribuinte (CSC)
            </h4>
        </div>

        <div class="col-sm-8">
            <div class="form-group">
                {!! Form::label('csc', 'CSC (Código de Segurança):') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-key"></i></span>
                    {!! Form::text('csc', $business->csc ?? '', ['class' => 'form-control', 'placeholder' => 'Código CSC fornecido pela SEFAZ']) !!}
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('csc_id', 'CSC ID:') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-hashtag"></i></span>
                    {!! Form::text('csc_id', $business->csc_id ?? '', ['class' => 'form-control', 'placeholder' => 'Ex: 000001']) !!}
                </div>
            </div>
        </div>
    </div>
</div>
