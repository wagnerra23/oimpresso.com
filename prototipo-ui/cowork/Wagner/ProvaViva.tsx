// @memcofre
//   tela: /financeiro/prova-viva
//   module: Financeiro
//   status: prova-viva (pilot ADR 0253 — mock data, NÃO é a Visão Unificada de produção)
//   adrs: 0253 (primitivos-layout · critério-de-pronto = tela 100% primitivos), ui/0013 (constituição-ui-v2), 0093 (multi-tenant Tier 0)
//   refs: design-handoff "Financeiro - Prova Viva (primitivos).html" (Cowork chat46 2026-06-07)
//
// Tela-piloto que fecha o "critério de pronto" da ADR 0253: a tela Financeiro
// composta 100% pelos primitivos de layout (Box · Stack · Inline · Grid · Text ·
// Container) — zero `<div className="flex">` solto, zero `.css` de tela. Tudo de
// token (@theme inertia.css). É a tradução fiel da prova viva aprovada no loop
// Cowork (identidade roxa, densidade ERP, frescor, drawer de domínio
// conciliação/fiscal/cobrança da rubrica 9.75).
//
// ⚠️ DADOS SÃO MOCK (ROWS local). Esta tela NÃO substitui Financeiro/Unificado/Index
// (produção). É prova de layout — conciliação/fiscal/cobrança são casca de domínio,
// não estão ligadas a dado real (charter /financeiro: "não apresentar mock como pronto").
//
// Adaptações vs. protótipo HTML (para honrar o contrato real dos primitivos / DS):
//  • `rounded="lg"` no lugar de `rounded-xl` (proibição charter/DS: raio ≤ lg em operacional)
//  • tamanhos de número na type-scale token (`4xl/3xl/2xl/base/sm`) no lugar de `text-[44px]` solto
//  • canvas = token real `bg-page-cream` (não o `--page` lavanda inventado no mock)
//  • `family="mono"` cai no mono do sistema até o token `--font-mono` existir (Tier 0 de [W])

import * as React from 'react';
import { useState, type ReactNode } from 'react';

import {
  Plus, Search, ChevronRight, Calendar, RefreshCw, Check,
  ArrowDownLeft, ArrowUpRight, Zap, FileText, Send, X, Link2,
  type LucideIcon,
} from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { Box, Stack, Inline, Grid, Container, Text } from '@/Components/layout';
import { cn } from '@/Lib/utils';

/* ═══ TIPOS ═══ */
type Kind = 'in' | 'out';
type StatusKey = 'recebido' | 'pago' | 'pendente' | 'vencendo' | 'atrasado';

interface Row {
  d: string;
  grp: string;
  kind: Kind;
  desc: string;
  who: string;
  cat: string;
  nf: string;
  st: StatusKey;
  amt: number;
  when: string;
  paid?: boolean;
}

/* ═══ DADOS (mock ROTA LIVRE — prova de layout) ═══ */
const CAT: Record<string, string> = {
  Banner: '265', Adesivo: '200', Fachada: '30', Placa: '150',
  Insumo: '330', Aluguel: '15', Utilidade: '55', 'Rápida': '285',
};

const ROWS: Row[] = [
  { d: '09/05', grp: 'Hoje · 09 mai · sexta', kind: 'in', desc: 'Banner fachada 3×1,5m', who: 'Ótica Visão Clara', cat: 'Banner', nf: 'NF 1204', st: 'pendente', amt: 1840, when: 'vence hoje' },
  { d: '09/05', grp: 'Hoje · 09 mai · sexta', kind: 'out', desc: 'Lona front-light · bobina', who: 'Suprigraf Insumos', cat: 'Insumo', nf: 'NF 8821', st: 'vencendo', amt: 2390, when: 'vence hoje' },
  { d: '08/05', grp: 'Ontem · 08 mai', kind: 'in', desc: 'Adesivo perfurado vitrine', who: 'Padaria Pão Nosso', cat: 'Adesivo', nf: 'NF 1203', st: 'recebido', amt: 760, paid: true, when: 'pago 08/05' },
  { d: '08/05', grp: 'Ontem · 08 mai', kind: 'in', desc: 'Placa PS 2mm c/ acabamento', who: 'Condomínio Solar', cat: 'Placa', nf: 'NF 1202', st: 'recebido', amt: 1280, paid: true, when: 'pago 08/05' },
  { d: '07/05', grp: '07 mai · quinta', kind: 'out', desc: 'Aluguel galpão produção', who: 'Imobiliária Centro', cat: 'Aluguel', nf: 'REC 552', st: 'pago', amt: 4200, paid: true, when: 'pago 07/05' },
  { d: '06/05', grp: '06 mai · quarta', kind: 'in', desc: 'Fachada ACM + iluminação', who: 'Auto Peças Veloz', cat: 'Fachada', nf: 'NF 1198', st: 'atrasado', amt: 4200, when: 'há 3 dias' },
  { d: '05/05', grp: '05 mai · terça', kind: 'out', desc: 'Energia · produção abril', who: 'CEMIG', cat: 'Utilidade', nf: 'FAT 0934', st: 'pago', amt: 1130, paid: true, when: 'pago 05/05' },
  { d: '05/05', grp: '05 mai · terça', kind: 'in', desc: 'Gráfica rápida · 500 flyers', who: 'Studio Yoga Namastê', cat: 'Rápida', nf: 'NF 1196', st: 'recebido', amt: 430, paid: true, when: 'pago 05/05' },
  { d: '05/05', grp: '05 mai · terça', kind: 'in', desc: 'Placas de obra · 6un', who: 'Construtora Horizonte', cat: 'Placa', nf: 'NF 1195', st: 'recebido', amt: 2880, paid: true, when: 'pago 05/05' },
];

