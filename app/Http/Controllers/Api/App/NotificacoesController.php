<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\User;
use App\Utils\Util;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notificações do app das lojas (tela 16). Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §6.1.
 * Lista, e marca como lida (uma ou todas) só as do próprio usuário.
 *
 * Fonte: a tabela `notifications` do Laravel, a mesma do sino da web. O texto sai de
 * Util::parseNotifications, o tradutor que a web e a API do Connector já usam, para o app não
 * manter uma segunda tradução de cada tipo de notificação.
 *
 * Tier 0 (ADR 0093): a notificação é do USUÁRIO (notifiable), e o usuário pertence a um só
 * business. Só aparecem as do usuário do token.
 */
class NotificacoesController extends Controller
{
    private const POR_PAGINA = 20;

    /** Prefixo do tipo da notificação → chip de origem mostrado no app. O primeiro que casar vale. */
    private const ORIGENS = [
        'App\\Notifications\\Recurring' => 'FIN',
        'Modules\\Essentials\\Notifications\\NewTask' => 'TAR',
        'Modules\\Essentials\\' => 'RH',
        'Modules\\Crm\\' => 'CRM',
        'Modules\\Jana\\' => 'IA',
        'Modules\\AssetManagement\\' => 'PAT',
        'Modules\\Woocommerce\\' => 'LOJ',
        'Modules\\Spreadsheet\\' => 'DOC',
    ];

    public function __construct(private Util $util)
    {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $pagina = max((int) $request->query('pagina', 1), 1);

        $linhas = $user->notifications()
            ->orderByDesc('created_at')
            ->offset(($pagina - 1) * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get();

        $temMais = $linhas->count() > self::POR_PAGINA;

        return response()->json([
            'itens' => $linhas->take(self::POR_PAGINA)->map(fn (DatabaseNotification $n) => $this->item($n))->values(),
            'nao_lidas' => $this->naoLidas($user),
            'pagina' => $pagina,
            'tem_mais' => $temMais,
        ]);
    }

    /**
     * POST /api/app/notificacoes/{id}/lida — marca UMA notificação do usuário como lida (§6.1).
     * Idempotente: já lida responde 200 igual. De outro usuário ou inexistente → 404.
     */
    public function marcarLida(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $n = $user->notifications()->where('id', $id)->first();
        if (! $n) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Notificação não encontrada.'], 404);
        }
        $n->markAsRead();

        return response()->json(['nao_lidas' => $this->naoLidas($user)]);
    }

    /** POST /api/app/notificacoes/lidas — marca TODAS as do usuário como lidas (§6.1). */
    public function marcarTodasLidas(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $marcadas = $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['nao_lidas' => $this->naoLidas($user), 'marcadas' => (int) $marcadas]);
    }

    /** Usado também pelo Início (o ponto no sino). */
    public function naoLidas(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    private function item(DatabaseNotification $n): array
    {
        $traduzida = $this->util->parseNotifications(collect([$n]))[0] ?? null;
        $msg = trim(html_entity_decode(strip_tags((string) ($traduzida['msg'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return [
            'id' => (string) $n->id,
            'origem' => $this->origem((string) $n->type),
            'titulo' => $msg !== '' ? $msg : 'Notificação',
            'texto' => null,
            'lida' => $n->read_at !== null,
            'quando' => $n->created_at?->toIso8601String(),
            'destino' => $this->destino($n),
        ];
    }

    private function origem(string $tipo): string
    {
        foreach (self::ORIGENS as $prefixo => $chip) {
            if (str_starts_with($tipo, $prefixo)) {
                return $chip;
            }
        }

        return 'SIS';
    }

    /** Só aponta destino quando a notificação carrega o id da tela do app; senão, null. */
    private function destino(DatabaseNotification $n): array
    {
        $data = (array) $n->data;
        if ($n->type === 'Modules\\Essentials\\Notifications\\NewTaskNotification' && ! empty($data['id'])) {
            return ['tipo' => 'tarefa', 'id' => 'todo:' . (int) $data['id']];
        }

        return ['tipo' => null, 'id' => null];
    }
}
