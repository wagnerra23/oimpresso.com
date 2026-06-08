// FinAnomalyDetector — Cowork KB-9.75 Financeiro Onda 6 R2 IA
// (detecta valor outlier vs histórico da contraparte).
//
// Refs:
//  - prototipo-ui/financeiro-ai.jsx — finAiAnomalia + FinAiAnomalia
//
// Pure compute (sem backend, sem LLM). Threshold: ≥25% desvio vs média e
// histórico ≥3 transações pra considerar recorrente.

import { useMemo } from 'react';
import { finPartyHistory } from './FinPartyHistory';

interface LancamentoLite {
  id: number;
  contraparte: string;
  categoria?: string;
  valor: number;
  vencimento?: string | Date | null;
  liquidacao?: string | Date | null;
  status?: string;
  kind?: 'receivable' | 'payable';
}

export type AnomalyKind = 'high' | 'low';

export interface AnomalyInfo {
  kind: AnomalyKind;
  pct: number;
  avg: number;
  desc: string;
  severity: 'low' | 'medium' | 'high';
}

const THRESHOLD_PCT = 25;
const SEVERITY_HIGH = 100;
const SEVERITY_MEDIUM = 50;

export function finAnomalyDetect(row: LancamentoLite, all: LancamentoLite[]): AnomalyInfo | null {
  const h = finPartyHistory(row.contraparte, row.id, all);
  if (!h.isRecurrent || h.avg === 0) return null;

  const diff = row.valor - h.avg;
  const pct = (diff / h.avg) * 100;

  if (Math.abs(pct) < THRESHOLD_PCT) return null;

  const absPct = Math.abs(pct);
  const severity: AnomalyInfo['severity'] =
    absPct >= SEVERITY_HIGH ? 'high' : absPct >= SEVERITY_MEDIUM ? 'medium' : 'low';

  return {
    kind: diff > 0 ? 'high' : 'low',
    pct,
    avg: h.avg,
    severity,
    desc:
      diff > 0
        ? `${pct.toFixed(0)}% acima da média histórica`
        : `${Math.abs(pct).toFixed(0)}% abaixo da média histórica`,
  };
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

interface FinAnomalyDetectorProps {
  row: LancamentoLite;
  all: LancamentoLite[];
}

export function FinAnomalyDetector({ row, all }: FinAnomalyDetectorProps) {
  const anomaly = useMemo(() => finAnomalyDetect(row, all), [row.id, row.valor, all]);

  // Estado vazio canon — aba IA precisa de feedback explícito, não silêncio.
  if (!anomaly) {
    return (
      <div className="fin-anomaly fin-anomaly-ok" data-kind="ok">
        <span className="fin-anomaly-ic">✓</span>
        <div className="fin-anomaly-body">
          <b>Sem desvio detectado</b>
          <small>Valor dentro do padrão histórico da contraparte.</small>
        </div>
      </div>
    );
  }

  const icon = anomaly.kind === 'high' ? '⚠' : '◇';

  return (
    <div
      className={`fin-anomaly fin-anomaly-${anomaly.kind} fin-anomaly-sev-${anomaly.severity}`}
      data-kind={anomaly.kind}
      data-severity={anomaly.severity}
    >
      <span className="fin-anomaly-ic">{icon}</span>
      <div className="fin-anomaly-body">
        <b>Valor fora do padrão</b>
        <small>{anomaly.desc}</small>
        <i>
          Média histórica · {brl(anomaly.avg)} · vs atual {brl(row.valor)}
        </i>
      </div>
    </div>
  );
}

export default FinAnomalyDetector;
