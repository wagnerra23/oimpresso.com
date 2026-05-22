// @memcofre
//   tela: /ia/cockpit (legacy: /copiloto/cockpit · /jana/cockpit — 301 redirects)
//   stories: US-COPI-COCKPIT-002 (V2 Analista IA · pivot Cowork 2026-05-15)
//   adrs: 0035 (stack IA), 0039 (Cockpit V2), 0094 (Constituição §IA), 0104 (MWART), 0107 (gate F1.5), 0114 (loop Cowork↔CC)
//   status: live (pivot Cowork aceito · supersedes MVP-piloto WhatsApp anti-pattern)
//   module: Jana
//   nota: substitui in-place V1 (138 lin · MVP-piloto-em-validacao).
//         Charter: resources/js/Pages/Jana/Cockpit.charter.md
//         Visual source: prototipo-ui/_cowork-export-2026-05-15/chat-jana.{jsx,css}
//         CRITIQUE: prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md
//
//         IMPORTANTE Tier 0:
//         - business_id scope respeitado via Controller (não há query DB aqui · mock fase F2)
//         - PT-BR em todo label
//         - PII detector regex CPF/CNPJ/cartão no composer (server-side PiiRedactor faz redaction real)
//         - dangerouslySetInnerHTML PROIBIDO (parser markdown custom limitado · F3 troca por react-markdown + rehype-sanitize)

import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { BusinessOpt } from '@/Components/cockpit/shared';

// ─── Tipos ─────────────────────────────────────────────────────────────

type Tone = 'primary' | 'ghost' | 'danger' | 'violet' | 'orange' | 'dark';
type DeltaCls = 'down' | 'up' | 'info' | 'red big' | '';
type AnaliseKind = 'buckets' | 'sparkline' | 'bars' | 'list' | 'donut' | 'text';
type PillTone = 'crit' | 'warn' | 'ok' | 'react';
type AcaoTone = 'rose' | 'violet' | 'peach' | 'grey';
type RichRun = ['normal' | 'strong' | 'danger', string];
type ParagraphKind = 'text' | 'action' | 'anomaly';
type StreamingKind = 'markdown';

interface Brief {
  greeting: string;
  paragraphs: Array<{ kind: ParagraphKind; icon?: string; body: RichRun[] }>;
  chips: Array<{ tone: Tone; icon: string; label: string }>;
}

interface Kpi {
  label: string;
  value: string;
  delta?: string;
  deltaCls?: DeltaCls;
  icon: string;
  sub?: string;
  emphasize?: boolean;
}

interface Bucket { label: string; bar: number; val: string; color: string }
interface BarItem { label: string; bar: number; pct: string }
interface ListItem { left: string; right: string }
interface DonutSeg { color: string; pct: number }
interface DonutLeg { color: string; label: string; val: string; danger?: boolean }

interface Analise {
  id: string;
  title: string;
  sub: string;
  pill?: { tone: PillTone; label: string };
  icon: string;
  kind: AnaliseKind;
  big?: { value: string; color?: string };
  buckets?: Bucket[];
  spark?: number[];
  sparkRange?: [string, string];
  bars?: BarItem[];
  list?: ListItem[];
  donut?: { pct: number; segs: DonutSeg[] };
  legend?: DonutLeg[];
  text?: string[];
  footer?: string;
  footnote?: string;
}

interface Acao {
  id: string;
  icon: string;
  tone: AcaoTone;
  title: string;
  sub: string;
  cta: { label: string; tone: Tone };
}

interface Source { n: number; label: string; href: string }

interface UserMsg { from: 'user'; kind?: 'text'; text: string }
interface MarkdownMsg { from: 'jana'; kind: 'markdown'; text: string; sources?: Source[] }
interface ToolUseMsg { from: 'jana'; kind: 'tool_use'; tool: string; status: 'running' | 'done' | 'error' }
interface DataTableMsg { from: 'jana'; kind: 'data_table'; caption?: string; columns: string[]; rows: string[][] }
interface ActionCardMsg {
  from: 'jana';
  kind: 'action_card';
  summary: string;
  confirm_required?: boolean;
  result?: 'done' | 'error' | null;
}
type Msg = UserMsg | MarkdownMsg | ToolUseMsg | DataTableMsg | ActionCardMsg;

interface ChatBlock {
  messages: Msg[];
  suggestions: Array<{ icon: string; label: string }>;
}

interface JanaData {
  person: { name: string; role: string };
  biz: { code: string; version: string };
  updatedAt: string;
  today: string;
  brief: Brief;
  kpis: Kpi[];
  analises: Analise[];
  acoes: Acao[];
  chat: ChatBlock;
}

