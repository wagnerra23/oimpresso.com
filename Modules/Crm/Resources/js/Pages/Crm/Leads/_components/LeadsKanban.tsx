// CRM-23 — o kanban de leads na forma do protótipo (`crm-blade.jsx` → `TelaLeads`, `.cb-kanban`).
//
// Uma coluna por estágio de vida do negócio, com o contador; cartões arrastáveis com nome, fonte ·
// responsável, frescor do último acompanhamento e a hora do próximo; "arraste aqui" na coluna vazia.
// Soltar muda o estágio pela rota da Blade (`post-life-stage`), com a coluna-alvo destacada.
//
// Comportamento que segue o código, não o protótipo: as colunas são TODOS os estágios do negócio
// (a Blade não escondia nenhum) e lead sem estágio não aparece no quadro (a Blade também não o mostrava).

import { useState } from 'react';
import { CrmPilula } from '../../_components/CrmBlocos';
import { frescor } from '../../_components/lead';

export interface CartaoLead {
  id: number; nome: string; fonte: string | null; atribuidos: string[];
  ultimo: string | null; proximo: string | null; estagio_id?: string | null;
}

export default function LeadsKanban({ leads, estagios, onMover, onAbrir }: {
  leads: CartaoLead[];
  estagios: { value: string; label: string }[];
  onMover: (lead: CartaoLead, estagioId: string) => void;
  onAbrir: (id: number) => void;
}) {
  const [arrastando, setArrastando] = useState<number | null>(null);
  const [alvo, setAlvo] = useState<string | null>(null);

  const soltar = (estagioId: string, idTexto: string) => {
    setAlvo(null);
    const lead = leads.find((l) => String(l.id) === idTexto);
    if (lead && lead.estagio_id !== estagioId) onMover(lead, estagioId);
  };

  return (
    <div data-contract="crm-leads-kanban" className="flex items-start gap-2.5 overflow-x-auto pb-1">
      {estagios.map((c) => {
        const cartoes = leads.filter((l) => l.estagio_id === c.value);
        const ehAlvo = alvo === c.value;
        return (
          <section
            key={c.value}
            aria-label={`${c.label}: ${cartoes.length}`}
            className="flex min-h-[180px] flex-[0_0_232px] flex-col rounded-[10px] border"
            style={{ background: ehAlvo ? 'var(--accent-soft)' : 'var(--bg-2)', borderColor: ehAlvo ? 'var(--accent)' : 'var(--border)' }}
            onDragOver={(e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; setAlvo(c.value); }}
            onDragLeave={() => setAlvo((a) => (a === c.value ? null : a))}
            onDrop={(e) => { e.preventDefault(); soltar(c.value, e.dataTransfer.getData('text/plain')); }}
          >
            <header className="flex items-center gap-2 border-b px-[11px] py-[9px] text-[11px] uppercase tracking-[0.06em]"
              style={{ color: 'var(--text-mute)', borderBottomColor: 'var(--border)' }}>
              <b className="text-[11.5px]" style={{ color: 'var(--text)' }}>{c.label}</b>
              <span className="ml-auto font-mono text-[10.5px]" style={{ color: 'var(--text-dim)' }}>{cartoes.length}</span>
            </header>
            <div className="flex flex-col gap-2 p-2">
              {cartoes.length === 0 && <p className="m-0 px-1 py-2 text-[12px]" style={{ color: 'var(--text-mute)' }}>arraste aqui</p>}
              {cartoes.map((l) => {
                const f = frescor(l.ultimo);
                const hora = l.proximo?.split(' ')[1] ?? '—';
                return (
                  <div
                    key={l.id}
                    draggable
                    tabIndex={0}
                    role="button"
                    aria-label={`Abrir ${l.nome}`}
                    className="flex cursor-default flex-col gap-[5px] rounded-lg border px-2.5 py-[9px] hover:border-[color-mix(in_oklch,var(--accent)_40%,var(--border))]"
                    style={{ background: 'var(--surface)', borderColor: 'var(--border)', opacity: arrastando === l.id ? 0.5 : 1 }}
                    onDragStart={(e) => { setArrastando(l.id); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(l.id)); }}
                    onDragEnd={() => { setArrastando(null); setAlvo(null); }}
                    onClick={() => onAbrir(l.id)}
                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onAbrir(l.id); } }}
                  >
                    <b className="text-[12.5px] leading-tight" style={{ color: 'var(--text)' }}>{l.nome}</b>
                    <small className="text-[11px]" style={{ color: 'var(--text-mute)' }}>
                      {[l.fonte, l.atribuidos.join(', ')].filter(Boolean).join(' · ') || '—'}
                    </small>
                    <div className="flex items-center justify-between gap-2">
                      {f ? <span title={l.ultimo ?? undefined}><CrmPilula tom={f.tom}>{f.texto}</CrmPilula></span> : <span />}
                      <span className="font-mono text-[11px]" style={{ color: 'var(--text-dim)' }}>{hora}</span>
                    </div>
                  </div>
                );
              })}
            </div>
          </section>
        );
      })}
    </div>
  );
}
