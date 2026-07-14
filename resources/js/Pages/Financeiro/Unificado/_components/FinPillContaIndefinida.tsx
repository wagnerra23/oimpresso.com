import { AlertTriangle } from 'lucide-react';

/**
 * US-FIN-038 — pill "Conta indefinida" (warning leve, cinza-amarelado — NÃO
 * vermelho de erro) nas linhas/drawer onde a baixa foi registrada sem vinculação
 * bancária (ADR 0175: baixa permite `conta_bancaria_id NULL`). O próprio pill é o
 * CTA: leva à tela de contas bancárias pra cadastrar/vincular a conta.
 *
 * Backend: `shapeTitulo.conta_indefinida` (UnificadoController). Dark-aware (tokens
 * amber com variante dark:). Reusável — as telas ContasReceber/Cobranca herdam
 * (fila da US-FIN-038; PR1 aplica só na Visão Unificada).
 */
export function FinPillContaIndefinida() {
  return (
    <a
      href="/financeiro/contas-bancarias"
      title="Pagamento registrado sem vinculação bancária. Cadastre conta pra organizar caixa."
      className="inline-flex items-center gap-1 rounded-full border border-amber-300/60 bg-amber-50 px-2 py-0.5 text-[10.5px] font-medium text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20"
    >
      <AlertTriangle className="h-3 w-3" aria-hidden />
      Conta indefinida
    </a>
  );
}
