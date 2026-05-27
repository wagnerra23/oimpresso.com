// CmdKPalette.tsx — palette de busca global cross-fiscal (PR #7 Wave)
//
// US-FISCAL-015 — atalho Cmd+K (Mac) / Ctrl+K (Win/Linux) abre modal overlay
// com:
//   1. Search input (debounced 200ms, mínimo 2 chars)
//   2. Navegação rápida (7 sub-páginas — derivado client-side, sempre visível)
//   3. Notas encontradas (NF-e/NFC-e — top 5 server-side)
//   4. DF-e encontrados (manifestação — top 5 server-side)
//
// Atalhos:
//   - Cmd/Ctrl+K: abre/fecha
//   - Esc: fecha
//   - ArrowUp/Down: navega resultados
//   - Enter: ativa resultado focado (router.visit URL)
//
// Backend: GET /fiscal/palette/search?q={query}
//   - Throttle 60/min anti-spam
//   - Multi-tenant scope automático (HasBusinessScope ADR 0093)
//   - LIMIT 5 cada categoria
//
// Design inspirado em Linear/Notion/Vercel Cmd+K patterns.

import { router } from '@inertiajs/react';
import { Archive, FileText, Receipt, RefreshCw, Search, Shield, ShieldAlert, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface NotaResult {
  id: number;
  tipo: string;
  numero: number;
  serie: string;
  chave_short: string | null;
  status: string;
  cstat: string | null;
  motivo: string | null;
  valor: number;
  emitido_em: string | null;
  url: string;
}

interface DfeResult {
  id: number;
  nsu: number;
  chave_short: string | null;
  emitente: string | null;
  cnpj: string | null;
  valor: number;
  status: string;
  data_emissao: string | null;
  url: string;
}

interface PaletteResponse {
  q: string;
  notas: NotaResult[];
  dfe: DfeResult[];
}

interface NavItem {
  id: string;
  label: string;
  url: string;
  icon: React.ReactNode;
}

const NAV_ITEMS: NavItem[] = [
  { id: 'cockpit', label: 'Cockpit fiscal',  url: '/fiscal',         icon: <ShieldAlert size={14}/> },
  { id: 'nfe',     label: 'NF-e · NFC-e',    url: '/fiscal/nfe',     icon: <Receipt size={14}/> },
  { id: 'nfse',    label: 'NFS-e',           url: '/fiscal/nfse',    icon: <FileText size={14}/> },
  { id: 'dfe',     label: 'Manifesto DF-e',  url: '/fiscal/dfe',     icon: <ShieldAlert size={14}/> },
  { id: 'eventos', label: 'Eventos timeline',url: '/fiscal/eventos', icon: <RefreshCw size={14}/> },
  { id: 'config',  label: 'Certif. & Cfg.',  url: '/fiscal/config',  icon: <Shield size={14}/> },
  { id: 'sped',    label: 'SPED & Livros',   url: '/fiscal/sped',    icon: <Archive size={14}/> },
];

function brl(v: number): string {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
}

interface FlatItem {
  kind: 'nav' | 'nota' | 'dfe';
  key: string;
  label: string;
  subtitle?: string;
  trailing?: string;
  icon?: React.ReactNode;
  url: string;
}

export default function CmdKPalette() {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<PaletteResponse | null>(null);
  const [cursor, setCursor] = useState(0);

  const inputRef = useRef<HTMLInputElement | null>(null);
  const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Listener global Cmd/Ctrl+K (abre/fecha)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setOpen(v => !v);
      } else if (e.key === 'Escape' && open) {
        setOpen(false);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [open]);

  // Auto-focus input ao abrir
  useEffect(() => {
    if (open) {
      // delay até DOM montar
      setTimeout(() => inputRef.current?.focus(), 10);
      setCursor(0);
    } else {
      setQuery('');
      setResult(null);
    }
  }, [open]);

  // Debounced search server-side (≥2 chars)
  useEffect(() => {
    if (debounceTimer.current) clearTimeout(debounceTimer.current);
    if (!open || query.trim().length < 2) {
      setResult(null);
      return;
    }
    debounceTimer.current = setTimeout(() => {
      setBusy(true);
      fetch(`/fiscal/palette/search?q=${encodeURIComponent(query.trim())}`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      })
        .then(r => r.ok ? r.json() : null)
        .then((data: PaletteResponse | null) => setResult(data))
        .catch(() => setResult(null))
        .finally(() => setBusy(false));
    }, 200);
    return () => {
      if (debounceTimer.current) clearTimeout(debounceTimer.current);
    };
  }, [query, open]);

  // Filtra nav items pelo query client-side (substring case-insensitive)
  const navFiltered = useMemo<NavItem[]>(() => {
    const q = query.trim().toLowerCase();
    if (q.length === 0) return NAV_ITEMS;
    return NAV_ITEMS.filter(n => n.label.toLowerCase().includes(q));
  }, [query]);

  // Flatten resultados em lista plana (pra navegação ArrowUp/Down)
  const flat = useMemo<FlatItem[]>(() => {
    const items: FlatItem[] = [];
    navFiltered.forEach(n => {
      items.push({
        kind: 'nav',
        key: `nav-${n.id}`,
        label: n.label,
        icon: n.icon,
        url: n.url,
      });
    });
    result?.notas?.forEach(n => {
      items.push({
        kind: 'nota',
        key: `nota-${n.id}`,
        label: `${n.tipo} ${n.numero} · série ${n.serie}`,
        subtitle: [n.chave_short, n.motivo].filter(Boolean).join(' · '),
        trailing: `${brl(n.valor)} · ${n.status}${n.cstat ? ` (${n.cstat})` : ''}`,
        icon: <Receipt size={14}/>,
        url: n.url,
      });
    });
    result?.dfe?.forEach(d => {
      items.push({
        kind: 'dfe',
        key: `dfe-${d.id}`,
        label: d.emitente ?? `DF-e ${d.nsu}`,
        subtitle: [d.cnpj, d.chave_short].filter(Boolean).join(' · '),
        trailing: `${brl(d.valor)} · ${d.status}`,
        icon: <ShieldAlert size={14}/>,
        url: d.url,
      });
    });
    return items;
  }, [navFiltered, result]);

  // Reset cursor quando lista muda
  useEffect(() => {
    setCursor(0);
  }, [flat.length]);

  // ArrowUp/Down/Enter no input
  const handleKey = useCallback((e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setCursor(c => Math.min(flat.length - 1, c + 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setCursor(c => Math.max(0, c - 1));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const item = flat[cursor];
      if (item) {
        setOpen(false);
        router.visit(item.url, { preserveScroll: false });
      }
    }
  }, [flat, cursor]);

  if (!open) return null;

  return (
    <div
      className="fx-drawer-bg"
      onClick={() => setOpen(false)}
      role="presentation"
      style={{ zIndex: 60 }}
    >
      <div
        role="dialog"
        aria-label="Busca global Fiscal (Cmd+K)"
        onClick={(e) => e.stopPropagation()}
        style={{
          background: 'white',
          borderRadius: 12,
          width: 640,
          maxWidth: '94vw',
          maxHeight: '70vh',
          margin: '12vh auto',
          boxShadow: '0 24px 60px rgba(0,0,0,.25)',
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
        }}
      >
        {/* Header com search input */}
        <header
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 10,
            padding: '14px 18px',
            borderBottom: '1px solid var(--fx-border, #e5e5e5)',
          }}
        >
          <Search size={16} aria-hidden="true"/>
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value.slice(0, 50))}
            onKeyDown={handleKey}
            placeholder="Buscar notas, DF-e ou navegar… (mín. 2 chars pra busca server)"
            aria-label="Buscar"
            style={{
              flex: 1,
              border: 0,
              outline: 'none',
              fontSize: 15,
              fontFamily: 'inherit',
              background: 'transparent',
            }}
          />
          {busy && (
            <span style={{ fontSize: 11, color: 'var(--fx-text-mute, #888)' }}>
              buscando…
            </span>
          )}
          <button
            type="button"
            onClick={() => setOpen(false)}
            aria-label="Fechar (ESC)"
            style={{ background: 'transparent', border: 0, cursor: 'pointer', padding: 4 }}
          >
            <X size={16}/>
            <kbd style={{ marginLeft: 4, fontSize: 10 }}>esc</kbd>
          </button>
        </header>

        {/* Body */}
        <div style={{ overflowY: 'auto', flex: 1 }}>
          {flat.length === 0 ? (
            <div style={{ padding: 24, textAlign: 'center', color: 'var(--fx-text-mute, #888)', fontSize: 13 }}>
              {query.trim().length < 2
                ? 'Digite ≥2 caracteres para buscar notas e DF-e'
                : busy
                  ? 'Buscando…'
                  : 'Nenhum resultado'}
            </div>
          ) : (
            <>
              {navFiltered.length > 0 && (
                <Section title="Navegação rápida">
                  {flat
                    .map((item, idx) => ({ item, idx }))
                    .filter(({ item }) => item.kind === 'nav')
                    .map(({ item, idx }) => (
                      <Row
                        key={item.key}
                        item={item}
                        focused={idx === cursor}
                        onClick={() => {
                          setOpen(false);
                          router.visit(item.url);
                        }}
                      />
                    ))}
                </Section>
              )}

              {result?.notas && result.notas.length > 0 && (
                <Section title={`Notas (${result.notas.length})`}>
                  {flat
                    .map((item, idx) => ({ item, idx }))
                    .filter(({ item }) => item.kind === 'nota')
                    .map(({ item, idx }) => (
                      <Row
                        key={item.key}
                        item={item}
                        focused={idx === cursor}
                        onClick={() => {
                          setOpen(false);
                          router.visit(item.url);
                        }}
                      />
                    ))}
                </Section>
              )}

              {result?.dfe && result.dfe.length > 0 && (
                <Section title={`DF-e recebidas (${result.dfe.length})`}>
                  {flat
                    .map((item, idx) => ({ item, idx }))
                    .filter(({ item }) => item.kind === 'dfe')
                    .map(({ item, idx }) => (
                      <Row
                        key={item.key}
                        item={item}
                        focused={idx === cursor}
                        onClick={() => {
                          setOpen(false);
                          router.visit(item.url);
                        }}
                      />
                    ))}
                </Section>
              )}
            </>
          )}
        </div>

        {/* Footer com atalhos */}
        <footer
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            padding: '10px 18px',
            borderTop: '1px solid var(--fx-border, #e5e5e5)',
            fontSize: 11,
            color: 'var(--fx-text-mute, #888)',
          }}
        >
          <span><kbd>↑↓</kbd> navegar · <kbd>⏎</kbd> abrir · <kbd>esc</kbd> fechar</span>
          <span>multi-tenant scope · top 5 por categoria</span>
        </footer>
      </div>
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div>
      <div
        style={{
          padding: '8px 18px 4px',
          fontSize: 11,
          textTransform: 'uppercase',
          letterSpacing: 0.4,
          color: 'var(--fx-text-mute, #888)',
          fontWeight: 600,
        }}
      >
        {title}
      </div>
      {children}
    </div>
  );
}

function Row({
  item,
  focused,
  onClick,
}: {
  item: FlatItem;
  focused: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        width: '100%',
        padding: '10px 18px',
        background: focused ? 'var(--fx-bg-2, #f5f5f7)' : 'transparent',
        border: 0,
        cursor: 'pointer',
        textAlign: 'left',
        fontFamily: 'inherit',
        borderLeft: focused ? '3px solid var(--fis, #d63a82)' : '3px solid transparent',
      }}
    >
      <span style={{ flexShrink: 0, color: 'var(--fx-text-dim, #555)' }}>{item.icon}</span>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13.5, fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {item.label}
        </div>
        {item.subtitle && (
          <div style={{ fontSize: 11.5, color: 'var(--fx-text-mute, #888)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
            {item.subtitle}
          </div>
        )}
      </div>
      {item.trailing && (
        <span style={{ fontSize: 11.5, color: 'var(--fx-text-mute, #888)', flexShrink: 0 }}>
          {item.trailing}
        </span>
      )}
    </button>
  );
}
