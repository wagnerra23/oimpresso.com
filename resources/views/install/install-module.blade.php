@extends('layouts.install')
@section('title', 'Installation')

{{--
    Tela simplificada (refatorada por Wagner em 2026-04-25):
      - REMOVIDA validação Envato/Codecanyon/UltimateFosters
      - REMOVIDOS campos license_code, login_username, ENVATO_EMAIL
      - REMOVIDOS links externos para ultimatefosters.com / envato

    Razão: Wagner já comprou o produto (UltimatePOS Codecanyon). Validação
    contínua não tem mais necessidade e abre vetor de supply-chain attack
    (qualquer mudança no servidor remoto vira código executado na instância).

    Os InstallController upstream ainda chamam request()->validate() exigindo
    license_code + login_username não-vazio. Por isso o form auto-submita
    valores fixos abaixo (não há call HTTP externa pra validar — checagem
    acontece só por presença string).
--}}

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <br/><br/>
            <div class="box box-primary active">
                <div class="box-body">

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {!! session('error') !!}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <h2>Instalar <code>{{ $module_display_name ?? 'módulo' }}</code></h2>
                    <p class="text-muted">
                        Confirma a instalação do módulo. Validação de license externa foi removida — produto já licenciado.
                    </p>
                    <hr/>

                    <form class="form" id="details_form" method="post" action="{{ $action_url }}">
                        {{ csrf_field() }}

                        {{-- Campos exigidos pelos InstallController upstream — auto-preenchidos --}}
                        <input type="hidden" name="license_code" value="OIMPRESSO-LICENSED">
                        <input type="hidden" name="login_username" value="{{ auth()->user()->username ?? 'admin' }}">
                        <input type="hidden" name="email" value="{{ auth()->user()->email ?? '' }}">

                        <div class="col-md-12">
                            <button type="submit" id="install_button" class="btn btn-primary">
                                Instalar
                            </button>
                            <a href="{{ action([\App\Http\Controllers\Install\ModulesController::class, 'index']) }}"
                               class="btn btn-default">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function () {
            $('form#details_form').submit(function () {
                $('button#install_button').attr('disabled', true).text('Instalando...');
            });
        });
    </script>
@endsection
