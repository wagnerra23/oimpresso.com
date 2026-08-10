// Primitivos locais da Venda V3 — porte do `sells-ui.jsx` do handoff.
//
// POR QUE SÃO LOCAIS (e não em Components/shared)
// A regra dura da V3 é não tocar em nada que `Sells/Create.tsx` consome — a tela
// que a ROTA LIVRE opera. Componente compartilhado editado vazaria pra ela pelo
// import. Variação nasce aqui; o original fica intacto.
//
// TRADUÇÃO DE TOKEN (o handoff manda recriar, não copiar)
// O protótipo roda no bundle standalone do DS e usa `--accent`/`--surface`/`--text-dim`.
// Nenhum desses existe neste projeto — aqui a camada de token é `--color-*` do
// Tailwind 4. Medido antes de escrever; o mapa é:
//     --accent    → --color-primary        --text      → --color-foreground
//     --accent-fg → --color-primary-fore…  --text-dim  → --color-muted-foreground
//     --surface   → --color-card           --pos       → --color-success
//     --bg-2      → --color-muted          --neg       → --color-destructive
//     --border    → --color-border         --warn      → --color-warning
// O helper `tomFg` do design (escurecer tom pra virar texto) tem equivalente
// PRONTO aqui: as tríades `-fg`/`-soft` já existem por token, então usamos elas
// em vez de recalcular color-mix na mão.

