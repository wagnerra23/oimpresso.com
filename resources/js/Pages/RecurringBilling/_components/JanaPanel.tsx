// Onda 15 v9,75 — JanaPanel no Drawer (3 tabs: Sugerir/Resumir/Perguntar).
import { useEffect, useState } from 'react';
import { Sparkles } from 'lucide-react';
import { askJana } from './useJanaAsk';

interface SubLite {
  id: number; client: string; status: string; method: string;
  paid: number; missed: number; ltv: number; since: string | null; plan_name: string;
}
interface Props { sub: SubLite }

type Tab = 'sugerir' | 'resumir' | 'perguntar';

const BRL = (n: number) => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function clientDiagnostic(sub: SubLite): string {
  if (sub.status === 'falhou') {
    return `Assinatura ${sub.client} com ${sub.missed} falhas consecutivas (método ${sub.method}). Recomendado: contato manual + considerar suspensão se inadimplência confirmada.`;
  }
  if (sub.status === 'retentando') {
    return `Retentativa em andamento. Verificar canal (boleto/${sub.method}) + confirmar com cliente se recebeu link de pagamento. Se 3ª falha → abrir troubleshooter.`;
  }
  return `Assinatura saudável. Cobrança recorrente ${sub.method} sem ocorrências.`;
}

function clientSummary(sub: SubLite): string {
  const months = sub.paid > 0 ? `${sub.paid} cobranças pagas` : 'sem pagamentos ainda';
  const since = sub.since || '—';
  return `${sub.client} · plano ${sub.plan_name}\nLTV ${BRL(sub.ltv)} · ${months} · ${sub.missed} falhas\nAtivo desde ${since}.`;
}

export default function JanaPanel({ sub }: Props) {
  const isCritical = sub.status === 'falhou' || sub.status === 'retentando';
  const [tab, setTab] = useState<Tab>(isCritical ? 'sugerir' : 'resumir');
  const [iaQ, setIaQ] = useState('');
  const [iaLoading, setIaLoading] = useState(false);
  const [iaResp, setIaResp] = useState('');

  useEffect(() => {
    setTab(isCritical ? 'sugerir' : 'resumir');
    setIaQ('');
    setIaResp('');
  }, [sub.id, isCritical]);

  async function ask() {
    if (!iaQ.trim()) return;
    setIaLoading(true);
    const ctx = clientSummary(sub);
    const out = await askJana(iaQ, ctx);
    setIaLoading(false);
    setIaResp(out.text);
  }

  if (sub.status === 'cancelada') return null;

  return (
    <div className="rounded-lg border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-3">
      <header className="mb-2 flex items-center gap-2">
        <span className="inline-flex items-center gap-1 rounded bg-violet-200 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-800">
          <Sparkles size={10} /> Jana · IA
        </span>
        <nav className="flex gap-1">
          {(['sugerir', 'resumir', 'perguntar'] as Tab[]).map((t) => (
            <button
              key={t}
              type="button"
              onClick={() => setTab(t)}
              className={`rounded px-2 py-0.5 text-[11px] font-medium transition ${
                tab === t ? 'bg-violet-600 text-white' : 'text-violet-700 hover:bg-violet-100'
              }`}
            >
              {t === 'sugerir' && isCritical && '⚠ '}
              {t.charAt(0).toUpperCase() + t.slice(1)}
            </button>
          ))}
        </nav>
      </header>

      {tab === 'sugerir' && (
        <div>
          <div className="text-xs text-zinc-700">{clientDiagnostic(sub)}</div>
          <button type="button" disabled title="Em breve" className="mt-2 rounded-lg bg-zinc-200 px-2 py-1 text-[11px] text-zinc-500">
            Aplicar sugestão (em breve)
          </button>
        </div>
      )}

      {tab === 'resumir' && (
        <div>
          <pre className="whitespace-pre-wrap rounded bg-white p-2 text-[11px] text-zinc-700 ring-1 ring-zinc-200">{clientSummary(sub)}</pre>
        </div>
      )}

      {tab === 'perguntar' && (
        <div>
          <div className="flex gap-2">
            <input
              type="text"
              value={iaQ}
              onChange={(e) => setIaQ(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') ask(); }}
              placeholder="Pergunte sobre esta assinatura…"
              className="flex-1 rounded border border-zinc-200 bg-white px-2 py-1 text-xs outline-none focus:border-violet-400"
            />
            <button
              type="button"
              onClick={ask}
              disabled={iaLoading || !iaQ.trim()}
              className="rounded-lg bg-violet-600 px-3 py-1 text-xs font-medium text-white hover:bg-violet-700 disabled:bg-zinc-300"
            >
              {iaLoading ? '…' : 'Enviar'}
            </button>
          </div>
          {iaResp && (
            <div className="mt-2 whitespace-pre-wrap rounded bg-white p-2 text-xs text-zinc-700 ring-1 ring-violet-200">{iaResp}</div>
          )}
        </div>
      )}
    </div>
  );
}
