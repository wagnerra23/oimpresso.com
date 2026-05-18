// FinChecklistFechamento — Cowork KB-9.75 Financeiro Onda 7 R3 Guia
// (trilha 12 passos do fechamento mensal · checkbox + progress + persist).
//
// Refs:
//  - prototipo-ui/financeiro-output.jsx — FinChecklistFechamento (canonical)
//
// Storage: localStorage[oimpresso.financeiro.fechamento.{YYYY-MM}] = { stepId: when }
// 12 passos canônicos do fechamento do mês — pode evoluir pra backend audit
// (`fin_closing_log`) se Eliana pedir mais robustez.

import { useCallback, useEffect, useMemo, useState } from 'react';

interface FechamentoStep {
  id: string;
  group: 'reconcile' | 'review' | 'export' | 'communicate';
  label: string;
  hint?: string;
}

const STEPS: FechamentoStep[] = [
  // Reconcile (4)
  { id: 'extrato-inter', group: 'reconcile', label: 'Bater extrato Inter com lançamentos',  hint: 'Conciliação OFX · diferenças >R$5 anotar' },
  { id: 'caixa-fisico',  group: 'reconcile', label: 'Conferir caixa físico do balcão',       hint: 'Sangria + suprimento = saldo contábil' },
  { id: 'boletos-pagos', group: 'reconcile', label: 'Marcar boletos pagos sem match',         hint: 'Confirmar manual em Conciliação' },
  { id: 'pix-conta-pj',  group: 'reconcile', label: 'Lançar PIX recebidos na conta PJ',       hint: 'PIX direto PF = aporte sócio' },

  // Review (4)
  { id: 'anomaly-fix',     group: 'review', label: 'Revisar anomalias destacadas pela IA',     hint: 'Outliers >25% vs média histórica' },
  { id: 'inadimplencia',   group: 'review', label: 'Atualizar status inadimplentes',           hint: 'Mover atrasados >30d pra cobrança' },
  { id: 'categorize-novo', group: 'review', label: 'Categorizar lançamentos sem classificação', hint: 'Sem categoria → distorce DRE' },
  { id: 'wagner-conferir', group: 'review', label: 'Wagner confere lançamentos > R$ 5k',       hint: 'Toggle Conferido em cada um' },

  // Export (2)
  { id: 'dre-export',  group: 'export', label: 'Exportar DRE pra contador', hint: 'PDF + Excel · enviar até dia 5' },
  { id: 'fluxo-12m',   group: 'export', label: 'Atualizar fluxo de caixa 12m',  hint: 'Previsão entrada/saída próximos meses' },

  // Communicate (2)
  { id: 'reuniao-socio', group: 'communicate', label: 'Pauta da reunião com sócio',     hint: 'KPIs + decisões pendentes' },
  { id: 'avisar-equipe', group: 'communicate', label: 'Avisar equipe do mês fechado',    hint: 'Lançamentos do mês anterior bloqueados' },
];

const GROUP_LABEL: Record<FechamentoStep['group'], string> = {
  reconcile: 'Conciliação',
  review: 'Revisão',
  export: 'Exportação',
  communicate: 'Comunicação',
};

interface CompletionMap {
  [stepId: string]: string; // ISO timestamp
}

function lsKey(periodLabel: string): string {
  // periodLabel típico "Maio 2026" — normaliza pra "2026-05"
  const m = periodLabel.match(/(\d{4})-?(\d{2})?/);
  const slug = m ? `${m[1]}-${m[2] ?? '00'}` : periodLabel.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase();
  return `oimpresso.financeiro.fechamento.${slug}`;
}

function loadCompletion(key: string): CompletionMap {
  if (typeof window === 'undefined') return {};
  try {
    return JSON.parse(window.localStorage.getItem(key) || '{}');
  } catch (_) {
    return {};
  }
}

function saveCompletion(key: string, m: CompletionMap): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(key, JSON.stringify(m));
  } catch (_) {
    /* ls indisponível */
  }
}

interface FinChecklistFechamentoProps {
  periodLabel: string;
  /** Quando true, mostra trilha. Quando false, retorna `null` (caller controla open). */
  open: boolean;
  onClose: () => void;
}

export function FinChecklistFechamento({ periodLabel, open, onClose }: FinChecklistFechamentoProps) {
  const key = useMemo(() => lsKey(periodLabel), [periodLabel]);
  const [completion, setCompletion] = useState<CompletionMap>(() => loadCompletion(key));

  useEffect(() => {
    saveCompletion(key, completion);
  }, [key, completion]);

  // Reload quando periodLabel muda (mes diferente)
  useEffect(() => {
    setCompletion(loadCompletion(key));
  }, [key]);

  const toggle = useCallback((stepId: string) => {
    setCompletion((prev) => {
      const next = { ...prev };
      if (next[stepId]) delete next[stepId];
      else next[stepId] = new Date().toISOString();
      return next;
    });
  }, []);

  const doneCount = Object.keys(completion).length;
  const total = STEPS.length;
  const pct = total > 0 ? Math.round((doneCount / total) * 100) : 0;

  const groups = useMemo(() => {
    const m = new Map<FechamentoStep['group'], FechamentoStep[]>();
    STEPS.forEach((s) => {
      if (!m.has(s.group)) m.set(s.group, []);
      m.get(s.group)!.push(s);
    });
    return Array.from(m.entries());
  }, []);

  if (!open) return null;

  return (
    <>
      <div className="fin-checklist-backdrop" onClick={onClose} aria-hidden="true" />
      <div className="fin-checklist-dialog" role="dialog" aria-labelledby="fin-checklist-title">
        <header className="fin-checklist-h">
          <div>
            <h2 id="fin-checklist-title">Fechamento · {periodLabel}</h2>
            <small>Trilha de 12 passos · {doneCount}/{total} concluídos · {pct}%</small>
          </div>
          <button type="button" className="fin-checklist-x" onClick={onClose} aria-label="Fechar trilha">×</button>
        </header>

        <div className="fin-checklist-progress">
          <div className="fin-checklist-bar" style={{ width: `${pct}%` }} aria-hidden="true" />
        </div>

        <div className="fin-checklist-body">
          {groups.map(([group, steps]) => (
            <section key={group} className="fin-checklist-group">
              <h3>{GROUP_LABEL[group]}</h3>
              <ul>
                {steps.map((step) => {
                  const done = !!completion[step.id];
                  return (
                    <li key={step.id} className={`fin-checklist-step ${done ? 'done' : ''}`}>
                      <button
                        type="button"
                        className="fin-checklist-check"
                        onClick={() => toggle(step.id)}
                        aria-pressed={done}
                        aria-label={`Marcar ${step.label} como ${done ? 'pendente' : 'feito'}`}
                      >
                        {done ? '✓' : ''}
                      </button>
                      <div className="fin-checklist-step-body">
                        <b>{step.label}</b>
                        {step.hint && <small>{step.hint}</small>}
                      </div>
                      {done && completion[step.id] && (
                        <time className="fin-checklist-when">
                          {new Date(completion[step.id]).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
                        </time>
                      )}
                    </li>
                  );
                })}
              </ul>
            </section>
          ))}
        </div>

        <footer className="fin-checklist-f">
          <small>Progresso salvo localmente · {pct === 100 ? 'Mês fechado! 🎯' : `${total - doneCount} passo${total - doneCount === 1 ? '' : 's'} pendente${total - doneCount === 1 ? '' : 's'}`}</small>
        </footer>
      </div>
    </>
  );
}

export default FinChecklistFechamento;