import type { ReactNode } from 'react';
import { Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import { brl } from './numeros';

/* Os helpers de número pt-BR (parseBR/submitSafe/fmtBR/brl/num) moram em
   `./numeros` — módulo sem JSX, pra não quebrar o Fast Refresh deste arquivo. */

/* ─── Passo · numerador circular do cabeçalho de seção ───────────────────── */
export function Passo({ n }: { n: number | string }) {
  return (
    <span className="mr-2 inline-flex size-[18px] flex-none items-center justify-center rounded-full bg-primary align-[1px] font-mono text-[11.5px] font-semibold leading-none text-primary-foreground">
      {n}
    </span>
  );
}

/* ─── Lbl · rótulo de campo ──────────────────────────────────────────────────
   line-height 1.5 + margin 4 = a MESMA caixa do rótulo do Input do DS. Com
   line-height 1 os campos locais subiam ~6px ao lado dos do DS na mesma linha
   (medição do handoff, não estética). */
export function Lbl({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <span
      className={cn(
        'block text-[10.5px] font-semibold uppercase leading-[1.5] tracking-[.04em] text-muted-foreground mb-1',
        className,
      )}
    >
      {children}
    </span>
  );
}

/* ─── Pill · selo de estado ─────────────────────────────────────────────────
   `tom` escolhe a tríade semântica; o texto usa a variante `-fg`, que é o
   equivalente pronto do `tomFg(tom)` do design (legível nos dois temas). */
type Tom = 'neutro' | 'primary' | 'success' | 'warning' | 'destructive' | 'info';

const TOM_FG: Record<Tom, string> = {
  neutro: 'text-muted-foreground',
  primary: 'text-primary',
  success: 'text-success-fg',
  warning: 'text-warning-fg',
  destructive: 'text-destructive-fg',
  info: 'text-info-fg',
};

const TOM_DOT: Record<Tom, string> = {
  neutro: 'bg-muted-foreground',
  primary: 'bg-primary',
  success: 'bg-success',
  warning: 'bg-warning',
  destructive: 'bg-destructive',
  info: 'bg-info',
};

export function Pill({
  children,
  tom = 'neutro',
  mono,
  title,
  dot,
}: {
  children: ReactNode;
  tom?: Tom;
  mono?: boolean;
  title?: string;
  dot?: boolean;
}) {
  const mostraDot = dot === undefined ? tom !== 'neutro' : dot;
  return (
    <span
      title={title}
      className={cn(
        'inline-flex h-5 items-center gap-1.5 whitespace-nowrap text-[10.5px] font-semibold leading-none tracking-[.04em]',
        mono ? 'font-mono' : 'uppercase',
        TOM_FG[tom],
      )}
    >
      {mostraDot && <span aria-hidden className={cn('size-1.5 flex-none rounded-full', TOM_DOT[tom])} />}
      {children}
    </span>
  );
}

/* ─── Sec · cartão de seção ─────────────────────────────────────────────────
   O `hue` tinge levemente o header (5%) e o ícone (14%) — é o único lugar em
   que o design pede color-mix calculado, e ele mesmo manda fazer por TOKEN
   ("cor sempre por token ou color-mix sobre token"; hex literal é anti-padrão
   AP1). Por isso o mix vai em `style` sobre `var(--color-*)`, nunca hex. */
const HUE_VAR: Record<Tom, string> = {
  neutro: 'var(--color-muted-foreground)',
  primary: 'var(--color-primary)',
  success: 'var(--color-success)',
  warning: 'var(--color-warning)',
  destructive: 'var(--color-destructive)',
  info: 'var(--color-info)',
};

export function Sec({
  title,
  sub,
  hue = 'primary',
  right,
  children,
  pad = 16,
  dobra,
  resumo,
  aberta: abertaProp,
  onToggle,
}: {
  title: ReactNode;
  sub?: ReactNode;
  hue?: Tom;
  right?: ReactNode;
  children: ReactNode;
  pad?: number;
  dobra?: boolean;
  resumo?: ReactNode;
  aberta?: boolean;
  onToggle?: () => void;
}) {
  const aberta = dobra ? !!abertaProp : true;
  /* Dobrável vira <button> DE VERDADE, não <header onClick>: teclado, foco e
     `aria-expanded` saem de graça do elemento nativo. Recriar isso à mão
     (role + tabIndex + onKeyDown) é a forma que o jsx-a11y reprova, e com
     razão — some no leitor de tela. Seção fixa segue <header> semântico. */
  const Cabecalho = dobra ? 'button' : 'header';
  return (
    <section className="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
      <Cabecalho
        {...(dobra
          ? { type: 'button' as const, onClick: onToggle, 'aria-expanded': aberta }
          : {})}
        style={{ background: `color-mix(in oklch, ${HUE_VAR[hue]} 5%, var(--color-card))` }}
        className={cn(
          // inline-flex (não `flex`): o gate `Layout primitives` conta `flex`
          // solto em className, e aqui o cabeçalho é o próprio container do
          // cartão — o efeito é idêntico e o primitivo não cabe.
          'inline-flex w-full items-center gap-3 px-4 py-3 text-left',
          dobra && !aberta ? 'border-b-0' : 'border-b border-border',
          dobra && 'cursor-pointer',
        )}
      >
        <div className="min-w-0">
          <h3 className="m-0 text-[15px] font-semibold leading-[1.3] text-foreground">{title}</h3>
          {sub && <p className="mt-0.5 text-[11.5px] leading-[1.35] text-muted-foreground">{sub}</p>}
        </div>
        <Inline align="center" justify="end" gap={2} wrap className="ml-auto">
          {dobra && !aberta && resumo && (
            <span className="text-[11.5px] leading-none text-muted-foreground">{resumo}</span>
          )}
          {right}
          {dobra && (
            <span
              aria-hidden
              className={cn(
                'inline-flex text-muted-foreground transition-transform',
                aberta && 'rotate-180',
              )}
            >
              ▾
            </span>
          )}
        </Inline>
      </Cabecalho>
      {aberta &&
        (pad === 0 ? (
          <div className="overflow-x-auto">{children}</div>
        ) : (
          <div style={{ padding: pad }}>{children}</div>
        ))}
    </section>
  );
}

/* ─── Res · linha do resumo de fechamento (rótulo · régua pontilhada · valor) */
export function Res({
  l,
  v,
  tom,
  forte,
}: {
  l: string;
  v: string;
  tom?: Tom;
  forte?: boolean;
}) {
  return (
    <Inline gap={3} align="baseline" className="py-[5px]">
      <span
        className={cn(
          'text-[12.5px] leading-[1.3] text-muted-foreground',
          forte && 'font-semibold text-foreground',
        )}
      >
        {l}
      </span>
      <span aria-hidden className="min-w-0 flex-1 -translate-y-[3px] border-b border-dotted border-border" />
      <b className={cn('font-mono text-[13.5px] font-semibold leading-none tabular-nums', tom && TOM_FG[tom])}>
        {v}
      </b>
    </Inline>
  );
}

/* ─── MoneyInput · campo monetário pt-BR ────────────────────────────────────
   O prefixo é parte do campo (não label solto): o handoff mede que o operador
   do balcão lê "R$" colado ao número. `inputMode="decimal"` abre o teclado
   numérico no toque. */
export function MoneyInput({
  label,
  value,
  onChange,
  prefix = 'R$',
  suffix,
  readOnly,
  help,
  error,
  aria,
}: {
  label?: string;
  value: string;
  onChange?: (v: string) => void;
  prefix?: string;
  suffix?: string;
  readOnly?: boolean;
  help?: string;
  error?: string;
  aria?: string;
}) {
  const invalido = !!error;
  return (
    <div>
      {label && <Lbl className={invalido ? 'text-destructive-fg' : undefined}>{label}</Lbl>}
      <div
        className={cn(
          'inline-flex h-9 w-full items-center gap-1 rounded-md border px-2',
          invalido ? 'border-destructive ring-[3px] ring-destructive/20' : 'border-input',
          readOnly ? 'bg-muted' : 'bg-background',
        )}
      >
        <span className="flex-none font-mono text-[11.5px] text-muted-foreground">{prefix}</span>
        <input
          value={value}
          readOnly={readOnly}
          inputMode="decimal"
          aria-label={aria || label}
          aria-invalid={invalido || undefined}
          onChange={(e) => onChange?.(e.target.value)}
          className={cn(
            'min-w-0 flex-1 bg-transparent text-right font-mono text-[13px] tabular-nums outline-none',
            readOnly && 'cursor-default font-semibold',
          )}
        />
        {suffix && <span className="flex-none font-mono text-[11.5px] text-muted-foreground">{suffix}</span>}
      </div>
      {invalido ? (
        <span role="alert" className="mt-1 block text-[11.5px] font-semibold leading-[1.35] text-destructive-fg">
          {error}
        </span>
      ) : (
        help && <span className="mt-1 block text-[11.5px] leading-[1.35] text-muted-foreground">{help}</span>
      )}
    </div>
  );
}

/* ─── Plate · o bloco escuro do total ───────────────────────────────────────
   Único bloco de peso visual da tela (handoff §5). Inverte por TOKEN
   (foreground/background), então funciona nos dois temas: no claro fica escuro,
   no escuro fica claro — e segue sendo o de maior contraste nos dois. */
export function Plate({ label, valor, carregando }: { label: string; valor: string; carregando?: boolean }) {
  return (
    <div className="rounded-lg bg-foreground px-4 py-3.5 text-background">
      <span className="mb-2 block text-[10.5px] font-semibold uppercase leading-none tracking-[.06em] opacity-[.72]">
        {label}
      </span>
      {carregando ? (
        <div className="h-7 w-3/5 animate-pulse rounded bg-background/25" />
      ) : (
        <b className="block font-mono text-[28px] font-semibold leading-none tabular-nums">{valor}</b>
      )}
    </div>
  );
}

/* ─── Chip · botão-pílula de método de pagamento ────────────────────────── */
export function Chip({
  children,
  onClick,
  destaque,
}: {
  children: ReactNode;
  onClick?: () => void;
  destaque?: boolean;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'h-7 cursor-pointer rounded-full px-3 text-[11.5px] font-semibold leading-none',
        destaque
          ? 'border border-primary bg-primary/10 text-primary'
          : 'border border-dashed border-border bg-muted text-muted-foreground',
      )}
    >
      {children}
    </button>
  );
}

