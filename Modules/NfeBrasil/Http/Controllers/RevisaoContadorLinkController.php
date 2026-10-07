<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Scopes\ScopeByBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\NfeBrasil\Mail\RevisaoContadorMail;
use Modules\NfeBrasil\Models\NfeContadorLink;
use Modules\NfeBrasil\Models\NfeRevisaoContador;
use Modules\NfeBrasil\Services\Tributacao\ImportRegrasCsvService;
use Modules\NfeBrasil\Services\Tributacao\RevisaoContadorService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Link de revisão do contador, sem conta (playbook Fiscal thread 15b · D-CONTADOR, caminho 1).
 *
 * - A empresa (`nfe.tributacao.manage`) envia o link: rota `signed`, 14 dias, presa a UM business
 *   (o business vai na URL assinada; trocar o número quebra a assinatura → 403).
 * - Ao abrir, o contador recebe um código de 6 dígitos no MESMO e-mail (15 min, uso único, 5 erros
 *   bloqueiam o link). Só com o código a sessão ganha acesso — link encaminhado não aceita por ele.
 * - A página mostra só regras fiscais: de → para, autor, data e origem. Nenhuma consulta daqui lê
 *   cliente, venda, valor de nota ou documento de terceiro.
 *
 * A página é pública: sem login, o escopo global de business não filtra nada. Toda busca é pelo
 * `business_id` do próprio link, explícito.
 */
class RevisaoContadorLinkController extends Controller
{
    private const SESSAO = 'nfe_contador_link';

    public function __construct(private readonly RevisaoContadorService $service) {}

    /** POST /nfe-brasil/tributacao/revisoes/link — a empresa envia o link ao contador. */
    public function enviar(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('nfe.tributacao.manage'), 403);
        $dados = $request->validate([
            'nome'  => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'crc'   => ['nullable', 'string', 'max:40'],
        ]);
        $biz = (int) $request->session()->get('business.id');

        $link = NfeContadorLink::query()->create($dados + [
            'business_id' => $biz,
            'expira_em'   => now()->addDays(14),
            'criado_por'  => $request->user()->id,
        ]);

        $url = URL::temporarySignedRoute('nfe-brasil.contador.revisao', $link->expira_em, [
            'business' => $biz, 'link' => $link->id,
        ]);
        Mail::to($link->email)->send(new RevisaoContadorMail($this->empresa($biz), url: $url));

