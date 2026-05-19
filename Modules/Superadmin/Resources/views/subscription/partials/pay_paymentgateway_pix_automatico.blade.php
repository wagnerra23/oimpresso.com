{{--
    ADR 0170 Onda 5 SIMPLIFICADA — partial PIX Automático BCB.

    Submete pra confirm() com gateway='paymentgateway_pix_automatico'.
    confirm() detecta a chave e delega pra confirm_paymentgateway() — que
    cria Subscription waiting + emite Cobranca via PaymentGatewayContract.
    Mandato BCB precisa autorização do tenant no app banco em até 7 dias.
--}}
<div class="col-md-12">
    <form action="{{ action([\Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm'], [$package->id]) }}" method="POST">
        {{ csrf_field() }}
        <input type="hidden" name="gateway" value="{{ $k }}">

        <button type="submit" class="btn btn-success">
            <i class="fas fa-qrcode"></i> {{ $v }}
        </button>
    </form>

    <p class="help-block">
        <i class="fas fa-info-circle"></i>
        <strong>PIX Automático BCB (recomendado):</strong>
        autoriza o mandato uma vez no app do seu banco, depois a mensalidade é
        debitada automaticamente todo mês. Sem boleto, sem cartão expirando.
    </p>
    <p class="help-block">
        Resolução BCB 380/2024. CNPJ recebedor precisa estar homologado.
        Após clicar, você terá 7 dias pra autorizar o mandato no app do banco.
    </p>
</div>
