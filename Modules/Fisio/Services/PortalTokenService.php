<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\PortalToken;

/**
 * Link + PIN do portal do paciente (US-FISIO-007a; ADR 0437 §4 e §7). Precedente: link de
 * aprovação da OficinaAuto (token HMAC + PIN + lockout). Diferença: o estado mora em
 * `fisio_portal_tokens`, não em cache, e o banco guarda SÓ hashes.
 *
 * TOKEN = `<payload>.<assinatura>`:
 *  - payload    = base64url(json{b: business_id, p: patient_id, n: nonce de 128 bits});
 *  - assinatura = HMAC-SHA256 em hex (64) do payload, com chave derivada de `app.key`.
 * O business viaja ASSINADO no token, nunca como parâmetro da rota. Validar exige as três
 * coisas: assinatura certa, linha com `token_hash = sha256(token)` do MESMO business e
 * paciente, e linha viva (não revogada, não expirada). Toda falha devolve `null` — quem chama
 * mostra o mesmo estado vazio, sem dizer qual condição quebrou (UC-FPP-02).
 *
 * PIN: 4 dígitos por CSPRNG (`random_int`), guardado com `Hash::make`. 5 erros travam por 30
 * min (`config('fisio.portal.*')`); a trava acaba sozinha e o contador zera no acerto.
 *
 * Rota pública, sem sessão de usuário: o escopo global de empresa não filtra sem auth, então
 * TODA leitura aqui filtra o business explicitamente (ADR 0093). O `withoutGlobalScopes()` é
 * para o caso de um usuário logado de outra empresa abrir o link no mesmo navegador: o escopo
 * da sessão dele não pode decidir o que o token resolve.
 */
class PortalTokenService
{
    public const PIN_OK = 'ok';

    public const PIN_INVALIDO = 'pin_invalido';

    public const PIN_BLOQUEADO = 'bloqueado';

    /**
     * Emite um link novo para o paciente e revoga os anteriores (um link vivo por paciente).
     * O token e o PIN em claro só existem no retorno: quem chama mostra uma vez e esquece.
     *
     * @return array{token: string, pin: string, expires_at: \Illuminate\Support\Carbon}
     *
     * @throws DomainException paciente bloqueado não recebe acesso ao portal (UC-FPS-07)
     */
    public function emitir(Patient $paciente): array
    {
        if ($paciente->estaBloqueado()) {
            throw new DomainException('Paciente bloqueado não recebe acesso ao portal.');
        }

        $biz = (int) $paciente->business_id;
        $token = $this->montarToken($biz, (int) $paciente->id);
        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expira = now()->addDays(max(1, (int) config('fisio.portal.validade_dias', 30)));

        DB::transaction(function () use ($paciente, $biz, $token, $pin, $expira): void {
            $this->revogarAtivos($biz, (int) $paciente->id);

            PortalToken::withoutGlobalScopes() // SUPERADMIN: grava no business do PRÓPRIO paciente, não no da sessão.
                ->create([
                    'business_id' => $biz,
                    'patient_id' => (int) $paciente->id,
                    'token_hash' => hash('sha256', $token),
                    'pin_hash' => Hash::make($pin),
                    'attempts' => 0,
                    'locked_until' => null,
                    'expires_at' => $expira,
                    'revoked_at' => null,
                ]);
        });

        return ['token' => $token, 'pin' => $pin, 'expires_at' => $expira];
    }

    /** Paciente dono de um token válido, ou null (mesmo null para toda falha). */
    public function validar(string $token): ?Patient
    {
        $p = $this->resolver($token)?->getRelation('patient');

        return $p instanceof Patient ? $p : null;
    }

    /**
     * Linha viva do token, com o paciente carregado, ou null. Paciente bloqueado, de outro
     * business, ou clínica sem o pacote `fisio_module` também dão null.
     */
    public function resolver(string $token): ?PortalToken
    {
        $dados = $this->lerToken($token);
        if ($dados === null) {
            return null;
        }
        [$biz, $pacienteId] = $dados;

        $linha = PortalToken::withoutGlobalScopes() // SUPERADMIN: rota pública; o business vem assinado no token e filtra abaixo.
            ->where('business_id', $biz)
            ->where('patient_id', $pacienteId)
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        if ($linha === null) {
            return null;
        }

        $paciente = Patient::withoutGlobalScopes() // SUPERADMIN: rota pública; filtra pelo business do token.
            ->where('business_id', $biz)
            ->whereKey($pacienteId)
            ->whereNull('blocked_at')
            ->first();
        if ($paciente === null || ! app(FisioAcesso::class)->pacoteAtivo($biz)) {
            return null;
        }

        $linha->setRelation('patient', $paciente);

        return $linha;
    }

