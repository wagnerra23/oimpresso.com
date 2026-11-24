<?php

use Illuminate\Database\Migrations\Migration;
use Modules\PaymentGateway\Services\CopiaCredencialLegadaRb;

/**
 * Decisão b1 (2026-11-23, auditoria RecurringBilling 2026-11-19): o dono das credenciais de
 * gateway é o PaymentGateway (ADR 0170). Antes de o RecurringBilling parar de ler
 * `rb_boleto_credentials`, as credenciais que só existem lá passam a existir em
 * `payment_gateway_credentials`.
 *
 * Toda a regra mora em `CopiaCredencialLegadaRb` (idempotente, decifra os campos cifrados do RB
 * sem expor o valor, cria SEM ligação a conta para não mudar quem emite). Rodar de novo não muda
 * nada. Esta migration só CRIA o que falta: linha que já existe no PG não é tocada (o deploy roda
 * `migrate` sozinho; reparar credencial viva é `paymentgateway:migrate-credentials --apply`).
 *
 * `down()` não apaga: não há como distinguir a linha copiada de uma que o usuário cadastrou depois
 * no wizard, e apagar credencial de gateway é perda de dado. `rb_boleto_credentials` não é tocada.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CopiaCredencialLegadaRb())->executar(null, true, repararExistentes: false);
    }

    public function down(): void
    {
        // Intencionalmente vazio — ver docblock.
    }
};
