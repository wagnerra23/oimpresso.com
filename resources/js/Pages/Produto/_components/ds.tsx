/**
 * Primitivos DS do Catálogo Unificado — porte VERBATIM do bundle Cowork.
 *
 * Fonte: `prototipo-ui/cowork/prototipos/produto/produto-unificado-v2.dc.html`
 * → `_ds/office-impresso-atual-.../\_ds_bundle.js` (OfficeImpressoDesignSystem).
 * Cada estilo abaixo é cópia literal do componente homônimo do bundle: os
 * valores NÃO foram arredondados nem traduzidos pra escala Tailwind. `10.5px`
 * é 10.5px; `padding: '9px 12px'` é 9px 12px. Traduzir pra `text-xs`/`px-3`
 * seria aproximar, e o pedido de [M] (2026-08-14) foi cópia exata.
 *
 * POR QUE AQUI e não em `Components/ui/`: são consumidos por UMA tela
 * (`Pages/Produto/Unificado/Index.tsx`), que é o critério da rule
 * `.claude/rules/components.md` — "domínio de 1 módulo só → Pages/<Mod>/_components/".
 * Publicá-los como primitivo canon criaria um SEGUNDO dono de papel que
 * `Components/shared/{DataTable,EmptyState}` já ocupam (mesmo divergindo do DS),
 * e o detector `component-registry-check --roles` existe exatamente pra isso.
 * A reconciliação daqueles com o DS é dívida cross-tela, registrada no §4 do
 * comparativo visual — não se resolve dentro de um PR de tela.
 *
 * Os componentes do DS que o repo JÁ porta fielmente não estão aqui, são
 * consumidos direto: `PageHeader` (@/Components/PageHeader) e `TabBar`
 * (@/Components/shared/PageHeaderTabs).
 */
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, ReactNode } from 'react';

/* ─── Button ──────────────────────────────────────────────────────────────── */

type DsButtonProps = {
  children?: ReactNode;
  variant?: 'primary' | 'ghost' | 'danger';
  size?: 'sm' | 'default' | 'lg';
  icon?: boolean;
  kbd?: string;
  disabled?: boolean;
  onClick?: () => void;
  style?: CSSProperties;
};

const BTN_VARIANT = {
  primary: { bg: 'var(--accent)', fg: 'var(--accent-fg)', bd: 'transparent', weight: 600, hbg: 'var(--accent-2)' },
  ghost: { bg: 'var(--surface)', fg: 'var(--text-dim)', bd: 'var(--border)', weight: 500, hbg: 'var(--bg-2)' },
  danger: {
    bg: 'var(--surface)',
    fg: 'var(--color-destructive-fg)',
    bd: 'color-mix(in oklch, var(--color-destructive-fg) 30%, transparent)',
    weight: 500,
    hbg: 'var(--color-destructive-soft)',
  },
} as const;

export function DsButton({
  children, variant = 'ghost', size = 'default', icon = false, kbd, disabled = false, onClick, style,
}: DsButtonProps) {
  const H = { sm: 26, default: 30, lg: 36 }[size];
  const PAD = icon ? 0 : { sm: '0 10px', default: '0 12px', lg: '0 16px' }[size];
  const FS = { sm: 12, default: 12.5, lg: 13 }[size];
  const V = BTN_VARIANT[variant];

  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      onMouseEnter={(e) => {
        if (disabled) return;
        e.currentTarget.style.background = V.hbg;
        if (variant === 'ghost') {
          e.currentTarget.style.color = 'var(--text)';
          e.currentTarget.style.borderColor = 'var(--text-mute)';
        }
      }}
      onMouseLeave={(e) => {
        if (disabled) return;
        e.currentTarget.style.background = V.bg;
        if (variant === 'ghost') {
          e.currentTarget.style.color = V.fg;
          e.currentTarget.style.borderColor = V.bd;
        }
      }}
      style={{
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6,
        height: H, width: icon ? H : undefined, padding: PAD,
        border: '1px solid ' + V.bd, borderRadius: 'var(--radius-md, 6px)',
        background: V.bg, color: V.fg,
        font: V.weight + ' ' + FS + 'px/1 var(--font-sans)',
        cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.5 : 1,
        transition: 'background .15s, color .15s, border-color .15s',
        ...style,
      }}
    >
      {children}
      {kbd && (
        <kbd style={{
          fontFamily: 'var(--font-mono)', fontSize: 10.5, padding: '1px 5px', marginLeft: 4,
          background: 'color-mix(in oklch, currentColor 14%, transparent)', borderRadius: 3,
        }}>{kbd}</kbd>
      )}
    </button>
  );
}

