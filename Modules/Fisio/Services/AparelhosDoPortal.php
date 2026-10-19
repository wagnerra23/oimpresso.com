<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use Modules\Fisio\Entities\PortalDevice;
use Modules\Fisio\Entities\PortalToken;

/**
 * "Lembrar este aparelho por 30 dias" no portal do paciente (US-FISIO-007b; etapa 02 §3.6 e
 * T-07a). Sem isso o paciente digita o PIN todo dia — o atrito nº 1 da ADR 0437 §2.
 *
 * O cookie (httpOnly, cifrado e assinado pelo EncryptCookies do grupo `web`) carrega um segredo
 * aleatório de 256 bits; o banco guarda só o sha256. Vale só para a MESMA linha de token que o
 * criou (cookie de outro link não abre este) e só até `remembered_until`. Link revogado pelo
 * profissional ou regenerado revoga os aparelhos do paciente (PortalTokenService::revogarAtivos).
 * Em qualquer desses casos o PIN volta a ser pedido.
 */
final class AparelhosDoPortal
{
    public function nomeCookie(PortalToken $linha): string
    {
        return 'fisio_aparelho_'.$linha->id;
    }

    public function dias(): int
    {
        return max(1, (int) config('fisio.portal.lembrar_aparelho_dias', 30));
    }

    /** Lembra este aparelho para o token. Devolve o segredo em claro (vai SÓ no cookie). */
    public function lembrar(PortalToken $linha): string
    {
        $segredo = bin2hex(random_bytes(32));

        PortalDevice::withoutGlobalScopes() // SUPERADMIN: rota pública; grava no business do PRÓPRIO token.
            ->create([
                'business_id' => (int) $linha->business_id,
                'token_id' => (int) $linha->id,
                'device_hash' => hash('sha256', $segredo),
                'remembered_until' => now()->addDays($this->dias()),
                'revoked_at' => null,
            ]);

        return $segredo;
    }

    /** O valor do cookie é de um aparelho lembrado, vivo, deste token e deste business? */
    public function reconhece(PortalToken $linha, mixed $segredo): bool
    {
        if (! is_string($segredo) || preg_match('/^[a-f0-9]{64}$/', $segredo) !== 1) {
            return false;
        }

        return PortalDevice::withoutGlobalScopes() // SUPERADMIN: rota pública; filtra pelo business e pela linha do token.
            ->where('business_id', (int) $linha->business_id)
            ->where('token_id', (int) $linha->id)
            ->where('device_hash', hash('sha256', $segredo))
            ->whereNull('revoked_at')
            ->where('remembered_until', '>', now())
            ->exists();
    }

    /** Revoga todos os aparelhos lembrados do paciente (link revogado ou regenerado). */
    public function revogarDoPaciente(int $biz, int $pacienteId): int
    {
        $tokens = PortalToken::withoutGlobalScopes() // SUPERADMIN: tokens do PRÓPRIO paciente, no business dele.
            ->where('business_id', $biz)
            ->where('patient_id', $pacienteId)
            ->pluck('id');

        return PortalDevice::withoutGlobalScopes() // SUPERADMIN: só aparelhos daqueles tokens, no mesmo business.
            ->where('business_id', $biz)
            ->whereIn('token_id', $tokens)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