interface Props {
  businessNome: string;
  businesses: BusinessOpt[];
  usuarioNome: string;
  usuarioNomeCurto: string;
  usuarioEmail: string;
  usuarioCargo: string;
  usuarioIniciais: string;
  jana: JanaData;
}

// ─── Constantes ────────────────────────────────────────────────────────

const LS_TAB = 'oimpresso.jana.cockpit.tab';

const PII_REGEX: Record<string, RegExp> = {
  cpf: /\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/,
  cnpj: /\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/,
  cartao: /\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/,
};

// ─── Sub-componentes ───────────────────────────────────────────────────

function RichSpan({ runs }: { runs: RichRun[] }) {
  return (
    <>
      {runs.map((r, i) => {
        const [kind, txt] = r;
        if (kind === 'strong') return <strong key={i}>{txt}</strong>;
        if (kind === 'danger') return <strong key={i} className="text-rose-600">{txt}</strong>;
        return <span key={i}>{txt}</span>;
      })}
    </>
  );
}

function JanaAvatar({ size = 32 }: { size?: number }) {
  // A1 fechado: quadrado mono primary letra "J" (substitui gradient + emoji 🤖)
  return (
    <div
      className="flex items-center justify-center rounded-md bg-primary text-primary-foreground font-semibold shrink-0"
      style={{ width: size, height: size, fontSize: Math.round(size * 0.45) }}
      aria-label="Jana — Analista IA"
    >
      J
    </div>
  );
}

function JanaHeader({
  data,
  businessNome,
}: {
  data: JanaData;
  businessNome: string;
}) {
  return (
    <header className="flex items-center gap-3 mb-3">
      <div className="flex items-center gap-3 flex-1 min-w-0">
        <JanaAvatar size={44} />
        <div>
          <h1 className="text-lg font-semibold tracking-tight">
            {data.person.name} <span className="text-muted-foreground mx-0.5">·</span> {data.person.role}
          </h1>
          <p className="text-[11.5px] text-muted-foreground font-mono mt-0.5">
            <span className="font-semibold text-foreground/80">{businessNome.toUpperCase()}</span>
            <span className="opacity-40 mx-1.5">·</span>
            {data.biz.code}
            <span className="opacity-40 mx-1.5">·</span>
            {data.biz.version}
          </p>
        </div>
      </div>
      <div className="flex items-center gap-2 shrink-0">
        <span className="inline-flex items-center gap-1.5 text-[11.5px] text-foreground/70 mr-1.5">
          <span className="w-[7px] h-[7px] rounded-full bg-emerald-600" />
          Atualizado {data.updatedAt}
        </span>
        <button
          type="button"
          className="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-md border border-border bg-card hover:bg-muted/50 transition"
        >
          <span>⚙</span> Configurar
        </button>
        <button
          type="button"
          className="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-md bg-foreground text-background hover:opacity-90 transition"
        >
          <span>⬇</span> Exportar
        </button>
      </div>
    </header>
  );
}

function BriefDiario({ today, brief }: { today: string; brief: Brief }) {
  return (
    <section className="rounded-lg border border-border bg-card p-4 mb-4 shadow-sm">
      <div className="flex items-center gap-2 mb-2 text-sm">
        <span>📅</span>
        <b className="text-foreground">Brief diário</b>
        <span className="opacity-40">·</span>
        <span className="text-muted-foreground">{today}</span>
        <span className="ml-auto text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-md bg-violet-100 text-violet-700">
          IA
        </span>
        <button
          type="button"
          className="text-xs text-muted-foreground hover:text-foreground transition"
          aria-label="Ouvir brief em áudio (TTS · backlog M2)"
        >
          ▶ Ouvir áudio
        </button>
      </div>
      <p className="text-[13px] leading-relaxed mb-2">
        <strong>{brief.greeting}</strong> <RichSpan runs={brief.paragraphs[0]?.body ?? []} />
      </p>
      {brief.paragraphs[1] && (
        <p className="text-[13px] leading-relaxed mb-2">
          <RichSpan runs={brief.paragraphs[1].body} />
        </p>
      )}
      {brief.paragraphs[2] && (
        <p className="text-[13px] leading-relaxed mb-2 pl-3 border-l-2 border-violet-300">
          <span className="mr-1.5">{brief.paragraphs[2].icon}</span>
          <RichSpan runs={brief.paragraphs[2].body} />
        </p>
      )}
      {brief.paragraphs[3] && (
        <p className="text-[12.5px] leading-relaxed mb-3 italic text-muted-foreground">
          <RichSpan runs={brief.paragraphs[3].body} />
        </p>
      )}
      <div className="border-t border-border my-3" />
      <div className="flex flex-wrap gap-2">
        {brief.chips.map((c, i) => (
          <button
            key={i}
            type="button"
            className={
              c.tone === 'primary'
                ? 'inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-md bg-primary text-primary-foreground hover:opacity-90 transition'
                : 'inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-md bg-muted/40 text-foreground hover:bg-muted transition'
            }
          >
            <span>{c.icon}</span> {c.label}
          </button>
        ))}
      </div>
    </section>
  );
}

