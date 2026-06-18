// InboxGuiaDialog — "Guia" da Caixa: Troubleshooters + Trilhas de onboarding.
// Port do protótipo Cowork inbox-cur.jsx (camada de curadoria — INBOX_TROUBLES +
// INBOX_PATHS + InboxTroubleDialog + InboxPathsDialog). Charter: §Goals "Guia".
//
// - Troubleshooters: diagnóstico guiado sim/não com a REGRA DE NEGÓCIO real
//   (aprovação Wagner > 15%, VIP LTV>5k, 25% urgência). Conteúdo estático.
// - Trilhas: checklist de onboarding do atendente; progresso em localStorage
//   per-user (SEM DB — mesmo anti-hook dos favoritos, charter §Automation Anti-hooks).
// Additivo: NÃO toca a bubble/thread (Caixa = ouro, diff=0 fora desta adição).

import { useEffect, useState } from 'react';
import { ChevronLeft, GraduationCap, LifeBuoy, RotateCcw } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import { Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

// ── Conteúdo (estático — regra de negócio real do oimpresso) ───────────────────
type Fix = { fix: string };
type TroubleStep = { q: string; yes: number | Fix; no: number | Fix };
type Trouble = { id: string; title: string; when: string; hue: number; steps: TroubleStep[] };

const INBOX_TROUBLES: Trouble[] = [
  {
    id: 'it-objpreco', title: 'Cliente diz que tá caro', when: 'objeção de preço aparece em qualquer etapa', hue: 60,
    steps: [
      { q: 'Cliente já fez orçamento com concorrente?', yes: 1, no: { fix: 'Pergunte de quanto é o orçamento dele. Sem comparação, é só percepção — explique custo de matéria-prima e prazo (sangria, acabamento, ICC). Ofereça opção mais simples (gramatura menor).' } },
      { q: 'A diferença é maior que 20%?', yes: { fix: 'Provavelmente é gráfica online em massa, sem acabamento profissional. Mostre que entrega no balcão, retoque grátis, atendimento humano. Não bate preço. Veja #a13 (roteiro de comunicação).' }, no: { fix: 'Dá pra negociar até 10% à vista. Se passar disso, oferece parcelar no PIX em 2x. Anote a regra: nunca tomar a decisão sem o Wagner se passar de 15%.' } },
    ],
  },
  {
    id: 'it-cobranca', title: 'Cliente com boleto atrasado pede pra produzir novo pedido', when: 'tem saldo a receber e pediu orçamento novo', hue: 25,
    steps: [
      { q: 'O atraso é maior que 15 dias?', yes: 1, no: { fix: 'Recebe o pedido, mas exige PIX antes de soltar produção. Lembra do boleto antigo no mesmo papo, sem culpar — "aproveitando que entrou em contato, ainda temos aquele boleto da OS X". Veja #a10.' } },
      { q: 'Cliente recorrente (LTV > R$ 5k)?', yes: { fix: 'Tratamento VIP — Wagner negocia diretamente. Não bloquear nada antes dele decidir. Move conversa pra fila #q-fin com nota interna.' }, no: { fix: 'Cobrança em dia primeiro, produção depois. Sem exceção. Aplique macro /cobrar e mova pra fila #q-fin. Resposta padrão em #a10.' } },
    ],
  },
  {
    id: 'it-prazo', title: 'Cliente pede prazo impossível', when: 'cliente quer urgência fora do nosso SLA', hue: 280,
    steps: [
      { q: 'É cliente fiel (já fez 3+ pedidos)?', yes: 1, no: { fix: 'Não prometa. Dê o prazo real + 1 dia de buffer. Cliente novo aceita melhor "em 4 dias certo" do que "em 3 dias talvez".' } },
      { q: 'Tem material em estoque?', yes: { fix: 'Topa fazer fora do SLA, mas cobra 25% de urgência. Wagner aprova por padrão. Move pra fila #q-prod com tag "urgência".' }, no: { fix: 'Bobina chega só amanhã. Seja honesto: "pra essa medida o material chega quinta, posso entregar sexta 18h". Cliente fiel respeita.' } },
    ],
  },
];

type PathStep = { kind: 'kb' | 'trouble'; ref: string; note: string };
type Path = { id: string; title: string; audience: string; desc: string; hue: number; steps: PathStep[] };

const INBOX_PATHS: Path[] = [
  {
    id: 'obd-atend-novo', title: 'Onboarding · Atendente novo', audience: 'primeira semana', desc: '8 passos pra atender sozinho com segurança.', hue: 220,
    steps: [
      { kind: 'kb', ref: 'a7', note: 'Brief mínimo — 6 dados que toda OS precisa' },
      { kind: 'kb', ref: 'a13', note: 'Comunicar atraso sem queimar relacionamento' },
      { kind: 'trouble', ref: 'it-objpreco', note: 'Como responder "tá caro"' },
      { kind: 'trouble', ref: 'it-cobranca', note: 'Cliente com boleto atrasado pedindo novo' },
      { kind: 'trouble', ref: 'it-prazo', note: 'Prazo impossível — quando aceitar com 25%' },
      { kind: 'kb', ref: 'a11', note: 'Atalhos do ERP' },
      { kind: 'kb', ref: 'a5', note: 'Conferir PDF do cliente antes de produzir' },
      { kind: 'kb', ref: 'a10', note: 'Boleto Inter — quando vai e quando não vai' },
    ],
  },
  {
    id: 'obd-cobranca', title: 'Onboarding · Cobrança', audience: 'atendente que vai cuidar de fila #q-fin', desc: 'Roteiros e ferramentas de cobrança.', hue: 295,
    steps: [
      { kind: 'kb', ref: 'a10', note: 'Fluxo Inter completo' },
      { kind: 'kb', ref: 'a9', note: 'Rejeições SEFAZ — quando culpa é nossa, quando é do cliente' },
      { kind: 'trouble', ref: 'it-cobranca', note: 'Cliente atrasado pedindo nova produção' },
    ],
  },
];

const PATHS_LS = 'oimpresso.inbox.paths';

// ── Troubleshooter (guiado sim/não) ───────────────────────────────────────────
function TroubleView({ trouble, onExit }: { trouble: Trouble; onExit: () => void }) {
  const [step, setStep] = useState(0);
  const [fix, setFix] = useState<string | null>(null);
  const [path, setPath] = useState<{ q: string; answer: boolean }[]>([]);

  const answer = (ans: boolean) => {
    const s = trouble.steps[step];
    const next = ans ? s.yes : s.no;
    setPath(p => [...p, { q: s.q, answer: ans }]);
    if (typeof next === 'number') setStep(next);
    else setFix(next.fix);
  };
  const restart = () => { setStep(0); setFix(null); setPath([]); };
  const current = trouble.steps[step];

  return (
    <Stack gap={3} data-contract="guia-troubleshoot-detail">
      <Inline gap={2} align="center">
        <button type="button" className="inline-flex items-center gap-0.5 text-[11px] font-medium text-muted-foreground hover:text-foreground" onClick={onExit}>
          <ChevronLeft size={13} aria-hidden /> Troubleshooters
        </button>
      </Inline>
      <h3 className="text-sm font-semibold leading-snug">{trouble.title}</h3>

      {path.length > 0 && (
        <Stack gap={1} className="rounded-md border bg-muted/30 p-2">
          {path.map((p, i) => (
            <Inline key={i} gap={2} justify="between" className="text-[11px]">
              <span className="text-muted-foreground truncate">{p.q}</span>
              <span className={cn('font-semibold flex-shrink-0', p.answer ? 'text-success-fg' : 'text-destructive-fg')}>{p.answer ? 'Sim' : 'Não'}</span>
            </Inline>
          ))}
        </Stack>
      )}

      {!fix ? (
        <>
          <Inline gap={2} align="start">
            <span className="flex-shrink-0 inline-flex h-6 w-6 items-center justify-center rounded-full text-[12px] font-bold"
              style={{ background: `oklch(0.94 0.06 ${trouble.hue})`, color: `oklch(0.36 0.13 ${trouble.hue})` }}>
              {path.length + 1}
            </span>
            <p className="text-[13px] leading-snug pt-0.5">{current.q}</p>
          </Inline>
          <Inline gap={2}>
            <button type="button" onClick={() => answer(true)} className="flex-1 rounded-md border border-success/40 bg-success/10 px-3 py-2 text-[12px] font-semibold text-success-fg hover:bg-success/20">Sim</button>
            <button type="button" onClick={() => answer(false)} className="flex-1 rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-[12px] font-semibold text-destructive-fg hover:bg-destructive/20">Não</button>
          </Inline>
        </>
      ) : (
        <>
          <div className="rounded-md border p-3" style={{ background: `oklch(0.97 0.025 ${trouble.hue})`, borderColor: `oklch(0.86 0.06 ${trouble.hue})` }}>
            <small className="block font-semibold mb-1" style={{ color: `oklch(0.42 0.13 ${trouble.hue})` }}>Solução sugerida</small>
            <p className="text-[12.5px] leading-relaxed text-foreground">{fix}</p>
          </div>
          <Inline gap={2}>
            <button type="button" onClick={restart} className="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:bg-muted">
              <RotateCcw size={12} aria-hidden /> Recomeçar
            </button>
            <button type="button" onClick={onExit} className="rounded-md border px-3 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:bg-muted">Outro</button>
          </Inline>
        </>
      )}
    </Stack>
  );
}

// ── Trilha (checklist com progresso localStorage) ──────────────────────────────
function PathsView({ progress, toggle }: { progress: Record<string, Record<number, boolean>>; toggle: (pathId: string, idx: number) => void }) {
  const [activeId, setActiveId] = useState<string | null>(null);
  const prog = (p: Path) => {
    const done = progress[p.id] || {};
    const c = Object.values(done).filter(Boolean).length;
    return { done: c, total: p.steps.length, pct: Math.round((c / p.steps.length) * 100) };
  };

  const active = activeId ? INBOX_PATHS.find(p => p.id === activeId) : null;
  if (active) {
    const done = progress[active.id] || {};
    const p = prog(active);
    return (
      <Stack gap={3} data-contract="guia-trilha-detail">
        <button type="button" className="inline-flex items-center gap-0.5 self-start text-[11px] font-medium text-muted-foreground hover:text-foreground" onClick={() => setActiveId(null)}>
          <ChevronLeft size={13} aria-hidden /> Trilhas
        </button>
        <div className="rounded-md p-3" style={{ background: `oklch(0.96 0.04 ${active.hue})` }}>
          <small className="block font-semibold" style={{ color: `oklch(0.36 0.13 ${active.hue})` }}>Trilha · {active.audience}</small>
          <h3 className="text-sm font-semibold mt-0.5">{active.title}</h3>
          <p className="text-[12px] text-muted-foreground mt-0.5">{active.desc}</p>
          <Inline gap={2} align="center" className="mt-2">
            <div className="h-1.5 flex-1 rounded-full bg-black/10 overflow-hidden">
              <div className="h-full rounded-full" style={{ width: `${p.pct}%`, background: `oklch(0.52 0.13 ${active.hue})` }} />
            </div>
            <span className="font-mono text-[10.5px] text-muted-foreground">{p.done}/{p.total} · {p.pct}%</span>
          </Inline>
        </div>
        <Stack asChild gap={2}>
        <ol>
          {active.steps.map((step, i) => {
            const ok = !!done[i];
            return (
              <li key={i}>
                <Inline gap={2} align="start">
                  <button type="button" onClick={() => toggle(active.id, i)} aria-pressed={ok}
                    className={cn('flex-shrink-0 inline-flex h-5 w-5 items-center justify-center rounded-full border text-[11px] font-bold mt-0.5',
                      ok ? 'border-success bg-success text-success-foreground' : 'border-border text-muted-foreground hover:border-foreground')}>
                    {ok ? '✓' : i + 1}
                  </button>
                  <div className={cn('min-w-0', ok && 'opacity-60')}>
                    <b className="block text-[12.5px] font-medium leading-snug">{step.note}</b>
                    <small className="font-mono text-[10px] text-muted-foreground">#{step.ref} · {step.kind === 'kb' ? 'artigo KB' : 'troubleshoot'}</small>
                  </div>
                </Inline>
              </li>
            );
          })}
        </ol>
        </Stack>
      </Stack>
    );
  }

  return (
    <Stack gap={2} data-contract="guia-trilha-lista">
      {INBOX_PATHS.map(path => {
        const p = prog(path);
        return (
          <button key={path.id} type="button" onClick={() => setActiveId(path.id)}
            className="rounded-md border bg-card p-3 text-left hover:bg-muted/50 transition-colors"
            style={{ borderLeftWidth: 3, borderLeftColor: `oklch(0.52 0.13 ${path.hue})` }}>
            <small className="block text-[10.5px] font-semibold" style={{ color: `oklch(0.42 0.13 ${path.hue})` }}>{path.audience}</small>
            <h4 className="text-[13px] font-semibold mt-0.5">{path.title}</h4>
            <p className="text-[11.5px] text-muted-foreground">{path.desc}</p>
            <Inline gap={2} align="center" className="mt-2">
              <div className="h-1.5 flex-1 rounded-full bg-muted overflow-hidden">
                <div className="h-full rounded-full" style={{ width: `${p.pct}%`, background: `oklch(0.52 0.13 ${path.hue})` }} />
              </div>
              <span className="font-mono text-[10px] text-muted-foreground">{p.done}/{p.total}</span>
            </Inline>
          </button>
        );
      })}
    </Stack>
  );
}

// ── Dialog raiz ────────────────────────────────────────────────────────────────
export default function InboxGuiaDialog({ open, onOpenChange }: Props) {
  const [tab, setTab] = useState<'trouble' | 'paths'>('trouble');
  const [activeTrouble, setActiveTrouble] = useState<string | null>(null);
  const [progress, setProgress] = useState<Record<string, Record<number, boolean>>>(() => {
    try { return JSON.parse(localStorage.getItem(PATHS_LS) || '{}'); } catch { return {}; }
  });
  useEffect(() => {
    try { localStorage.setItem(PATHS_LS, JSON.stringify(progress)); } catch { /* quota/private mode — ignora */ }
  }, [progress]);
  const toggle = (pathId: string, idx: number) =>
    setProgress(p => ({ ...p, [pathId]: { ...(p[pathId] || {}), [idx]: !(p[pathId] || {})[idx] } }));

  const trouble = activeTrouble ? INBOX_TROUBLES.find(t => t.id === activeTrouble) : null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg max-h-[85vh] overflow-y-auto" data-testid="caixa-unif-guia" data-contract="guia">
        <DialogHeader>
          <DialogTitle>Guia da Caixa</DialogTitle>
          <DialogDescription>Diagnóstico guiado de atendimento + trilhas de onboarding.</DialogDescription>
        </DialogHeader>

        <Inline gap={1} className="rounded-md bg-muted p-0.5">
          {([['trouble', 'Troubleshooters', LifeBuoy], ['paths', 'Trilhas', GraduationCap]] as const).map(([id, label, Icon]) => (
            <button key={id} type="button" onClick={() => { setTab(id); setActiveTrouble(null); }}
              className={cn('flex-1 inline-flex items-center justify-center gap-1.5 rounded px-3 py-1.5 text-[12px] font-medium transition-colors',
                tab === id ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground')}
              data-testid={`caixa-unif-guia-tab-${id}`}>
              <Icon size={13} aria-hidden /> {label}
            </button>
          ))}
        </Inline>

        <div className="pt-1">
          {tab === 'trouble' ? (
            trouble ? (
              <TroubleView trouble={trouble} onExit={() => setActiveTrouble(null)} />
            ) : (
              <Stack gap={2} data-contract="guia-troubleshoot-lista">
                {INBOX_TROUBLES.map(t => (
                  <button key={t.id} type="button" onClick={() => setActiveTrouble(t.id)}
                    className="rounded-md border bg-card p-3 text-left hover:bg-muted/50 transition-colors"
                    style={{ borderLeftWidth: 3, borderLeftColor: `oklch(0.55 0.13 ${t.hue})` }}>
                    <small className="block text-[10.5px] font-semibold" style={{ color: `oklch(0.42 0.13 ${t.hue})` }}>atendimento</small>
                    <h4 className="text-[13px] font-semibold mt-0.5">{t.title}</h4>
                    <p className="text-[11.5px] text-muted-foreground">Use quando {t.when}.</p>
                    <span className="mt-1 inline-block text-[10px] font-mono text-muted-foreground">{t.steps.length} perguntas</span>
                  </button>
                ))}
              </Stack>
            )
          ) : (
            <PathsView progress={progress} toggle={toggle} />
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
