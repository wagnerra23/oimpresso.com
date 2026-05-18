// Onda 13 v9,75 — Modo apresentação fullscreen (dashboard limpo).
import { useEffect, useMemo } from 'react';

interface KpisLite { mrr: number; mrr_delta: number; churn_count: number; churn_rate: number; active_count: number; total_ltv: number }
interface SubLite { status: string; plan_id: number; next_value: number }
interface PlanLite { id: number; name: string; price: number }
interface Props { kpis: KpisLite; subs: SubLite[]; plans: PlanLite[]; onClose: () => void }

const PLAN_HUES = [295, 250, 60, 145, 200];
const BRL = (n: number) => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const BRLshort = (n: number) =>
  Math.abs(n) >= 1000 ? `R$ ${(n / 1000).toFixed(1)}k` : BRL(n);

export default function PresentationMode({ kpis, subs, plans, onClose }: Props) {
  useEffect(() => {
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prev;
      window.removeEventListener('keydown', onKey);
    };
  }, [onClose]);

  const byPlan = useMemo(() => {
    const active = subs.filter((s) => s.status !== 'cancelada');
    const rows = plans.map((p) => {
      const planSubs = active.filter((s) => s.plan_id === p.id);
      const mrr = planSubs.filter((s) => s.status === 'em_dia').reduce((acc, s) => acc + (s.next_value || p.price), 0);
      return { ...p, count: planSubs.length, mrr };
    });
    return rows.sort((a, b) => b.mrr - a.mrr);
  }, [subs, plans]);
  const maxMrr = Math.max(...byPlan.map((p) => p.mrr), 1);
  const deltaPct = kpis.mrr_delta && kpis.mrr ? ((kpis.mrr_delta / Math.max(kpis.mrr - kpis.mrr_delta, 1)) * 100).toFixed(1) : '0';

  return (
    <div role="dialog" aria-modal="true" className="fixed inset-0 z-50 overflow-y-auto bg-gradient-to-br from-zinc-900 via-zinc-950 to-zinc-900 text-white">
      <button type="button" onClick={onClose} className="fixed top-4 right-4 rounded-lg bg-white/10 px-3 py-1.5 text-xs hover:bg-white/20">
        Sair · Esc
      </button>
      <div className="mx-auto max-w-6xl px-8 py-12">
        <header className="mb-10 flex items-end justify-between">
          <div>
            <h1 className="text-4xl font-bold tracking-tight">Oimpresso</h1>
            <p className="text-sm text-zinc-400">Cobrança Recorrente · {new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}</p>
          </div>
        </header>
        <div className="grid grid-cols-4 gap-6">
          <div className="col-span-2 rounded-2xl bg-emerald-500/10 p-6 ring-1 ring-emerald-500/30">
            <div className="text-[11px] uppercase tracking-wider text-emerald-300">MRR · receita recorrente mensal</div>
            <div className="mt-2 text-5xl font-bold tabular-nums">{BRL(kpis.mrr)}</div>
            <div className="mt-2 text-sm text-emerald-400">↑ {BRL(kpis.mrr_delta)} vs mês anterior · +{deltaPct}%</div>
          </div>
          <div className="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
            <div className="text-[11px] uppercase tracking-wider text-zinc-400">Ativas</div>
            <div className="mt-2 text-4xl font-bold tabular-nums">{kpis.active_count}</div>
          </div>
          <div className="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
            <div className="text-[11px] uppercase tracking-wider text-zinc-400">Churn mês</div>
            <div className="mt-2 text-4xl font-bold tabular-nums text-amber-400">{kpis.churn_count}</div>
            <div className="text-xs text-zinc-400">taxa {kpis.churn_rate}%</div>
          </div>
        </div>
        <section className="mt-10">
          <h2 className="mb-4 text-xs uppercase tracking-wider text-zinc-400">Distribuição por plano</h2>
          <ul className="space-y-3">
            {byPlan.map((p, i) => {
              const hue = PLAN_HUES[i % PLAN_HUES.length];
              return (
                <li key={p.id} className="grid grid-cols-[200px_60px_1fr_120px] items-center gap-4 text-sm">
                  <span className="font-medium">{p.name}</span>
                  <span className="text-zinc-400">{p.count} assin.</span>
                  <div className="h-2 overflow-hidden rounded-full bg-white/10">
                    <div className="h-full rounded-full" style={{ width: `${(p.mrr / maxMrr) * 100}%`, background: `oklch(0.65 0.15 ${hue})` }} />
                  </div>
                  <span className="text-right font-mono tabular-nums">{BRL(p.mrr)}<small className="text-zinc-500">/mês</small></span>
                </li>
              );
            })}
          </ul>
        </section>
        <footer className="mt-12 border-t border-white/10 pt-4 text-center text-xs text-zinc-500">
          Modo apresentação · dados sensíveis ocultos · LTV total {BRLshort(kpis.total_ltv)}
        </footer>
      </div>
    </div>
  );
}
