// WriteOffAuditoriaCard.tsx — Card de auditoria mensal "write-off candidatos".
//
// Onda 3 L. Port do fiscal-page.jsx §JanaAuditoria — mas SEM branding IA
// (Wagner: IA não pertence ao Fiscal). Substituído por dicionário determinístico:
// títulos >365d incobráveis automaticamente flagados como write-off candidates.
//
// Renderizado no Cockpit como "callout" lateral. Click no botão abre drawer
// (TODO[CL]) com lista filtrada pra revisão manual.

import { Archive, AlertTriangle, TrendingDown } from 'lucide-react';

export interface WriteOffSummary {
  totalCandidates: number;
  totalValor: number;
  oldestAge: number; // dias
  category: 'incobravel' | 'duplicidade' | 'saldo_virtual';
  scopeLabel: string; // ex "Inadimplência >365d"
}

interface WriteOffAuditoriaCardProps {
  summary: WriteOffSummary | null;
  onReview?: () => void;
}

const CATEGORY_LABEL: Record<WriteOffSummary['category'], string> = {
  incobravel: 'Incobrável >365d',
  duplicidade: 'Duplicidade detectada',
  saldo_virtual: 'Saldo virtual residual',
};

function brl(v: number): string {
  return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function WriteOffAuditoriaCard({ summary, onReview }: WriteOffAuditoriaCardProps) {
  if (!summary || summary.totalCandidates === 0) return null;

  return (
    <section className="fx-writeoff-card" role="region" aria-label="Auditoria fiscal mensal — write-off candidatos">
      <div className="fx-writeoff-h">
        <span className="fx-writeoff-ic">
          <Archive size={16} />
        </span>
        <div className="fx-writeoff-body">
          <b>Auditoria mensal · write-off candidatos</b>
          <small>
            <b>{summary.totalCandidates.toLocaleString('pt-BR')}</b> títulos · {brl(summary.totalValor)} ·{' '}
            mais antigo {summary.oldestAge}d · {CATEGORY_LABEL[summary.category]}
          </small>
        </div>
        <button
          type="button"
          className="fx-btn ghost"
          onClick={onReview}
          disabled={!onReview}
          title={onReview ? 'Abrir drawer pra revisar candidatos' : 'Wire-up no PR seguinte'}
        >
          <TrendingDown size={12} /> Revisar
        </button>
      </div>
      <ul className="fx-writeoff-bullets">
        <li>
          <AlertTriangle size={11} />
          Critério determinístico (sem IA): títulos vencidos &gt;365d sem nenhum pagamento parcial registrado.
        </li>
        <li>
          <AlertTriangle size={11} />
          Write-off requer aprovação Wagner + nota fiscal de baixa (CST/CSOSN 00 — não passa SEFAZ).
        </li>
        <li>
          <AlertTriangle size={11} />
          Recomendação: rodar mensalmente após fechamento. Libera dashboard inadimplência.
        </li>
      </ul>
    </section>
  );
}