    /** Revoga o link vivo do paciente (o profissional tira o acesso; UC-FPS-34). */
    public function revogar(Patient $paciente): void
    {
        $this->revogarAtivos((int) $paciente->business_id, (int) $paciente->id);
    }

    /**
     * Confere o PIN contando tentativa. Formato errado (não são 4 dígitos) também conta.
     *
     * @return array{resultado: string, tentativas_restantes: int}
     */
    public function conferirPin(PortalToken $linha, string $pin): array
    {
        $max = $this->maxTentativas();

        return DB::transaction(function () use ($linha, $pin, $max): array {
            $atual = PortalToken::withoutGlobalScopes() // SUPERADMIN: mesma linha já resolvida pelo token (business conferido).
                ->whereKey($linha->id)
                ->where('business_id', $linha->business_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($atual->locked_until !== null && $atual->locked_until->isFuture()) {
                return ['resultado' => self::PIN_BLOQUEADO, 'tentativas_restantes' => 0];
            }
            if ($atual->locked_until !== null) {
                // A trava acabou: recomeça a contagem.
                $atual->locked_until = null;
                $atual->attempts = 0;
            }

            if (preg_match('/^\d{4}$/', $pin) === 1 && Hash::check($pin, $atual->pin_hash)) {
                $atual->attempts = 0;
                $atual->save();

                return ['resultado' => self::PIN_OK, 'tentativas_restantes' => $max];
            }

            $atual->attempts = $atual->attempts + 1;
            if ($atual->attempts >= $max) {
                $atual->locked_until = now()->addMinutes((int) config('fisio.portal.lockout_minutos', 30));
                $atual->save();

                return ['resultado' => self::PIN_BLOQUEADO, 'tentativas_restantes' => 0];
            }
            $atual->save();

            return ['resultado' => self::PIN_INVALIDO, 'tentativas_restantes' => $max - $atual->attempts];
        });
    }

    /** Quantas tentativas restam agora (0 se travado; o máximo se a trava já acabou). */
    public function tentativasRestantes(PortalToken $linha): int
    {
        if ($linha->locked_until !== null) {
            return $linha->locked_until->isFuture() ? 0 : $this->maxTentativas();
        }

        return max(0, $this->maxTentativas() - (int) $linha->attempts);
    }

    private function maxTentativas(): int
    {
        return max(1, (int) config('fisio.portal.max_tentativas', 5));
    }

    private function revogarAtivos(int $biz, int $pacienteId): void
    {
        // Link revogado ou regenerado derruba também os aparelhos lembrados (US-FISIO-007b, T-07a):
        // o PIN volta a ser pedido.
        app(AparelhosDoPortal::class)->revogarDoPaciente($biz, $pacienteId);

        PortalToken::withoutGlobalScopes() // SUPERADMIN: revoga só os links do PRÓPRIO paciente, no business dele.
            ->where('business_id', $biz)
            ->where('patient_id', $pacienteId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function montarToken(int $biz, int $pacienteId): string
    {
        $payload = $this->b64(json_encode(['b' => $biz, 'p' => $pacienteId, 'n' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->assinar($payload);
    }

    /** @return array{0: int, 1: int}|null [business_id, patient_id] de um token bem assinado */
    private function lerToken(string $token): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 2 || $partes[0] === '' || ! preg_match('/^[a-f0-9]{64}$/', $partes[1])) {
            return null;
        }
        if (! hash_equals($this->assinar($partes[0]), $partes[1])) {
            return null;
        }
        $json = base64_decode(strtr($partes[0], '-_', '+/'), true);
        $dados = $json === false ? null : json_decode($json, true);
        if (! is_array($dados) || ! is_int($dados['b'] ?? null) || ! is_int($dados['p'] ?? null)) {
            return null;
        }

        return [$dados['b'], $dados['p']];
    }

    private function assinar(string $payload): string
    {
        return hash_hmac('sha256', 'fisio-portal|'.$payload, (string) config('app.key'));
    }

    private function b64(string $bruto): string
    {
        return rtrim(strtr(base64_encode($bruto), '+/', '-_'), '=');
    }
}