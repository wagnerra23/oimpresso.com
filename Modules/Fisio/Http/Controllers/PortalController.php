<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\PortalToken;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Services\Consentimentos;
use Modules\Fisio\Services\PortalTokenService;

/**
 * Portal público do paciente — `/fisio/p/{token}` (US-FISIO-007a; ADR 0437 §4, §5 e §7).
 *
 * Sem conta no sistema e sem middleware de admin: quem abre é o paciente, com o link que a
 * clínica gerou e o PIN que a fisioterapeuta passou. Etapas, nesta ordem e sem atalho:
 *   1. `pin`           — link válido; nenhum dado clínico sai antes do PIN (UC-FPP-01);
 *   2. `consentimento` — PIN certo, mas sem o consentimento de teleatendimento na versão
 *                        vigente: nada do programa sai (UC-FPP-05);
 *   3. `hoje`          — o programa ativo do PRÓPRIO paciente, da PRÓPRIA clínica.
 *
 * Toda falha de link (malformado, adulterado, expirado, revogado, substituído, paciente
 * bloqueado, clínica sem o pacote) é o MESMO estado vazio `link_invalido` (UC-FPP-02/07).
 *
 * Sessão do paciente: o PIN aceito fica na sessão amarrado ao ID da linha do token
 * (`fisio_portal.<id>`). Link novo ou revogado = linha nova ou morta, então a sessão antiga
 * não vale para ele. O business nunca vem de parâmetro: vem assinado no token e cada leitura
 * filtra por ele (ADR 0093).
 *
 * O check-in (`POST .../checkin`) já passa pelos mesmos portões aqui; gravar o dia, o
 * telemonitoramento e o jogo é a US-FISIO-007b.
 */
class PortalController extends Controller
{
    public const CANAL = 'portal';

    public function __construct(
        private readonly PortalTokenService $tokens,
        private readonly Consentimentos $consentimentos,
    ) {
    }

