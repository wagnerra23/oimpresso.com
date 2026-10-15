{{-- Aba "App das lojas": ajustes `pos_settings.app_*`. Chaves, padrões e textos vêm de App\Services\AppLojas\AjustesDaEmpresa. --}}
@php($ajustesApp = \App\Services\AppLojas\AjustesDaEmpresa::completar((array) $pos_settings))
<div class="pos-tab-content">
    <div class="row">
        <div class="col-sm-12">
            <p class="help-block">O app das lojas lê estes ajustes ao abrir. Cada um passa a valer quando a função chega ao app.</p>
        </div>
        @foreach (\App\Services\AppLojas\AjustesDaEmpresa::CHAVES as $chave => $c)
            @if ($c['na_tela'] && $c['tipo'] === 'bool')
                <div class="col-sm-6">
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                {{-- Desmarcado manda 0: sem isso, chave com padrão ligado não teria como ser desligada. --}}
                                <input type="hidden" name="pos_settings[{{ $chave }}]" value="0">
                                {!! Form::checkbox('pos_settings['.$chave.']', 1, $ajustesApp[$chave], ['class' => 'input-icheck', 'id' => 'pos_settings_'.$chave]) !!} {{ $c['rotulo'] }}
                            </label>
                            <p class="help-block">{{ $c['ajuda'] }}</p>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
