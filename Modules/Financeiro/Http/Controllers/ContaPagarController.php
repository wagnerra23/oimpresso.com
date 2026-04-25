<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;

/**
 * Tela /financeiro/contas-pagar.
 * Lista titulos tipo='pagar' com filtros + acao "Pagar" que registra TituloBaixa.
 */
class ContaPagarController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = $request->session()->get('business.id');

        $titulos = Titulo::where('business_id', $businessId)
            ->where('tipo', 'pagar')
            ->whereNull('deleted_at')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->vence_em, function ($q, $vence) {
                if ($vence === 'hoje') return $q->whereDate('vencimento', today());
                if ($vence === 'atrasado') return $q->whereDate('vencimento', '<', today())->where('status', '!=', 'quitado');
                if ($vence === 'semana') return $q->whereBetween('vencimento', [today(), today()->addDays(7)]);
                return $q;
            })
            ->orderBy('vencimento')
            ->limit(100)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'numero' => $t->numero,
                'cliente_descricao' => $t->cliente_descricao,
                'valor_total' => $t->valor_total,
                'valor_aberto' => $t->valor_aberto,
                'vencimento' => $t->vencimento?->toDateString(),
                'status' => $t->status,
                'origem' => $t->origem,
                'origem_id' => $t->origem_id,
            ]);

        $contas = ContaBancaria::where('business_id', $businessId)
            ->select(['id', 'banco_codigo'])
            ->with(['account:id,name'])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->account?->name ?? "Conta #{$c->id}",
            ]);

        return Inertia::render('Financeiro/ContasPagar/Index', [
            'titulos' => $titulos,
            'contas_bancarias' => $contas,
            'filtros' => [
                'status' => $request->status,
                'vence_em' => $request->vence_em,
            ],
        ]);
    }

    public function pagar(Request $request, int $tituloId): RedirectResponse
    {
        $businessId = $request->session()->get('business.id');

        $request->validate([
            'conta_bancaria_id' => ['required', 'integer', 'exists:fin_contas_bancarias,id'],
            'valor_baixa' => ['required', 'numeric', 'min:0.01'],
            'data_baixa' => ['required', 'date'],
            'meio_pagamento' => ['required', 'string', 'in:dinheiro,pix,boleto,cartao_credito,cartao_debito,transferencia,cheque,compensacao'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ]);

        $titulo = Titulo::where('business_id', $businessId)->findOrFail($tituloId);

        if ($titulo->status === 'quitado' || $titulo->status === 'cancelado') {
            return back()->with('error', 'Titulo ja '.$titulo->status.'. Nao pode receber baixa.');
        }

        $valor = (float) $request->input('valor_baixa');
        if ($valor > (float) $titulo->valor_aberto) {
            return back()->with('error', 'Valor da baixa excede o aberto.');
        }

        TituloBaixa::create([
            'business_id' => $businessId,
            'titulo_id' => $titulo->id,
            'conta_bancaria_id' => $request->integer('conta_bancaria_id'),
            'valor_baixa' => $valor,
            'data_baixa' => $request->input('data_baixa'),
            'meio_pagamento' => $request->input('meio_pagamento'),
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'observacoes' => $request->input('observacoes'),
            'created_by' => $request->user()->id,
        ]);

        $novoAberto = (float) $titulo->valor_aberto - $valor;
        $titulo->valor_aberto = max(0, $novoAberto);
        $titulo->status = $titulo->valor_aberto <= 0 ? 'quitado' : 'parcial';
        $titulo->save();

        return back()->with('success', 'Baixa registrada.');
    }
}