    public function show(Request $request, string $token): Response
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return Inertia::render('Fisio/Public/Portal', ['erro' => 'link_invalido', 'etapa' => null, 'token' => $token]);
        }

        if (! $this->pinAceito($request, $linha)) {
            return Inertia::render('Fisio/Public/Portal', [
                'erro' => null,
                'etapa' => 'pin',
                'token' => $token,
                'tentativas_restantes' => $this->tokens->tentativasRestantes($linha),
            ]);
        }

        $paciente = $this->pacienteDe($linha);
        $base = ['erro' => null, 'token' => $token, 'clinica' => $this->nomeClinica($paciente)];

        if (! $this->consentimentos->vigente($paciente, Consentimentos::TELEATENDIMENTO)) {
            return Inertia::render('Fisio/Public/Portal', $base + [
                'etapa' => 'consentimento',
                'consentimento' => [
                    'purpose' => Consentimentos::TELEATENDIMENTO,
                    'version' => $this->consentimentos->versaoVigente(Consentimentos::TELEATENDIMENTO),
                ],
                'opcionais' => $this->opcionais(),
            ]);
        }

        return Inertia::render('Fisio/Public/Portal', $base + [
            'etapa' => 'hoje',
            'hoje' => ['itens' => $this->itensDeHoje($paciente)],
            'consentimentos' => $this->consentimentos->situacao($paciente),
        ]);
    }

    /** 200 aceito · 422 `pin_invalido` + restantes · 423 `bloqueado` · 404 `link_invalido`. */
    public function pin(Request $request, string $token): JsonResponse
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return $this->linkInvalido();
        }

        $pin = $request->input('pin');
        $r = $this->tokens->conferirPin($linha, is_scalar($pin) ? (string) $pin : '');

        if ($r['resultado'] === PortalTokenService::PIN_BLOQUEADO) {
            return response()->json(['erro' => 'bloqueado', 'tentativas_restantes' => 0], 423);
        }
        if ($r['resultado'] === PortalTokenService::PIN_INVALIDO) {
            return response()->json(['erro' => 'pin_invalido', 'tentativas_restantes' => $r['tentativas_restantes']], 422);
        }

        // Troca o ID da sessão ao entrar (anti session fixation) sem trocar o token CSRF da página,
        // e guarda o acesso SÓ deste token.
        $request->session()->migrate(true);
        $request->session()->put($this->chaveSessao($linha), true);

        return response()->json(['ok' => true]);
    }

    /** Aceite de uma finalidade (versão SEMPRE da config). 201 criou · 200 já aceito. */
    public function consentir(Request $request, string $token): JsonResponse
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return $this->linkInvalido();
        }
        if (! $this->pinAceito($request, $linha)) {
            return response()->json(['erro' => 'pin_pendente'], 403);
        }

        $dados = $request->validate([
            'purpose' => ['required', 'string', 'in:'.implode(',', $this->consentimentos->finalidades())],
            'aceito' => ['accepted'],
        ]);

        $r = $this->consentimentos->conceder($this->pacienteDe($linha), (string) $dados['purpose'], $request->ip(), self::CANAL);

        return response()->json([
            'purpose' => $r['consentimento']->purpose,
            'version' => $r['consentimento']->version,
            'granted_at' => $r['consentimento']->granted_at->toIso8601String(),
        ], $r['criado'] ? 201 : 200);
    }

    /** Revogação pelo próprio paciente. A linha fica, com `revoked_at` (ADR 0437 §7). */
    public function revogar(Request $request, string $token, string $purpose): JsonResponse
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return $this->linkInvalido();
        }
        if (! $this->pinAceito($request, $linha)) {
            return response()->json(['erro' => 'pin_pendente'], 403);
        }
        if (! in_array($purpose, $this->consentimentos->finalidades(), true)) {
            return response()->json(['erro' => 'finalidade_invalida'], 422);
        }

        return response()->json(['revogados' => $this->consentimentos->revogar($this->pacienteDe($linha), $purpose)]);
    }

    /**
     * Check-in do dia: aqui só os portões (link, PIN, consentimento de teleatendimento), que a
     * US-FISIO-007a fecha. Gravar o dia é a US-FISIO-007b — até lá, 501 explícito.
     */
    public function checkin(Request $request, string $token): JsonResponse
    {
        $linha = $this->tokens->resolver($token);
        if ($linha === null) {
            return $this->linkInvalido();
        }
        if (! $this->pinAceito($request, $linha)) {
            return response()->json(['erro' => 'pin_pendente'], 403);
        }
        if (! $this->consentimentos->vigente($this->pacienteDe($linha), Consentimentos::TELEATENDIMENTO)) {
            return response()->json(['erro' => 'consentimento_pendente'], 403);
        }

        return response()->json(['erro' => 'checkin_indisponivel'], 501);
    }

    /** Paciente que o PortalTokenService já resolveu (business do token, não bloqueado). */
    private function pacienteDe(PortalToken $linha): Patient
    {
        $p = $linha->getRelation('patient');
        abort_unless($p instanceof Patient, 404);

        return $p;
    }

    private function pinAceito(Request $request, PortalToken $linha): bool
    {
        return $request->session()->get($this->chaveSessao($linha)) === true;
    }

    private function chaveSessao(PortalToken $linha): string
    {
        return 'fisio_portal.'.$linha->id;
    }

    private function linkInvalido(): JsonResponse
    {
        return response()->json(['erro' => 'link_invalido'], 404);
    }

    /** Nome da clínica para o texto do consentimento (ADR 0437 §7: o termo nomeia quem trata). */
    private function nomeClinica(Patient $paciente): ?string
    {
        $nome = \App\Business::query()->whereKey($paciente->business_id)->value('name');

        return is_string($nome) ? $nome : null;
    }

    /** @return list<array{purpose: string, version: string|null}> finalidades opcionais oferecidas junto */
    private function opcionais(): array
    {
        return array_map(
            fn (string $f) => ['purpose' => $f, 'version' => $this->consentimentos->versaoVigente($f)],
            array_values(array_intersect(['whatsapp', 'camera'], $this->consentimentos->finalidades())),
        );
    }

    /**
     * Itens do programa ativo do paciente — só o que ele precisa para fazer o exercício. Nada de
     * queixa, marcador de condição, aviso de contraindicação nem série de dor (ADR 0437 §5).
     *
     * @return list<array<string, mixed>>
     */
    private function itensDeHoje(Patient $paciente): array
    {
        $biz = (int) $paciente->business_id;
        $programa = Program::withoutGlobalScopes() // SUPERADMIN: portal público sem sessão; filtra pelo business do token.
            ->where('business_id', $biz)
            ->where('patient_id', $paciente->id)
            ->where('status', Program::STATUS_ATIVO)
            ->orderByDesc('id')
            ->first();
        if ($programa === null) {
            return [];
        }

        return ProgramItem::withoutGlobalScopes() // SUPERADMIN: portal público sem sessão; filtra pelo business do token.
            ->where('business_id', $biz)
            ->where('program_id', $programa->id)
            ->with(['exercise' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'name', 'steps', 'safety_notes'])]) // SUPERADMIN: exercício do item já filtrado pelo business acima.
            ->orderBy('position')
            ->get()
            ->map(fn (ProgramItem $i) => [
                'id' => (int) $i->id,
                'nome' => $i->exercise?->name,
                'passos' => $i->exercise?->steps ?? [],
                'cuidados' => $i->exercise?->safety_notes ?? [],
                'sets' => $i->sets,
                'reps' => $i->reps,
                'hold_s' => $i->hold_s,
                'times_per_day' => $i->times_per_day,
                'notes' => $i->notes,
            ])
            ->all();
    }
}