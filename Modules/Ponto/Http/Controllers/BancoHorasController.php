<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\BancoHorasMovimento;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Services\BancoHorasService;

class BancoHorasController extends Controller
{
    protected $service;

    public function __construct(BancoHorasService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): Response
    {
        $businessId = session('business.id') ?: $request->user()->business_id;

        // Wave 26 D6 Inertia::defer DEFAULT — paginate(30) + 4 aggregates (sum/count)
        // viram closures lazy (RUNBOOK-inertia-defer-pattern.md).
        return Inertia::render('Ponto/BancoHoras/Index', [
            'saldos' => Inertia::defer(fn () => $this->buildSaldosPagina($businessId)),
            'totais' => Inertia::defer(fn () => $this->buildTotaisSaldos($businessId)),
        ]);
    }

    /**
     * Paginação 30 saldos (orderByDesc saldo) — eager `colaborador.user`. Wave 26 extraído.
     */
    private function buildSaldosPagina(int $businessId)
    {
        $paginated = BancoHorasSaldo::where('business_id', $businessId)
            ->with('colaborador.user:id,first_name,last_name')
            ->orderByDesc('saldo_minutos')
            ->paginate(30)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($s) => [
            'colaborador_id' => $s->colaborador_config_id,
            'matricula'      => optional($s->colaborador)->matricula,
            'nome'           => trim(
                optional(optional($s->colaborador)->user)->first_name . ' ' .
                optional(optional($s->colaborador)->user)->last_name
            ) ?: '—',
            'saldo_minutos'  => (int) $s->saldo_minutos,
            'atualizado_em'  => optional($s->updated_at)->diffForHumans(),
        ]);

        return $paginated;
    }

    /**
     * Totais credito/debito + contagem colaboradores (4 aggregates). Wave 26 extraído.
     *
     * @return array<string,int>
     */
    private function buildTotaisSaldos(int $businessId): array
    {
        return [
            'credito_total' => (int) BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '>', 0)->sum('saldo_minutos'),
            'debito_total' => (int) BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '<', 0)->sum('saldo_minutos'),
            'colaboradores_credito' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '>', 0)->count(),
            'colaboradores_debito' => BancoHorasSaldo::where('business_id', $businessId)
                ->where('saldo_minutos', '<', 0)->count(),
        ];
    }

    public function show(Request $request, int $colaboradorId): Response
    {
        $saldo = BancoHorasSaldo::where('colaborador_config_id', $colaboradorId)
            ->with([
                'colaborador.user:id,first_name,last_name,essentials_designation_id',
                'colaborador.escalaAtual:id,nome',
            ])
            ->firstOrFail();

        // Wave 26 D6 Inertia::defer — paginate(50) movimentos lazy. Saldo header eager
        // (já materializado pra findOrFail validar acesso tenant).
        return Inertia::render('Ponto/BancoHoras/Show', [
            'saldo' => [
                'colaborador_id' => $saldo->colaborador_config_id,
                'matricula'      => optional($saldo->colaborador)->matricula,
                'nome'           => trim(
                    optional(optional($saldo->colaborador)->user)->first_name . ' ' .
                    optional(optional($saldo->colaborador)->user)->last_name
                ) ?: '—',
                'saldo_minutos'  => (int) $saldo->saldo_minutos,
                // Cabeçalho do extrato como o protótipo (ponto-telas.jsx:370):
                // "matrícula · cargo · escala". Cargo = cargo do HRM do usuário; nulo quando
                // o business não usa o Essentials — a tela omite o trecho, não inventa.
                'cargo'          => $this->cargoDoUsuario((int) $saldo->business_id, optional($saldo->colaborador)->user),
                'escala'         => optional(optional($saldo->colaborador)->escalaAtual)->nome,
                'atualizado_em'  => optional($saldo->updated_at)->format('Y-m-d H:i'),
            ],
            // KPIs "Teto do acordo" e "Prazo de compensação" (D-BH-KPI, [W] 2026-09-14):
            // a regra de limite/expiração fica VISÍVEL. Só exibe — quem aplica é o
            // BancoHorasService, que lê o mesmo config (Non-Goal: a tela não recalcula).
            'acordo' => [
                'teto_horas'  => (int) config('pontowr2.banco_horas.saldo_maximo_horas', 200),
                'piso_horas'  => (int) config('pontowr2.banco_horas.saldo_minimo_horas', -40),
                'prazo_meses' => (int) config('pontowr2.banco_horas.prazo_compensacao_meses', 6),
            ],
            'movimentos' => Inertia::defer(fn () => $this->buildMovimentosPagina($colaboradorId)),
        ]);
    }

    /**
     * Cargo do HRM (Essentials): `users.essentials_designation_id` → `categories`
     * de `hrm_designation` — o mesmo caminho do PayrollController do Essentials.
     * `business_id` EXPLÍCITO (Tier 0, ADR 0093): `categories` é tabela core sem o
     * global scope do Ponto, e um id de categoria de outro business não pode vazar o nome.
     */
    private function cargoDoUsuario(int $businessId, $user): ?string
    {
        if (! $user || empty($user->essentials_designation_id)) {
            return null;
        }

        return DB::table('categories')
            ->where('business_id', $businessId) // do SALDO (já tenant-scoped), não da sessão
            ->where('category_type', 'hrm_designation')
            ->whereNull('deleted_at')
            ->where('id', $user->essentials_designation_id)
            ->value('name');
    }

    /**
     * Paginação 50 movimentos do ledger. Wave 26 extraído pra closure lazy.
     */
    private function buildMovimentosPagina(int $colaboradorId)
    {
        $paginated = BancoHorasMovimento::where('colaborador_config_id', $colaboradorId)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($m) => [
            'id'             => $m->id,
            'minutos'        => (int) $m->minutos,
            'tipo'           => $m->tipo,
            'data_referencia'=> optional($m->data_referencia)->format('Y-m-d'),
            'observacao'     => $m->observacao,
            'created_at'     => optional($m->created_at)->format('Y-m-d H:i'),
            'created_at_human' => optional($m->created_at)->diffForHumans(),
        ]);

        return $paginated;
    }

    public function ajustarManual(Request $request, $colaboradorId)
    {
        $request->validate([
            'minutos' => 'required|integer',
            'observacao' => 'required|string|max:500',
        ]);

        $this->service->ajustarManual(
            $colaboradorId,
            $request->input('minutos'),
            $request->input('observacao'),
            auth()->id()
        );

        return back()->with('success', 'Ajuste manual registrado no ledger.');
    }
}
