// FiscalSplitCard — split fiscal da OS por natureza (US-OFICINA-042).
// Delta #6 do protótipo Cowork "Nova OS" (seção "Fiscal"): peças = mercadoria → NF-e 55,
// mão de obra/serviço = serviço → NFS-e. Painel de planejamento (presentacional), computado
// de order.items — a emissão real sai pela Transaction derivada (Observer · ADR 0192).
// Converge com o componente único FiscalStatusBadge (NfeBrasil) quando a OS carregar
// status de doc emitido — por ora mostra a natureza + valor.

import { Receipt, ShieldCheck } from 'lucide-react';
import type { ServiceOrderItemDto } from './ServiceOrderItemRow';

interface Props {
  items: ServiceOrderItemDto[];
}

function toFloat(value: number | string | null | undefined): number {
  if (value === null || value === undefined || value === '') return 0;
  const n = typeof value === 'string' ? parseFloat(value) : value;
  return Number.isNaN(n) ? 0 : n;
}

function formatBRL(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function FiscalSplitCard({ items }: Props) {
  if (items.length === 0) return null;

  const pecas = items
    .filter((it) => it.tipo === 'peca')
    .reduce((sum, it) => sum + toFloat(it.valor_total), 0);
  const servicos = items
    .filter((it) => it.tipo === 'mao_obra' || it.tipo === 'servico_terceiro')
    .reduce((sum, it) => sum + toFloat(it.valor_total), 0);

  return (
    <section className="mt-6 rounded-md border bg-card p-4">
      <div className="flex items-center gap-2 mb-3">
        <Receipt className="size-4 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">Fiscal</h2>
        <span className="text-[11px] text-muted-foreground">naturezas distintas</span>
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between text-sm">
          <span className="flex items-center gap-2">
            <span className="rounded bg-secondary text-secondary-foreground text-[10px] font-semibold px-1.5 py-0.5">
              NF-e 55
            </span>
            Peças <span className="text-muted-foreground text-xs">mercadoria</span>
          </span>
          <span className="tabular-nums">{formatBRL(pecas)}</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="flex items-center gap-2">
            <span className="rounded bg-secondary text-secondary-foreground text-[10px] font-semibold px-1.5 py-0.5">
              NFS-e
            </span>
            Mão de obra <span className="text-muted-foreground text-xs">serviço</span>
          </span>
          <span className="tabular-nums">{formatBRL(servicos)}</span>
        </div>
      </div>

      <div className="mt-3 pt-3 border-t flex items-center gap-2 text-xs text-muted-foreground">
        <ShieldCheck className="size-3.5" />
        Garantia de 90 dias em serviço e peça
      </div>
    </section>
  );
}