        return response()->json(['id' => $link->id, 'expira_em' => $link->expira_em->toIso8601String()]);
    }

    /** GET (signed) — tela do código ou, com o código já validado nesta sessão, a lista. */
    public function show(Request $request, int $business, int $link): Response
    {
        $l = $this->doUrl($business, $link);

        if ($this->liberado($request, $l)) {
            return response()->view('nfebrasil::contador.revisao', [
                'modo' => 'lista', 'link' => $l, 'empresa' => $this->empresa($l->business_id),
                'revisoes' => $this->revisoes($l),
            ]);
        }

        if ($l->codigo_hash === null || $l->codigo_expira_em === null || $l->codigo_expira_em->isPast()) {
            $this->emitirCodigo($l);
        }

        return $this->telaCodigo($l);
    }

    /** POST (signed) — confere o código. */
    public function codigo(Request $request, int $business, int $link): Response|RedirectResponse
    {
        $l = $this->doUrl($business, $link);
        $codigo = trim((string) $request->input('codigo', ''));

        if ($l->codigo_hash === null || $l->codigo_expira_em === null || $l->codigo_expira_em->isPast()) {
            $this->emitirCodigo($l);

            return $this->telaCodigo($l, 'O código expirou. Enviamos outro para o seu e-mail.', 422);
        }

        if (! Hash::check($codigo, $l->codigo_hash)) {
            $l->tentativas++;
            if ($l->tentativas >= NfeContadorLink::MAX_TENTATIVAS) {
                $l->bloqueado_em = now();
                $l->save();
                abort(403, 'Link bloqueado por tentativas erradas. Peça um novo à empresa.');
            }
            $l->save();

            return $this->telaCodigo($l, 'Código errado.', 422);
        }

        // Uso único: o código some ao ser aceito.
        $l->forceFill(['codigo_hash' => null, 'codigo_expira_em' => null, 'tentativas' => 0])->save();
        $request->session()->regenerate();
        $request->session()->put(self::SESSAO, $l->id);

        return redirect()->to($request->fullUrl(), 303);
    }

    /** GET — "Baixar regras (CSV)", no formato que o Import CSV lê de volta. */
    public function csv(Request $request, int $link): StreamedResponse
    {
        $l = $this->daSessao($request, $link);

        $regras = DB::table('nfe_fiscal_rules')
            ->where('business_id', $l->business_id)->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('valida_ate')->orWhere('valida_ate', '>=', now()->toDateString()))
            ->orderBy('ncm')->orderBy('id')
            ->get(ImportRegrasCsvService::COLUNAS_OBRIGATORIAS);

        return response()->streamDownload(function () use ($regras) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ImportRegrasCsvService::COLUNAS_OBRIGATORIAS, ',', '"', '');
            foreach ($regras as $r) {
                fputcsv($out, array_map(fn ($v) => $v ?? '', (array) $r), ',', '"', '');
            }
            fclose($out);
        }, 'regras-fiscais.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** POST — aceitar pelo link. */
    public function aceitar(Request $request, int $link, int $id): JsonResponse|RedirectResponse
    {
        $l = $this->daSessao($request, $link);
        $r = $this->service->aceitarPeloLink($this->revisao($l, $id), $l, $request->ip());

        return $request->expectsJson() ? response()->json(['id' => $r->id, 'status' => $r->status]) : back();
    }

    /** POST — pedir ajuste pelo link (comentário obrigatório). */
    public function ajuste(Request $request, int $link, int $id): JsonResponse|RedirectResponse
    {
        $l = $this->daSessao($request, $link);
        $comentario = trim((string) $request->input('comentario', ''));
        if ($comentario === '' || mb_strlen($comentario) > 2000) {
            throw ValidationException::withMessages(['comentario' => 'Diga o que precisa ser ajustado.']);
        }
        $r = $this->service->pedirAjustePeloLink($this->revisao($l, $id), $l, $comentario);

        return $request->expectsJson() ? response()->json(['id' => $r->id, 'status' => $r->status]) : back();
    }

    private function doUrl(int $business, int $link): NfeContadorLink
    {
        // SUPERADMIN: página pública do contador. Se quem abre estiver logado em OUTRA empresa, o
        // escopo da sessão dele esconderia o link; a fronteira aqui é o business do próprio link,
        // que vem da URL assinada.
        $l = NfeContadorLink::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $business)->whereKey($link)->firstOrFail();
        abort_unless($l->aberto(), 403, 'Link expirado ou bloqueado. Peça um novo à empresa.');

        return $l;
    }

    private function daSessao(Request $request, int $link): NfeContadorLink
    {
        abort_unless($request->session()->get(self::SESSAO) === $link, 403);
        // SUPERADMIN: mesmo motivo de doUrl() — a sessão já provou o código deste link.
        $l = NfeContadorLink::query()->withoutGlobalScope(ScopeByBusiness::class)->findOrFail($link);
        abort_unless($l->aberto(), 403, 'Link expirado ou bloqueado. Peça um novo à empresa.');

        return $l;
    }

    private function liberado(Request $request, NfeContadorLink $l): bool
    {
        return $request->session()->get(self::SESSAO) === $l->id;
    }

    private function revisao(NfeContadorLink $l, int $id): NfeRevisaoContador
    {
        // SUPERADMIN: página pública — a fronteira é o business do link, explícita no where.
        return NfeRevisaoContador::query()->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $l->business_id)->whereKey($id)->firstOrFail();
    }

    private function emitirCodigo(NfeContadorLink $l): void
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $l->forceFill(['codigo_hash' => Hash::make($codigo), 'codigo_expira_em' => now()->addMinutes(15)])->save();
        Mail::to($l->email)->send(new RevisaoContadorMail($this->empresa($l->business_id), codigo: $codigo));
    }

    private function telaCodigo(NfeContadorLink $l, ?string $erro = null, int $status = 200): Response
    {
        return response()->view('nfebrasil::contador.revisao', [
            'modo' => 'codigo', 'link' => $l, 'empresa' => $this->empresa($l->business_id), 'erro' => $erro,
        ], $status);
    }

    /** Só regras fiscais: o de → para, a regra (NCM/UF), quem mudou, quando e de onde veio. */
    private function revisoes(NfeContadorLink $l): \Illuminate\Support\Collection
    {
        return DB::table('nfe_revisoes_contador as v')
            ->join('nfe_fiscal_rules as r', 'r.id', '=', 'v.regra_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.autor_id')
            ->where('v.business_id', $l->business_id)
            ->whereIn('v.status', ['pendente', 'ajuste_pedido'])
            ->orderByDesc('v.id')
            ->get(['v.id', 'v.origem', 'v.diff', 'v.status', 'v.comentario', 'v.created_at',
                'r.ncm', 'r.uf_origem', 'r.uf_destino', 'u.first_name', 'u.last_name'])
            ->map(function ($v) {
                $v->diff = json_decode((string) $v->diff, true) ?: [];
                $v->autor = trim(($v->first_name ?? '') . ' ' . ($v->last_name ?? '')) ?: '—';
                unset($v->first_name, $v->last_name);

                return $v;
            });
    }

    private function empresa(int $biz): string
    {
        return (string) DB::table('business')->where('id', $biz)->value('name');
    }
}
