<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\Titulo;
use Modules\Fisio\Entities\Pacote;
use Modules\Fisio\Entities\PacoteLancamento;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Exceptions\PacoteIndisponivelException;
use Modules\Fisio\Services\Pacotes\SaldoDoPacote;
use Modules\NFSe\Models\NfseEmissao;

/**
 * Aba "Pacote e cobrança" da ficha (US-FISIO-021, fonte `prototipo-ui/.../fisio/Financeiro.dc.html`).
 *
 * Escreve só SESSÕES (ledger do pacote). Dinheiro é leitura: títulos pelo model do Financeiro,
 * nota pelo model do NFSe, os dois pela venda do núcleo ligada ao pacote (`transaction_id`).
 * Nenhum valor é gravado aqui (regra do lote 4; vender/renovar é a US-FISIO-022).
 */
class PacotesController extends Controller
{
    public function __construct(private readonly SaldoDoPacote $saldo) {}

    public function show(Request $request, int $id): Response
    {
        $biz = $this->businessId($request);
        $paciente = Patient::query()->where('business_id', $biz)->with('contact:id,name')->findOrFail($id);
        $atual = Pacote::query()->where('business_id', $biz)->where('patient_id', $paciente->id)
            ->where('status', Pacote::STATUS_ATIVO)->latest('id')->first();

        return Inertia::render('Fisio/Pacientes/Pacote', [
            'paciente' => ['id' => $paciente->id, 'nome' => $paciente->contact?->name],
            'pode_editar' => (bool) $request->user()?->can('fisio.editar'),
            'pacote' => Inertia::defer(fn () => $atual ? $this->resumo($atual) : null),
            'cobranca' => Inertia::defer(fn () => $this->titulos($atual)),
            'nota' => Inertia::defer(fn () => $this->nota($atual)),
            'vendas' => Inertia::defer(fn () => $this->vendasDoPaciente($paciente)),
        ]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $paciente = Patient::query()->where('business_id', $this->businessId($request))->findOrFail($id);
        $dados = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'sessions_total' => ['required', 'integer', 'min:1', 'max:200'],
            'sessions_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $this->saldo->abrir($paciente, $dados['title'], (int) $dados['sessions_total'], $dados['valid_until'] ?? null,
            isset($dados['sessions_per_week']) ? (int) $dados['sessions_per_week'] : null, (int) $request->user()->id);

        return back()->with('success', 'Pacote aberto.');
    }

    public function consumir(Request $request, int $pacote): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $p = Pacote::query()->where('business_id', $this->businessId($request))->findOrFail($pacote);
        try {
            $this->saldo->consumir($p, (int) $request->user()->id, null, $request->string('note')->limit(200)->toString() ?: null);
        } catch (PacoteIndisponivelException $e) {
            throw ValidationException::withMessages(['pacote' => $e->getMessage()]);
        }

