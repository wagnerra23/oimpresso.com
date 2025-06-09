<div class="pos-tab-content active">
    <div class="row">
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('name',__('business.business_name') . ':*') !!}
                {!! Form::text('name', $business->name, ['class' => 'form-control', 'required',
                'placeholder' => __('business.business_name')]); !!}
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('razao_social',__('business.business_razao') . ':*') !!}
                {!! Form::text('razao_social', $business->razao_social, ['class' => 'form-control', 'required',
                'placeholder' => __('business.business_razao')]); !!}
                @if($errors->has('razao_social'))
                <span class="text-danger">{{ $errors->first('razao_social') }}</span>
                @endif

            </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('start_date', __('business.start_date') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-calendar"></i>
                    </span>
                    
                    {!! Form::text('start_date', @format_date($business->start_date), ['class' => 'form-control start-date-picker','placeholder' => __('business.start_date'), 'readonly']); !!}
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('default_profit_percent', __('business.default_profit_percent') . ':*') !!} @show_tooltip(__('tooltip.default_profit_percent'))
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-plus-circle"></i>
                    </span>
                    {!! Form::text('default_profit_percent', @num_format($business->default_profit_percent), ['class' => 'form-control input_number']); !!}
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('currency_id', __('business.currency') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fas fa-money-bill-alt"></i>
                    </span>
                    {!! Form::select('currency_id', $currencies, $business->currency_id, ['class' => 'form-control select2','placeholder' => __('business.currency'), 'required']); !!}
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('currency_symbol_placement', __('lang_v1.currency_symbol_placement') . ':') !!}
                {!! Form::select('currency_symbol_placement', ['before' => __('lang_v1.before_amount'), 'after' => __('lang_v1.after_amount')], $business->currency_symbol_placement, ['class' => 'form-control select2', 'required']); !!}
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('time_zone', __('business.time_zone') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fas fa-clock"></i>
                    </span>
                    {!! Form::select('time_zone', $timezone_list, $business->time_zone, ['class' => 'form-control select2', 'required']); !!}
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('business_logo', __('business.upload_logo') . ':') !!}
                    {!! Form::file('business_logo', ['accept' => 'image/*']); !!}
                    <p class="help-block"><i> @lang('business.logo_help')</i></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('fy_start_month', __('business.fy_start_month') . ':') !!} @show_tooltip(__('tooltip.fy_start_month'))
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-calendar"></i>
                    </span>
                    {!! Form::select('fy_start_month', $months, $business->fy_start_month, ['class' => 'form-control select2', 'required']); !!}
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('accounting_method', __('business.accounting_method') . ':*') !!}
                @show_tooltip(__('tooltip.accounting_method'))
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-calculator"></i>
                    </span>
                    {!! Form::select('accounting_method', $accounting_methods, $business->accounting_method, ['class' => 'form-control select2', 'required']); !!}
                </div>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('transaction_edit_days', __('business.transaction_edit_days') . ':*') !!}
                @show_tooltip(__('tooltip.transaction_edit_days'))
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-edit"></i>
                    </span>
                    {!! Form::number('transaction_edit_days', $business->transaction_edit_days, ['class' => 'form-control','placeholder' => __('business.transaction_edit_days'), 'required']); !!}
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('date_format', __('lang_v1.date_format') . ':*') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fa fa-calendar"></i>
                    </span>
                    {!! Form::select('date_format', $date_formats, $business->date_format, ['class' => 'form-control select2', 'required']); !!}
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('time_format', __('lang_v1.time_format') . ':*') !!}
                <div class="input-group">
                    <span class="input-group-addon">
                        <i class="fas fa-clock"></i>
                    </span>
                    {!! Form::select('time_format', [12 => __('lang_v1.12_hour'), 24 => __('lang_v1.24_hour')], $business->time_format, ['class' => 'form-control select2', 'required']); !!}
                </div>
            </div>
        </div>



        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('cnpj', 'CNPJ' . ':*') !!}
                {!! Form::text('cnpj', $business->cnpj, ['class' => 'form-control', 'required', 'data-mask="00.000.000/0000-00"', 
                'placeholder' => 'CNPJ']); !!}
    </div>
        </div>

        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('ie', 'IE' . ':*') !!}
                {!! Form::text('ie', $business->ie, ['class' => 'form-control', 'required',
                'placeholder' => 'IE']); !!}
            </div>
        </div>

        <div class="clearfix"></div>
        <div class="col-sm-4">
            <div class="form-group">
                <label for="certificado">Certificado:</label>
                <input name="certificado" type="file" id="certificado">
                <p class="help-block"><i>O Certificado anterior (se existir) será substituído</i></p>
            </div>
        </div>

        @if($infoCertificado != null && $infoCertificado != -1)
        <h5>Serial: <strong>{{$infoCertificado['serial']}}</strong></h5>
        <h5>Expiração: <strong>{{$infoCertificado['expiracao']}}</strong></h5>
        <h5>ID: <strong>{{$infoCertificado['id']}}</strong></h5>
        @endif

        @if($infoCertificado == -1)
        <h5 style="color: #ff0000">Erro na leitura do certificado, verifique a senha e outros dados, e realize o upload novamente!!</h5>
        @endif


        <div class="clearfix"></div>

        <div class="col-sm-2">
            <div class="form-group">
                {!! Form::label('senha_certificado', 'Senha' . ':*') !!}
                {!! Form::text('senha_certificado', '', ['class' => 'form-control',
                'placeholder' => 'Senha']); !!}
            </div>
        </div>

        <div class="clearfix"></div>

        <div class="col-sm-5">
            <div class="form-group">
                {!! Form::label('rua', 'Rua' . ':*') !!}
                {!! Form::text('rua', $business->rua, ['class' => 'form-control', 'required',
                'placeholder' => 'Rua']); !!}
            </div>
        </div>

        <div class="col-sm-2">
            <div class="form-group">
                {!! Form::label('numero', 'Número' . ':*') !!}
                {!! Form::text('numero', $business->numero, ['class' => 'form-control', 'required',
                'placeholder' => 'Número']); !!}
            </div>
        </div>
        <div class="col-md-3 customer_fields">
            <div class="form-group">
              {!! Form::label('cidade_id', 'Cidade:*') !!}
              {!! Form::select('cidade_id', $cities, $business->cidade_id, ['class' => 'form-control select2', 'required']); !!}
          </div>
      </div>

      <div class="clearfix"></div>

      <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('bairro', 'Bairro' . ':*') !!}
            {!! Form::text('bairro', $business->bairro, ['class' => 'form-control', 'required',
            'placeholder' => 'Bairro']); !!}
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('cep', 'CEP' . ':*') !!}
            {!! Form::text('cep', $business->cep, ['class' => 'form-control', 'required', 'data-mask="00000-000"',
            'placeholder' => 'CEP']); !!}
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('telefone', 'Telefone' . ':*') !!}
            {!! Form::text('telefone', $business->telefone, ['class' => 'form-control', 'required', 'data-mask="00 000000000"',
            'placeholder' => 'Telefone']); !!}
        </div>
    </div>


    <div class="col-md-2">
        <div class="form-group">

            {!! Form::label('regime', 'Regime' . ':') !!}
            {!! Form::select('regime', ['1' => 'Simples', '3' => 'Normal'], $business->regime, ['class' => 'form-control select2', 'required']); !!}
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('ultimo_numero_nfe', 'Ultimo Núm. NF-e' . ':*') !!}
            {!! Form::text('ultimo_numero_nfe', $business->ultimo_numero_nfe, ['class' => 'form-control', 'required',
            'placeholder' => 'Ultimo Núm. NF-e']); !!}
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('ultimo_numero_nfce', 'Ultimo Núm. NFC-e' . ':*') !!}
            {!! Form::text('ultimo_numero_nfce', $business->ultimo_numero_nfce, ['class' => 'form-control', 'required',
            'placeholder' => 'Ultimo Núm. NFC-e']); !!}
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('ultimo_numero_cte', 'Ultimo Núm. CT-e' . ':*') !!}
            {!! Form::text('ultimo_numero_cte', $business->ultimo_numero_cte, ['class' => 'form-control', 'required',
            'placeholder' => 'Ultimo Núm. CT-e']); !!}
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('numero_serie_nfe', 'Núm. Série NF-e' . ':*') !!}
            {!! Form::text('numero_serie_nfe', $business->numero_serie_nfe, ['class' => 'form-control', 'required',
            'placeholder' => 'Núm. Série NF-e']); !!}
        </div>
    </div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('numero_serie_nfce', 'Núm. Série NFC-e' . ':*') !!}
            {!! Form::text('numero_serie_nfce', $business->numero_serie_nfce, ['class' => 'form-control', 'required',
            'placeholder' => 'Núm. Série NFC-e']); !!}
        </div>
    </div>

    

    <div class="col-md-4">
        <div class="form-group">

            {!! Form::label('ambiente', 'Ambiente' . ':') !!}
            {!! Form::select('ambiente', ['1' => 'Produção', '2' => 'Homologação'], $business->ambiente, ['class' => 'form-control select2', 'required']); !!}
        </div>
    </div>

    <div class="clearfix"></div>

    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('csc_id', 'CSCID' . ':*') !!}
            {!! Form::text('csc_id', $business->csc_id, ['class' => 'form-control', 'required', 
            'placeholder' => 'CSCID']); !!}
        </div>
    </div>

    <div class="col-sm-5">
        <div class="form-group">
            {!! Form::label('csc', 'CSC' . ':*') !!}
            {!! Form::text('csc', $business->csc, ['class' => 'form-control', 'required', 
            'placeholder' => 'CSC']); !!}
        </div>
    </div>

</div>
</div>