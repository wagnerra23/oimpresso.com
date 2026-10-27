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
use Modules\Marketplaces\Services\Nuvemshop\NuvemshopOAuth;
use Throwable;

/**
 * Conectar a loja Nuvemshop da empresa (ADR 0443, fatia a). Mesmo contrato do Mercado Livre
 * (ContasController): `state` aleatório na sessão com empresa, usuário e validade; o callback só
 * aceita esse `state`, e a empresa vem SEMPRE da sessão, nunca da URL de retorno.
 */
class ContasNuvemshopController extends Controller
{
    private const CHAVE_STATE = 'marketplaces.oauth_state_nuvemshop';

    private const VALIDADE_STATE_MIN = 10;

    public function __construct(private NuvemshopOAuth $oauth, private ContasVendedor $contas)
    {
    }

    public function conectar(Request $request): RedirectResponse
    {
        if (! $this->oauth->configurado()) {
            return $this->voltar(false, 'A integração com a Nuvemshop ainda não foi ligada no servidor.');
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
        if (! $this->oauth->configurado() || ! $request->filled('code')) {
            return $this->voltar(false, 'A conexão com a Nuvemshop foi cancelada.');
        }

        $canal = Marketplace::query()->where('slug', Marketplace::NUVEMSHOP)->first();
        if ($canal === null) {
            return $this->voltar(false, 'Nuvemshop indisponível no catálogo.');
        }

        try {
            $conta = $this->contas->gravarTokens($businessId, (int) $canal->id, $this->oauth->trocarCodigo((string) $request->query('code')));
        } catch (Throwable $e) {
            Log::warning('marketplaces: falha ao trocar código da Nuvemshop', ['business_id' => $businessId, 'erro' => $e->getMessage()]);

            return $this->voltar(false, 'A Nuvemshop não confirmou a conexão. Tente de novo.');
        }

        return $this->voltar(true, 'Loja Nuvemshop conectada (loja '.$conta->seller_id_external.').');
    }

    private function voltar(bool $ok, string $msg): RedirectResponse
    {
        return redirect('/home')->with('status', ['success' => $ok ? 1 : 0, 'msg' => $msg]);
    }
}
