import { AlertTriangle } from 'lucide-react';

/**
 * US-FIN-038 — pill "Conta indefinida" (warning leve — NÃO vermelho de erro) nas
 * linhas/drawer onde a baixa foi registrada sem vinculação bancária (ADR 0175:
 * baixa permite `conta_bancaria_id NULL`). O próprio pill é o CTA: leva à tela de
 * contas bancárias pra cadastrar/vincular a conta.
 *
 * Cor via TOKENS do design-system (`warning-soft`/`warning-fg`/`warning`, os mesmos
 * do `Components/ui/badge.tsx` variant="warning") — dark-aware por construção, sem
 * cor crua (regra R1 do `ui:lint`). Backend: `shapeTitulo.conta_indefinida`.
 * Reusável — ContasReceber/Cobranca herdam (fila US-FIN-038; PR1 = Visão Unificada).
 */
export function FinPillContaIndefinida() {
  return (
    <a
      href="/financeiro/contas-bancarias"
      title="Pagamento registrado sem vinculação bancária. Cadastre conta pra organizar caixa."
      className="inline-flex items-center gap-1 rounded-full border border-warning/20 bg-warning-soft px-2 py-0.5 text-[10.5px] font-medium text-warning-fg transition-colors hover:border-warning/30 hover:bg-warning-soft/70"
    >
      <AlertTriangle className="h-3 w-3" aria-hidden />
      Conta indefinida
    </a>
  );
}
