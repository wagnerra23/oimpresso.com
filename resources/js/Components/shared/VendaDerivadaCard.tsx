// Card "Esta OS gerou venda #V-NNNN" — Integração Vendas × Oficina (ADR 0192).
//
// Shared component cross-módulo:
//  - Modules/Repair (kanban shared OS — desde PR #1504 Onda 5)
//  - Modules/OficinaAuto (drawer ServiceOrderSheet — desde feat/shared-venda-derivada-card)
//
// Renderiza quando OS está no terminal stage (is_completed_status) AND
// venda_derivada !== null (Transaction com source='oficina' criada pelo
// JobSheetObserver (Repair) ou ServiceOrderObserver (OficinaAuto · PR #1530).
//
// Mapeamento direto do protótipo Cowork `oficina-page.jsx` linhas 392-458 +
// `oficina-page.css` linhas 465-598 (.ofc-venda-* tokens preservados verbatim).
//
// Vocabulário shared (ADR 0121 §P8): zero termos automotivos — cross-vertical
// (OficinaAuto · ComunicacaoVisual · Vestuario · qualquer vertical com OS→Venda).
// Multi-tenant Tier 0 (ADR 0093): payload já scopado backend, frontend só lê.
//
// 3 CTAs:
//  1. "Abrir #V-NNNN" → dispatch CustomEvent 'oimpresso:open-venda' (Worker A
//     Sells/Index listener Onda 4 abre drawer SaleSheet · loose coupling)
//  2. "Imprimir recibo" → window.open rota Blade legacy preservada
//  3. "Compartilhar" → Web Share API nativa (mobile/PWA) + fallback
//     navigator.clipboard.writeText() + toast Sonner (pattern Repair/JobSheet)
//
// FASE B (PR #1516 · 2026-05-25 · backend Onda 3 #1510):
// Card evoluído pra exibir breakdown peças/serviço + badge fiscal NF-e + lista
// items_list collapsable. Tokens `.ofc-venda-grid` + `.ofc-vc` + `.ofc-fb-*`
// mapeados verbatim do protótipo Cowork. Empty states tolerantes:
//   - items_list ausente/[] → não renderiza breakdown nem lista expandível
//   - fiscal null → renderiza badge "Sem nota fiscal" sutil (slate · OS informal)
//   - fiscal.status 'autorizada' → verde + link DANFE clicável
//   - fiscal.status 'pendente'   → amber "NF-e pendente SEFAZ"
//   - fiscal.status 'rejeitada'  → rose "NF-e rejeitada"
// Items list collapsed por default; user clica ▾ pra expandir. Max 10 visíveis
// + "+N mais" sumário se exceder. Prefixo textual "Peça" / "Serviço" em vez de
// emoji (skill pageheader-canon · ZERO emoji em UI).

import { useState } from 'react';
import { toast } from 'sonner';

export interface VendaItem {
  type: 'product' | 'service';
  name: string;
  qty: number;
  unit_price: number;
  subtotal: number;
}

export interface VendaItemsSummary {
  products_count: number;
  products_total: number;
  services_count: number;
  services_total: number;
  tax_total: number;
  discount_total: number;
}

export interface VendaFiscal {
  status: 'autorizada' | 'pendente' | 'rejeitada';
  modelo: string | null;       // '55' | '65' | 'NFSe' (Cowork pode trazer '65' como int — cast string)
  chave: string | null;        // 44 dígitos SEFAZ
  danfe_url: string | null;    // '/danfe/{id}'
}

export interface VendaDerivada {
  // Core (Onda 5 — sempre presente).
  id: number;
  invoice_no: string;
  final_total: number;
  transaction_date: string | null;  // ISO date 'YYYY-MM-DD'
  // Fase B — expandido (Wave Z-2 W2). Opcionais pra backward compat.
  items_list?: VendaItem[];
  items_summary?: VendaItemsSummary;
  fiscal?: VendaFiscal | null;
}