function KPICard({ kpi }: { kpi: Kpi }) {
  const valueClass =
    kpi.deltaCls === 'red big' ? 'text-2xl font-bold text-rose-600' : 'text-2xl font-bold';
  const deltaClass = useMemo(() => {
    if (!kpi.deltaCls) return 'text-muted-foreground';
    if (kpi.deltaCls === 'down' || kpi.deltaCls === 'red big') return 'text-rose-600';
    if (kpi.deltaCls === 'up') return 'text-emerald-600';
    return 'text-muted-foreground';
  }, [kpi.deltaCls]);
  return (
    <div
      className={
        'rounded-lg border bg-card p-4 shadow-sm ' +
        (kpi.emphasize ? 'border-rose-300 bg-rose-50/40' : 'border-border')
      }
    >
      <div className="flex items-center justify-between text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">
        <span>{kpi.label}</span>
        <span className="text-base">{kpi.icon}</span>
      </div>
      <div className={valueClass}>{kpi.value}</div>
      {kpi.delta && <div className={'text-[11.5px] mt-1 ' + deltaClass}>{kpi.delta}</div>}
      {kpi.sub && <div className="text-[11.5px] text-muted-foreground mt-1">{kpi.sub}</div>}
    </div>
  );
}

// SVG sparkline curva suave
function Sparkline({ data, w = 280, h = 60 }: { data: number[]; w?: number; h?: number }) {
  if (!data || data.length < 2) return null;
  const min = Math.min(...data);
  const max = Math.max(...data);
  const norm = (v: number) => (max === min ? 0.5 : (v - min) / (max - min));
  const xStep = w / (data.length - 1);
  const pts: Array<[number, number]> = data.map((v, i) => [i * xStep, h - 4 - norm(v) * (h - 10)]);
  const first = pts[0]!;
  let d = `M ${first[0]} ${first[1]}`;
  for (let i = 1; i < pts.length; i++) {
    const prev = pts[i - 1]!;
    const cur = pts[i]!;
    const cx = (prev[0] + cur[0]) / 2;
    d += ` Q ${cx} ${prev[1]}, ${cx} ${(prev[1] + cur[1]) / 2} T ${cur[0]} ${cur[1]}`;
  }
  const area = d + ` L ${w} ${h} L 0 ${h} Z`;
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-[60px]" role="img" aria-label="Sparkline 24 meses">
      <defs>
        <linearGradient id="janaSparkGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="oklch(0.62 0.16 145)" stopOpacity="0.28" />
          <stop offset="100%" stopColor="oklch(0.62 0.16 145)" stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={area} fill="url(#janaSparkGrad)" />
      <path d={d} fill="none" stroke="oklch(0.55 0.16 145)" strokeWidth={2} />
    </svg>
  );
}

function Donut({ segs, centerLabel }: { segs: DonutSeg[]; centerLabel: string }) {
  const R = 32, sw = 10, C = 2 * Math.PI * R;
  let offset = 0;
  return (
    <svg viewBox="0 0 80 80" width={80} height={80} role="img" aria-label="Donut frota">
      <circle cx={40} cy={40} r={R} fill="none" stroke="#efeae0" strokeWidth={sw} />
      {segs.map((s, i) => {
        const len = (s.pct / 100) * C;
        const dash = `${len} ${C - len}`;
        const el = (
          <circle
            key={i}
            cx={40}
            cy={40}
            r={R}
            fill="none"
            stroke={s.color}
            strokeWidth={sw}
            strokeDasharray={dash}
            strokeDashoffset={-offset}
            transform="rotate(-90 40 40)"
            strokeLinecap="butt"
          />
        );
        offset += len;
        return el;
      })}
      <text x={40} y={44} textAnchor="middle" className="text-[14px] font-semibold fill-foreground">
        {centerLabel}
      </text>
    </svg>
  );
}

