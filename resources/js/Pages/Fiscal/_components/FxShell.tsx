// FxShell.tsx — wrapper das 7 páginas do módulo Fiscal
// Port do design fiscal-page.jsx §6 FxShell (sub-nav horizontal + footer cheats)
// ⌘K palette habilitada em PR #7 Wave (US-FISCAL-015) — busca cross-fiscal.

import { router } from '@inertiajs/react';
import { type MapaProcedencia } from '../_lib/procedencia';
import { BotaoProcedencia } from './SeloProcedencia';
import { FX_PAGES } from '../_lib/paginas-fiscais';
import { useEffect, type ReactNode } from 'react';

import CmdKPalette from './CmdKPalette';
import DebitosConhecidos from './DebitosConhecidos';
import DecisaoPendente from './DecisaoPendente';

interface FxShellProps {
  route: string;
  title: string;
  crumb?: string;
  env?: string;
  envTone?: 'ok' | 'warn' | 'bad';
  /** Selo de procedência do próprio `env` — a situação da SEFAZ é uma superfície como as outras. */
  envSelo?: ReactNode;
  /**
   * CU-FISC-16 — mapa superfície → procedência servido pelo controller da tela.
   *
   * Presente e não-vazio ⇒ o botão "Procedência" aparece no cabeçalho. Ausente ⇒ não
   * aparece, de propósito: as telas que ainda não declararam o mapa ganhariam um toggle
   * que não acende selo nenhum — e botão inerte é pior que botão ausente.
   */
  procedencia?: MapaProcedencia;
  actions?: ReactNode;
  cheats?: Array<{ keys: string[]; label: string }>;
  counts?: Partial<Record<string, number | null>>;
  children: ReactNode;
}

const DEFAULT_CHEATS = [
  { keys: ['⌘', 'K'], label: 'buscar tudo' },
  { keys: ['2'],      label: 'NF-e' },
  { keys: ['J', 'K'], label: 'navegar lista' },
  { keys: ['?'],      label: 'todos os atalhos' },
];

export default function FxShell({
  route,
  title,
  crumb,
  env,
  envTone = 'ok',
  envSelo,
  procedencia,
  actions,
  cheats = DEFAULT_CHEATS,
  counts = {},
  children,
}: FxShellProps) {
  // Atalhos 1-7 pra navegar entre sub-páginas (placeholder pra # → noop)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null;
      const isTyping =
        target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
      if (isTyping || e.metaKey || e.ctrlKey || e.altKey) return;

      const page = FX_PAGES.find(p => p.short === e.key);
      if (page && page.url !== '#') {
        e.preventDefault();
        router.visit(page.url, { preserveScroll: false });
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  return (
    <div className="fx-page" data-screen-label={`00 ${route}`}>
      {/* Cabeçalho na forma do `FxHeader` do protótipo (`fiscal-page.jsx`): crumb em `<p>`,
          pílula de ambiente neutra com o ponto colorido, Procedência ANTES do ⌘K e o ⌘K como
          par de teclas — sem rótulo "Buscar". Medido contra o protótipo em 2026-11-26. */}
      <header className="fx-hero">
        <div className="fx-hero-l">
          <h1>{title}</h1>
          {crumb && <p className="fx-hero-crumb">{crumb}</p>}
        </div>
        <div className="fx-hero-r">
          {env && (
            <span className="fx-env" data-tone={envTone}>
              <i aria-hidden="true" />
              {env}
              {envSelo}
            </span>
          )}
          {procedencia && Object.keys(procedencia).length > 0 && <BotaoProcedencia />}
          <button
            type="button"
            className="fx-kbd"
            onClick={() => {
              // Dispara o listener Cmd/Ctrl+K do CmdKPalette via synthetic event.
              window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true }));
            }}
            title="Busca fiscal (Cmd/Ctrl+K)"
            aria-label="Busca global fiscal (Cmd/Ctrl+K)"
          >
            <b>⌘</b>
            <b>K</b>
          </button>
          {actions}
        </div>
      </header>

      {/* `data-contract` ancora a subnav no Contrato de Tela (ADR 0286). Antes de 2026-09-09
          o FxShell não tinha âncora nenhuma, então os 7 rótulos — a copy que [W] pegou
          divergindo do protótipo — não estavam sob catraca nenhuma. */}
      <nav className="fx-subnav" aria-label="Páginas do módulo Fiscal" data-contract="fiscal-subnav">
        {FX_PAGES.map(p => {
          const active = route === p.id;
          const disabled = p.url === '#';
          const n = counts[p.id];
          return (
            <button
              key={p.id}
              type="button"
              className={`fx-subnav-chip${active ? ' active' : ''}${disabled ? ' disabled' : ''}`}
              onClick={() => !disabled && router.visit(p.url)}
              disabled={disabled}
              title={disabled ? 'Em PR seguinte' : `Atalho: ${p.short}`}
            >
              {p.icon}
              <span>{p.label}</span>
              {n != null && n > 0 && <span className="n">{n}</span>}
              <kbd>{p.short}</kbd>
            </button>
          );
        })}
      </nav>

      <div className="fx-body">
        {children}
        {/* Um ponto de render cobre as 7 telas — é onde o protótipo os põe também
            (`<FxDebitosPage tela={route} />`, `fiscal-page.jsx:524`), logo antes do rodapé.
            Tela sem item não desenha nada.

            A decisão pendente vem ANTES da dívida: ela é o que ainda não foi respondido, e
            quem lê a tela precisa saber disso antes de ler o que já se sabe que falta. */}
        <DecisaoPendente route={route} />
        <DebitosConhecidos route={route} />
      </div>

      <footer className="fx-shell-foot">
        <div className="fx-cheatsheet" role="region" aria-label="Atalhos de teclado">
          {cheats.map((it, i) => (
            <span key={i} className="fx-cs-item">
              {it.keys.map((k, j) => <kbd key={j}>{k}</kbd>)}
              <span>{it.label}</span>
            </span>
          ))}
        </div>
      </footer>

      {/* PR #7 Wave — Cmd+K palette cross-fiscal (US-FISCAL-015) */}
      <CmdKPalette />
    </div>
  );
}
