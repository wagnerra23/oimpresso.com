<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\PortalToken;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Services\AparelhosDoPortal;
use Modules\Fisio\Services\CheckinDoPortal;
use Modules\Fisio\Services\Consentimentos;
use Modules\Fisio\Services\DiaLocal;
use Modules\Fisio\Services\JogoService;
use Modules\Fisio\Services\PortalTokenService;

/**
 * Portal público do paciente — `/fisio/p/{token}` (US-FISIO-007a/007b; ADR 0437 §4 a §7).
 *
 * Sem conta no sistema e sem middleware de admin: quem abre é o paciente, com o link que a
 * clínica gerou e o PIN que a fisioterapeuta passou. Etapas, nesta ordem e sem atalho:
 *   1. `pin`           — link válido; nenhum dado clínico sai antes do PIN (UC-FPP-01). Aparelho
 *                        lembrado por 30 dias pula o PIN (UC-FPP-22/23);
 *   2. `consentimento` — PIN certo, mas sem o consentimento de teleatendimento na versão
 *                        vigente: nada do programa sai (UC-FPP-05);
 *   3. `hoje`          — o programa ativo do PRÓPRIO paciente, da PRÓPRIA clínica, o estado do
 *                        jogo e o "Como foi hoje". Com dor ≥ limiar ou sinal de alerta no dia,
 *                        `modo=pausa`: só a instrução escrita pelo profissional (UC-FPP-19).
 *
 * Uma página Inertia só (`Fisio/Public/Portal`): as telas Entrada, Hoje e Check-in são
 * componentes dela, escolhidos pela `etapa` que o SERVIDOR decide.
 *
 * Toda falha de link (malformado, adulterado, expirado, revogado, substituído, paciente
 * bloqueado, clínica sem o pacote) é o MESMO estado vazio `link_invalido` (UC-FPP-02/07).
 *
 * Sessão do paciente: o PIN aceito fica na sessão amarrado ao ID da linha do token
 * (`fisio_portal.<id>`). Link novo ou revogado = linha nova ou morta, então a sessão antiga
 * não vale para ele. O business nunca vem de parâmetro: vem assinado no token e cada leitura
 * filtra por ele (ADR 0093).
 */
class PortalController extends Controller
{
    public const CANAL = 'portal';

    public function __construct(
        private readonly PortalTokenService $tokens,
        private readonly Consentimentos $consentimentos,
        private readonly AparelhosDoPortal $aparelhos,
        private readonly CheckinDoPortal $checkins,
        private readonly JogoService $jogo,
    ) {
    }

    public function show(Request $request, string $token): Response|SymfonyResponse
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
                'lembrar_dias' => $this->aparelhos->dias(),
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

        $deHoje = $this->checkins->checkinDeHoje($paciente);
        $modo = $this->checkins->modo($paciente, $deHoje);
        $comum = $base + [
            'etapa' => 'hoje',
            'modo' => $modo,
            'jogo' => $this->jogo->estado($paciente),
            'consentimentos' => $this->consentimentos->situacao($paciente),
            'registrado_hoje' => $deHoje !== null,
            // O portal só guarda check-in na fila offline quando o aparelho é lembrado (UC-FPP-31).
            'guardar_offline' => $this->lembrado($request, $linha),
        ];

        if ($modo === CheckinDoPortal::MODO_PAUSA) {
            // Dia de pausa: nada de "faça mais" — só a palavra do profissional (Anvisa, UC-FPP-19).
            return $this->offline($request, $linha, Inertia::render('Fisio/Public/Portal', $comum + ['instrucao' => $this->checkins->instrucao($paciente)]));
        }

        $programa = $this->checkins->programaAtivo($paciente);

