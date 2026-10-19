<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Whatsapp;

use Illuminate\Support\Facades\Log;
use Modules\Fisio\Contracts\LinkDoPortal;
use Modules\Fisio\Entities\Patient;

/**
 * Implementação padrão do contrato `LinkDoPortal`: delega ao `PortalTokenService` do portal do
 * paciente (US-FISIO-007a), cuja assinatura combinada no README da suíte é
 * `emitir(Patient): array{token, pin}`. A URL pública é `/fisio/p/{token}`.
 *
 * O nome da classe fica em string de propósito: enquanto o 007a não estiver no main a classe não
 * existe, e esta implementação responde null (portal indisponível) em vez de quebrar o job.
 * Quando o 007a entrar, nada aqui muda.
 */
final class LinkDoPortalPorToken implements LinkDoPortal
{
    public const SERVICO = 'Modules\\Fisio\\Services\\PortalTokenService';

    public function emitir(Patient $paciente): ?array
    {
        if (! class_exists(self::SERVICO)) {
            Log::warning('[fisio.whatsapp] portal do paciente indisponível (US-FISIO-007a ausente) — envio não feito', [
                'business_id' => (int) $paciente->business_id,
                'patient_id' => (int) $paciente->id,
            ]);

            return null;
        }

        $emitido = app(self::SERVICO)->emitir($paciente);
        $token = (string) ($emitido['token'] ?? '');
        $pin = (string) ($emitido['pin'] ?? '');
        if ($token === '' || $pin === '') {
            return null;
        }

        return ['url' => url('/fisio/p/'.$token), 'pin' => $pin];
    }
}