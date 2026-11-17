<?php

declare(strict_types=1);

namespace Modules\Associacoes\Services;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Entities\PortalAssociada;

/**
 * Link + PIN da área da associada no app (ADR 0451 D6 · US-ASSOC-005). Réplica mínima do
 * `Modules\Fisio\Services\PortalTokenService` (US-FISIO-007a), que roda no mesmo app da Play.
 * Não foi extraído para um serviço comum porque o do Fisio está acoplado ao paciente, ao pacote
 * do Fisio e aos aparelhos lembrados; a duplicação está declarada no relato do PR.
 *
 * TOKEN = `<payload>.<assinatura>`:
 *  - payload    = base64url(json{b: business_id, a: associada_id, n: nonce de 128 bits});
 *  - assinatura = HMAC-SHA256 em hex do payload, com chave derivada de `app.key` e prefixo próprio
 *    (`associacoes-portal|`), então um token do Fisio nunca vale aqui nem o contrário.
 * O business viaja ASSINADO no token, nunca como parâmetro da rota. Validar exige assinatura certa,
 * linha com `token_hash = sha256(token)` do MESMO business e associada, linha viva, associada ativa
 * e o pacote da empresa ligado. Toda falha devolve `null`: quem chama mostra o mesmo estado vazio.
 *
 * PIN: 4 dígitos por CSPRNG, guardado com `Hash::make`. 5 erros travam por 30 min; a trava acaba
 * sozinha e o contador zera no acerto.
 */
class PortalAssociadaService
{
    public const PIN_OK = 'ok';

    public const PIN_INVALIDO = 'pin_invalido';

    public const PIN_BLOQUEADO = 'bloqueado';

    public function __construct(private readonly AssociacoesAcesso $acesso) {}

    /**
     * Emite um link novo e revoga os anteriores (um link vivo por associada). Token e PIN em claro
     * só existem no retorno: quem chama mostra uma vez e esquece.
     *
     * @return array{token: string, pin: string, expires_at: \Illuminate\Support\Carbon}
     *
     * @throws DomainException associada desligada não recebe acesso
     */
    public function emitir(Associada $associada, ?int $criadoPor = null): array
    {
        if ($associada->situacao !== 'ativa') {
            throw new DomainException('Associada desligada não recebe acesso à área do app.');
        }

        $biz = (int) $associada->business_id;
        $token = $this->montarToken($biz, (int) $associada->id);
        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $expira = now()->addDays(max(1, (int) config('associacoes.portal.validade_dias', 30)));

        DB::transaction(function () use ($associada, $biz, $token, $pin, $expira, $criadoPor): void {
            $this->revogarAtivos($biz, (int) $associada->id);

            PortalAssociada::withoutGlobalScopes() // SUPERADMIN: grava no business da PRÓPRIA associada, não no da sessão.
                ->create([
                    'business_id' => $biz,
                    'associada_id' => (int) $associada->id,
                    'token_hash' => hash('sha256', $token),
                    'pin_hash' => Hash::make($pin),
                    'tentativas' => 0,
                    'travado_ate' => null,
                    'expires_at' => $expira,
                    'revogado_em' => null,
                    'criado_por' => $criadoPor,
                ]);
        });

        return ['token' => $token, 'pin' => $pin, 'expires_at' => $expira];
    }

    /** Revoga o link vivo da associada (a associação tira o acesso). Não apaga a linha. */
    public function revogar(Associada $associada): int
    {
        return $this->revogarAtivos((int) $associada->business_id, (int) $associada->id);
    }

