// FinTranscriptPDF — Cowork KB-9.75 Financeiro Onda 7c
// (folha jurídica imprimível — pra Eliana mandar pro escritório/contabilidade).
//
// Refs:
//  - prototipo-ui/financeiro-output.jsx FinTranscriptPDF + @print media
//
// Render fullscreen overlay com "folha A4" simulada. Print CSS isola a folha
// quando user dá Ctrl+P (esconde overlay + header + footer da página).
//
// Pure compute — zero backend, zero LLM. Gera HTML imprimível direto do
// state atual (lancamentos + kpis filtrados pelo filtro corrente).
//
// Multi-tenant safe: só renderiza o que recebe via props (não toca queries).

import { useEffect, useMemo } from 'react';

interface LancamentoLite {
  id: number;
  kind?: 'receivable' | 'payable';
  descricao: string;
  contraparte: string;
  contraparte_doc?: string | null;
  categoria?: string;
  valor: number;
  vencimento: string;
  vencimento_label?: string;
  liquidacao?: string | null;
  status?: string;
  nfe_numero?: string | null;
}

interface FinTranscriptPDFProps {
  open: boolean;
  onClose: () => void;
  lancamentos: LancamentoLite[];
  periodLabel: string;
  businessName?: string;
  /** Quando true, mostra só os favoritados (passa o Set externamente). */
  onlyFavs?: Set<number> | null;
  /** Operador que está fechando — vai pro rodapé "Assinatura". */
  operatorName?: string;
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function dateNow(): string {
  const d = new Date();
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

export function FinTranscriptPDF({
  open,
  onClose,
  lancamentos,
  periodLabel,
  businessName,
  onlyFavs = null,
  operatorName,
}: FinTranscriptPDFProps) {
  // Atalho Esc fecha + Ctrl+P imprime
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
        return;
      }
      // Cmd/Ctrl+P já é nativo do browser — não interceptamos, só deixamos rolar
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose]);

  const rows = useMemo(() => {
    if (!onlyFavs || onlyFavs.size === 0) return lancamentos;
    return lancamentos.filter((l) => onlyFavs.has(l.id));
  }, [lancamentos, onlyFavs]);

  const totals = useMemo(() => {
    let entradas = 0;
    let saidas = 0;
    for (const r of rows) {
      if (r.kind === 'receivable') entradas += r.valor || 0;
      else saidas += r.valor || 0;
    }
    return { entradas, saidas, saldo: entradas - saidas, qtd: rows.length };
  }, [rows]);

  if (!open) return null;

  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="fin-transcript-overlay" role="dialog" aria-label="Folha imprimível">
      <div className="fin-transcript-bar">
        <div className="fin-transcript-bar-l">
          <strong>📄 Folha imprimível</strong>
          <small>· {rows.length} lançamento{rows.length === 1 ? '' : 's'}{onlyFavs && onlyFavs.size > 0 ? ' (só favoritos)' : ''}</small>
        </div>
        <div className="fin-transcript-bar-r">
          <button type="button" className="fin-transcript-btn primary" onClick={handlePrint} title="Ctrl+P">
            🖨 Imprimir
          </button>
          <button type="button" className="fin-transcript-btn" onClick={onClose} title="Esc">
            ✕ Fechar
          </button>
        </div>
      </div>

      <div className="fin-transcript-page">
        <header className="fin-transcript-h">
          <div>
            <h1>Financeiro · Demonstrativo</h1>
            <p>
              {businessName && <span>{businessName} · </span>}
              {periodLabel} · emitido em {dateNow()}
            </p>
          </div>
          <div className="fin-transcript-totals">
            <small>Total líquido</small>
            <b>{brl(totals.saldo)}</b>
          </div>
        </header>

        <table className="fin-transcript-tbl">
          <thead>
            <tr>
              <th style={{ width: 80 }}>Vencimento</th>
              <th>Descrição / NF-e</th>
              <th>Contraparte</th>
              <th>Categoria</th>
              <th style={{ width: 70 }}>Status</th>
              <th style={{ width: 110, textAlign: 'right' }}>Valor</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 && (
              <tr><td colSpan={6} style={{ textAlign: 'center', padding: '32px 12px', color: '#777' }}>
                Sem lançamentos no período.
              </td></tr>
            )}
            {rows.map((r) => (
              <tr key={r.id}>
                <td className="mono">{r.vencimento_label || r.vencimento}</td>
                <td>
                  <b>{r.descricao}</b>
                  {r.nfe_numero && <span className="fin-transcript-nfe"> · NF-e {r.nfe_numero}</span>}
                </td>
                <td>
                  {r.contraparte}
                  {r.contraparte_doc && <small style={{ display: 'block', color: '#777' }}>{r.contraparte_doc}</small>}
                </td>
                <td>{r.categoria || '—'}</td>
                <td className="mono">{r.status || '—'}</td>
                <td className={'mono right ' + (r.kind === 'receivable' ? 'pos' : 'neg')}>
                  {r.kind === 'receivable' ? '+' : '−'} {brl(r.valor).replace('R$', '').trim()}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={4} style={{ textAlign: 'right' }}><strong>Entradas</strong></td>
              <td className="mono right pos" colSpan={2}><b>{brl(totals.entradas)}</b></td>
            </tr>
            <tr>
              <td colSpan={4} style={{ textAlign: 'right' }}><strong>Saídas</strong></td>
              <td className="mono right neg" colSpan={2}><b>{brl(totals.saidas)}</b></td>
            </tr>
            <tr>
              <td colSpan={4} style={{ textAlign: 'right' }}><strong>Saldo líquido</strong></td>
              <td className="mono right" colSpan={2}><b>{brl(totals.saldo)}</b></td>
            </tr>
          </tfoot>
        </table>

        <footer className="fin-transcript-f">
          <div className="fin-transcript-sig">
            <div className="fin-transcript-sig-line" />
            <small>{operatorName ? `${operatorName} · Financeiro` : 'Financeiro'}</small>
          </div>
          <div className="fin-transcript-sig">
            <div className="fin-transcript-sig-line" />
            <small>Contabilidade</small>
          </div>
        </footer>

        <p className="fin-transcript-foot-meta">
          Documento gerado automaticamente · oimpresso.com · {dateNow()}
        </p>
      </div>
    </div>
  );
}

export default FinTranscriptPDF;
