// @memcofre
//   modulo: Cockpit (Sidebar)
//   adrs: UI-0008 (cockpit como layout-mae), UI-0011 (sidebar single-pane)
//   nota: sidebar single-pane (260px). Toggle Chat/Menu REMOVIDO em 2026-05-05.
//         Conteúdo: CompanyPicker (topo) + SidebarMenu (corpo) + SidebarFooter
//         com user dropdown + Superadmin accordion (rodapé). SidebarChat e
//         SidebarTabs removidos — conv switcher migrou pra Pages/Copiloto/Chat.tsx.

import { useEffect, useRef, useState } from 'react';
import {
  Check, ChevronDown, ChevronUp, Keyboard, LogOut,
  Moon, Search, ShieldAlert, User,
} from 'lucide-react';

import {
  BusinessOpt,
  LS,
  ShellMenuItem,
  gradientFor,
  isSuperadminMenu,
} from './shared';

// ── CompanyPicker ──────────────────────────────────────────────────────

export function CompanyPicker({
  businesses,
  fallbackNome,
}: {
  businesses: BusinessOpt[];
  fallbackNome: string;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const ativa = businesses.find((b) => b.ativa) ?? businesses[0];
  const nome = ativa?.nome ?? fallbackNome;
  const iniciais = ativa?.iniciais ?? fallbackNome.slice(0, 2).toUpperCase();
  const grad = ativa ? gradientFor(ativa.id) : gradientFor(1);

  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  return (
    <div className="sb-cp" ref={ref}>
      <button
        className="sb-cp-btn"
        type="button"
        onClick={() => setOpen((v) => !v)}
      >
        <span className="avatar" style={{ background: grad }}>
          {iniciais}
        </span>
        <span className="name">{nome}</span>
        <ChevronDown size={14} />
      </button>
      {open && (
        <div className="sb-dd">
          <div className="sb-dd-h">EMPRESAS</div>
          {businesses.length === 0 && (
            <div className="sb-dd-empty">Nenhuma empresa disponível</div>
          )}
          {businesses.map((b) => (
            <div
              key={b.id}
              className={`sb-dd-i ${b.ativa ? 'active' : ''}`}
              onClick={() => {
                if (b.ativa) {
                  setOpen(false);
                  return;
                }
                // TODO Fase 4: POST /copiloto/cockpit/switch-business
                alert(`Switch para "${b.nome}" — em breve (Fase 4 do cockpit).`);
                setOpen(false);
              }}
            >
              <span className="avatar-sm" style={{ background: gradientFor(b.id) }}>
                {b.iniciais}
              </span>
              <span className="name">{b.nome}</span>
              {b.ativa && <Check size={14} className="check" />}
            </div>
          ))}
          <div className="sb-dd-sep" />
          <div className="sb-dd-foot">+ Adicionar empresa</div>
        </div>
      )}
    </div>
  );
}

// SidebarTabs + SidebarChat removidos em 2026-05-05 (UI-0011 single-pane).
// Histórico: dual Chat/Menu introduzido em UI-0008 (2026-04-27). Wagner pediu
// remoção em sessão 2026-05-05 — conteúdo de chat (atalhos + Fixadas + Rotinas
// + Recentes) migrou pra `Pages/Copiloto/Chat.tsx` como master/detail interno.

// ── SidebarMenuItem (recursivo p/ children) ─────────────────────────────

function SidebarMenuItem({ item }: { item: ShellMenuItem }) {
  const [expanded, setExpanded] = useState(false);
  const hasChildren = !!item.children?.length;
  const href = item.href ?? '#';

  if (hasChildren) {
    return (
      <>
        <div className="sb-item" onClick={() => setExpanded((v) => !v)}>
          <ChevronDown
            size={12}
            className="ic"
            style={{
              transform: expanded ? 'rotate(0)' : 'rotate(-90deg)',
              transition: 'transform 120ms',
              opacity: 0.6,
            }}
          />
          <span className="label">{item.label}</span>
        </div>
        {expanded &&
          item.children!.map((c, i) => (
            <a
              key={`${c.label}-${i}`}
              href={c.href ?? '#'}
              className="sb-item sb-item-child"
            >
              <span className="ic dot" />
              <span className="label">{c.label}</span>
            </a>
          ))}
      </>
    );
  }

  return (
    <a href={href} className="sb-item">
      <span className="ic dot" />
      <span className="label">{item.label}</span>
    </a>
  );
}

// ── SidebarMenu (espelha shell.menu real) ───────────────────────────────

export function SidebarMenu({ items }: { items: ShellMenuItem[] }) {
  if (!items?.length) {
    return (
      <div className="sb-menu-stub">
        <Hash size={20} style={{ opacity: 0.4, marginBottom: 8 }} />
        <p>Menu vazio — sem items disponíveis.</p>
      </div>
    );
  }
  // Filtra superadmin (vão pro rodapé)
  const principais = items.filter((i) => !isSuperadminMenu(i.label));
  return (
    <div>
      {principais.map((item, idx) => (
        <SidebarMenuItem key={`${item.label}-${idx}`} item={item} />
      ))}
    </div>
  );
}

// ── SidebarUserMenu (popup completo: perfil/disponivel/aparencia/etc) ──

function SidebarUserMenu({
  open,
  onClose,
  nome,
  email,
  iniciais,
  superadminItems,
}: {
  open: boolean;
  onClose: () => void;
  nome: string;
  email: string;
  iniciais: string;
  superadminItems: ShellMenuItem[];
}) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!open) return;
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) onClose();
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open, onClose]);

  // Superadmin separado: header + children
  const headerSuper = superadminItems.find((i) => /^superadmin$/i.test(i.label.trim()));
  const childrenSuper = superadminItems.filter((i) => !/^superadmin$/i.test(i.label.trim()));
  const hasSuperadmin = !!(headerSuper || childrenSuper.length > 0);

  const [superExpanded, setSuperExpanded] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(LS.SUPER_EXPANDED) === '1';
  });
  useEffect(() => {
    localStorage.setItem(LS.SUPER_EXPANDED, superExpanded ? '1' : '0');
  }, [superExpanded]);

  if (!open) return null;
  return (
    <div className="user-menu" ref={ref}>
      <div className="user-menu-head">
        <span className="avatar">{iniciais}</span>
        <div className="meta">
          <b>{nome}</b>
          <small>{email}</small>
        </div>
      </div>
      <div className="um-item">
        <User size={14} className="ic" />
        <span className="label">Meu perfil</span>
      </div>

      {/* Superadmin accordion — entra logo abaixo de Meu perfil */}
      {hasSuperadmin && (
        <>
          <div
            className="um-item um-superadmin-header"
            onClick={() => setSuperExpanded((v) => !v)}
            aria-expanded={superExpanded}
            role="button"
          >
            <ShieldAlert size={14} className="ic" />
            <span className="label">{headerSuper?.label ?? 'Superadmin'}</span>
            <ChevronDown
              size={11}
              className="um-superadmin-chev"
              style={{
                transform: superExpanded ? 'rotate(0)' : 'rotate(-90deg)',
                transition: 'transform 150ms ease',
                opacity: 0.6,
              }}
            />
          </div>
          {superExpanded && (
            <div className="um-superadmin-children">
              {childrenSuper.map((item, idx) => (
                <a
                  key={`super-${idx}`}
                  href={item.href ?? '#'}
                  className="um-item um-superadmin-item"
                  title={item.label}
                >
                  <span className="ic dot" />
                  <span className="label">{item.label}</span>
                </a>
              ))}
              {headerSuper && headerSuper.href && headerSuper.href !== '#' && (
                <a
                  href={headerSuper.href}
                  className="um-item um-superadmin-item"
                  title="Acessar tela Superadmin"
                >
                  <span className="ic dot" />
                  <span className="label">Acessar Superadmin ›</span>
                </a>
              )}
            </div>
          )}
        </>
      )}

      <div className="um-item">
        <span
          className="um-status"
          style={{ background: 'oklch(0.72 0.18 145)' }}
        />
        <span className="label">Disponível</span>
        <span className="arrow">›</span>
      </div>
      <div className="um-item">
        <Moon size={14} className="ic" />
        <span className="label">Aparência</span>
        <span className="arrow">›</span>
      </div>
      <div className="um-sep" />
      <div className="um-item">
        <Keyboard size={14} className="ic" />
        <span className="label">Atalhos</span>
        <span className="kbd">⌘/</span>
      </div>
      <div className="um-item">
        <Search size={14} className="ic" />
        <span className="label">Central de ajuda</span>
      </div>
      <div className="um-sep" />
      <a href="/logout" className="um-item">
        <LogOut size={14} className="ic" />
        <span className="label">Sair</span>
      </a>
    </div>
  );
}