    /** Linha viva do token, com a associada carregada, ou null (mesmo null para toda falha). */
    public function resolver(string $token): ?PortalAssociada
    {
        $dados = $this->lerToken($token);
        if ($dados === null) {
            return null;
        }
        [$biz, $associadaId] = $dados;

        $linha = PortalAssociada::withoutGlobalScopes() // SUPERADMIN: rota pública; o business vem assinado no token e filtra abaixo.
            ->where('business_id', $biz)
            ->where('associada_id', $associadaId)
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revogado_em')
            ->where('expires_at', '>', now())
            ->first();
        if ($linha === null) {
            return null;
        }

        $associada = Associada::withoutGlobalScopes() // SUPERADMIN: rota pública; filtra pelo business do token.
            ->where('business_id', $biz)
            ->whereKey($associadaId)
            ->where('situacao', 'ativa')
            ->first();
        if ($associada === null || ! $this->acesso->pacoteAtivo($biz)) {
            return null;
        }

        $linha->setRelation('associada', $associada);

        return $linha;
    }

    /**
     * Confere o PIN contando tentativa. Formato errado (não são 4 dígitos) também conta.
     *
     * @return array{resultado: string, tentativas_restantes: int}
     */
    public function conferirPin(PortalAssociada $linha, string $pin): array
    {
        $max = $this->maxTentativas();

        return DB::transaction(function () use ($linha, $pin, $max): array {
            $atual = PortalAssociada::withoutGlobalScopes() // SUPERADMIN: mesma linha já resolvida pelo token (business conferido).
                ->whereKey($linha->id)
                ->where('business_id', $linha->business_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($atual->travado_ate !== null && $atual->travado_ate->isFuture()) {
                return ['resultado' => self::PIN_BLOQUEADO, 'tentativas_restantes' => 0];
            }
            if ($atual->travado_ate !== null) {
                // A trava acabou: recomeça a contagem.
                $atual->travado_ate = null;
                $atual->tentativas = 0;
            }

            if (preg_match('/^\d{4}$/', $pin) === 1 && Hash::check($pin, $atual->pin_hash)) {
                $atual->tentativas = 0;
                $atual->save();

                return ['resultado' => self::PIN_OK, 'tentativas_restantes' => $max];
            }

            $atual->tentativas = $atual->tentativas + 1;
            if ($atual->tentativas >= $max) {
                $atual->travado_ate = now()->addMinutes((int) config('associacoes.portal.lockout_minutos', 30));
                $atual->save();

                return ['resultado' => self::PIN_BLOQUEADO, 'tentativas_restantes' => 0];
            }
            $atual->save();

            return ['resultado' => self::PIN_INVALIDO, 'tentativas_restantes' => $max - $atual->tentativas];
        });
    }

    /** Quantas tentativas restam agora (0 se travado; o máximo se a trava já acabou). */
    public function tentativasRestantes(PortalAssociada $linha): int
    {
        if ($linha->travado_ate !== null) {
            return $linha->travado_ate->isFuture() ? 0 : $this->maxTentativas();
        }

        return max(0, $this->maxTentativas() - (int) $linha->tentativas);
    }

    private function maxTentativas(): int
    {
        return max(1, (int) config('associacoes.portal.max_tentativas', 5));
    }

    private function revogarAtivos(int $biz, int $associadaId): int
    {
        return PortalAssociada::withoutGlobalScopes() // SUPERADMIN: revoga só os links da PRÓPRIA associada, no business dela.
            ->where('business_id', $biz)
            ->where('associada_id', $associadaId)
            ->whereNull('revogado_em')
            ->update(['revogado_em' => now()]);
    }

    private function montarToken(int $biz, int $associadaId): string
    {
        $payload = $this->b64(json_encode(['b' => $biz, 'a' => $associadaId, 'n' => bin2hex(random_bytes(16))], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->assinar($payload);
    }

    /** @return array{0: int, 1: int}|null [business_id, associada_id] de um token bem assinado */
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
        if (! is_array($dados) || ! is_int($dados['b'] ?? null) || ! is_int($dados['a'] ?? null)) {
            return null;
        }

        return [$dados['b'], $dados['a']];
    }

    private function assinar(string $payload): string
    {
        return hash_hmac('sha256', 'associacoes-portal|'.$payload, (string) config('app.key'));
    }

    private function b64(string $bruto): string
    {
        return rtrim(strtr(base64_encode($bruto), '+/', '-_'), '=');
    }
}
