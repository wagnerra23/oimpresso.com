// printOficinaFila — folha A4 "Fila da oficina" (lista de OS abertas do quadro).
//
// Port do protótipo Cowork homologado `oficina-print.js` (printFila) traduzido pro
// stack real: HTML auto-contido montado client-side a partir dos cards JÁ filtrados
// do Board (busca + KPI-filtro + selects), impresso via mecanismo canônico
// printHtmlDocument (iframe fora da vista + fallback window.open — printServiceOrder.ts).
//
// Ordenação canon do protótipo: atrasadas primeiro, depois pela ordem das etapas.
// CSS embutido no doc (folha preto-e-branco A4 — independe dos tokens da SPA).
// Multi-tenant Tier 0 (ADR 0093): só imprime o que o board já entregou pro tenant.

import { printHtmlDocument } from './printServiceOrder';

export interface FilaPrintRow {
  number: string;
  etapa: string;
  /** índice da etapa na ordem do quadro (pra ordenação) */
  etapaIndex: number;
  vehicle: string | null;
  plate: string | null;
  cliente: string | null;
  mecanico: string | null;
  box: string | null;
  /** prazo formatado (dd/mm) ou null */
  prazo: string | null;
  valor: number;
  atrasada: boolean;
}

export interface FilaPrintOptions {
  /** descrição dos filtros ativos (ex.: "busca: ABC · KPI: Atrasadas") */
  filtro?: string | null;
}

const esc = (s: unknown): string =>
  String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c] as string));

const brl = (n: number): string =>
  'R$ ' + Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const hoje = (): string =>
  new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });

const agora = (): string =>
  new Date().toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

// Folha A4 preto-e-branco — espelha a estrutura .ofc-sheet do protótipo
// (brand head + nota de contexto + tabela + rodapé). Auto-contida de propósito.
const SHEET_CSS = `
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #111; background: #fff; }
  .ofc-sheet { max-width: 190mm; margin: 0 auto; padding: 10mm 0; font-size: 11px; }
  .ofc-sheet-top { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
  .ofc-sheet-brand { font-size: 17px; font-weight: 800; letter-spacing: -0.02em; }
  .ofc-sheet-brand small { display: block; font-size: 10px; font-weight: 600; color: #555; letter-spacing: 0.06em; text-transform: uppercase; }
  .ofc-sheet-doc { text-align: right; }
  .ofc-sheet-doc .t { font-size: 13px; font-weight: 700; }
  .ofc-sheet-doc .d { font-size: 10px; color: #555; margin-top: 2px; }
  .ofc-sheet-note { font-size: 10.5px; color: #444; margin-bottom: 8px; }
  .ofc-sheet-tbl { width: 100%; border-collapse: collapse; }
  .ofc-sheet-tbl th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; color: #555; border-bottom: 1.5px solid #111; padding: 4px 6px; }
  .ofc-sheet-tbl td { border-bottom: 1px solid #ddd; padding: 5px 6px; font-size: 10.5px; vertical-align: top; }
  .ofc-sheet-tbl .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .ofc-sheet-tbl tr.urg td { font-weight: 700; }
  .ofc-sheet-tbl tr.urg td:first-child::before { content: '⚠ '; }
  .ofc-sheet-tbl tfoot td { border-top: 1.5px solid #111; border-bottom: none; font-weight: 700; padding-top: 6px; }
  .ofc-sheet-empty { text-align: center; color: #777; font-style: italic; padding: 14px 0; }
  .ofc-sheet-foot { display: flex; justify-content: space-between; margin-top: 14px; padding-top: 6px; border-top: 1px solid #ddd; font-size: 9px; color: #666; }
  @page { size: A4 portrait; margin: 12mm; }
  @media print { .ofc-sheet { padding: 0; } }
`;

/**
 * Monta e imprime a folha "Fila da oficina" com as OS visíveis no quadro.
 * `rows` já chegam filtradas (busca + KPI + selects) — esta função só ordena
 * (atrasadas primeiro, depois etapa) e renderiza.
 */
export function printOficinaFila(rows: FilaPrintRow[], opts: FilaPrintOptions = {}): Promise<void> {
  const list = [...rows].sort(
    (a, b) => (Number(b.atrasada) - Number(a.atrasada)) || (a.etapaIndex - b.etapaIndex),
  );

  const bodyRows = list.map((os) => `
    <tr class="${os.atrasada ? 'urg' : ''}">
      <td>${esc(os.etapa)}</td>
      <td class="num">${esc(os.number)}</td>
      <td>${esc(os.vehicle ?? '—')}</td>
      <td class="num">${esc(os.plate ?? '—')}</td>
      <td>${esc(os.cliente ?? '—')}</td>
      <td>${esc(os.mecanico ?? '—')}</td>
      <td>${esc(os.box ?? '—')}</td>
      <td>${esc(os.prazo ?? '—')}</td>
      <td class="num">${os.valor > 0 ? esc(brl(os.valor)) : '—'}</td>
    </tr>`).join('');

  const total = list.reduce((a, os) => a + (os.valor || 0), 0);
  const atrasadas = list.filter((os) => os.atrasada).length;

  const htmlContent = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Fila da oficina</title>
<style>${SHEET_CSS}</style>
</head>
<body>
  <div class="ofc-sheet">
    <div class="ofc-sheet-top">
      <div class="ofc-sheet-brand">Oimpresso<small>Oficina Auto</small></div>
      <div class="ofc-sheet-doc">
        <div class="t">Fila da oficina</div>
        <div class="d">${esc(hoje())}</div>
      </div>
    </div>

    <div class="ofc-sheet-note">${list.length} ordem(ns) aberta(s)${atrasadas ? ` · ${atrasadas} atrasada(s)` : ''}${opts.filtro ? ` · filtro: ${esc(opts.filtro)}` : ''}</div>

    <table class="ofc-sheet-tbl">
      <thead><tr>
        <th>Etapa</th><th class="num">OS</th><th>Veículo</th><th class="num">Placa</th>
        <th>Cliente</th><th>Mecânico</th><th>Box</th><th>Prazo</th><th class="num">Valor</th>
      </tr></thead>
      <tbody>${bodyRows || '<tr><td colspan="9" class="ofc-sheet-empty">Nenhuma OS na fila.</td></tr>'}</tbody>
      ${list.length ? `<tfoot><tr><td colspan="8">Total previsto em carteira</td><td class="num">${esc(brl(total))}</td></tr></tfoot>` : ''}
    </table>

    <div class="ofc-sheet-foot">
      <span>Oimpresso ERP · Oficina Auto · Fila de produção</span>
      <span>Emitido ${esc(agora())}</span>
    </div>
  </div>
</body>
</html>`;

  return printHtmlDocument({ htmlContent, title: 'Fila da oficina' });
}