// ── SidebarFooter (superadmin accordion + user dropdown) ────────────────

export function SidebarFooter({
  nome,
  nomeCurto,
  email,
  cargo,
  iniciais,
  superadminItems,
}: {
  nome: string;
  nomeCurto: string;
  email: string;
  cargo: string;
  iniciais: string;
  superadminItems: ShellMenuItem[];
}) {
  const [openUser, setOpenUser] = useState(false);

  // Sprint 0 (2026-04-27): items superadmin migraram pro user dropdown
  // (logo abaixo de "Meu perfil") — antes ficavam separados num accordion
  // standalone acima do user button, agora consolidados pra rodapé limpo.
  return (
    <div className="sb-user-wrap">
      {/* User dropdown — agora inclui Superadmin entre Meu perfil e Disponível */}
      <div className="sb-user" style={{ position: 'relative' }}>
        <SidebarUserMenu
          open={openUser}
          onClose={() => setOpenUser(false)}
          nome={nome}
          email={email}
          iniciais={iniciais}
          superadminItems={superadminItems}
        />
        <button
          className="sb-user-btn"
          type="button"
          onClick={() => setOpenUser((v) => !v)}
        >
          <span className="avatar">{iniciais}</span>
          <div className="who">
            <b>{nomeCurto}</b>
            <small>{cargo}</small>
          </div>
          <ChevronUp size={12} />
        </button>
      </div>
    </div>
  );
}