/* ─── Input / Select (controlStyle + focusRing do DS) ─────────────────────── */

function controlStyle(): CSSProperties {
  return {
    font: '13px/1.4 var(--font-sans)', color: 'var(--text)', width: '100%', boxSizing: 'border-box',
    padding: '7px 10px', borderRadius: 'var(--radius-md, 6px)', border: '1px solid var(--border)',
    background: 'var(--surface)', outline: 'none',
    transition: 'border-color .15s, box-shadow .15s',
  };
}
function focusRing(el: HTMLElement) {
  el.style.borderColor = 'var(--accent)';
  el.style.boxShadow = '0 0 0 3px var(--accent-soft)';
}
function blurRing(el: HTMLElement) {
  el.style.boxShadow = 'none';
  el.style.borderColor = 'var(--border)';
}

export function DsInput({ value, onChange, placeholder, inputRef, ariaLabel }: {
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  inputRef?: React.RefObject<HTMLInputElement | null>;
  ariaLabel?: string;
}) {
  return (
    <input
      ref={inputRef}
      type="text"
      value={value}
      placeholder={placeholder}
      aria-label={ariaLabel}
      onChange={(e) => onChange(e.target.value)}
      onFocus={(e) => focusRing(e.currentTarget)}
      onBlur={(e) => blurRing(e.currentTarget)}
      style={controlStyle()}
    />
  );
}

/**
 * `<select>` NATIVO, como no DS — não o Radix `<Select>`. Não é regressão de
 * acessibilidade: o nativo é operável por teclado por construção. E evita a
 * armadilha do `<SelectItem value="">`, que explode o Radix em runtime
 * (§5 2026-06-29) — aqui a opção vazia é um `<option>` legítimo.
 */
