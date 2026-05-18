// Onda 14 v9,75 — Command Palette ⌘K + fallback "Perguntar à Jana".
import { useEffect, useMemo, useRef, useState } from 'react';
import { Search, Sparkles } from 'lucide-react';
import { askJana } from './useJanaAsk';

interface CmdSub { id: number; client: string; cnpj?: string | null; os?: string | null; plan_name?: string }
interface CmdPlan { id: number; name: string; price?: number; cycle_label?: string }
interface PickItem { kind: 'sub' | 'plan'; id: number | string; label: string }
interface Props {
  subs: CmdSub[];
  plans: CmdPlan[];
  onClose: () => void;
  onPick: (item: PickItem) => void;
}

const BRL = (n: number) => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function CmdPalette({ subs, plans, onClose, onPick }: Props) {
  const [q, setQ] = useState('');
  const [sel, setSel] = useState(0);
  const [iaLoading, setIaLoading] = useState(false);
  const [iaText, setIaText] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => { inputRef.current?.focus(); }, []);

  const results = useMemo(() => {
    const t = q.trim().toLowerCase();
    if (!t) {
      return [
        ...subs.slice(0, 5).map<PickItem & { sub: string }>((s) => ({
          kind: 'sub', id: s.id, label: s.client, sub: 'Assinante · ' + (s.plan_name || ''),
        })),
        ...plans.map<PickItem & { sub: string }>((p) => ({
          kind: 'plan', id: p.id, label: p.name, sub: `Plano · ${p.price ? BRL(p.price) : ''}/${p.cycle_label || ''}`,
        })),
      ];
    }
    const subHits = subs.filter((s) =>
      (s.client || '').toLowerCase().includes(t) ||
      (s.cnpj || '').toLowerCase().includes(t) ||
      (s.os || '').toLowerCase().includes(t),
    ).slice(0, 8).map<PickItem & { sub: string }>((s) => ({
      kind: 'sub', id: s.id, label: s.client, sub: `Assinante · ${s.os || '—'}`,
    }));
    const planHits = plans.filter((p) => (p.name || '').toLowerCase().includes(t))
      .map<PickItem & { sub: string }>((p) => ({
        kind: 'plan', id: p.id, label: p.name, sub: `Plano · ${p.price ? BRL(p.price) : ''}`,
      }));
    return [...subHits, ...planHits];
  }, [q, subs, plans]);

  useEffect(() => { setSel(0); setIaText(''); }, [q]);

  async function askIa() {
    setIaLoading(true);
    const ctx = `Lista total assinantes (${subs.length}):\n` + subs.slice(0, 30).map((s) => `- ${s.client}${s.cnpj ? ' (' + s.cnpj + ')' : ''}`).join('\n');
    const out = await askJana(`Pergunta sobre cobrança recorrente: "${q}"`, ctx);
    setIaLoading(false);
    setIaText(out.text);
  }

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      else if (e.key === 'ArrowDown') { e.preventDefault(); setSel((s) => Math.min(s + 1, results.length - 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setSel((s) => Math.max(s - 1, 0)); }
      else if (e.key === 'Enter') {
        e.preventDefault();
        const r = results[sel];
        if (r) onPick(r);
        else if (q.trim() && !iaText && !iaLoading) askIa();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [results, sel, q, iaText, iaLoading]);

  return (
    <div role="dialog" aria-modal="true" onClick={onClose} className="fixed inset-0 z-50 flex items-start justify-center bg-zinc-900/50 backdrop-blur-sm pt-32">
      <div onClick={(e) => e.stopPropagation()} className="w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-zinc-200 overflow-hidden">
        <div className="flex items-center gap-2 border-b border-zinc-100 px-3 py-2.5">
          <Search size={16} className="text-zinc-400" />
          <input
            ref={inputRef}
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Buscar assinante, CNPJ, OS — ou perguntar à Jana"
            className="flex-1 bg-transparent text-sm outline-none placeholder:text-zinc-400"
          />
          <kbd className="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-mono text-zinc-500 ring-1 ring-zinc-200">Esc</kbd>
        </div>
        <ul className="max-h-96 overflow-y-auto py-1">
          {results.length === 0 && q.trim() && !iaText && !iaLoading && (
            <li className="p-4 text-center">
              <p className="mb-2 text-sm text-zinc-500">Nada encontrado para "<b>{q}</b>".</p>
              <button type="button" onClick={askIa} className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-700">
                <Sparkles size={12} /> Perguntar à Jana →
              </button>
            </li>
          )}
          {iaLoading && (
            <li className="px-4 py-3 text-xs text-zinc-500">
              <Sparkles size={12} className="inline animate-pulse" /> Jana pensando…
            </li>
          )}
          {iaText && (
            <li className="border-y border-violet-100 bg-violet-50/50 px-4 py-3 text-sm text-zinc-800">
              <div className="mb-1 text-[10px] font-semibold uppercase tracking-wider text-violet-700">
                <Sparkles size={10} className="inline" /> Jana
              </div>
              <div className="whitespace-pre-wrap text-xs">{iaText}</div>
            </li>
          )}
          {results.map((r, i) => (
            <li
              key={`${r.kind}-${r.id}`}
              onMouseEnter={() => setSel(i)}
              onClick={() => onPick(r)}
              className={`flex cursor-pointer items-center gap-3 px-4 py-2 text-sm ${i === sel ? 'bg-violet-50' : 'hover:bg-zinc-50'}`}
            >
              <span className={`rounded px-1.5 py-0.5 text-[10px] font-semibold ${r.kind === 'sub' ? 'bg-zinc-200 text-zinc-700' : 'bg-blue-200 text-blue-800'}`}>
                {r.kind === 'sub' ? 'assin.' : 'plano'}
              </span>
              <b className="flex-1 truncate text-zinc-900">{r.label}</b>
              <small className="truncate text-xs text-zinc-500">{(r as { sub: string }).sub}</small>
            </li>
          ))}
        </ul>
        <footer className="flex items-center justify-between gap-2 border-t border-zinc-100 px-3 py-2 text-[10px] text-zinc-500">
          <span><kbd className="rounded bg-zinc-100 px-1 ring-1 ring-zinc-200">↑↓</kbd> navegar</span>
          <span><kbd className="rounded bg-zinc-100 px-1 ring-1 ring-zinc-200">↵</kbd> selecionar</span>
          <span><kbd className="rounded bg-zinc-100 px-1 ring-1 ring-zinc-200">Esc</kbd> fechar</span>
        </footer>
      </div>
    </div>
  );
}