        return back()->with('success', 'Sessão registrada no pacote.');
    }

    public function estornar(Request $request, int $lancamento): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:200']])['motivo'];
        $l = PacoteLancamento::query()->where('business_id', $this->businessId($request))->findOrFail($lancamento);
        try {
            $this->saldo->estornar($l, (int) $request->user()->id, $motivo);
        } catch (PacoteIndisponivelException $e) {
            throw ValidationException::withMessages(['pacote' => $e->getMessage()]);
        }

        return back()->with('success', 'Sessão devolvida ao pacote.');
    }

    /** Liga uma venda já feita na tela de venda do núcleo ao pacote (US-FISIO-022). */
    public function ligarVenda(Request $request, int $pacote): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $p = Pacote::query()->where('business_id', $this->businessId($request))->with('patient')->findOrFail($pacote);
        $txId = (int) $request->validate(['transaction_id' => ['required', 'integer']])['transaction_id'];
        $venda = DB::table('transactions')->where('id', $txId)->where('business_id', $p->business_id)
            ->where('type', 'sell')->where('status', 'final')->where('contact_id', $p->patient->contact_id)->exists();
        if (! $venda) {
            throw ValidationException::withMessages(['transaction_id' => 'Venda não encontrada para este paciente.']);
        }
        try {
            $this->saldo->ligarVenda($p, $txId);
        } catch (PacoteIndisponivelException $e) {
            throw ValidationException::withMessages(['transaction_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Venda ligada ao pacote.');
    }

    /**
     * Vendas finalizadas do paciente que ainda não pagam nenhum pacote (leitura do núcleo).
     *
     * @return list<array<string, mixed>>
     */
    private function vendasDoPaciente(Patient $paciente): array
    {
        $ligadas = Pacote::query()->where('business_id', $paciente->business_id)->whereNotNull('transaction_id')->pluck('transaction_id');

        return DB::table('transactions')->where('business_id', $paciente->business_id)
            ->where('contact_id', $paciente->contact_id)->where('type', 'sell')->where('status', 'final')
            ->whereNotIn('id', $ligadas)->orderByDesc('transaction_date')->limit(20)
            ->get(['id', 'invoice_no', 'transaction_date', 'final_total'])
            ->map(fn ($v) => ['id' => (int) $v->id, 'numero' => $v->invoice_no, 'data' => substr((string) $v->transaction_date, 0, 10), 'valor' => (string) $v->final_total])
            ->values()->all();
    }

    /** Empresa da sessão, aplicada em toda consulta além do escopo global (ADR 0093). */
    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    /** @return array<string, mixed> */
    private function resumo(Pacote $p): array
    {
        $estornados = PacoteLancamento::query()->where('business_id', $p->business_id)->where('package_id', $p->id)->where('kind', PacoteLancamento::ESTORNO)
            ->pluck('ref')->map(fn ($r) => (int) str_replace('estorno:', '', (string) $r))->all();

        return [
            'id' => $p->id,
            'title' => $p->title,
            'sessions_total' => $p->sessions_total,
            'saldo' => $this->saldo->saldo($p),
            'validade' => $this->saldo->validade($p)?->toDateString(),
            'tem_venda' => $p->transaction_id !== null,
            'sessoes' => PacoteLancamento::query()->where('business_id', $p->business_id)->where('package_id', $p->id)->where('kind', PacoteLancamento::DEBITO)
                ->orderBy('id')->get(['id', 'created_at', 'note', 'ref'])
                ->map(fn (PacoteLancamento $l, int $i) => [
                    'id' => $l->id, 'n' => $i + 1, 'data' => $l->created_at?->toDateString(),
                    'nota' => $l->note, 'estornada' => in_array($l->id, $estornados, true),
                ])->all(),
        ];
    }

    /** Títulos da venda do pacote, lidos do Financeiro (só leitura). @return list<array<string, mixed>> */
    private function titulos(?Pacote $p): array
    {
        if ($p?->transaction_id === null || ! class_exists(Titulo::class)) {
            return [];
        }

        return Titulo::query()->where('business_id', $p->business_id)->where('tipo', 'receber')
            ->where('origem', 'venda')->where('origem_id', $p->transaction_id)->orderBy('vencimento')
            ->get(['id', 'numero', 'status', 'valor_total', 'vencimento', 'forma_pagamento'])
            ->map(fn (Titulo $t) => [
                'id' => $t->id, 'numero' => $t->numero, 'status' => $t->status,
                'valor' => (string) $t->valor_total, 'vencimento' => $t->vencimento?->toDateString(), 'forma' => $t->forma_pagamento,
            ])->values()->all();
    }

    /** Última NFS-e da venda do pacote (só leitura). @return array<string, mixed>|null */
    private function nota(?Pacote $p): ?array
    {
        if ($p?->transaction_id === null || ! class_exists(NfseEmissao::class)) {
            return null;
        }
        $n = NfseEmissao::query()->where('business_id', $p->business_id)->where('transaction_id', $p->transaction_id)->latest('id')->first();

        return $n ? ['numero' => $n->numero, 'status' => $n->status, 'erro' => $n->status === 'erro' ? $n->erro_mensagem : null] : null;
    }
}