function AnaliseCard({ a }: { a: Analise }) {
  const pillToneCls: Record<PillTone, string> = {
    crit: 'bg-rose-100 text-rose-700',
    warn: 'bg-amber-100 text-amber-700',
    ok: 'bg-emerald-100 text-emerald-700',
    react: 'bg-violet-100 text-violet-700',
  };
  const bigCls = a.big?.color === 'danger' ? 'text-rose-600' : a.big?.color === 'ok' ? 'text-emerald-700' : 'text-foreground';
  return (
    <div className="rounded-lg border border-border bg-card p-4 shadow-sm">
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-start gap-2">
          <span className="text-xl">{a.icon}</span>
          <div>
            <b className="text-sm">{a.title}</b>
            <small className="block text-[11.5px] text-muted-foreground">{a.sub}</small>
          </div>
        </div>
        {a.pill && (
          <span className={'text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-md ' + pillToneCls[a.pill.tone]}>
            {a.pill.label}
          </span>
        )}
      </div>
      {a.big && <div className={'text-2xl font-bold mb-3 ' + bigCls}>{a.big.value}</div>}

      {a.kind === 'buckets' && a.buckets && (
        <div className="space-y-1.5">
          {a.buckets.map((b, i) => (
            <div key={i} className="flex items-center gap-2 text-[11.5px]">
              <span className="w-16 text-muted-foreground shrink-0">{b.label}</span>
              <div className="flex-1 h-2 rounded-sm bg-muted/40 overflow-hidden">
                <div className="h-full" style={{ width: b.bar + '%', background: b.color }} />
              </div>
              <span className="w-20 text-right tabular-nums shrink-0">{b.val}</span>
            </div>
          ))}
        </div>
      )}

      {a.kind === 'sparkline' && a.spark && (
        <>
          <Sparkline data={a.spark} />
          <div className="flex justify-between text-[10.5px] text-muted-foreground mt-1">
            <span>{a.sparkRange?.[0]}</span>
            <span>{a.sparkRange?.[1]}</span>
          </div>
        </>
      )}

      {a.kind === 'bars' && a.bars && (
        <div className="space-y-1.5">
          {a.bars.map((b, i) => (
            <div key={i} className="flex items-center gap-2 text-[11.5px]">
              <span className="w-16 text-muted-foreground shrink-0">{b.label}</span>
              <div className="flex-1 h-2 rounded-sm bg-muted/40 overflow-hidden">
                <div className="h-full bg-primary" style={{ width: b.bar + '%' }} />
              </div>
              <span className="w-12 text-right tabular-nums shrink-0">{b.pct}</span>
            </div>
          ))}
        </div>
      )}

      {a.kind === 'list' && a.list && (
        <div className="space-y-1.5">
          {a.list.map((it, i) => (
            <div key={i} className="flex items-center justify-between text-[11.5px]">
              <span className="truncate">{it.left}</span>
              <span className="text-muted-foreground tabular-nums">{it.right}</span>
            </div>
          ))}
        </div>
      )}

      {a.kind === 'donut' && a.donut && a.legend && (
        <div className="flex items-center gap-3">
          <Donut segs={a.donut.segs} centerLabel={a.donut.pct + '%'} />
          <div className="flex-1 space-y-1">
            {a.legend.map((l, i) => (
              <div key={i} className="flex items-center gap-2 text-[11.5px]">
                <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ background: l.color }} />
                <span className="flex-1">{l.label}</span>
                <span className={'tabular-nums ' + (l.danger ? 'text-rose-600 font-semibold' : '')}>{l.val}</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {a.kind === 'text' && a.text && (
        <ul className="space-y-1 text-[12px]">
          {a.text.map((t, i) => (
            <li key={i} className="flex gap-1.5">
              <span className="text-muted-foreground">·</span>
              <span>{t}</span>
            </li>
          ))}
        </ul>
      )}

      {a.footer && <div className="text-[11px] text-muted-foreground mt-3 pt-2 border-t border-border">{a.footer}</div>}
      {a.footnote && <div className="text-[11px] text-muted-foreground mt-2 italic">{a.footnote}</div>}
    </div>
  );
}

function AcaoRow({ a }: { a: Acao }) {
  const toneCls: Record<AcaoTone, string> = {
    rose: 'bg-rose-50 border-rose-200',
    violet: 'bg-violet-50 border-violet-200',
    peach: 'bg-amber-50 border-amber-200',
    grey: 'bg-muted/40 border-border',
  };
  const ctaCls: Record<Tone, string> = {
    primary: 'bg-primary text-primary-foreground',
    danger: 'bg-rose-600 text-white',
    violet: 'bg-violet-600 text-white',
    orange: 'bg-amber-600 text-white',
    dark: 'bg-foreground text-background',
    ghost: 'bg-card border border-border',
  };
  return (
    <div className={'flex items-center gap-3 p-3 rounded-lg border ' + toneCls[a.tone]}>
      <span className="text-xl shrink-0">{a.icon}</span>
      <div className="flex-1 min-w-0">
        <b className="text-sm block truncate">{a.title}</b>
        <small className="text-[11.5px] text-muted-foreground block truncate">{a.sub}</small>
      </div>
      <button
        type="button"
        className={'text-xs px-3 py-1.5 rounded-md hover:opacity-90 transition shrink-0 ' + ctaCls[a.cta.tone]}
      >
        {a.cta.label}
      </button>
    </div>
  );
}

// ─── 4 kinds bubble (C1 amendment v2.1) ────────────────────────────────

function MarkdownBubble({ m }: { m: MarkdownMsg }) {
  // Parser custom limitado (bold/italic/link/code/citations) — F3 troca por react-markdown + rehype-sanitize.
  // dangerouslySetInnerHTML PROIBIDO.
  const renderInline = (text: string): React.ReactNode[] => {
    const tokens: React.ReactNode[] = [];
    let buf = '';
    let i = 0;
    let key = 0;
    const flush = () => {
      if (buf) {
        tokens.push(buf);
        buf = '';
      }
    };
    while (i < text.length) {
      // Citation [N]
      const citeMatch = text.slice(i).match(/^\[(\d+)\]/);
      if (citeMatch && citeMatch[1]) {
        flush();
        const n = parseInt(citeMatch[1], 10);
        const src = m.sources?.find((s) => s.n === n);
        tokens.push(
          src ? (
            <sup key={key++}>
              <a className="text-primary font-semibold no-underline hover:underline" href={src.href}>
                [{n}]
              </a>
            </sup>
          ) : (
            <sup key={key++}>[{n}]</sup>
          ),
        );
        i += citeMatch[0].length;
        continue;
      }
      // Bold **
      if (text.slice(i, i + 2) === '**') {
        const end = text.indexOf('**', i + 2);
        if (end > -1) {
          flush();
          tokens.push(<b key={key++}>{text.slice(i + 2, end)}</b>);
          i = end + 2;
          continue;
        }
      }
      // Inline code `
      if (text[i] === '`') {
        const end = text.indexOf('`', i + 1);
        if (end > -1) {
          flush();
          tokens.push(
            <code key={key++} className="px-1 py-0.5 rounded bg-muted/50 text-[11.5px] font-mono">
              {text.slice(i + 1, end)}
            </code>,
          );
          i = end + 1;
          continue;
        }
      }
      // Link [txt](url)
      const linkMatch = text.slice(i).match(/^\[([^\]]+)\]\(([^)]+)\)/);
      if (linkMatch) {
        flush();
        tokens.push(
          <a key={key++} className="text-primary underline" href={linkMatch[2]}>
            {linkMatch[1]}
          </a>,
        );
        i += linkMatch[0].length;
        continue;
      }
      buf += text[i];
      i++;
    }
    flush();
    return tokens;
  };
  return (
    <div className="self-start max-w-[90%] rounded-md border border-border bg-card px-3.5 py-2.5 text-[13px] leading-relaxed shadow-sm">
      {m.text.split('\n').map((line, i) => (
        <p key={i} className={i > 0 ? 'mt-1.5' : ''}>
          {renderInline(line)}
        </p>
      ))}
      {m.sources && m.sources.length > 0 && (
        <div className="mt-2 pt-2 border-t border-border flex flex-wrap gap-2">
          {m.sources.map((s) => (
            <a key={s.n} className="text-[11px] text-primary hover:underline" href={s.href}>
              [{s.n}] {s.label}
            </a>
          ))}
        </div>
      )}
    </div>
  );
}

function ToolUseChip({ m }: { m: ToolUseMsg }) {
  const stCls = m.status === 'done' ? 'text-emerald-700' : m.status === 'error' ? 'text-rose-600' : 'text-amber-700';
  const stLabel = m.status === 'done' ? 'pronto' : m.status === 'error' ? 'erro' : 'rodando…';
  return (
    <div className="self-start max-w-[90%] flex items-center gap-2 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-[12px] text-sky-900">
      <span>⚙</span>
      <span>
        <b>Consultou</b>{' '}
        <code className="px-1 py-0.5 rounded bg-white/70 text-[11px] font-mono">{m.tool}</code>
      </span>
      <span className={'ml-auto text-[11px] ' + stCls}>{stLabel}</span>
    </div>
  );
}

function DataTableBubble({ m }: { m: DataTableMsg }) {
  const [expanded, setExpanded] = useState(false);
  const visible = expanded ? m.rows : m.rows.slice(0, 5);
  const remaining = m.rows.length - 5;
  return (
    <div className="self-start max-w-[90%] rounded-md border border-border bg-card px-3.5 py-2.5 shadow-sm">
      {m.caption && <b className="block text-[13px] mb-2">{m.caption}</b>}
      <table className="w-full text-[12px] border-collapse">
        <thead>
          <tr className="border-b border-border">
            {m.columns.map((c) => (
              <th key={c} className="text-left px-2 py-1.5 font-semibold text-foreground">
                {c}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {visible.map((r, i) => (
            <tr key={i} className="border-b border-border last:border-0">
              {r.map((c, j) => (
                <td key={j} className="px-2 py-1.5 tabular-nums">
                  {c}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
      {!expanded && remaining > 0 && (
        <button
          type="button"
          onClick={() => setExpanded(true)}
          className="mt-2 text-[11px] text-primary hover:underline"
        >
          ver mais ({remaining})
        </button>
      )}
    </div>
  );
}

function ActionCardBubble({ m }: { m: ActionCardMsg }) {
  const tone =
    m.result === 'done'
      ? 'border-l-emerald-500 bg-emerald-50/60'
      : m.result === 'error'
        ? 'border-l-rose-500 bg-rose-50/60'
        : 'border-l-amber-500 bg-amber-50/60';
  return (
    <div
      className={
        'self-start max-w-[90%] rounded-md border border-border border-l-4 px-3.5 py-2.5 text-[13px] shadow-sm ' +
        tone
      }
    >
      <b className="block">{m.summary}</b>
      {m.confirm_required && (
        <div className="mt-3 flex gap-2">
          <button
            type="button"
            className="text-xs px-3 py-1.5 rounded-md bg-primary text-primary-foreground hover:opacity-90 transition"
          >
            Confirmar
          </button>
          <button
            type="button"
            className="text-xs px-3 py-1.5 rounded-md border border-border bg-card hover:bg-muted/50 transition"
          >
            Cancelar
          </button>
        </div>
      )}
      {m.result === 'done' && (
        <small className="text-[11px] text-emerald-700 block mt-1.5">✓ Executado</small>
      )}
      {m.result === 'error' && (
        <small className="text-[11px] text-rose-600 block mt-1.5">✗ Erro</small>
      )}
    </div>
  );
}

// ─── Streaming mock (A5 amendment v2.1) ────────────────────────────────

function startMockStream(prompt: string, onDelta: (chunk: string) => void, onFinal: (text: string, kind: StreamingKind) => void) {
  const lower = prompt.toLowerCase();
  const tokensSet =
    lower.includes('régua') || lower.includes('regua')
      ? ['📨 ', 'Régua ', 'preparada ', 'para ', '8 ', 'clientes ', 'ouro ', '>90d.\n', '[1] ', 'VARGAS ', 'R$ [redacted Tier 0]k ', '[2] ', 'TORK ', 'R$ [redacted Tier 0]k.\n', 'Confirma ', 'envio?']
      : ['Analisando ', 'sua ', 'pergunta ', 'sobre ', `"${prompt.slice(0, 40)}"`, '...\n\n', 'Posso ', 'consultar ', 'o ', 'módulo ', 'relevante ', 'agora?'];
  let i = 0;
  const id = window.setInterval(() => {
    if (i >= tokensSet.length) {
      window.clearInterval(id);
      onFinal(tokensSet.join(''), 'markdown');
      return;
    }
    const tok = tokensSet[i++];
    if (tok !== undefined) onDelta(tok);
  }, 60);
  return () => window.clearInterval(id);
}

// ─── Aba Analista IA · thread + composer ───────────────────────────────

function ConverseComJana({ chat }: { chat: ChatBlock }) {
  const [draft, setDraft] = useState('');
  const [msgs, setMsgs] = useState<Msg[]>(chat.messages);
  const [streaming, setStreaming] = useState(false);
  const [streamBuf, setStreamBuf] = useState('');
  const composerRef = useRef<HTMLTextAreaElement | null>(null);
  const threadRef = useRef<HTMLDivElement | null>(null);

  // Auto-scroll fim quando msg nova chega (pause se user rolou pra cima)
  useEffect(() => {
    const el = threadRef.current;
    if (!el) return;
    const distFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
    if (distFromBottom < 120) {
      el.scrollTop = el.scrollHeight;
    }
  }, [msgs, streamBuf]);

  // B7 fechado: atalhos globais / J/K Esc filtra input focus
  useEffect(() => {
    const isInputFocused = () => {
      const a = document.activeElement;
      return a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || (a as HTMLElement).isContentEditable);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.metaKey || e.ctrlKey || e.altKey) return;
      if (isInputFocused() && e.key !== 'Escape') return;
      if (e.key === '/') {
        e.preventDefault();
        composerRef.current?.focus();
      } else if (e.key === 'Escape') {
        composerRef.current?.blur();
      }
      // J/K stub — F3 plug navegação entre msgs
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, []);

  // C4 fechado: PII detector
  const piiDetected = useMemo<string | null>(() => {
    for (const [k, rx] of Object.entries(PII_REGEX)) if (rx.test(draft)) return k;
    return null;
  }, [draft]);

  function onSend() {
    const text = draft.trim();
    if (!text || streaming) return;
    setMsgs((m) => [...m, { from: 'user', kind: 'text', text }]);
    setDraft('');
    setStreaming(true);
    setStreamBuf('');
    startMockStream(
      text,
      (chunk) => setStreamBuf((b) => b + chunk),
      (final, kind) => {
        setMsgs((m) => [...m, { from: 'jana', kind, text: final }]);
        setStreamBuf('');
        setStreaming(false);
      },
    );
  }

  const empty = msgs.length === 0;

  return (
    <section className="rounded-lg border border-border bg-card flex flex-col h-[calc(100vh-220px)] min-h-[480px] overflow-hidden">
      <div className="px-4 py-2.5 border-b border-border flex items-center gap-2">
        <span>💬</span>
        <b className="text-sm">Converse com Jana</b>
        <small className="text-[11.5px] text-muted-foreground ml-auto">
          Chat com contexto do seu negócio
        </small>
      </div>

      <div ref={threadRef} className="flex-1 overflow-y-auto px-4 py-3 flex flex-col gap-2.5">
        {empty ? (
          <div className="flex-1 flex flex-col items-center justify-center gap-3 text-center px-6">
            <JanaAvatar size={48} />
            <h3 className="text-base font-semibold">Como posso ajudar hoje?</h3>
            <p className="text-[13px] text-muted-foreground max-w-md">
              Pergunte sobre vendas, OS, financeiro ou peça uma ação.
            </p>
            <div className="grid grid-cols-2 gap-2 mt-2 max-w-md w-full">
              {[
                { icon: '📈', label: 'Vendas de hoje', prompt: 'Quantas vendas tive hoje?' },
                { icon: '⏰', label: 'OS atrasadas', prompt: 'Listar OS atrasadas' },
                { icon: '💰', label: 'Inadimplentes', prompt: 'Top 5 clientes em débito' },
                { icon: '📊', label: 'Financeiro mensal', prompt: 'Resumo financeiro do mês' },
              ].map((p, i) => (
                <button
                  key={i}
                  type="button"
                  onClick={() => setDraft(p.prompt)}
                  className="text-xs px-3 py-2 rounded-md border border-border bg-card hover:bg-muted/40 transition text-left"
                >
                  <span className="mr-1.5">{p.icon}</span>
                  {p.label}
                </button>
              ))}
            </div>
            <div className="text-[11px] text-muted-foreground mt-3">
              <kbd className="px-1.5 py-0.5 rounded border border-border bg-muted/30 font-mono">⌘K</kbd> busca histórico
              <span className="mx-2">·</span>
              <kbd className="px-1.5 py-0.5 rounded border border-border bg-muted/30 font-mono">/</kbd> foca composer
            </div>
          </div>
        ) : (
          <>
            {msgs.map((m, i) => {
              if (m.from === 'user') {
                return (
                  <div
                    key={i}
                    className="self-end max-w-[80%] rounded-md bg-primary text-primary-foreground px-3.5 py-2 text-[13px] leading-relaxed"
                  >
                    {m.text}
                  </div>
                );
              }
              if (m.kind === 'markdown') return <MarkdownBubble key={i} m={m} />;
              if (m.kind === 'tool_use') return <ToolUseChip key={i} m={m} />;
              if (m.kind === 'data_table') return <DataTableBubble key={i} m={m} />;
              if (m.kind === 'action_card') return <ActionCardBubble key={i} m={m} />;
              return null;
            })}
            {streaming && (
              <div className="self-start max-w-[90%] rounded-md border border-border bg-card px-3.5 py-2.5 text-[13px] leading-relaxed shadow-sm">
                {streamBuf || (
                  <span className="inline-flex items-center gap-2 text-muted-foreground">
                    <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse" />
                    Jana está pensando
                  </span>
                )}
                {streamBuf && (
                  <span
                    className="inline-block w-1.5 h-3 ml-0.5 bg-primary animate-pulse"
                    aria-hidden
                  />
                )}
              </div>
            )}
          </>
        )}
      </div>

      <div className="border-t border-border px-4 py-3 relative">
        <div className="flex gap-2 items-end">
          <textarea
            ref={composerRef}
            rows={1}
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                onSend();
              }
            }}
            placeholder="Pergunte algo à Jana sobre vendas, OS, financeiro..."
            className="flex-1 resize-none text-[13px] px-3 py-2 rounded-md border border-border bg-card focus:outline-none focus:ring-2 focus:ring-primary/30 max-h-[160px]"
            style={{ minHeight: 38 }}
          />
          <button
            type="button"
            onClick={onSend}
            disabled={!draft.trim() || streaming}
            className="text-xs px-4 py-2 rounded-md bg-primary text-primary-foreground hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
          >
            Enviar
          </button>
        </div>
        {piiDetected && (
          <div className="mt-2 text-[11px] px-2.5 py-1.5 rounded-md bg-amber-50 border border-amber-200 text-amber-800">
            ⚠️ Conteúdo sensível detectado ({piiDetected.toUpperCase()}) — Jana redige sem PII no audit log
          </div>
        )}
        {!empty && (
          <div className="mt-2 flex flex-wrap gap-1.5">
            {chat.suggestions.map((s, i) => (
              <button
                key={i}
                type="button"
                onClick={() => setDraft(s.label)}
                className="text-[11px] px-2.5 py-1 rounded-md bg-muted/40 text-foreground hover:bg-muted transition"
              >
                <span className="mr-1">{s.icon}</span> {s.label}
              </button>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}

// ─── Componente principal ──────────────────────────────────────────────

export default function Cockpit({
  businessNome,
  businesses,
  usuarioNome,
  usuarioNomeCurto,
  usuarioEmail,
  usuarioCargo,
  usuarioIniciais,
  jana,
}: Props) {
  const [tab, setTab] = useState<'dashboard' | 'ia'>(() => {
    if (typeof window === 'undefined') return 'dashboard';
    const v = localStorage.getItem(LS_TAB);
    return v === 'ia' ? 'ia' : 'dashboard';
  });

  useEffect(() => {
    try {
      localStorage.setItem(LS_TAB, tab);
    } catch {
      // ignore SSR/quota
    }
  }, [tab]);

  return (
    <AppShellV2
      title="Jana · Analista IA"
      business={{ nome: businessNome, opcoes: businesses }}
      user={{
        nome: usuarioNome,
        nomeCurto: usuarioNomeCurto,
        email: usuarioEmail,
        cargo: usuarioCargo,
        iniciais: usuarioIniciais,
      }}
    >
      <Head title="Jana · Cockpit" />
      <div className="px-6 py-5 max-w-[1280px] mx-auto">
        <JanaHeader data={jana} businessNome={businessNome} />

        <nav className="flex gap-1 border-b border-border mb-5" aria-label="Modo do Jana">
          {[
            { key: 'dashboard' as const, label: 'Dashboard', icon: '📊' },
            { key: 'ia' as const, label: 'Analista IA', icon: '🤖' },
          ].map((t) => {
            const active = tab === t.key;
            return (
              <button
                key={t.key}
                type="button"
                onClick={() => setTab(t.key)}
                className={
                  'inline-flex items-center gap-1.5 text-xs px-4 py-2 border-b-2 transition ' +
                  (active
                    ? 'border-primary text-primary font-semibold'
                    : 'border-transparent text-muted-foreground hover:text-foreground')
                }
              >
                <span>{t.icon}</span> {t.label}
              </button>
            );
          })}
        </nav>

        {tab === 'dashboard' ? (
          <>
            <BriefDiario today={jana.today} brief={jana.brief} />
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
              {jana.kpis.map((k, i) => (
                <KPICard key={i} kpi={k} />
              ))}
            </div>
            <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-muted-foreground mb-3">
              <span>📊</span> Análises principais
            </h2>
            <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-3 mb-5">
              {jana.analises.map((a) => (
                <AnaliseCard key={a.id} a={a} />
              ))}
            </div>
            <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-muted-foreground mb-3">
              <span>💡</span> Ações que {jana.person.name} sugere
            </h2>
            <div className="space-y-2">
              {jana.acoes.map((a) => (
                <AcaoRow key={a.id} a={a} />
              ))}
            </div>
          </>
        ) : (
          <ConverseComJana chat={jana.chat} />
        )}
      </div>
    </AppShellV2>
  );
}