const STATUS: Record<StatusKey, { soft: string; dot: string; label: string }> = {
  recebido: { soft: 'bg-success/12 text-success', dot: 'bg-success', label: 'Recebido' },
  pago: { soft: 'bg-success/12 text-success', dot: 'bg-success', label: 'Pago' },
  pendente: { soft: 'bg-muted text-muted-foreground', dot: 'bg-muted-foreground/60', label: 'Pendente' },
  vencendo: { soft: 'bg-warning/15 text-warning', dot: 'bg-warning', label: 'Vencendo' },
  atrasado: { soft: 'bg-destructive/12 text-destructive', dot: 'bg-destructive', label: 'Atrasado' },
};

/* ═══ FORMATADORES ═══ */
const parts = (n: number): { int: string; dec: string } => {
  const a = Math.abs(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const [int = '0', dec = '00'] = a.split(',');
  return { int, dec };
};
const fmt = (n: number): string => Math.abs(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const K = (n: number): string =>
  Math.abs(n) >= 1000
    ? (Math.abs(n) / 1000).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + 'k'
    : fmt(n);

/* ═══ ÍCONES (lucide-react — canon R4, zero svg inline) ═══ */
const ICN = {
  plus: Plus, search: Search, chev: ChevronRight, cal: Calendar, refresh: RefreshCw,
  check: Check, in: ArrowDownLeft, out: ArrowUpRight, bolt: Zap, doc: FileText,
  send: Send, x: X, link: Link2,
} satisfies Record<string, LucideIcon>;
/** Adapter fino: mantém o call-site `<Ic d={ICN.foo} s={N} w={W}/>` mas renderiza lucide. */
const Ic = ({ d: Glyph, s = 14, w = 2, className }: { d: LucideIcon; s?: number; w?: number; className?: string }) => (
  <Glyph size={s} strokeWidth={w} className={className} />
);

/* ═══ ÁTOMOS (compostos só de primitivos) ═══ */
type NumSize = 'hero' | 'xl' | 'lg' | 'md' | 'sm';
const NUM_MAIN: Record<NumSize, TextSize> = { hero: '4xl', xl: '3xl', lg: '2xl', md: 'base', sm: 'sm' };
const NUM_SUB: Record<NumSize, TextSize> = { hero: 'base', xl: 'sm', lg: 'xs', md: 'xs', sm: 'xs' };
type TextSize = 'xs' | 'sm' | 'base' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl';
type Tone = 'default' | 'muted' | 'primary' | 'success' | 'warning' | 'destructive';

function Num({ n, size = 'lg', tone = 'default', signed, white }: { n: number; size?: NumSize; tone?: Tone; signed?: boolean; white?: boolean }) {
  const p = parts(n);
  const main = NUM_MAIN[size];
  const sub = NUM_SUB[size];
  const curCls = white ? 'text-white/55' : '';
  return (
    <Inline gap={0} align="baseline" className="whitespace-nowrap">
      {signed && <Text as="span" size={sub} tone="muted" family="mono" className={cn('mr-0.5', curCls)}>{n >= 0 ? '+' : '−'}</Text>}
      <Text as="span" size={sub} tone="muted" family="mono" weight="medium" className={cn('mr-0.5', curCls)}>R$</Text>
      <Text as="span" size={main} tone={tone} family="mono" numeric="tabular" weight="bold" leading="none" className={cn('tracking-[-0.02em]', white && 'text-white')}>{p.int}</Text>
      <Text as="span" size={sub} tone="muted" family="mono" numeric="tabular" className={curCls}>,{p.dec}</Text>
    </Inline>
  );
}

function Money({ n, signed, tone = 'default', size = 'sm', strong }: { n: number; signed?: boolean; tone?: Tone; size?: TextSize; strong?: boolean }) {
  const p = parts(n);
  return (
    <Inline gap={0} align="baseline" justify="end" className="whitespace-nowrap">
      {signed && <Text as="span" size="xs" tone="muted" family="mono" className="mr-0.5">{n >= 0 ? '+' : '−'}</Text>}
      <Text as="span" size="xs" tone="muted" family="mono" className="mr-0.5">R$</Text>
      <Text as="span" size={size} tone={tone} family="mono" numeric="tabular" weight={strong ? 'semibold' : 'medium'}>{p.int}</Text>
      <Text as="span" size="xs" tone="muted" family="mono" numeric="tabular">,{p.dec}</Text>
    </Inline>
  );
}

function Label({ children, dot, white }: { children: ReactNode; dot?: string; white?: boolean }) {
  return (
    <Inline gap={2} className="shrink-0">
      {dot && <span className={cn('w-1.5 h-1.5 rounded-full shrink-0', dot)} />}
      <Text as="span" weight="semibold" className={cn('text-[11px] uppercase tracking-[0.13em] whitespace-nowrap', white ? 'text-white/70' : 'text-muted-foreground')}>{children}</Text>
    </Inline>
  );
}

function Badge({ st }: { st: StatusKey }) {
  const s = STATUS[st];
  return (
    <Box className={cn('inline-flex items-center gap-1.5 rounded-full pl-1.5 pr-2 py-0.5 w-fit', s.soft)}>
      <span className={cn('w-1.5 h-1.5 rounded-full', s.dot)} />
      <Text as="span" size="xs" weight="semibold" className="leading-none tracking-tight">{s.label}</Text>
    </Box>
  );
}

function Dir({ kind, s = 22 }: { kind: Kind; s?: number }) {
  return (
    <Box rounded="md" className={cn('grid place-items-center shrink-0', kind === 'in' ? 'bg-success/12 text-success' : 'bg-destructive/12 text-destructive')} style={{ width: s, height: s }}>
      <Ic d={kind === 'in' ? ICN.in : ICN.out} s={s * 0.56} w={2.4} />
    </Box>
  );
}

function Kbd({ children }: { children: ReactNode }) {
  return (
    <Box bg="muted" rounded="sm" border className="px-1.5 py-0.5">
      <Text as="span" size="xs" family="mono" tone="muted" className="leading-none">{children}</Text>
    </Box>
  );
}

function Btn({ primary, icon, children, ...rest }: { primary?: boolean; icon?: LucideIcon } & React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      {...rest}
      className={cn(
        'inline-flex items-center gap-1.5 h-8 rounded-md text-[13px] font-medium border transition-all active:translate-y-px',
        children ? 'px-3' : 'w-8 justify-center',
        primary
          ? 'bg-primary text-primary-foreground border-primary shadow-sm hover:brightness-110'
          : 'bg-card text-foreground border-border hover:bg-muted',
      )}
    >
      {icon && <Ic d={icon} s={14} w={2.1} />}{children}
    </button>
  );
}

/* ═══ TIER 1 — HERO (roxo cheio · a marca assume a tela) ═══ */
function Hero({ previsto, saldo, pend }: { previsto: number; saldo: number; pend: number }) {
  return (
    <Box rounded="lg" className="relative overflow-hidden shadow-sm text-white sm:col-span-2 xl:col-span-2" style={{ background: 'linear-gradient(135deg, oklch(0.56 0.16 296), oklch(0.47 0.15 292))' }}>
      <span className="absolute -right-10 -top-12 w-48 h-48 rounded-full" style={{ background: 'radial-gradient(closest-side, oklch(0.75 0.16 300 / .35), transparent)' }} />
      <Stack gap={3} className="relative p-4">
        <Inline justify="between" align="start">
          <Inline gap={2}>
            <Box rounded="md" className="bg-white/15 grid place-items-center w-6 h-6"><Ic d={ICN.cal} s={13} w={2} /></Box>
            <Text as="span" size="xs" weight="semibold" className="uppercase tracking-[0.16em] text-white/80 whitespace-nowrap">Saldo previsto · maio</Text>
          </Inline>
          <Box className="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-0.5">
            <Ic d={ICN.out} s={11} w={2.6} className="text-white" />
            <Text as="span" size="xs" weight="bold" family="mono" numeric="tabular" className="leading-none text-white">12,4%</Text>
          </Box>
        </Inline>
        <Num n={previsto} size="hero" white />
        <Inline gap={6} align="center" wrap className="gap-y-2">
          <Stack gap={1}>
            <Text as="span" size="xs" className="uppercase tracking-wider text-white/55 leading-none whitespace-nowrap">Realizado</Text>
            <Inline gap={1} align="baseline"><Text as="span" size="xs" family="mono" className="text-white/55">R$</Text><Text as="span" size="base" family="mono" numeric="tabular" weight="semibold" className="text-white leading-none">{fmt(saldo)}</Text></Inline>
          </Stack>
          <span className="w-px h-7 bg-white/20" />
          <Stack gap={1}>
            <Text as="span" size="xs" className="uppercase tracking-wider text-white/55 leading-none whitespace-nowrap">Pendente líq.</Text>
            <Inline gap={1} align="baseline"><Text as="span" size="xs" family="mono" className="text-white/55">R$</Text><Text as="span" size="base" family="mono" numeric="tabular" weight="semibold" className="text-white leading-none">{K(pend)}</Text></Inline>
          </Stack>
        </Inline>
        <Box className="-mx-1 -mb-1">
          <svg viewBox="0 0 360 48" preserveAspectRatio="none" className="w-full h-12 block" aria-hidden="true">
            <defs><linearGradient id="finhg" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stopColor="#fff" stopOpacity="0.28" /><stop offset="100%" stopColor="#fff" stopOpacity="0" /></linearGradient></defs>
            <path d="M0,40 L30,36 L60,38 L90,30 L120,33 L150,24 L180,27 L210,18 L240,21 L270,14 L300,17 L330,9 L360,6 L360,48 L0,48 Z" fill="url(#finhg)" />
            <path d="M0,40 L30,36 L60,38 L90,30 L120,33 L150,24 L180,27 L210,18 L240,21 L270,14 L300,17 L330,9 L360,6" fill="none" stroke="#fff" strokeWidth="2" strokeOpacity="0.9" vectorEffect="non-scaling-stroke" />
            <circle cx="360" cy="6" r="3" fill="#fff" />
          </svg>
        </Box>
      </Stack>
    </Box>
  );
}

/* ═══ TIER 2 — A receber (acionável · ageing embutido) ═══ */
function ReceberCard({ total, atraso, emdia }: { total: number; atraso: number; emdia: number }) {
  return (
    <Box bg="card" border rounded="lg" className="relative overflow-hidden p-4 shadow-sm transition-colors hover:border-success/40">
      <span className="absolute inset-y-0 left-0 w-1 bg-success/60" />
      <Stack gap={3}>
        <Inline justify="between"><Label dot="bg-success">A receber</Label><Text as="span" tone="muted" className="text-[11px]">3 títulos</Text></Inline>
        <Num n={total} size="xl" />
        <Stack gap={2}>
          <Inline gap={1} className="h-1.5 rounded-full overflow-hidden bg-muted">
            <span className="bg-destructive" style={{ flex: atraso }} />
            <span className="bg-success" style={{ flex: emdia }} />
          </Inline>
          <Inline gap={4} wrap className="gap-y-1">
            <Inline gap={2}><span className="w-1.5 h-1.5 rounded-full bg-destructive shrink-0" /><Text as="span" tone="muted" className="text-[11px]">Atraso</Text><Text as="span" tone="destructive" family="mono" numeric="tabular" weight="semibold" className="text-[11px]">R$ {fmt(atraso)}</Text></Inline>
            <Inline gap={2}><span className="w-1.5 h-1.5 rounded-full bg-success shrink-0" /><Text as="span" tone="muted" className="text-[11px]">A vencer</Text><Text as="span" tone="success" family="mono" numeric="tabular" weight="semibold" className="text-[11px]">R$ {fmt(emdia)}</Text></Inline>
          </Inline>
        </Stack>
      </Stack>
    </Box>
  );
}

/* ═══ TIER 2 — A pagar (próximo vencimento) ═══ */
function PagarCard({ total }: { total: number }) {
  return (
    <Box bg="card" border rounded="lg" className="relative overflow-hidden p-4 shadow-sm transition-colors hover:border-warning/40">
      <span className="absolute inset-y-0 left-0 w-1 bg-warning/50" />
      <Stack gap={3}>
        <Inline justify="between"><Label dot="bg-warning">A pagar</Label><Text as="span" tone="muted" className="text-[11px]">2 títulos</Text></Inline>
        <Num n={total} size="xl" />
        <Stack gap={2}>
          <Text as="span" tone="muted" className="text-[10px] uppercase tracking-[0.13em] font-semibold">Próximo vencimento</Text>
          <Inline justify="between" className="rounded-md bg-muted/60 px-2.5 py-1.5">
            <Inline gap={2}><span className="w-1.5 h-1.5 rounded-full bg-warning" /><Text as="span" size="sm" weight="medium">10 mai · Suprigraf</Text></Inline>
            <Text as="span" size="sm" family="mono" numeric="tabular" weight="medium">R$ [redacted Tier 0]</Text>
          </Inline>
        </Stack>
      </Stack>
    </Box>
  );
}

/* ═══ TIER 3 — Realizado do mês (faixa fina secundária) ═══ */
function RealizadoStrip({ recebido, pago, saldo }: { recebido: number; pago: number; saldo: number }) {
  const stat = (l: string, v: number, t: Tone) => (
    <Inline gap={2} align="baseline"><Text as="span" tone="muted" className="text-[11px] uppercase tracking-wider">{l}</Text><Num n={v} size="sm" tone={t} /></Inline>
  );
  return (
    <Box bg="card" border rounded="lg" className="px-4 py-2.5 shadow-sm">
      <Inline justify="between" wrap className="gap-y-2">
        <Label>Realizado · maio</Label>
        <Inline gap={6} wrap className="gap-y-1.5">
          {stat('Recebido', recebido, 'success')}{stat('Pago', pago, 'destructive')}{stat('Saldo', saldo, 'default')}
        </Inline>
      </Inline>
    </Box>
  );
}

/* ═══ LEDGER (denso · acento de urgência · tag de categoria) ═══ */
const COLS = 'grid-cols-[78px_24px_minmax(0,1fr)_140px_104px_116px_32px] items-center';

function Ledger({ rows, onOpen }: { rows: Row[]; onOpen: (r: Row) => void }) {
  const groups: { grp: string; rows: Row[] }[] = [];
  rows.forEach((r) => {
    const g = groups.find((x) => x.grp === r.grp);
    if (g) g.rows.push(r);
    else groups.push({ grp: r.grp, rows: [r] });
  });
  return (
    <Box bg="card" border rounded="lg" className="overflow-hidden shadow-sm">
      <Grid gap={2} className={cn(COLS, 'px-3.5 h-8 bg-muted/50 border-b border-border')}>
        <Text as="span" size="xs" tone="muted" weight="semibold" className="uppercase tracking-wider">Venc.</Text><span />
        <Text as="span" size="xs" tone="muted" weight="semibold" className="uppercase tracking-wider">Lançamento</Text>
        <Text as="span" size="xs" tone="muted" weight="semibold" className="uppercase tracking-wider">Contraparte</Text>
        <Text as="span" size="xs" tone="muted" weight="semibold" className="uppercase tracking-wider">Status</Text>
        <Text as="span" size="xs" tone="muted" weight="semibold" align="right" className="uppercase tracking-wider">Valor</Text><span />
      </Grid>
      <Stack gap={0} divider>
        {groups.map((g) => (
          <Stack key={g.grp} gap={0} divider>
            <Inline className="px-3.5 h-6 bg-muted/25"><Text as="span" size="xs" tone="muted" weight="semibold" className="uppercase tracking-[0.16em]">{g.grp}</Text></Inline>
            {g.rows.map((r, i) => {
              const urg = r.st === 'atrasado'
                ? 'shadow-[inset_3px_0_0_hsl(var(--destructive))]'
                : r.st === 'vencendo'
                  ? 'shadow-[inset_3px_0_0_hsl(var(--warning))]'
                  : '';
              return (
                <Grid
                  key={i}
                  gap={2}
                  onClick={() => onOpen(r)}
                  className={cn(COLS, 'group px-3.5 h-10 hover:bg-primary/[0.04] transition-colors cursor-pointer', urg, r.paid && 'opacity-[0.62] hover:opacity-100')}
                >
                  <Stack gap={0}>
                    <Text as="span" size="sm" family="mono" numeric="tabular" weight="semibold" leading="tight">{r.d}</Text>
                    <Text as="span" size="xs" tone={r.st === 'atrasado' ? 'destructive' : r.st === 'vencendo' ? 'warning' : 'muted'} leading="none">{r.when}</Text>
                  </Stack>
                  <Dir kind={r.kind} s={24} />
                  <Inline gap={2} className="min-w-0">
                    <Text as="span" size="sm" weight="medium" truncate>{r.desc}</Text>
                    <Inline gap={1} className="shrink-0">
                      <span className="w-1.5 h-1.5 rounded-full" style={{ background: `oklch(0.62 0.13 ${CAT[r.cat] || '280'})` }} />
                      <Text as="span" size="xs" tone="muted">{r.cat}</Text>
                    </Inline>
                    <Text as="span" size="xs" tone="muted" family="mono" className="shrink-0">{r.nf}</Text>
                  </Inline>
                  <Text as="span" size="sm" tone="muted" truncate>{r.who}</Text>
                  <Badge st={r.st} />
                  <Money n={r.kind === 'in' ? r.amt : -r.amt} signed tone={r.kind === 'in' ? 'success' : 'default'} size="sm" strong />
                  <Inline justify="end">
                    {!r.paid && (
                      <button onClick={(e) => e.stopPropagation()} title={r.kind === 'in' ? 'Marcar recebido' : 'Marcar pago'} className="opacity-0 group-hover:opacity-100 w-6 h-6 grid place-items-center rounded-md text-success hover:bg-success/12 transition-all">
                        <Ic d={ICN.check} s={14} w={2.4} />
                      </button>
                    )}
                  </Inline>
                </Grid>
              );
            })}
          </Stack>
        ))}
      </Stack>
    </Box>
  );
}

/* ═══ DRAWER DE DETALHE — domínio 9.75 (conciliação · fiscal · cobrança) ═══ */
function Sec({ icon, title, right, children }: { icon: ReactNode; title: string; right?: ReactNode; children: ReactNode }) {
  return (
    <Stack gap={3} className="py-4">
      <Inline justify="between">
        <Inline gap={2}><Box rounded="md" className="w-6 h-6 grid place-items-center bg-primary/10 text-primary shrink-0">{icon}</Box><Text as="span" size="sm" weight="semibold">{title}</Text></Inline>
        {right}
      </Inline>
      {children}
    </Stack>
  );
}

function FSM({ stage }: { stage: number }) {
  const steps = ['Emitido', 'Conferido', 'Conciliado', 'Liquidado'];
  return (
    <Inline gap={0} className="w-full">
      {steps.map((s, i) => {
        const done = i < stage;
        const cur = i === stage;
        return (
          <React.Fragment key={s}>
            <Stack gap={2} align="center" className="shrink-0">
              <Box className={cn('w-6 h-6 rounded-full grid place-items-center border-2', done ? 'bg-primary border-primary text-primary-foreground' : cur ? 'border-primary text-primary bg-card' : 'border-border text-muted-foreground bg-card')}>
                {done ? <Ic d={ICN.check} s={12} w={3} /> : <Text as="span" size="xs" family="mono" weight="bold" className="leading-none">{i + 1}</Text>}
              </Box>
              <Text as="span" size="xs" tone={done || cur ? 'default' : 'muted'} weight={cur ? 'semibold' : 'normal'} className="whitespace-nowrap leading-none">{s}</Text>
            </Stack>
            {i < 3 && <span className={cn('flex-1 h-0.5 mt-3', done ? 'bg-primary' : 'bg-border')} />}
          </React.Fragment>
        );
      })}
    </Inline>
  );
}

function KV({ k, v, mono }: { k: string; v: string; mono?: boolean }) {
  return (
    <Stack gap={0}>
      <Text as="span" size="xs" tone="muted" className="uppercase tracking-wider">{k}</Text>
      <Text as="span" size="sm" weight="medium" family={mono ? 'mono' : undefined}>{v}</Text>
    </Stack>
  );
}

function Drawer({ row, onClose }: { row: Row | null; onClose: () => void }) {
  if (!row) return null;
  const isIn = row.kind === 'in';
  const settled = !!row.paid;
  const late = row.st === 'atrasado';
  const stage = settled ? 4 : 2;
  const p = parts(row.amt);
  return (
    <Box className="fixed inset-0 z-[60]">
      <button type="button" aria-label="Fechar detalhe" onClick={onClose} className="absolute inset-0 bg-foreground/25 cursor-default" />
      <Stack gap={0} role="dialog" aria-modal="true" className="absolute top-0 right-0 h-full w-[452px] max-w-[94vw] bg-card text-card-foreground border-l border-border shadow-2xl">
        {/* header */}
        <Inline gap={3} className="px-5 h-14 border-b border-border shrink-0">
          <Dir kind={row.kind} s={30} />
          <Stack gap={0} className="flex-1 min-w-0">
            <Text as="span" size="xs" tone="muted" weight="semibold" className="uppercase tracking-wider">{isIn ? 'A receber' : 'A pagar'} · #FIN-{row.nf.replace(/\D/g, '')}</Text>
            <Text as="span" size="sm" weight="semibold" truncate>{row.desc}</Text>
          </Stack>
          <button onClick={onClose} className="w-8 h-8 grid place-items-center rounded-md text-muted-foreground hover:bg-muted shrink-0"><Ic d={ICN.x} s={16} w={2.2} /></button>
        </Inline>
        {/* hero */}
        <Box className="px-5 pt-4 pb-4 shrink-0 border-b border-border">
          <Stack gap={3}>
            <Inline justify="between" align="end">
              <Stack gap={1}>
                <Text as="span" size="xs" weight="semibold" tone={late ? 'destructive' : 'muted'} className="uppercase tracking-wider">{settled ? 'Liquidado' : 'Vencimento'}</Text>
                <Inline gap={2} align="baseline"><Text as="span" size="lg" weight="semibold" family="mono" numeric="tabular">{row.d}/2026</Text><Text as="span" size="xs" tone={late ? 'destructive' : 'muted'}>{row.when}</Text></Inline>
              </Stack>
              <Badge st={row.st} />
            </Inline>
            <Inline gap={0} align="baseline">
              <Text as="span" size="sm" tone="muted" family="mono" className="mr-1">{isIn ? '+ R$' : '− R$'}</Text>
              <Text as="span" size="4xl" weight="bold" family="mono" numeric="tabular" tone={isIn ? 'success' : 'default'} leading="none" className="tracking-tight">{p.int}</Text>
              <Text as="span" size="sm" tone="muted" family="mono" className="ml-0.5">,{p.dec}</Text>
            </Inline>
            <FSM stage={stage} />
          </Stack>
        </Box>
        {/* body */}
        <Box className="flex-1 overflow-y-auto px-5">
          <Stack gap={0} divider>
            {/* CONCILIAÇÃO */}
            <Sec icon={<Ic d={ICN.refresh} s={13} w={2} />} title="Conciliação" right={<Text as="span" size="xs" tone={settled ? 'success' : 'muted'} weight="semibold">{settled ? '100% match' : 'sem match'}</Text>}>
              {settled ? (
                <Box rounded="lg" className="p-3 border border-success/25 bg-success/[0.06]">
                  <Inline gap={2} align="start">
                    <Box className="w-5 h-5 rounded-full grid place-items-center bg-success/15 text-success shrink-0 mt-0.5"><Ic d={ICN.check} s={11} w={3} /></Box>
                    <Stack gap={1}><Text as="span" size="sm" weight="medium">Conciliado · extrato OFX 04392</Text><Text as="span" size="xs" tone="muted" family="mono" numeric="tabular">{row.d}/05 · R$ {fmt(row.amt)} · ±R$ [redacted Tier 0] · ±0 dias</Text></Stack>
                  </Inline>
                </Box>
              ) : (
                <Stack gap={3}>
                  <Box bg="muted" rounded="lg" className="p-3">
                    <Stack gap={2}>
                      <Inline gap={2}><Box className="w-5 h-5 rounded-full grid place-items-center bg-primary/15 text-primary shrink-0"><Ic d={ICN.bolt} s={11} w={2} /></Box><Text as="span" size="xs" tone="muted">Sugestão IA · linha provável no extrato (±R$ [redacted Tier 0] · ±2 dias)</Text></Inline>
                      <Inline justify="between" className="pl-7"><Text as="span" size="sm" family="mono" numeric="tabular">{row.d}/05 · {row.who}</Text><Text as="span" size="sm" family="mono" numeric="tabular" weight="medium">R$ {fmt(row.amt)}</Text></Inline>
                    </Stack>
                  </Box>
                  <Inline gap={2}><Btn icon={ICN.check}>Conciliar</Btn><Btn>Importar OFX</Btn></Inline>
                </Stack>
              )}
            </Sec>
            {/* FISCAL */}
            <Sec icon={<Ic d={ICN.doc} s={13} w={2} />} title="Fiscal" right={<Box className="inline-flex items-center gap-1.5 rounded-full bg-success/12 text-success px-2 py-0.5"><span className="w-1.5 h-1.5 rounded-full bg-success" /><Text as="span" size="xs" weight="semibold">Autorizada</Text></Box>}>
              <Stack gap={3}>
                <Grid cols={2} gap={3}>
                  <KV k="Documento" v={row.nf} />
                  <KV k="Natureza" v={isIn ? 'NF-e · saída' : 'NF-e · entrada'} />
                </Grid>
                <Stack gap={1}>
                  <Text as="span" size="xs" tone="muted" className="uppercase tracking-wider">Chave de acesso</Text>
                  <Box bg="muted" rounded="md" className="px-2.5 py-1.5"><Text as="span" size="xs" family="mono" className="break-all leading-relaxed">3526 0512 3456 7800 0125 5500 1000 0419 8100 0419 8100</Text></Box>
                </Stack>
                <Stack gap={0} divider>
                  <Inline justify="between" className="py-1.5"><Text as="span" size="sm" tone="muted">ISS retido · 5%</Text><Text as="span" size="sm" family="mono" numeric="tabular" weight="medium">R$ {fmt(row.amt * 0.05)}</Text></Inline>
                  <Inline justify="between" className="py-1.5"><Text as="span" size="sm" tone="muted">A recolher · DAS Simples</Text><Text as="span" size="sm" family="mono" numeric="tabular" weight="medium" tone="warning">R$ {fmt(row.amt * 0.06)}</Text></Inline>
                </Stack>
              </Stack>
            </Sec>
            {/* COBRANÇA */}
            <Sec icon={<Ic d={ICN.send} s={12} w={2} />} title="Cobrança" right={<Text as="span" size="xs" tone={settled ? 'success' : late ? 'destructive' : 'warning'} weight="semibold">{settled ? 'pago' : late ? 'vencido' : 'registrado'}</Text>}>
              <Stack gap={3}>
                <Stack gap={1}>
                  <Text as="span" size="xs" tone="muted" className="uppercase tracking-wider">Linha digitável · boleto</Text>
                  <Box bg="muted" rounded="md" className="px-2.5 py-1.5"><Text as="span" size="xs" family="mono" numeric="tabular" className="break-all">34191.79001 01043.510047 91020.150008 1 9877000{p.int}00</Text></Box>
                </Stack>
                <Stack gap={0}>
                  <Text as="span" size="xs" tone="muted" className="uppercase tracking-wider mb-1.5">Régua de cobrança</Text>
                  {([['D+0', 'Boleto emitido', 'done'], ['D+3', 'Lembrete WhatsApp', late ? 'done' : 'pending'], ['D+8', 'Aviso de vencido', late ? 'cur' : 'pending']] as const).map(([dd, l, s], i) => (
                    <Inline key={i} gap={2} className="py-1">
                      <Text as="span" size="xs" family="mono" tone="muted" className="w-7">{dd}</Text>
                      <span className={cn('w-2 h-2 rounded-full shrink-0', s === 'done' ? 'bg-success' : s === 'cur' ? 'bg-destructive' : 'bg-border')} />
                      <Text as="span" size="sm" tone={s === 'pending' ? 'muted' : 'default'}>{l}</Text>
                    </Inline>
                  ))}
                </Stack>
                {!settled && <Inline gap={2} wrap className="gap-y-2"><Btn icon={ICN.link}>2ª via</Btn><Btn icon={ICN.bolt}>Link PIX</Btn><Btn primary icon={ICN.send}>Lembrar cliente</Btn></Inline>}
              </Stack>
            </Sec>
            {/* DETALHE */}
            <Sec icon={<Ic d={ICN.search} s={12} w={2} />} title="Detalhe">
              <Grid cols={2} gap={3}>
                <KV k="Contraparte" v={row.who} />
                <KV k="Categoria" v={row.cat} />
                <KV k="Conta" v="Itaú PJ · 4521" />
                <KV k="Canal" v="Boleto registrado" />
              </Grid>
            </Sec>
          </Stack>
        </Box>
        {/* footer */}
        <Inline gap={2} className="px-5 h-16 border-t border-border shrink-0 bg-muted/30">
          <Btn icon={ICN.doc}>Ver NF-e</Btn>
          <Box className="flex-1" />
          {!settled ? (
            <Btn primary icon={ICN.check}>{isIn ? 'Recebi' : 'Paguei'}</Btn>
          ) : (
            <Inline gap={2}><span className="text-success"><Ic d={ICN.check} s={15} w={2.4} /></span><Text as="span" size="sm" tone="success" weight="semibold">Liquidado</Text></Inline>
          )}
        </Inline>
      </Stack>
    </Box>
  );
}

/* ═══ TELA ═══ */
function FinanceiroProvaViva() {
  const [lens, setLens] = useState<'caixa' | 'receber' | 'pagar'>('caixa');
  const [sel, setSel] = useState<Row | null>(null);

  const recebido = ROWS.filter((r) => r.kind === 'in' && r.paid).reduce((s, r) => s + r.amt, 0);
  const pago = ROWS.filter((r) => r.kind === 'out' && r.paid).reduce((s, r) => s + r.amt, 0);
  const aReceber = ROWS.filter((r) => r.kind === 'in' && !r.paid).reduce((s, r) => s + r.amt, 0);
  const aPagar = ROWS.filter((r) => r.kind === 'out' && !r.paid).reduce((s, r) => s + r.amt, 0);
  const atraso = ROWS.filter((r) => r.kind === 'in' && !r.paid && r.st === 'atrasado').reduce((s, r) => s + r.amt, 0);
  const saldo = recebido - pago;
  const previsto = saldo + aReceber - aPagar;
  const totalIn = ROWS.filter((r) => r.kind === 'in').reduce((s, r) => s + r.amt, 0);
  const totalOut = ROWS.filter((r) => r.kind === 'out').reduce((s, r) => s + r.amt, 0);

  const LENSES: [typeof lens, string][] = [['caixa', 'Caixa'], ['receber', 'A receber'], ['pagar', 'A pagar']];
  const FILTERS: [string, number][] = [['Todas', 9], ['Aberto', 3], ['A receber', 2], ['A pagar', 1], ['Atraso', 1]];

  return (
    <Box className="bg-page-cream min-h-full">
      <Container size="xl" px={6} className="py-5">
        <Stack gap={4}>
          {/* HEADER */}
          <Inline justify="between" align="end" wrap className="gap-y-3">
            <Stack gap={1}>
              <Inline gap={2}><Text as="span" size="xs" tone="muted">Financeiro</Text><Ic d={ICN.chev} s={11} w={2} /><Text as="span" size="xs" weight="medium">Prova viva · primitivos</Text></Inline>
              <Inline gap={3} align="baseline" wrap><Text as="h1" weight="bold" className="text-[26px] tracking-[-0.025em] leading-none">Financeiro</Text><Text as="span" tone="muted" weight="semibold" className="text-[11px] uppercase tracking-[0.16em] whitespace-nowrap">Maio 2026 · ROTA LIVRE</Text></Inline>
            </Stack>
            <Inline gap={2} wrap className="gap-y-2">
              <Box bg="muted" rounded="md" className="p-0.5">
                <Inline gap={1}>{LENSES.map(([id, l]) => (
                  <button key={id} onClick={() => setLens(id)} className={cn('h-7 px-3 rounded-md text-[13px] font-medium transition-all', lens === id ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground')}>{l}</button>
                ))}</Inline>
              </Box>
              <Inline gap={2} className="h-8 px-2.5 w-[190px] bg-card border border-border rounded-md"><Ic d={ICN.search} s={14} w={2} /><Text as="span" size="sm" tone="muted" className="flex-1 truncate">Buscar…</Text><Kbd>⌘K</Kbd></Inline>
              <Btn icon={ICN.refresh}>Conciliar</Btn>
              <Btn primary icon={ICN.plus}>Novo</Btn>
            </Inline>
          </Inline>

          {/* TIER 1+2 — acionável (Hero + A receber + A pagar) */}
          <Grid cols={1} gap={3} className="sm:grid-cols-2 xl:grid-cols-4">
            <Hero previsto={previsto} saldo={saldo} pend={aReceber - aPagar} />
            <ReceberCard total={aReceber} atraso={atraso} emdia={aReceber - atraso} />
            <PagarCard total={aPagar} />
          </Grid>

          {/* TIER 3 — realizado do mês */}
          <RealizadoStrip recebido={recebido} pago={pago} saldo={saldo} />

          {/* FILTROS densos */}
          <Inline gap={2} wrap className="gap-y-2">
            {FILTERS.map(([f, n], i) => (
              <button key={f} className={cn('inline-flex items-center gap-1.5 h-7 rounded-full pl-3 pr-2 text-xs font-semibold border transition-colors', i === 0 ? 'bg-primary text-primary-foreground border-primary' : 'bg-card border-border text-muted-foreground hover:text-foreground hover:border-primary/40')}>
                {f}
                <Box className={cn('rounded-full px-1.5 min-w-[16px] text-center', i === 0 ? 'bg-white/20' : 'bg-muted')}><Text as="span" size="xs" family="mono" numeric="tabular" tone={i === 0 ? undefined : 'muted'} className={i === 0 ? 'text-white' : undefined}>{n}</Text></Box>
              </button>
            ))}
            <Box className="flex-1 min-w-[8px]" />
            <Inline gap={1} className="h-7 px-2.5 bg-card border border-border rounded-md"><Text as="span" size="xs" tone="muted">Conta</Text><Text as="span" size="xs" weight="medium">Itaú PJ · 4521</Text><Ic d={ICN.chev} s={11} w={2} /></Inline>
          </Inline>

          <Ledger rows={ROWS} onOpen={setSel} />

          {/* FOOTER */}
          <Box bg="card" border rounded="lg" className="h-11 px-4 shadow-sm">
            <Inline justify="between" wrap align="center" className="w-full h-full">
              <Inline gap={4} divider wrap className="gap-y-1">
                <Inline gap={2}><Text as="span" size="sm" weight="bold" family="mono" numeric="tabular">{ROWS.length}</Text><Text as="span" size="sm" tone="muted">lançamentos</Text></Inline>
                <Inline gap={2} className="pl-4"><Text as="span" size="sm" tone="muted">Entrada</Text><Text as="span" size="sm" family="mono" numeric="tabular" tone="success" weight="semibold">R$ {fmt(totalIn)}</Text></Inline>
                <Inline gap={2} className="pl-4"><Text as="span" size="sm" tone="muted">Saída</Text><Text as="span" size="sm" family="mono" numeric="tabular" weight="semibold">R$ {fmt(totalOut)}</Text></Inline>
              </Inline>
              <Inline gap={3} wrap className="gap-y-1">
                <Inline gap={2}><Kbd>J</Kbd><Kbd>K</Kbd><Text as="span" size="xs" tone="muted">navegar</Text></Inline>
                <Inline gap={2}><Kbd>␣</Kbd><Text as="span" size="xs" tone="muted">liquidar</Text></Inline>
                <Inline gap={2}><Kbd>/</Kbd><Text as="span" size="xs" tone="muted">buscar</Text></Inline>
              </Inline>
            </Inline>
          </Box>
        </Stack>
      </Container>
      <Drawer row={sel} onClose={() => setSel(null)} />
    </Box>
  );
}

FinanceiroProvaViva.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Prova viva (primitivos)"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Prova viva (primitivos)' }]}
  >
    {page}
  </AppShellV2>
);

export default FinanceiroProvaViva;
