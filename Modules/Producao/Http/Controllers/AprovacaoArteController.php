<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Producao\Entities\AprovacaoArte;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Exceptions\ApontamentoRecusado;
use Modules\Producao\Services\AprovacaoArteService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cliente aprova a arte por link (UC-FLX-02 · US-COMVIS-NEW-004).
 *
 * `solicitar` (logado, `producao.access`): gera o link da prova da OS por item e devolve ao
 * operador. O envio por WhatsApp/e-mail com opt-in LGPD é follow-up — aqui o operador copia o link.
 *
 * `mostrar`/`aprovar`/`recusar` (públicas, sem login, throttle na rota): a página mostra só a prova
 * e o nome da etapa — nenhum dado do cliente. Link inexistente → 404; respondido ou vencido → 410.
 */
class AprovacaoArteController extends Controller
{
    public function __construct(private readonly AprovacaoArteService $service, private readonly ModuleUtil $moduleUtil) {}

    public function solicitar(Request $request, int $id): JsonResponse
    {
        $biz = (int) $request->session()->get('user.business_id');
        $pode = $request->user()?->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pode && $request->user()->can('producao.access'), 403);
        $dados = $request->validate([
            'programacao_id' => ['required', 'integer'],
            'arquivo' => ['required', 'url:http,https', 'max:500'],
        ]);

        try {
            $r = $this->service->solicitar($biz, $id, (int) $dados['programacao_id'], $dados['arquivo'], $request->user());
        } catch (ApontamentoRecusado $e) {
            return response()->json(['codigo' => $e->codigo, 'mensagem' => $e->getMessage()], $e->codigo === ApontamentoRecusado::NAO_ENCONTRADO ? 404 : 422);
        }

        return response()->json(['id' => $r['id'], 'link' => route('producao.arte.mostrar', $r['token']), 'expira_em' => $r['expira_em']->toIso8601String()], 201);
    }

    public function mostrar(string $token): Response
    {
        return $this->pagina(fn () => $this->service->aberto($token), $token);
    }

    public function aprovar(string $token): Response
    {
        return $this->pagina(fn () => $this->service->aprovar($token), $token, 'Arte aprovada. Obrigado! A produção segue para a impressão.');
    }

    public function recusar(Request $request, string $token): Response
    {
        $comentario = (string) $request->validate(['comentario' => ['required', 'string', 'max:2000']])['comentario'];

        return $this->pagina(fn () => $this->service->recusar($token, $comentario), $token, 'Pedido de alteração enviado. A gráfica vai ajustar a arte e mandar uma nova prova.');
    }

    /** @param callable(): AprovacaoArte $acao */
    private function pagina(callable $acao, string $token, ?string $feito = null): Response
    {
        try {
            $pedido = $acao();
        } catch (ApontamentoRecusado $e) {
            abort_if($e->codigo === ApontamentoRecusado::NAO_ENCONTRADO, 404);
            if ($e->codigo !== ApontamentoRecusado::APROVACAO_ENCERRADA) {
                return back()->withErrors(['comentario' => $e->getMessage()]);
            }

            return $this->view(null, $token, $e->getMessage(), 410);
        }

        return $this->view($feito ? null : $pedido, $token, $feito, 200);
    }

    private function view(?AprovacaoArte $pedido, string $token, ?string $mensagem, int $status): Response
    {
        $etapa = $pedido ? Programacao::query()->where('business_id', $pedido->business_id)->find($pedido->programacao_id)?->nome : null;
        $html = view()->file(__DIR__.'/../../Resources/views/aprovacao-arte.blade.php',
            ['pedido' => $pedido, 'etapa' => $etapa, 'token' => $token, 'mensagem' => $mensagem])->render();

        return response($html, $status)->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