        return $this->offline($request, $linha, Inertia::render('Fisio/Public/Portal', $comum + [
            'hoje' => ['itens' => $this->itensDeHoje($programa)],
            'crise' => ['itens' => $this->checkins->versaoDeCrise($programa)],
            'relato' => $deHoje !== null ? $this->relatoDeHoje($deHoje) : null,
            'red_flags' => array_values(array_map('strval', (array) config('fisio.red_flags', []))),
        ]));
    }

    /**
     * Manifesto do app instalável (etapa 02 §3.6 · UC-FPP-26). Um por link: o app instalado abre
     * direto no programa do paciente. Não consulta o banco e não diz o nome da clínica — o
     * manifesto é público e vem antes do PIN; o nome aparece dentro do app depois da entrada.
     */
    /**
     * Entrada do app instalado pela Google Play (UC-FPP-29). Sem token não há paciente: a tela só
     * orienta a abrir o link da clínica. Não consulta o banco.
     */
    public function app(): Response
    {
        return Inertia::render('Fisio/Public/Portal', ['erro' => 'sem_link', 'etapa' => null, 'token' => '']);
    }

    public function manifest(string $token): JsonResponse
    {
        $inicio = '/fisio/p/'.$token;

        return response()->json([
            'id' => $inicio,
            'name' => 'Meu programa de exercícios',
            'short_name' => 'Exercícios',
            'description' => 'Seus exercícios do dia e o registro de como foi, enviados pela sua fisioterapeuta.',
            'start_url' => $inicio,
            'scope' => '/fisio/p/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'lang' => 'pt-BR',
            // Roxo 295 do DS (tokens primary oklch(0.55 0.15 295)) e fundo background oklch(1 0 0).
            'theme_color' => '#795bbf',
            'background_color' => '#ffffff',
            'icons' => [
                ['src' => '/img/fisio-portal-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/img/fisio-portal-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/img/fisio-portal-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /**
     * Autoriza o service worker a guardar esta tela para abrir sem rede (UC-FPP-27) — só em
     * aparelho LEMBRADO. Com o PIN só na sessão, o programa não fica no aparelho: senão a cópia
     * guardada abriria sem PIN depois que a sessão acabasse. Sem este cabeçalho o SW apaga a cópia.
     */
    private function offline(Request $request, PortalToken $linha, Response $tela): SymfonyResponse
    {
        $resposta = $tela->toResponse($request);
        $resposta->headers->set('X-Fisio-Offline', $this->lembrado($request, $linha) ? 'guardar' : 'nao-guardar');

        return $resposta;
    }

    /** Aparelho lembrado deste token (cookie) — só nele o programa e a fila ficam no aparelho. */
    private function lembrado(Request $request, PortalToken $linha): bool
    {
        return $this->aparelhos->reconhece($linha, $request->cookie($this->aparelhos->nomeCookie($linha)));
    }

    /**
     * 200 aceito · 422 `pin_invalido` + restantes · 423 `bloqueado` · 404 `link_invalido`.
     * Com `lembrar=true`, o aparelho fica lembrado por 30 dias (cookie httpOnly cifrado).
     */
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

        $resposta = response()->json(['ok' => true]);
        if ($request->boolean('lembrar')) {
            $resposta->withCookie(cookie(
                $this->aparelhos->nomeCookie($linha),
                $this->aparelhos->lembrar($linha),
                $this->aparelhos->dias() * 24 * 60,
                '/fisio/p',
                null,
                null,   // secure: segue config('session.secure')
                true,   // httpOnly
                false,
                'lax',
            ));
        }

        return $resposta;
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
     * "Como foi hoje" (US-FISIO-007b). Portões: link, PIN (ou aparelho lembrado) e
     * consentimento de teleatendimento. 201 criou o dia · 200 atualizou (reenvio) · 422 inválido.
     * Em dia de pausa a resposta é SÓ {modo, instrucao, checkin_id} (UC-FPP-19).
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
        $paciente = $this->pacienteDe($linha);
        if (! $this->consentimentos->vigente($paciente, Consentimentos::TELEATENDIMENTO)) {
            return response()->json(['erro' => 'consentimento_pendente'], 403);
        }

        $dados = $request->validate([
            'done_item_ids' => ['present', 'array', 'max:100'],
            'done_item_ids.*' => ['integer'],
            'vezes' => ['nullable', 'array', 'max:100'],
            'vezes.*' => ['integer', 'min:0', 'max:50'],
            'pain' => ['nullable', 'integer', 'between:0,10'],
            'effort' => ['required', 'string', Rule::in(CheckinDoPortal::ESFORCOS)],
            'note' => ['nullable', 'string', 'max:'.CheckinDoPortal::NOTA_MAX],
            'red_flags' => ['nullable', 'array', 'max:20'],
            'red_flags.*' => ['string', Rule::in((array) config('fisio.red_flags', []))],
            'crisis' => ['nullable', 'boolean'],
            'chave' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
            'dia' => ['nullable', 'date_format:Y-m-d'],
            'offline' => ['nullable', 'boolean'],
        ]);

        // Fila offline (UC-FPP-30..32): o check-in guardado no aparelho só vale no dia em que o
        // paciente o fez, e só existe em aparelho lembrado. Nos dois casos nada é gravado.
        if (isset($dados['dia']) && $dados['dia'] !== DiaLocal::de(now())) {
            return response()->json(['erro' => 'dia_passou'], 422);
        }
        if (($dados['offline'] ?? false) && ! $this->lembrado($request, $linha)) {
            return response()->json(['erro' => 'fila_so_aparelho_lembrado'], 403);
        }

        $r = $this->checkins->registrar($paciente, $dados);
        $status = $r['criado'] ? 201 : 200;
        $checkin = $r['checkin'];

        if ($this->checkins->modo($paciente, $checkin) === CheckinDoPortal::MODO_PAUSA) {
            return response()->json([
                'modo' => CheckinDoPortal::MODO_PAUSA,
                'instrucao' => $this->checkins->instrucao($paciente),
                'checkin_id' => (int) $checkin->id,
            ], $status);
        }

        return response()->json([
            'modo' => CheckinDoPortal::MODO_NORMAL,
            'checkin_id' => (int) $checkin->id,
            'xp_ganho' => $r['xp_ganho'],
            'jogo' => $this->jogo->estado($paciente),
        ], $status);
    }

    /** Paciente que o PortalTokenService já resolveu (business do token, não bloqueado). */
    private function pacienteDe(PortalToken $linha): Patient
    {
        $p = $linha->getRelation('patient');
        abort_unless($p instanceof Patient, 404);

        return $p;
    }

    /**
     * PIN aceito nesta sessão, OU aparelho lembrado deste mesmo token (cookie), que então
     * passa a valer para a sessão também.
     */
    private function pinAceito(Request $request, PortalToken $linha): bool
    {
        if ($request->session()->get($this->chaveSessao($linha)) === true) {
            return true;
        }
        if ($this->aparelhos->reconhece($linha, $request->cookie($this->aparelhos->nomeCookie($linha)))) {
            $request->session()->put($this->chaveSessao($linha), true);

            return true;
        }

        return false;
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
     * O relato de HOJE (para o paciente corrigir). Só o dia corrente: nenhuma série de dor
     * sai para o portal (UC-FPP-20) — a chave da dor é `dor`, nunca o nome da coluna.
     *
     * @return array<string, mixed>
     */
    private function relatoDeHoje(Checkin $c): array
    {
        return [
            'feitos' => array_values(array_map('intval', (array) ($c->done_item_ids ?? []))),
            'dor' => $c->pain !== null ? (int) $c->pain : null,
            'esforco' => $c->effort,
            'observacao' => $c->note,
            'crise' => (bool) $c->crisis,
        ];
    }

    /**
     * Itens do programa ativo — só o que o paciente precisa para fazer o exercício: nome, passos,
     * dose e os cuidados da biblioteca. Nada de queixa, marcador de condição, aviso de
     * contraindicação nem série de dor (ADR 0437 §5).
     *
     * @return list<array<string, mixed>>
     */
    private function itensDeHoje(?Program $programa): array
    {
        if ($programa === null) {
            return [];
        }

        return ProgramItem::withoutGlobalScopes() // SUPERADMIN: portal público sem sessão; filtra pelo business do token.
            ->where('business_id', (int) $programa->business_id)
            ->where('program_id', $programa->id)
            ->with(['exercise' => fn ($q) => $q->withoutGlobalScopes()->select(['id', 'name', 'steps', 'safety_notes'])]) // SUPERADMIN: exercício do item já filtrado pelo business acima.
            ->orderBy('position')
            ->get()
            ->map(fn (ProgramItem $i) => [
                'id' => (int) $i->id,
                'nome' => $i->exercise?->name,
                'passos' => $i->exercise?->steps ?? [],
                'cuidados' => $i->exercise?->safety_notes ?? [],
                'sets' => $i->sets !== null ? (int) $i->sets : null,
                'reps' => $i->reps !== null ? (int) $i->reps : null,
                'hold_s' => $i->hold_s !== null ? (int) $i->hold_s : null,
                'times_per_day' => max(1, (int) $i->times_per_day),
                'notes' => $i->notes,
            ])
            ->all();
    }
}
