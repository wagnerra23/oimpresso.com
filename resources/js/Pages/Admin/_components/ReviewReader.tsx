import * as React from 'react';
import {
  BookOpen,
  Camera,
  Check,
  X,
  RefreshCw,
  FileText,
  ExternalLink,
  AlertTriangle,
  Target,
} from 'lucide-react';
import { cn } from '@/Lib/utils';
import RoundBadge, { type ReviewStatus } from './RoundBadge';
import type { ScreenRow } from './ScreenList';

/**
 * ReviewReader — coluna 3 do tri-pane (port de kb/NodeReader)
 *
 * Wave 30 Agent B (W30-B). Pane direito com:
 *  - Header: nome tela + módulo + RoundBadge status atual
 *  - Screenshots 1440 + 1280 lado-a-lado (lazy-load)
 *  - Charter excerpt (UX targets bullets)
 *  - Lista desvios último round (apenas count — texto vem do .review.md)
 *  - 4 botões Wagner: Aprovar / Rejeitar (+ checkbox initiative) / Iterar / Re-smoke
 *
 * Empty state quando `screen === null`.
 */
interface Props {
  screen: ScreenRow | null;
  /** dispara POST `/admin/screen-review/{path}/status` */
  onAction?: (action: ReviewStatus, opts?: { createInitiative?: boolean; notes?: string }) => void;
  onResmoke?: () => void;
  className?: string;
}