export default function VendaDerivadaCard({ venda }: { venda: VendaDerivada }) {
  const [itemsExpanded, setItemsExpanded] = useState(false);

  const handleAbrir = () => {
    window.dispatchEvent(
      new CustomEvent('oimpresso:open-venda', {
        detail: { venda_id: venda.id },
      }),
    );
  };

  const handleImprimirRecibo = () => {
    window.open(`/sells/${venda.id}/print`, '_blank', 'noopener,noreferrer');
  };

  // Decisão Wagner Onda 5 follow-up: Web Share API nativa (mobile/PWA share-sheet)
  // + fallback navigator.clipboard.writeText() + toast Sonner (pattern já em uso
  // no projeto, ver Modules/Repair Pages/Repair/JobSheet/Show.tsx). Sem dep nova.
  // - AbortError (user cancelou share-sheet) NÃO loga erro (UX silencioso).
  // - canShare check protege Safari iOS quirks (sem text-only sem url).
  const handleCompartilhar = async () => {
    const url = `${window.location.origin}/sells/${venda.id}`;
    const text = `Venda #${venda.invoice_no} · ${venda.final_total.toLocaleString('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    })}${venda.transaction_date ? ` · ${new Date(venda.transaction_date + 'T00:00:00').toLocaleDateString('pt-BR')}` : ''}`;
    const shareData = { title: `Venda #${venda.invoice_no}`, text, url };

    if (typeof navigator !== 'undefined' && typeof navigator.share === 'function') {
      const canShare = typeof navigator.canShare === 'function' ? navigator.canShare(shareData) : true;
      if (canShare) {
        try {
          await navigator.share(shareData);
          return;
        } catch (err) {
          // AbortError = user dismissed share-sheet · não logar nem mostrar toast erro.
          if ((err as DOMException)?.name === 'AbortError') return;
          // Outros erros → cair pro fallback clipboard.
          console.error('Web Share falhou, caindo pro clipboard:', err);
        }
      }
    }

    // Fallback clipboard + toast Sonner (pattern projeto).
    try {
      await navigator.clipboard.writeText(`${text}\n${url}`);
      toast.success('Link da venda copiado');
    } catch (err) {
      console.error('Clipboard falhou:', err);
      toast.error('Não foi possível copiar o link');
    }
  };

  const fmtBRL = (n: number) =>
    n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  const totalBR = fmtBRL(venda.final_total);

  const dataBR = venda.transaction_date
    ? new Date(venda.transaction_date + 'T00:00:00').toLocaleDateString('pt-BR')
    : '—';

  // FASE B — empty states tolerantes (Wave Z-2 W2 backward compat).
  const itemsList = venda.items_list ?? [];
  const summary = venda.items_summary;
  const hasBreakdown = !!summary && itemsList.length > 0;
  const subtotal = summary
    ? Number((summary.products_total + summary.services_total).toFixed(2))
    : 0;
  const fiscal = venda.fiscal ?? null;

  // Max 10 items visíveis na lista expandida — resto vira "+N mais".
  const VISIBLE_ITEMS_CAP = 10;
  const visibleItems = itemsList.slice(0, VISIBLE_ITEMS_CAP);
  const hiddenItemsCount = Math.max(0, itemsList.length - VISIBLE_ITEMS_CAP);

  return (
    <div className="ofc-venda-card relative mx-5 mt-4 mb-2 rounded-[10px] border border-emerald-600/70 bg-gradient-to-br from-emerald-50 to-amber-50/30 px-5 pt-5 pb-4">
      <div className="ofc-venda-flag absolute -top-2.5 left-4 rounded-full bg-emerald-600 px-2.5 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider text-white">
        Integração Vendas × Oficina
      </div>

      <div className="ofc-venda-head mb-3">
        <div className="text-sm font-bold leading-tight text-slate-900">
          Esta OS gerou a venda{' '}
          <code className="ml-0.5 rounded border border-emerald-200 bg-white px-2 py-0.5 font-mono text-[12.5px] font-bold text-emerald-700">
            #{venda.invoice_no}
          </code>
        </div>
        <div className="mt-1 text-[11px] font-medium text-emerald-800/80">
          Auto-criada na transição para "Pronto" (ADR 0192)
        </div>
      </div>

      <div className="ofc-venda-grid mb-3 grid grid-cols-2 gap-2">
        <div className="rounded-md bg-white/65 px-3 py-2">
          <div className="text-[9.5px] font-bold uppercase tracking-wider text-slate-500">
            Total
          </div>
          <div className="mt-0.5 text-sm font-semibold text-slate-900">{totalBR}</div>
        </div>
        <div className="rounded-md bg-white/65 px-3 py-2">
          <div className="text-[9.5px] font-bold uppercase tracking-wider text-slate-500">
            Data
          </div>
          <div className="mt-0.5 text-sm font-semibold text-slate-900">{dataBR}</div>
        </div>
      </div>

      {/* FASE B — Breakdown peças vs serviço (renderiza só se W2 backend entregou).
          Grid 2-col responsive (empilha sm:grid-cols-2 → grid-cols-1 abaixo) +
          linha subtotal · desconto · impostos quando aplicáveis. */}
      {hasBreakdown && summary && (
        <div className="ofc-venda-breakdown mb-3 rounded-md bg-white/65 px-3 py-2.5">
          <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div>
              <div className="text-[9.5px] font-bold uppercase tracking-wider text-slate-500">
                Peças
              </div>
              <div className="mt-0.5 text-[12.5px] font-semibold text-slate-900">
                {summary.products_count} {summary.products_count === 1 ? 'item' : 'itens'} ·{' '}
                {fmtBRL(summary.products_total)}
              </div>
            </div>
            <div>
              <div className="text-[9.5px] font-bold uppercase tracking-wider text-slate-500">
                Serviços
              </div>
              <div className="mt-0.5 text-[12.5px] font-semibold text-slate-900">
                {summary.services_count} {summary.services_count === 1 ? 'item' : 'itens'} ·{' '}
                {fmtBRL(summary.services_total)}
              </div>
            </div>
          </div>

          <div className="mt-2 space-y-0.5 border-t border-emerald-100 pt-2 text-[11px] text-slate-700">
            <div className="flex items-center justify-between">
              <span>Subtotal</span>
              <span className="font-mono font-semibold text-slate-900">{fmtBRL(subtotal)}</span>
            </div>
            {summary.discount_total > 0 && (
              <div className="flex items-center justify-between text-rose-700">
                <span>Desconto</span>
                <span className="font-mono font-semibold">-{fmtBRL(summary.discount_total)}</span>
              </div>
            )}
            {summary.tax_total > 0 && (
              <div className="flex items-center justify-between text-slate-700">
                <span>Impostos</span>
                <span className="font-mono font-semibold">+{fmtBRL(summary.tax_total)}</span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* FASE B — Badge fiscal (NF-e). 4 estados: autorizada (verde + DANFE link) ·
          pendente (amber) · rejeitada (rose) · null (slate sutil "Sem nota fiscal").
          Vocabulário shared: usa "NF-e" genérico (cross-vertical · OficinaAuto / ComVisual / Vestuario). */}
      <div className="ofc-venda-fiscal mb-3 flex flex-wrap gap-1.5">
        {fiscal === null && (
          <span className="ofc-fb ofc-fb-na inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500">
            Sem nota fiscal
          </span>
        )}
        {fiscal?.status === 'autorizada' && (
          <>
            <span className="ofc-fb ofc-fb-ok inline-flex items-center gap-1 rounded border border-emerald-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
              NF-e{fiscal.modelo ? ` ${fiscal.modelo}` : ''} autorizada
            </span>
            {fiscal.danfe_url && (
              <button
                type="button"
                onClick={() => window.open(fiscal.danfe_url!, '_blank', 'noopener,noreferrer')}
                aria-label={`Abrir DANFE da venda ${venda.invoice_no}`}
                className="inline-flex items-center gap-1 rounded border border-emerald-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
              >
                DANFE ↗
              </button>
            )}
          </>
        )}
        {fiscal?.status === 'pendente' && (
          <span className="ofc-fb ofc-fb-wait inline-flex items-center gap-1 rounded border border-amber-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-amber-700">
            NF-e{fiscal.modelo ? ` ${fiscal.modelo}` : ''} pendente SEFAZ
          </span>
        )}
        {fiscal?.status === 'rejeitada' && (
          <span className="ofc-fb ofc-fb-bad inline-flex items-center gap-1 rounded border border-rose-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-rose-700">
            NF-e{fiscal.modelo ? ` ${fiscal.modelo}` : ''} rejeitada
          </span>
        )}
      </div>

      {/* FASE B — Lista items expandível (collapsed por default · disclosure pattern).
          Prefix textual "Peça" / "Serviço" (skill pageheader-canon: ZERO emoji em UI). */}
      {hasBreakdown && (
        <div className="ofc-venda-items-toggle mb-3">
          <button
            type="button"
            onClick={() => setItemsExpanded((v) => !v)}
            aria-expanded={itemsExpanded}
            aria-controls={`venda-items-${venda.id}`}
            className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-800 hover:text-emerald-900 focus:outline-none"
          >
            <span aria-hidden="true">{itemsExpanded ? '▾' : '▸'}</span>
            {itemsExpanded ? 'Ocultar' : 'Ver'} {itemsList.length}{' '}
            {itemsList.length === 1 ? 'item da venda' : 'itens da venda'}
          </button>
          {itemsExpanded && (
            <ul
              id={`venda-items-${venda.id}`}
              className="ofc-venda-items mt-2 space-y-1 rounded-md bg-white/65 px-3 py-2 text-[11.5px] text-slate-800"
            >
              {visibleItems.map((item, idx) => (
                <li
                  key={`${item.type}-${item.name}-${idx}`}
                  className="flex items-baseline justify-between gap-2"
                >
                  <span className="truncate">
                    <span className="font-semibold text-slate-600">
                      {item.type === 'service' ? 'Serviço' : 'Peça'}
                    </span>
                    <span className="text-slate-400"> · </span>
                    <span>{item.name}</span>
                    <span className="text-slate-400">
                      {' · '}
                      {item.qty.toLocaleString('pt-BR')}× {fmtBRL(item.unit_price)}
                    </span>
                  </span>
                  <span className="font-mono font-semibold text-slate-900">
                    {fmtBRL(item.subtotal)}
                  </span>
                </li>
              ))}
              {hiddenItemsCount > 0 && (
                <li className="pt-1 text-[11px] italic text-slate-500">
                  + {hiddenItemsCount} {hiddenItemsCount === 1 ? 'item' : 'itens'} adicionais
                </li>
              )}
            </ul>
          )}
        </div>
      )}

      <div className="ofc-venda-actions flex flex-wrap gap-1.5">
        <button
          type="button"
          onClick={handleAbrir}
          aria-label={`Abrir venda ${venda.invoice_no}`}
          className="ofc-venda-cta primary inline-flex items-center gap-1 rounded-[5px] border border-emerald-600 bg-emerald-600 px-3 py-1.5 text-[11.5px] font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
        >
          Abrir #{venda.invoice_no} ↗
        </button>
        <button
          type="button"
          onClick={handleImprimirRecibo}
          className="ofc-venda-cta inline-flex items-center gap-1 rounded-[5px] border border-emerald-200 bg-white px-3 py-1.5 text-[11.5px] font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
        >
          Imprimir recibo
        </button>
        <button
          type="button"
          onClick={handleCompartilhar}
          aria-label={`Compartilhar venda ${venda.invoice_no}`}
          className="ofc-venda-cta inline-flex items-center gap-1 rounded-[5px] border border-emerald-200 bg-white px-3 py-1.5 text-[11.5px] font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
        >
          Compartilhar
        </button>
      </div>
    </div>
  );
}
