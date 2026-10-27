<?php

declare(strict_types=1);

namespace Modules\Marketplaces\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Marketplaces\Entities\Marketplace;
use Modules\Marketplaces\Services\ContasVendedor;
use Modules\Marketplaces\Services\MercadoLivre\MercadoLivreOAuth;
use Throwable;

/**
 * Conectar a conta do Mercado Livre da empresa (US-MKT-003).
 *
 * `conectar` guarda um `state` aleatório na sessão (com empresa e usuário) e manda para a tela
 * de autorização do ML. `callback` só aceita o `state` dessa mesma sessão, ainda válido e da mesma
 * empresa — sem isso, um link de callback forjado poderia ligar a conta de alguém à empresa errada.
 * A empresa vem SEMPRE da sessão, nunca da URL de retorno.
 */
class ContasController extends Controller
{
    private const CHAVE_STATE = 'marketplaces.oauth_state';

    private const VALIDADE_STATE_MIN = 10;

    public function __construct(private MercadoLivreOAuth $oauth, private ContasVendedor $contas)
    {
    }

    public function conectar(Request $request): RedirectResponse
    {
        if (! $this->oauth->configurado()) {
            return $this->voltar(false, 'O aplicativo do Mercado Livre ainda não foi configurado no servidor.');
        }

        $state = Str::random(40);
        $request->session()->put(self::CHAVE_STATE, [
            'state' => $state,
            'business_id' => (int) $request->session()->get('user.business_id'),
            'user_id' => (int) $request->user()->id,
            'expira_em' => now()->addMinutes(self::VALIDADE_STATE_MIN)->getTimestamp(),
        ]);

        return redirect()->away($this->oauth->urlAutorizacao($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $guardado = $request->session()->pull(self::CHAVE_STATE);
        $businessId = (int) $request->session()->get('user.business_id');

        $valido = is_array($guardado)
            && hash_equals((string) ($guardado['state'] ?? ''), (string) $request->query('state', ''))
            && (int) ($guardado['business_id'] ?? 0) === $businessId
            && (int) ($guardado['user_id'] ?? 0) === (int) $request->user()->id
            && (int) ($guardado['expira_em'] ?? 0) >= now()->getTimestamp();

        if (! $valido) {
            return $this->voltar(false, 'Não foi possível confirmar a conexão. Tente conectar de novo.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->voltar(false, 'A conexão com o Mercado Livre foi cancelada.');
        }

        $ml = Marketplace::query()->where('slug', Marketplace::MERCADO_LIVRE)->where('ativo', true)->first();
        if ($ml === null) {
            return $this->voltar(false, 'Mercado Livre indisponível no catálogo.');
        }

        try {
            $conta = $this->contas->gravarTokens($businessId, (int) $ml->id, $this->oauth->trocarCodigo((string) $request->query('code')));
        } catch (Throwable $e) {
            Log::warning('marketplaces: falha ao trocar código do Mercado Livre', ['business_id' => $businessId, 'erro' => $e->getMessage()]);

            return $this->voltar(false, 'O Mercado Livre não confirmou a conexão. Tente de novo.');
        }

        return $this->voltar(true, 'Conta do Mercado Livre conectada (vendedor '.$conta->seller_id_external.').');
    }

    private function voltar(bool $ok, string $msg): RedirectResponse
    {
        return redirect('/home')->with('status', ['success' => $ok ? 1 : 0, 'msg' => $msg]);
    }
}