export default function ReviewReader({ screen, onAction, onResmoke, className }: Props) {
  const [createInitiative, setCreateInitiative] = React.useState(true);
  const [notes, setNotes] = React.useState('');

  // Reset notes/checkbox quando troca tela
  React.useEffect(() => {
    setNotes('');
    setCreateInitiative(true);
  }, [screen?.path]);

  if (!screen) {
    return (
      <section
        className={cn(
          'kb-reader flex flex-col items-center justify-center gap-2 bg-background p-8 text-center',
          className,
        )}
        aria-label="Nenhuma tela selecionada"
      >
        <BookOpen size={28} className="text-muted-foreground/60" />
        <h2 className="text-[14px] font-semibold text-foreground">
          Selecione uma tela
        </h2>
        <p className="max-w-sm text-[12px] text-muted-foreground">
          Escolha um módulo à esquerda e uma tela na lista pra ver screenshots,
          charter, último round e ações Wagner (Aprovar / Rejeitar / Iterar).
        </p>
      </section>
    );
  }

  const screenshot1440 = screen.screenshot_url;
  // Convention: replace -1440 por -1280 no path
  const screenshot1280 = screenshot1440
    ? screenshot1440.replace('-1440.', '-1280.')
    : null;

  return (
    <section
      className={cn(
        'kb-reader flex flex-col gap-3 overflow-y-auto bg-background p-4',
        className,
      )}
      aria-label={`Reader tela ${screen.name}`}
    >
      {/* Header */}
      <header className="space-y-1.5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <h2 className="truncate text-[16px] font-bold text-foreground">
              {screen.name}
            </h2>
            <p className="truncate text-[11px] text-muted-foreground">
              {screen.path}.tsx · módulo <strong>{screen.module}</strong>
            </p>
          </div>
          <RoundBadge round={screen.current_round} status={screen.status} size="md" />
        </div>
        {screen.last_review_at && (
          <p className="text-[10.5px] text-muted-foreground">
            Último round:{' '}
            <span className="tabular-nums">
              {new Date(screen.last_review_at).toLocaleString('pt-BR')}
            </span>
          </p>
        )}
      </header>

      {/* Screenshots side-by-side */}
      <div className="grid grid-cols-1 gap-2 lg:grid-cols-2">
        <ScreenshotPanel label="1440px desktop" url={screenshot1440} />
        <ScreenshotPanel label="1280px (ROTA LIVRE)" url={screenshot1280} />
      </div>

      {/* Charter excerpt */}
      <section className="rounded-md border border-border bg-card p-3">
        <header className="mb-2 flex items-center justify-between gap-2">
          <h3 className="flex items-center gap-1.5 text-[12px] font-semibold text-foreground">
            <Target size={13} className="text-muted-foreground" />
            UX Targets (charter)
          </h3>
          {screen.charter_path && (
            <a
              href={`/admin/memoria?q=${encodeURIComponent(screen.charter_path)}`}
              className="inline-flex items-center gap-0.5 text-[10.5px] text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/40"
              title="Abrir charter"
            >
              <FileText size={10} />
              charter
              <ExternalLink size={9} />
            </a>
          )}
        </header>
        {screen.ux_targets.length === 0 ? (
          <p className="text-[11px] italic text-muted-foreground">
            {screen.charter_path
              ? 'Charter existe mas sem seção `## UX Targets`.'
              : 'Sem charter ao lado (gere com skill `charter-first`).'}
          </p>
        ) : (
          <ul className="space-y-1 text-[11.5px] text-foreground">
            {screen.ux_targets.map((t, i) => (
              <li key={i} className="flex gap-1.5">
                <span className="text-muted-foreground" aria-hidden>
                  •
                </span>
                <span>{t}</span>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* Desvios catalogados */}
      {screen.desvios_count > 0 && (
        <div
          role="status"
          className="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-[11.5px] text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200"
        >
          <AlertTriangle size={13} className="shrink-0" />
          <span>
            <strong>{screen.desvios_count}</strong> desvio
            {screen.desvios_count === 1 ? '' : 's'} catalogado
            {screen.desvios_count === 1 ? '' : 's'} no último round (veja
            `<code>.review.md</code>` no repo).
          </span>
        </div>
      )}

      {/* Wagner action panel */}
      <section className="space-y-2 rounded-md border border-border bg-card p-3">
        <h3 className="text-[12px] font-semibold text-foreground">Ação Wagner</h3>
        <textarea
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="Notes opcionais (max 2000 chars)…"
          rows={2}
          maxLength={2000}
          className="w-full rounded-md border border-border bg-background px-2 py-1.5 text-[12px] text-foreground placeholder:text-muted-foreground/70 focus:outline-none focus:ring-2 focus:ring-primary/40"
        />
        <label className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
          <input
            type="checkbox"
            checked={createInitiative}
            onChange={(e) => setCreateInitiative(e.target.checked)}
            className="rounded border-border"
          />
          Abrir Initiative governance se rejeitar (deadline 14d)
        </label>
        <div className="flex flex-wrap gap-1.5 pt-1">
          <ActionButton
            tone="approve"
            onClick={() => onAction?.('approved', { notes })}
          >
            <Check size={12} />
            Aprovar
          </ActionButton>
          <ActionButton
            tone="iterate"
            onClick={() => onAction?.('iterate', { notes })}
          >
            <RefreshCw size={12} />
            Iterar
          </ActionButton>
          <ActionButton
            tone="reject"
            onClick={() => {
              if (
                typeof window !== 'undefined' &&
                !window.confirm(
                  `Rejeitar ${screen.name}?\n\n` +
                    (createInitiative
                      ? '• Initiative governance será aberta (14d deadline)\n'
                      : '• Initiative NÃO será aberta\n') +
                    '• Round novo append em .review.md (append-only)',
                )
              ) {
                return;
              }
              onAction?.('rejected', { createInitiative, notes });
            }}
          >
            <X size={12} />
            Rejeitar
          </ActionButton>
          <ActionButton tone="neutral" onClick={onResmoke}>
            <Camera size={12} />
            Re-smoke
          </ActionButton>
        </div>
      </section>
    </section>
  );
}

function ScreenshotPanel({ label, url }: { label: string; url: string | null }) {
  return (
    <figure className="overflow-hidden rounded-md border border-border bg-muted/30">
      <figcaption className="border-b border-border bg-card/50 px-2 py-1 text-[10.5px] font-medium text-muted-foreground">
        {label}
      </figcaption>
      {url ? (
        <a
          href={url}
          target="_blank"
          rel="noreferrer"
          className="block focus:outline-none focus:ring-2 focus:ring-primary/40"
          title="Abrir screenshot em nova aba"
        >
          <img
            src={url}
            alt={label}
            loading="lazy"
            className="block h-auto w-full object-contain"
          />
        </a>
      ) : (
        <div className="flex h-32 items-center justify-center gap-1.5 text-[11px] text-muted-foreground">
          <Camera size={14} className="opacity-60" />
          sem screenshot
        </div>
      )}
    </figure>
  );
}

function ActionButton({
  tone,
  onClick,
  children,
}: {
  tone: 'approve' | 'reject' | 'iterate' | 'neutral';
  onClick?: () => void;
  children: React.ReactNode;
}) {
  const cls = {
    approve:
      'border-emerald-400 bg-emerald-500 text-white hover:bg-emerald-600 dark:border-emerald-600',
    reject: 'border-destructive bg-destructive text-destructive-foreground hover:bg-destructive/90',
    iterate:
      'border-amber-400 bg-amber-500 text-white hover:bg-amber-600 dark:border-amber-600',
    neutral: 'border-border bg-card text-foreground hover:bg-accent',
  }[tone];

  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[11.5px] font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40',
        cls,
      )}
    >
      {children}
    </button>
  );
}