export function DsSelect({ value, onChange, options, ariaLabel, style }: {
  value: string;
  onChange: (v: string) => void;
  options: { value: string; label: string }[];
  ariaLabel?: string;
  style?: CSSProperties;
}) {
  return (
    /* ds/no-native-select: o `<Select>` do Radix é um listbox em popover; o protótipo usa
       `<select>` NATIVO estilizado (mesma `controlStyle` do Input + chevron como
       background-image). Trocar mudaria a anatomia do controle "Por página", que é
       justamente o que [M] pediu pra copiar exato. Nativo não é regressão de a11y — é
       operável por teclado por construção. */
    // eslint-disable-next-line no-restricted-syntax
    <select
      value={value}
      aria-label={ariaLabel}
      onChange={(e) => onChange(e.target.value)}
      onFocus={(e) => focusRing(e.currentTarget)}
      onBlur={(e) => blurRing(e.currentTarget)}
      style={{
        ...controlStyle(),
        cursor: 'pointer',
        appearance: 'none',
        backgroundImage:
          'url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23888\' stroke-width=\'2\'><path d=\'M6 9l6 6 6-6\'/></svg>")',
        backgroundRepeat: 'no-repeat',
        backgroundPosition: 'right 10px center',
        paddingRight: 28,
        ...style,
      }}
    >
      {options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select>
  );
}

/* ─── DropdownMenu ────────────────────────────────────────────────────────── */

export type DsMenuItem = {
  id: string;
  label: string;
  icon?: ReactNode;
  kbd?: string;
  tone?: 'danger';
  disabled?: boolean;
  separator?: boolean;
  onSelect?: () => void;
};

export function DsDropdownMenu({ trigger, items = [], align = 'start', width = 220 }: {
  trigger: (p: { open: boolean; onClick: () => void }) => ReactNode;
  items: DsMenuItem[];
  align?: 'start' | 'end';
  width?: number;
}) {
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const wrap = useRef<HTMLDivElement>(null);
  const actionable = items.filter((it) => !it.separator && !it.disabled);

  useEffect(() => {
    if (!open) { setActive(-1); return; }
    const onDoc = (e: MouseEvent) => {
      if (wrap.current && !wrap.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [open]);

  const run = (it?: DsMenuItem) => {
    if (it && !it.disabled && !it.separator) { it.onSelect?.(); setOpen(false); }
  };

  let ai = -1;
  return (
    <div
      ref={wrap}
      // `presentation`: o wrapper é só o âncora de posicionamento — quem é interativo são o
      // gatilho e os itens, ambos <button> nativos. O onKeyDown vive aqui porque precisa
      // capturar ↑/↓/Esc vindos dos DOIS (gatilho e menu); é a adição de teclado, não a
      // falta dela, que a regra jsx-a11y confunde com div clicável.
      role="presentation"
      style={{ position: 'relative', display: 'inline-flex' }}
      onKeyDown={(e) => {
        if (e.key === 'Escape') setOpen(false);
        else if (e.key === 'ArrowDown') {
          e.preventDefault();
          if (!open) { setOpen(true); return; }
          setActive((a) => Math.min(a + 1, actionable.length - 1));
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          setActive((a) => Math.max(a - 1, 0));
        } else if (e.key === 'Enter' && open && active >= 0) {
          e.preventDefault();
          run(actionable[active]);
        }
      }}
    >
      {trigger({ open, onClick: () => setOpen((o) => !o) })}
      {open && (
        <div
          role="menu"
          style={{
            position: 'absolute', top: '100%', [align === 'end' ? 'right' : 'left']: 0,
            marginTop: 6, zIndex: 70, width,
            background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 10,
            padding: 5, boxShadow: 'var(--shadow-pop)',
          }}
        >
          {items.map((it, i) => {
            if (it.separator) {
              return <div key={'sep' + i} role="separator" style={{ height: 1, background: 'var(--border)', margin: '5px 6px' }} />;
            }
            ai++;
            const my = ai;
            const on = my === active;
            const danger = it.tone === 'danger';
            return (
              <button
                key={it.id}
                role="menuitem"
                type="button"
                disabled={it.disabled}
                onMouseEnter={() => setActive(my)}
                onClick={() => run(it)}
                style={{
                  display: 'flex', alignItems: 'center', gap: 10, width: '100%',
                  padding: '7px 8px', border: 0, borderRadius: 7,
                  cursor: it.disabled ? 'default' : 'pointer', textAlign: 'left',
                  font: '500 13px/1 var(--font-sans)',
                  background: on && !it.disabled ? (danger ? 'var(--neg-soft)' : 'var(--accent-soft)') : 'transparent',
                  color: it.disabled ? 'var(--text-mute)' : danger ? 'var(--neg)' : on ? 'var(--accent)' : 'var(--text)',
                  opacity: it.disabled ? 0.55 : 1,
                }}
              >
                {it.icon && (
                  <span style={{ flex: 'none', width: 16, height: 16, display: 'grid', placeItems: 'center', opacity: 0.85 }}>
                    {it.icon}
                  </span>
                )}
                <span style={{ flex: 1, minWidth: 0, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  {it.label}
                </span>
                {it.kbd && (
                  <kbd style={{
                    font: '600 10px/1 var(--font-mono)', color: 'var(--text-mute)',
                    background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 4, padding: '2px 5px',
                  }}>{it.kbd}</kbd>
                )}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

/* ─── FilterChip ──────────────────────────────────────────────────────────── */

export function DsFilterChip({ label, value, onRemove }: { label: string; value?: ReactNode; onRemove?: () => void }) {
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 6, height: 24,
      padding: onRemove ? '0 4px 0 10px' : '0 10px', borderRadius: 99, fontSize: 11.5,
      background: 'color-mix(in oklch, var(--accent) 12%, var(--surface))',
      color: 'var(--accent)',
      border: '1px solid color-mix(in oklch, var(--accent) 32%, transparent)',
    }}>
      <span style={{ fontWeight: 600 }}>{label}</span>
      {value != null && (
        <span style={{ fontWeight: 400, color: 'color-mix(in oklch, var(--accent) 80%, var(--text))' }}>{value}</span>
      )}
      {onRemove && (
        <button
          type="button"
          onClick={onRemove}
          aria-label={'Remover filtro ' + label}
          onMouseEnter={(e) => {
            e.currentTarget.style.background = 'color-mix(in oklch, var(--accent) 18%, transparent)';
            e.currentTarget.style.opacity = '1';
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.background = 'transparent';
            e.currentTarget.style.opacity = '0.7';
          }}
          style={{
            display: 'grid', placeItems: 'center', width: 18, height: 18, marginLeft: 1,
            border: 0, borderRadius: '50%', background: 'transparent', color: 'currentColor',
            cursor: 'pointer', opacity: 0.7, fontSize: 12, lineHeight: 1,
          }}
        >×</button>
      )}
    </span>
  );
}

/* ─── KpiFilterCard ───────────────────────────────────────────────────────── */

const KPI_TONE = {
  primary: ['color-mix(in oklch, var(--color-primary) 16%, transparent)', 'var(--color-primary)'],
  amber: ['color-mix(in oklch, oklch(0.72 0.15 70) 18%, transparent)', 'oklch(0.80 0.13 70)'],
  rose: ['color-mix(in oklch, oklch(0.65 0.20 20) 18%, transparent)', 'oklch(0.78 0.16 20)'],
  emerald: ['color-mix(in oklch, oklch(0.65 0.14 155) 18%, transparent)', 'oklch(0.78 0.12 155)'],
  violet: ['color-mix(in oklch, oklch(0.60 0.18 295) 18%, transparent)', 'oklch(0.80 0.14 295)'],
} as const;

export type DsKpiTone = keyof typeof KPI_TONE;

export function DsKpiFilterCard({ label, value, sub, icon, tone = 'primary', selected = false, onClick }: {
  label: string;
  value: ReactNode;
  sub?: string;
  icon?: ReactNode;
  tone?: DsKpiTone;
  selected?: boolean;
  onClick?: () => void;
}) {
  const [tileBg, tileFg] = KPI_TONE[tone] ?? KPI_TONE.primary;
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={selected}
      style={{
        display: 'flex', alignItems: 'center', gap: 12, width: '100%', textAlign: 'left',
        padding: 12, borderRadius: 8, cursor: 'pointer', background: 'var(--color-card)',
        border: '1px solid ' + (selected ? 'var(--color-primary)' : 'var(--color-border)'),
        // O DS escreve `0 1px 2px rgba(0,0,0,.05)`; `--shadow-soft` é `0 1px 2px rgba(0,0,0,.04)`
        // — mesma sombra, e é dark-aware. Manter o literal quebraria o tema escuro sem alarme.
        boxShadow: selected ? '0 0 0 1px var(--color-primary)' : 'var(--shadow-soft)',
        transition: 'box-shadow .15s, border-color .15s',
      }}
    >
      <span style={{
        width: 36, height: 36, borderRadius: 8, flexShrink: 0, display: 'grid', placeItems: 'center',
        background: tileBg, color: tileFg,
      }}>{icon}</span>
      <span style={{ minWidth: 0 }}>
        <span style={{
          display: 'block', fontSize: 'var(--fs-1)', fontWeight: 600, letterSpacing: '.06em',
          textTransform: 'uppercase', color: 'var(--color-muted-foreground)', lineHeight: 1,
        }}>{label}</span>
        <span style={{
          display: 'block', fontSize: 'var(--fs-6)', fontWeight: 600, fontVariantNumeric: 'tabular-nums',
          color: 'var(--color-foreground)', lineHeight: 1.2, marginTop: 4,
        }}>{value}</span>
        {sub && (
          <span style={{
            display: 'block', fontSize: 'var(--fs-1)', color: 'var(--color-muted-foreground)',
            marginTop: 2, lineHeight: 1,
          }}>{sub}</span>
        )}
      </span>
    </button>
  );
}

/* ─── DataTable ───────────────────────────────────────────────────────────── */

export type DsColumn = {
  key: string;
  label: string;
  align?: 'right';
  mono?: boolean;
  width?: number;
  sortable?: boolean;
};
export type DsRow = {
  id: string | number;
  state?: 'urgent' | 'selected' | 'archived';
  cells: Record<string, ReactNode | { primary: ReactNode; sub?: ReactNode }>;
};

function isPrimary(v: unknown): v is { primary: ReactNode; sub?: ReactNode } {
  return typeof v === 'object' && v !== null && 'primary' in v;
}

/**
 * ⚠️ Os tamanhos aqui são o RESULTADO RENDERIZADO do protótipo, não os defaults do
 * bundle. O `.dc.html` reescreve a tipografia da tabela por CSS com `!important`
 * (`th` 12px, corpo 14px, `b` 14/500, `small` 11/1.25, mono 12, padding lateral 16)
 * porque lá o bundle é inedítavel. Aqui o componente é nosso, então o valor final
 * entra direto — mesma pixelagem, sem `!important` (que o stylelint proíbe, com razão).
 */
function DsCell({ value, mono }: { value: unknown; mono?: boolean }) {
  if (isPrimary(value)) {
    return (
      <>
        <b style={{
          display: 'block', fontWeight: 500, fontSize: 14, letterSpacing: '-0.006em',
          color: 'var(--text)', overflow: 'hidden', textOverflow: 'ellipsis',
        }}>
          {value.primary}
        </b>
        {value.sub && (
          <small style={{ display: 'block', fontSize: 11, lineHeight: 1.25, color: 'var(--text-mute)', marginTop: 2 }}>
            {value.sub}
          </small>
        )}
      </>
    );
  }
  if (mono) {
    return <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12, letterSpacing: '-0.01em' }}>{value as ReactNode}</span>;
  }
  return <>{value as ReactNode}</>;
}

/** Padding vertical do `td` por densidade — o `data-d` do protótipo, agora tipado. */
const DENSIDADE_PAD = { compact: 5, normal: 10, comfy: 16 } as const;

export function DsDataTable({
  columns = [], rows = [], onRowClick, sortKey, sortDir, onSort, densidade = 'normal', focoId = null,
}: {
  columns: DsColumn[];
  rows: DsRow[];
  onRowClick?: (row: DsRow) => void;
  sortKey?: string | null;
  sortDir?: 'asc' | 'desc';
  onSort?: (key: string) => void;
  densidade?: keyof typeof DENSIDADE_PAD;
  /** Linha sob o cursor J/K (ou com a ficha aberta) — ganha tinta accent + rail. */
  focoId?: string | number | null;
}) {
  const padV = DENSIDADE_PAD[densidade] ?? DENSIDADE_PAD.normal;
  // `nowrap + ellipsis` só funciona junto com `table-layout: fixed` (abaixo): sem ele a
  // célula empurra a vizinha em vez de cortar, e a régua de larguras não vale nada.
  const cortar: CSSProperties = { whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', boxSizing: 'border-box' };
  const thBase: CSSProperties = {
    ...cortar,
    background: 'var(--bg-2)', padding: '9px 16px', fontSize: 12, fontWeight: 600,
    textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--text-mute)',
    borderBottom: '1px solid var(--border)',
  };
  const tdBase: CSSProperties = {
    ...cortar,
    padding: `${padV}px 16px`, borderBottom: '1px solid var(--border-2)', verticalAlign: 'middle',
  };

  return (
    <table style={{
      width: '100%', borderCollapse: 'separate', borderSpacing: 0, fontSize: 14, background: 'var(--bg)',
      tableLayout: 'fixed', minWidth: 1014, fontVariantNumeric: 'tabular-nums',
    }}>
      <thead>
        <tr>
          {columns.map((c) => {
            const sortable = c.sortable === true && !!onSort;
            const isSorted = sortKey === c.key;
            const glyph = isSorted ? (sortDir === 'desc' ? '↓' : '↑') : '↕';
            return (
              <th
                key={c.key}
                scope="col"
                aria-sort={isSorted ? (sortDir === 'desc' ? 'descending' : 'ascending') : sortable ? 'none' : undefined}
                style={{ ...thBase, textAlign: c.align === 'right' ? 'right' : 'left', width: c.width }}
              >
                {sortable ? (
                  <span
                    role="button"
                    tabIndex={0}
                    onClick={() => onSort(c.key)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onSort(c.key); }
                    }}
                    style={{
                      display: 'inline-flex', alignItems: 'center', gap: 3, cursor: 'pointer',
                      color: isSorted ? 'var(--text)' : 'inherit',
                    }}
                  >
                    {c.label}
                    <span aria-hidden style={{ opacity: isSorted ? 1 : 0.4 }}>{glyph}</span>
                  </span>
                ) : c.label}
              </th>
            );
          })}
        </tr>
      </thead>
      <tbody>
        {rows.map((row) => {
          const archived = row.state === 'archived';
          const foco = focoId !== null && String(focoId) === String(row.id);
          const isSel = row.state === 'selected' || foco;
          // O protótipo pinta o foco por CSS (`tr:has(.foco) td`) porque não alcança o
          // inline do bundle. Aqui sai do próprio componente — some o `:has()` e o
          // `!important` que ele exigia.
          const tdBg = foco ? 'color-mix(in oklab, var(--accent) 7%, transparent)'
            : isSel ? 'var(--accent-soft)' : 'transparent';
          const clickable = !!onRowClick;
          return (
            <tr
              key={row.id}
              onClick={clickable ? () => onRowClick(row) : undefined}
              tabIndex={clickable ? 0 : undefined}
              role={clickable ? 'button' : undefined}
              onKeyDown={clickable ? (e) => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onRowClick(row); }
              } : undefined}
              style={{
                cursor: clickable ? 'pointer' : 'default',
                boxShadow: row.state === 'urgent' ? 'inset 3px 0 0 var(--color-destructive)' : 'none',
              }}
              onMouseEnter={(e) => {
                if (!isSel) for (const td of Array.from(e.currentTarget.children)) (td as HTMLElement).style.background = 'var(--bg-2)';
              }}
              onMouseLeave={(e) => {
                if (!isSel) for (const td of Array.from(e.currentTarget.children)) (td as HTMLElement).style.background = 'transparent';
              }}
            >
              {columns.map((c, ci) => (
                <td
                  key={c.key}
                  style={{
                    ...tdBase, background: tdBg,
                    // Rail accent na 1ª célula da linha em foco (o `td:first-child` do protótipo).
                    boxShadow: foco && ci === 0 ? 'inset 3px 0 0 var(--accent)' : undefined,
                    textAlign: c.align === 'right' ? 'right' : 'left',
                    fontVariantNumeric: c.align === 'right' ? 'tabular-nums' : 'normal',
                    opacity: archived ? 0.55 : 1,
                    filter: archived ? 'saturate(0.7)' : 'none',
                  }}
                >
                  <DsCell value={row.cells?.[c.key]} mono={c.mono} />
                </td>
              ))}
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

/* ─── DrawerSection ───────────────────────────────────────────────────────── */

export function DsDrawerSection({ title, children }: { title?: string; children: ReactNode }) {
  return (
    <div style={{ padding: '14px 18px', borderTop: '1px solid var(--border-2)' }}>
      {title && (
        <h4 style={{
          margin: '0 0 8px', font: '600 10.5px/1.4 var(--font-sans)', textTransform: 'uppercase',
          letterSpacing: '.05em', color: 'var(--text-mute)',
        }}>{title}</h4>
      )}
      <div style={{ fontSize: 13, color: 'var(--text)', lineHeight: 1.5 }}>{children}</div>
    </div>
  );
}

/* ─── EmptyState ──────────────────────────────────────────────────────────── */

const EMPTY_VARIANT = {
  default: { border: 'var(--border)', dash: false, bg: 'var(--surface)', ico: ['var(--bg-2)', 'var(--text-mute)'] },
  first: {
    border: 'color-mix(in oklch, var(--accent) 30%, var(--border))', dash: false,
    bg: 'color-mix(in oklch, var(--accent-soft) 30%, var(--surface))', ico: ['var(--accent-soft)', 'var(--accent)'],
  },
  'no-results': { border: 'var(--border)', dash: true, bg: 'var(--surface)', ico: ['var(--bg-2)', 'var(--text-mute)'] },
  'no-perm': {
    border: 'color-mix(in oklch, var(--color-warning) 30%, var(--border))', dash: false,
    bg: 'color-mix(in oklch, var(--color-warning-soft) 40%, var(--surface))',
    ico: ['var(--color-warning-soft)', 'var(--color-warning-fg)'],
  },
  error: {
    border: 'color-mix(in oklch, var(--color-destructive) 35%, var(--border))', dash: false,
    bg: 'color-mix(in oklch, var(--color-destructive-soft) 30%, var(--surface))',
    ico: ['var(--color-destructive-soft)', 'var(--color-destructive-fg)'],
  },
} as const;

export type DsEmptyVariant = keyof typeof EMPTY_VARIANT;

export function DsEmptyState({ variant = 'default', icon, title, description, action }: {
  variant?: DsEmptyVariant;
  icon?: ReactNode;
  title?: string;
  description?: string;
  action?: ReactNode;
}) {
  const v = EMPTY_VARIANT[variant] ?? EMPTY_VARIANT.default;
  return (
    <div style={{
      textAlign: 'center', padding: '32px 24px',
      border: '1px ' + (v.dash ? 'dashed' : 'solid') + ' ' + v.border,
      borderRadius: 'var(--radius-md, 6px)', background: v.bg,
    }}>
      <span aria-hidden style={{
        width: 40, height: 40, margin: '0 auto 12px', display: 'grid', placeItems: 'center',
        borderRadius: '50%', background: v.ico[0], color: v.ico[1],
      }}>{icon}</span>
      {title && (
        <b style={{ display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4, color: 'var(--text)' }}>{title}</b>
      )}
      {description && (
        <small style={{
          display: 'block', fontSize: 12, color: 'var(--text-dim)', maxWidth: 320, margin: '0 auto', lineHeight: 1.5,
        }}>{description}</small>
      )}
      {action && <div style={{ marginTop: 12 }}>{action}</div>}
    </div>
  );
}

/* ─── Alert ───────────────────────────────────────────────────────────────── */

const ALERT_TONES = {
  info: { c: 'var(--accent)', d: 'M12 16v-4M12 8h.01M12 3a9 9 0 100 18 9 9 0 000-18z' },
  success: { c: 'var(--pos)', d: 'M20 6L9 17l-5-5' },
  warn: { c: 'var(--warn)', d: 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L14.4 3.9a2 2 0 00-3.4 0z' },
  danger: { c: 'var(--neg)', d: 'M12 8v5M12 16h.01M12 3a9 9 0 100 18 9 9 0 000-18z' },
} as const;

export function DsAlert({ tone = 'info', title, children }: {
  tone?: keyof typeof ALERT_TONES;
  title?: string;
  children?: ReactNode;
}) {
  const tk = ALERT_TONES[tone] ?? ALERT_TONES.info;
  const mix = (a: string) => 'color-mix(in oklab, ' + tk.c + ' ' + a + ', transparent)';
  return (
    <div
      role={tone === 'danger' ? 'alert' : 'status'}
      style={{
        display: 'flex', alignItems: 'flex-start', gap: 11, padding: '12px 14px',
        background: mix('6%'), border: '1px solid ' + mix('22%'),
        borderRadius: 'var(--radius, 8px)', font: 'var(--font-sans)', color: 'var(--text)',
      }}
    >
      <span style={{
        flex: 'none', width: 22, height: 22, borderRadius: 6, display: 'grid', placeItems: 'center',
        background: mix('14%'), color: tk.c, marginTop: 1,
      }}>
        <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <path d={tk.d} />
        </svg>
      </span>
      <div style={{ flex: 1, minWidth: 0, paddingTop: 1 }}>
        {title && <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--text)', lineHeight: 1.3 }}>{title}</div>}
        {children != null && (
          <div style={{ fontSize: 12.5, color: 'var(--text-dim)', lineHeight: 1.45, marginTop: title ? 2 : 0 }}>
            {children}
          </div>
        )}
      </div>
    </div>
  );
}

/* ─── Skeleton ────────────────────────────────────────────────────────────── */

const SKEL_VARIANT = {
  text: { height: 12, width: '80%' },
  row: { height: 38, width: '100%', borderRadius: 6 },
} as const;

export function DsSkeleton({ variant = 'text', count = 1 }: { variant?: keyof typeof SKEL_VARIANT; count?: number }) {
  const base: CSSProperties = {
    display: 'block',
    backgroundColor: 'color-mix(in oklch, var(--text-mute) 55%, var(--bg-2))',
    backgroundImage: 'linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.45) 50%, transparent 100%)',
    backgroundSize: '200px 100%', backgroundRepeat: 'no-repeat',
    borderRadius: 'var(--radius-sm, 4px)', animation: 'ds-shimmer 1.4s linear infinite',
    color: 'transparent', userSelect: 'none',
  };
  const style = { ...base, ...SKEL_VARIANT[variant] };
  if (count <= 1) return <span aria-hidden style={style} />;
  return (
    <span aria-hidden style={{ display: 'flex', flexDirection: 'column', gap: 6, width: '100%' }}>
      {Array.from({ length: count }).map((_, i) => <span key={i} style={style} />)}
    </span>
  );
}

/* ─── Toast ───────────────────────────────────────────────────────────────── */

export function DsToast({ children, tone = 'default' }: { children: ReactNode; tone?: 'default' | 'ok' | 'warn' | 'danger' }) {
  const bg = tone === 'ok' ? 'var(--color-success)'
    : tone === 'warn' ? 'var(--color-warning)'
    : tone === 'danger' ? 'var(--color-destructive)'
    : 'oklch(0.21 0 0)';
  return (
    /* ds/no-inline-raw-color: `oklch(0.97 0 0)` é INTENCIONALMENTE independente de tema.
       Toast é uma pastilha escura com texto claro nos DOIS modos (é assim no DS, e é o
       que dá contraste sobre qualquer fundo). Trocar por `var(--text)` inverteria a
       pastilha no dark e ela sumiria — o oposto do que a regra protege. */
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 10, padding: '10px 14px',
      background: bg,
      // eslint-disable-next-line no-restricted-syntax
      color: 'oklch(0.97 0 0)',
      borderRadius: 'var(--radius-md, 6px)',
      fontSize: 12.5, fontWeight: 500, boxShadow: 'var(--sh-2)',
    }}>{children}</span>
  );
}
