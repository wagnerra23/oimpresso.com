// FinMonthResume — Cowork KB-9.75 Financeiro Onda 9
// (narrativa exec do mês — substitui o alert do botão "✦ Resumir mês").
//
// Refs:
//  - prototipo-ui/financeiro-ai.jsx — FinAiResume
//  - FinMonthDigest.tsx — base de cálculo (Onda 6)
//
// Estratégia 2-fases:
//   Fase 1 (Onda 9 — esta): narrativa COMPUTACIONAL (pure compute). Gera
//   texto markdown-style direto dos lancamentos+kpis. Zero backend, zero LLM.
//   Funciona offline + pra biz=4 que ainda não tem JanaService.
//
//   Fase 2 (futura): plugar JanaService quando disponível na main —
//   sinalizar via FEATURE_JANA_AVAILABLE e prompt enviando o digest pre-computado
//   pra LLM enriquecer. Variável fica como toggle pra A/B test.
//
// Output: dialog modal markdown-rendered + botão "Copiar pro WhatsApp" pra Eliana
// mandar pro escritório.
//
// Pure compute, sem backend, sem LLM. Wagner regra Tier 0 multi-tenant safe.

import { useEffect, useMemo, useState, type ReactNode } from 'react';

interface LancamentoLite {
  id: number;
  contraparte: string;
  categoria?: string;
  valor: number;
  kind?: 'receivable' | 'payable';
  status?: string;
  vencimento?: string;
  liquidacao?: string | null;
}

interface KpiSnapshot {
  saldo_previsto: number;
  recebido: { valor: number; qtd: number };
  a_receber: { valor: number; qtd: number };
  pago: { valor: number; qtd: number };
  a_pagar: { valor: number; qtd: number };
}

interface ResumeBlock {
  title: string;
  bullets: string[];
}

