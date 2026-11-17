<?php

declare(strict_types=1);

namespace Modules\Associacoes\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Entities\ConsentimentoAssociada;
use Modules\Associacoes\Entities\PortalAssociada;
use Modules\Associacoes\Services\PortalAssociadaService;

/**
 * Área PÚBLICA da associada no app — `/associacoes/m/{token}` (ADR 0451 D6 · US-ASSOC-005).
 * Mesmo padrão do portal do paciente do Fisio (`Modules\Fisio\Http\Controllers\PortalController`).
 *
 * Sem conta no sistema: quem abre é a associada, com o link que a associação gerou e o PIN que
 * recebeu junto. Etapas, nesta ordem e sem atalho:
 *   1. `pin`   — link válido; NENHUM dado sai antes do PIN (UC-AMB-01);
 *   2. `dados` — "Meus dados" (nome, associação, situação, data de entrada) e os consentimentos,
 *                com revogar (UC-AMB-05). O consentimento `associada` não se revoga aqui: é o que
 *                mantém a filiação, e só a associação o encerra.
 *
 * Toda falha de link (malformado, adulterado, expirado, revogado, substituído, associada
 * desligada, empresa sem o pacote) é o MESMO estado vazio `link_invalido` (UC-AMB-02).
 * O business nunca vem de parâmetro: vem assinado no token e cada leitura filtra por ele (ADR 0093).
 */
class PortalAssociadaController extends Controller
{
    public const PAGINA = 'Associacoes/Public/Membro';

    /** Finalidade que só a associação encerra (D5). */
    public const FINALIDADE_FILIACAO = 'associada';

    public function __construct(private readonly PortalAssociadaService $tokens) {}

    public function show(Request $request, string $token): Response
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return Inertia::render(self::PAGINA, ['erro' => 'link_invalido', 'etapa' => null, 'token' => $token]);
        }

        if (! $this->pinAceito($request, $linha)) {
            return Inertia::render(self::PAGINA, [
                'erro' => null,
                'etapa' => 'pin',
                'token' => $token,
                'tentativas_restantes' => $this->tokens->tentativasRestantes($linha),
            ]);
        }

        /** @var Associada $associada */
        $associada = $linha->getRelation('associada');
        $biz = (int) $linha->business_id;

        $associacao = Associacao::withoutGlobalScopes() // SUPERADMIN: rota pública; filtra pelo business do token.
            ->where('business_id', $biz)
            ->whereKey($associada->associacao_id)
            ->value('nome');
        $nome = DB::table('contacts')
            ->where('business_id', $biz)
            ->where('id', $associada->contact_id)
            ->value('name');

        return Inertia::render(self::PAGINA, [
            'erro' => null,
            'etapa' => 'dados',
            'token' => $token,
            'dados' => [
                'nome' => $nome,
                'associacao' => $associacao,
                'situacao' => $associada->situacao,
                'entrou_em' => $associada->entrou_em?->toDateString(),
            ],
            'consentimentos' => $this->consentimentos($associada),
        ]);
    }

    public function pin(Request $request, string $token): JsonResponse
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return $this->linkInvalido();
        }

        $pin = $request->input('pin');
        $r = $this->tokens->conferirPin($linha, is_scalar($pin) ? (string) $pin : '');

        if ($r['resultado'] === PortalAssociadaService::PIN_BLOQUEADO) {
            return response()->json(['erro' => 'bloqueado', 'tentativas_restantes' => 0], 423);
        }
        if ($r['resultado'] === PortalAssociadaService::PIN_INVALIDO) {
            return response()->json(['erro' => 'pin_invalido', 'tentativas_restantes' => $r['tentativas_restantes']], 422);
        }

        // Troca o ID da sessão ao entrar (anti session fixation) e guarda o acesso SÓ deste token.
        $request->session()->migrate(true);
        $request->session()->put($this->chaveSessao($linha), true);

        return response()->json(['ok' => true]);
    }

    /**
     * Revoga um consentimento da própria associada. Grava `revoked_at`; a linha fica (é prova
     * LGPD, o banco recusa DELETE). 200 revogado · 404 finalidade desconhecida · 422 `associada`.
     *
     * TODO(onda 4): quando o Service de consentimentos da onda 4 (US-ASSOC-003) existir, trocar a
     * escrita direta abaixo por ele, para tela e app gravarem pelo mesmo caminho.
     */
    public function revogarConsentimento(Request $request, string $token, string $finalidade): JsonResponse
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return $this->linkInvalido();
        }
        if (! $this->pinAceito($request, $linha)) {
            return response()->json(['erro' => 'pin_pendente'], 403);
        }
        if (! array_key_exists($finalidade, $this->versoes())) {
            return response()->json(['erro' => 'finalidade_desconhecida'], 404);
        }
        if ($finalidade === self::FINALIDADE_FILIACAO) {
            return response()->json(['erro' => 'so_pela_associacao'], 422);
        }

        /** @var Associada $associada */
        $associada = $linha->getRelation('associada');

        ConsentimentoAssociada::withoutGlobalScopes() // SUPERADMIN: rota pública; só os consentimentos da PRÓPRIA associada, no business do token.
            ->where('business_id', (int) $linha->business_id)
            ->where('associada_id', (int) $associada->id)
            ->where('finalidade', $finalidade)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return response()->json(['consentimentos' => $this->consentimentos($associada)]);
    }

    /** @return list<array{finalidade: string, vigente: bool, concedido_em: ?string, revogavel: bool}> */
    private function consentimentos(Associada $associada): array
    {
        $vivos = ConsentimentoAssociada::withoutGlobalScopes() // SUPERADMIN: rota pública; filtra pelo business da associada.
            ->where('business_id', (int) $associada->business_id)
            ->where('associada_id', (int) $associada->id)
            ->whereNull('revoked_at')
            ->orderByDesc('concedido_em')
            ->get(['finalidade', 'versao', 'concedido_em']);

        $lista = [];
        foreach ($this->versoes() as $finalidade => $versao) {
            $vivo = $vivos->first(fn ($c) => $c->finalidade === $finalidade && (string) $c->versao === (string) $versao);
            $lista[] = [
                'finalidade' => (string) $finalidade,
                'vigente' => $vivo !== null,
                'concedido_em' => $vivo?->concedido_em?->toDateString(),
                'revogavel' => $finalidade !== self::FINALIDADE_FILIACAO,
            ];
        }

        return $lista;
    }

    /** @return array<string, string> */
    private function versoes(): array
    {
        return (array) config('associacoes.consentimento_versoes', []);
    }

    private function pinAceito(Request $request, PortalAssociada $linha): bool
    {
        return $request->session()->get($this->chaveSessao($linha)) === true;
    }

    private function chaveSessao(PortalAssociada $linha): string
    {
        return 'associacoes_portal.'.$linha->id;
    }

    private function linkInvalido(): JsonResponse
    {
        return response()->json(['erro' => 'link_invalido'], 404);
    }
}