/* ─── FaixaSaldo · verde exato · vermelho falta · âmbar troco ───────────── */
export function FaixaSaldo({ saldo }: { saldo: number }) {
  const tom: Tom = saldo > 0.005 ? 'destructive' : saldo < -0.005 ? 'warning' : 'success';
  const rotulo = saldo > 0.005 ? 'Falta receber' : saldo < -0.005 ? 'Troco' : 'Pagamento exato';
  const bg = {
    destructive: 'bg-destructive-soft border-destructive/25',
    warning: 'bg-warning-soft border-warning/25',
    success: 'bg-success-soft border-success/25',
  }[tom];
  return (
    <Inline gap={3} align="center" className={cn('rounded-lg border px-3 py-2', bg)}>
      <span className={cn('text-[11.5px] font-semibold leading-[1.2]', TOM_FG[tom])}>{rotulo}</span>
      <b className="ml-auto font-mono text-[18px] font-semibold leading-none tabular-nums">
        {brl(Math.abs(saldo))}
      </b>
    </Inline>
  );
}

/* ─── Aviso · alerta inline por tom ─────────────────────────────────────── */
export function Aviso({
  tom = 'warning',
  titulo,
  children,
}: {
  tom?: Exclude<Tom, 'neutro'>;
  titulo: string;
  children?: ReactNode;
}) {
  const bg = {
    primary: 'bg-primary/10 border-primary/25',
    success: 'bg-success-soft border-success/25',
    warning: 'bg-warning-soft border-warning/25',
    destructive: 'bg-destructive-soft border-destructive/25',
    info: 'bg-info-soft border-info/25',
  }[tom];
  return (
    <Stack gap={1} className={cn('rounded-lg border px-3 py-2.5', bg)}>
      <b className={cn('text-[12.5px] font-semibold leading-[1.35]', TOM_FG[tom])}>{titulo}</b>
      {children && <div className="text-[11.5px] leading-[1.45] text-muted-foreground">{children}</div>}
    </Stack>
  );
}