function brl(n: number): string {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function pct(num: number, den: number): string {
  if (den === 0) return '0%';
  return Math.round((num / den) * 100) + '%';
}

/**
 * Computa o digest narrativo do mês. Exportado pra também ser usado
 * em testes + futura integração JanaService (que enriquecerá esse output).
 */
export function buildMonthResume(
  lancamentos: LancamentoLite[],
  kpis: KpiSnapshot,
  periodLabel: string,
): ResumeBlock[] {
  const blocks: ResumeBlock[] = [];

  const netRealizado = kpis.recebido.valor - kpis.pago.valor;
  const netPendente = kpis.a_receber.valor - kpis.a_pagar.valor;
  const saudeIcone = kpis.saldo_previsto >= 0 ? '🟢' : '🔴';
  const tendencia = netRealizado >= 0 ? 'positiva' : 'negativa';

  blocks.push({
    title: `${saudeIcone} Visão geral · ${periodLabel}`,
    bullets: [
      `Saldo previsto fim de período: *${brl(kpis.saldo_previsto)}*`,
      `Realizado até agora: ${brl(netRealizado)} (tendência ${tendencia})`,
      `Pendente: ${brl(netPendente)} (${kpis.a_receber.qtd} a receber / ${kpis.a_pagar.qtd} a pagar)`,
    ],
  });

  // Top contrapartes (in + out)
  const partyMap: Record<string, { in: number; out: number; count: number }> = {};
  for (const l of lancamentos) {
    const k = l.contraparte || 'Sem contraparte';
    if (!partyMap[k]) partyMap[k] = { in: 0, out: 0, count: 0 };
    partyMap[k].count++;
    if (l.kind === 'receivable') partyMap[k].in += l.valor || 0;
    else partyMap[k].out += l.valor || 0;
  }
  const partiesIn = Object.entries(partyMap)
    .filter(([, v]) => v.in > 0)
    .sort((a, b) => b[1].in - a[1].in)
    .slice(0, 3);
  const partiesOut = Object.entries(partyMap)
    .filter(([, v]) => v.out > 0)
    .sort((a, b) => b[1].out - a[1].out)
    .slice(0, 3);

  if (partiesIn.length > 0) {
    blocks.push({
      title: '📈 Top contrapartes (entradas)',
      bullets: partiesIn.map(([nome, v]) =>
        `${nome} — ${brl(v.in)} (${pct(v.in, kpis.recebido.valor + kpis.a_receber.valor)})`),
    });
  }
  if (partiesOut.length > 0) {
    blocks.push({
      title: '📉 Top contrapartes (saídas)',
      bullets: partiesOut.map(([nome, v]) =>
        `${nome} — ${brl(v.out)} (${pct(v.out, kpis.pago.valor + kpis.a_pagar.valor)})`),
    });
  }

  // Categorias com maior peso
  const catMap: Record<string, number> = {};
  for (const l of lancamentos) {
    const k = l.categoria || 'Sem categoria';
    catMap[k] = (catMap[k] || 0) + (l.valor || 0);
  }
  const topCats = Object.entries(catMap).sort((a, b) => b[1] - a[1]).slice(0, 3);
  if (topCats.length > 0) {
    const totalGeral = topCats.reduce((s, [, v]) => s + v, 0);
    blocks.push({
      title: '🏷️ Categorias com maior peso',
      bullets: topCats.map(([nome, v]) => `${nome} — ${brl(v)} (${pct(v, totalGeral)})`),
    });
  }

  // Alertas
  const atrasados = lancamentos.filter((l) => l.status === 'atrasado');
  const vencendo = lancamentos.filter((l) => l.status === 'vencendo');
  const alertBullets: string[] = [];
  if (atrasados.length > 0) {
    const total = atrasados.reduce((s, l) => s + (l.valor || 0), 0);
    alertBullets.push(`⚠️ ${atrasados.length} título${atrasados.length === 1 ? '' : 's'} atrasado${atrasados.length === 1 ? '' : 's'} (${brl(total)})`);
  }
  if (vencendo.length > 0) {
    const total = vencendo.reduce((s, l) => s + (l.valor || 0), 0);
    alertBullets.push(`🔔 ${vencendo.length} título${vencendo.length === 1 ? '' : 's'} vencendo (${brl(total)})`);
  }
  if (alertBullets.length === 0) {
    alertBullets.push('✅ Nenhum título atrasado ou vencendo no período.');
  }
  blocks.push({ title: '🚨 Alertas', bullets: alertBullets });

  // Recomendação derivada da posição
  const recos: string[] = [];
  if (kpis.a_pagar.valor > kpis.a_receber.valor && netPendente < 0) {
    recos.push(`Saídas pendentes (${brl(kpis.a_pagar.valor)}) maiores que entradas pendentes (${brl(kpis.a_receber.valor)}). Acompanhar de perto o caixa nos próximos dias.`);
  }
  if (atrasados.length > 0) {
    recos.push('Cobrar os atrasados antes do fechamento mensal — usar trilha "☑ Fechamento" pra checklist.');
  }
  if (recos.length === 0) {
    recos.push('Posição saudável. Continuar conferindo lançamentos via toggle "Conferido" e usar trilha de fechamento.');
  }
  blocks.push({ title: '💡 Recomendação', bullets: recos });

  return blocks;
}

/**
 * Gera texto plain pra clipboard (formato WhatsApp/email).
 */
export function resumeToPlainText(blocks: ResumeBlock[]): string {
  return blocks
    .map((b) => `${b.title}\n${b.bullets.map((x) => `• ${x}`).join('\n')}`)
    .join('\n\n');
}

interface FinMonthResumeDialogProps {
  open: boolean;
  onClose: () => void;
  lancamentos: LancamentoLite[];
  kpis: KpiSnapshot;
  periodLabel: string;
  businessName?: string;
}

export function FinMonthResumeDialog({
  open,
  onClose,
  lancamentos,
  kpis,
  periodLabel,
  businessName,
}: FinMonthResumeDialogProps): ReactNode {
  const [copied, setCopied] = useState(false);

  // Atalho Esc fecha
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open, onClose]);

  const blocks = useMemo(
    () => buildMonthResume(lancamentos, kpis, periodLabel),
    [lancamentos, kpis, periodLabel],
  );

  const handleCopy = async () => {
    const text = resumeToPlainText(blocks);
    const header = `*Resumo financeiro · ${periodLabel}${businessName ? ` — ${businessName}` : ''}*\n\n`;
    try {
      await navigator.clipboard.writeText(header + text);
      setCopied(true);
      setTimeout(() => setCopied(false), 2400);
    } catch {
      // Fallback simples — não bloqueia
      setCopied(false);
    }
  };

  if (!open) return null;

  return (
    <>
      <div className="fin-resume-backdrop" onClick={onClose} aria-hidden="true" />
      <div className="fin-resume-dialog" role="dialog" aria-labelledby="fin-resume-title">
        <header className="fin-resume-h">
          <div>
            <h2 id="fin-resume-title">✦ Resumo executivo · {periodLabel}</h2>
            <small>
              {businessName ? `${businessName} · ` : ''}
              narrativa computacional · pode copiar pro WhatsApp
            </small>
          </div>
          <button type="button" className="fin-resume-x" onClick={onClose} aria-label="Fechar">×</button>
        </header>

        <div className="fin-resume-body">
          {blocks.map((b) => (
            <section key={b.title} className="fin-resume-block">
              <h3>{b.title}</h3>
              <ul>
                {b.bullets.map((bullet, i) => (
                  <li key={i}>{bullet}</li>
                ))}
              </ul>
            </section>
          ))}
        </div>

        <footer className="fin-resume-f">
          <small>
            Versão 1 (compute-based) · Fase 2 plugará JanaService LLM quando disponível
          </small>
          <div className="fin-resume-f-actions">
            <button type="button" className="fin-resume-btn" onClick={onClose}>Fechar</button>
            <button
              type="button"
              className={'fin-resume-btn primary' + (copied ? ' copied' : '')}
              onClick={handleCopy}
            >
              {copied ? '✓ Copiado' : '📋 Copiar pro WhatsApp'}
            </button>
          </div>
        </footer>
      </div>
    </>
  );
}

export default FinMonthResumeDialog;
