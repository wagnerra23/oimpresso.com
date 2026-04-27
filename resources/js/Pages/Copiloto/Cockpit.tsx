// @memcofre
//   tela: /copiloto/cockpit
//   stories: US-COPI-COCKPIT-001 (MVP)
//   adrs: 0039 (padrao "Chat Cockpit")
//   status: mvp-piloto-em-validacao
//   module: Copiloto
//   nota: rota PARALELA ao /copiloto atual; nao substitui Chat.tsx.

import { Head, usePage } from '@inertiajs/react';
import React, { useEffect, useRef, useState } from 'react';
import {
  Bell, Briefcase, Check, CheckCheck, ChevronDown, ChevronRight, ChevronUp, Cog,
  DollarSign, FileText, Hash, History, Inbox, Info, Keyboard, LogOut,
  MessageCircle, MoreHorizontal, Moon, Paperclip, Phone, Pin, Plus, Search,
  Send, Smile, Sliders, User, X,
} from 'lucide-react';

import '../../../css/cockpit.css';

// ── tipos do payload Inertia ─────────────────────────────────────────────
interface ConversaResumo {
  id: string;
  titulo: string;
  unread?: number;
  origem?: string | null;
  ativa?: boolean;
}
interface Rotina {
  id: string;
  titulo: string;
  frequencia: string;
}
interface AvatarRef {
  iniciais: string;
  gradId: number;
}
interface Mensagem {
  id: number;
  autor: 'me' | 'them';
  texto: string;
  hora: string;
  dia?: string;
  lida?: boolean;
  whoAvatar?: AvatarRef;
  whoNome?: string;
}
interface OsContext {
  numero: string;
  cliente: string;
  estagio: string;
  prazo: string;
}
interface FinContext {
  saldo: string;
  boletos: string;
}
interface HistoricoEvent {
  quando: string;
  quem: string;
  oque: string;
}
interface AnexoFile {
  nome: string;
  tamanho: string;
}
interface ConversaFoco {
  id: string;
  titulo: string;
  tipo: string;
  online?: boolean;
  avatar?: AvatarRef;
  cliente?: {
    nome: string;
    telefone: string;
    ultimoContato: string;
  };
  os?: OsContext;
  financeiro?: FinContext;
  historico?: HistoricoEvent[];
  anexos?: AnexoFile[];
  mensagens: Mensagem[];
}

interface BusinessOpt {
  id: number;
  nome: string;
  iniciais: string;
  ativa: boolean;
}

interface Props {
  businessNome: string;
  businesses: BusinessOpt[];
  usuarioNome: string;
  usuarioNomeCurto: string;
  usuarioEmail: string;
  usuarioCargo: string;
  usuarioIniciais: string;
  conversas: {
    fixadas: ConversaResumo[];
    rotinas: Rotina[];
    recentes: ConversaResumo[];
  };
  conversaFoco: ConversaFoco;
  conversaAtivaRealId: number | null;
}

// MenuItem do shell global (vem via Inertia shared props)
interface ShellMenuItem {
  label: string;
  href?: string;
  icon?: string;
  inertia?: boolean;
  children?: ShellMenuItem[];
}

// Labels de menu items que pertencem ao "rodapé superadmin"
// (heurística por enquanto — TODO: virar flag no MenuItem do backend)
const SUPERADMIN_LABELS = new Set<string>([
  'Backup', 'CMS', 'Connector', 'Office Impresso', 'Officeimpresso',
  'Módulos', 'Modulos', 'Manage Modules', 'Personalizar', 'Memória', 'MemCofre',
]);

function isSuperadminMenu(label: string): boolean {
  const norm = label.trim();
  if (SUPERADMIN_LABELS.has(norm)) return true;
  // matching parcial pra labels longos
  return /superadmin|module|backup|connector|cms\b/i.test(norm);
}

// ── constantes ──────────────────────────────────────────────────────────
const LS_TAB = 'oimpresso.cockpit.sidebar.tab';
const LS_CHAT_TAB = 'oimpresso.cockpit.chat.tab';
const LS_LINKED = 'oimpresso.cockpit.linked.collapsed';
const LS_CONV = 'oimpresso.cockpit.conv';
const LS_TW_VIBE = 'oimpresso.cockpit.tweaks.vibe';
const LS_TW_DENSITY = 'oimpresso.cockpit.tweaks.density';
const LS_TW_HUE = 'oimpresso.cockpit.tweaks.accentHue';
const LS_TW_OPEN = 'oimpresso.cockpit.tweaks.open';

type Vibe = 'workspace' | 'daylight' | 'focus';
const VIBES: Array<{ id: Vibe; label: string }> = [
  { id: 'workspace', label: 'workspace' },
  { id: 'daylight', label: 'daylight' },
  { id: 'focus', label: 'focus' },
];

const CHAT_TABS: Array<{ id: string; label: string }> = [
  { id: 'todos', label: 'Todos' },
  { id: 'os', label: 'OS' },
  { id: 'equipe', label: 'Equipe' },
  { id: 'clientes', label: 'Clientes' },
];

// ── componentes internos ────────────────────────────────────────────────

// Gradiente determinístico por id (mesma empresa = mesma cor)
function gradientFor(id: number): string {
  const hue = (id * 47) % 360;
  return `linear-gradient(135deg, oklch(0.55 0.15 ${hue}), oklch(0.65 0.15 ${(hue + 60) % 360}))`;
}

function CompanyPicker({
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

function SidebarTabs({
  tab,
  onTab,
}: {
  tab: 'chat' | 'menu';
  onTab: (t: 'chat' | 'menu') => void;
}) {
  return (
    <div className="sb-tabs">
      <button
        type="button"
        className={`sb-tab ${tab === 'chat' ? 'active' : ''}`}
        onClick={() => onTab('chat')}
      >
        <MessageCircle size={13} /> <span>Chat</span>
      </button>
      <button
        type="button"
        className={`sb-tab ${tab === 'menu' ? 'active' : ''}`}
        onClick={() => onTab('menu')}
      >
        <Hash size={13} /> <span>Menu</span>
      </button>
    </div>
  );
}

function SidebarChat({
  fixadas,
  rotinas,
  recentes,
  activeId,
  onSelect,
}: {
  fixadas: ConversaResumo[];
  rotinas: Rotina[];
  recentes: ConversaResumo[];
  activeId: string;
  onSelect: (id: string) => void;
}) {
  return (
    <div>
      <div className="sb-actions">
        <div className="sb-action">
          <Plus size={14} /> <span>Nova conversa</span>
          <span className="kbd" style={{ marginLeft: 'auto' }}>⌘N</span>
        </div>
        <div className="sb-action">
          <Inbox size={14} /> <span>Tarefas</span>
          <span className="badge">6</span>
        </div>
        <div className="sb-action">
          <Bell size={14} /> <span>Despachos</span>
          <span className="beta">Beta</span>
        </div>
        <div className="sb-action">
          <Cog size={14} /> <span>Personalizar</span>
        </div>
      </div>

      <div className="sb-section-h">Fixadas</div>
      {fixadas.length === 0 ? (
        <div className="sb-action" style={{ opacity: 0.6 }}>
          <Pin size={14} /> <span>Arraste para fixar</span>
        </div>
      ) : (
        fixadas.map((c) => (
          <div
            key={c.id}
            className={`sb-conv ${c.id === activeId ? 'active' : ''}`}
            onClick={() => onSelect(c.id)}
          >
            <span className={`sb-bullet ${c.unread ? 'filled' : ''}`} />
            <span className="sb-conv-t">{c.titulo}</span>
          </div>
        ))
      )}

      <div className="sb-section-h">Rotinas</div>
      {rotinas.map((r) => (
        <div key={r.id} className="sb-routine">
          <span className="sb-bullet" />
          <span className="sb-routine-t">{r.titulo}</span>
          <span className="sb-routine-f">{r.frequencia}</span>
        </div>
      ))}

      <div className="sb-section-h">Recentes</div>
      {recentes.map((c) => (
        <div
          key={c.id}
          className={`sb-conv ${c.id === activeId ? 'active' : ''}`}
          onClick={() => onSelect(c.id)}
        >
          <span className={`sb-bullet ${c.unread ? 'filled' : ''}`} />
          <span className="sb-conv-t">{c.titulo}</span>
        </div>
      ))}
    </div>
  );
}

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

function SidebarMenu({ items }: { items: ShellMenuItem[] }) {
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

function SidebarUserMenu({
  open,
  onClose,
  nome,
  email,
  iniciais,
}: {
  open: boolean;
  onClose: () => void;
  nome: string;
  email: string;
  iniciais: string;
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

function SidebarFooter({
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
  return (
    <div className="sb-user-wrap">
      {/* Items superadmin (Backup, CMS, Connector, etc) */}
      {superadminItems.length > 0 && (
        <div className="sb-superadmin">
          {superadminItems.map((item, idx) => (
            <a
              key={`super-${idx}`}
              href={item.href ?? '#'}
              className="sb-superadmin-item"
              title={item.label}
            >
              <span className="ic dot" />
              <span className="label">{item.label}</span>
            </a>
          ))}
        </div>
      )}

      {/* User dropdown */}
      <div className="sb-user" style={{ position: 'relative' }}>
        <SidebarUserMenu
          open={openUser}
          onClose={() => setOpenUser(false)}
          nome={nome}
          email={email}
          iniciais={iniciais}
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

function ChatTabs({
  active,
  onChange,
}: {
  active: string;
  onChange: (t: string) => void;
}) {
  return (
    <div className="chat-tabsbar">
      <div className="chat-tabs">
        {CHAT_TABS.map((t) => (
          <button
            key={t.id}
            type="button"
            className={`chat-tab ${active === t.id ? 'active' : ''}`}
            onClick={() => onChange(t.id)}
          >
            {t.label}
          </button>
        ))}
      </div>
      <div className="chat-search">
        <Search size={12} />
        <input placeholder="Buscar nesta conversa…" />
      </div>
    </div>
  );
}

function ThreadHeader({ conv }: { conv: ConversaFoco }) {
  const av = conv.avatar ?? { iniciais: conv.titulo.slice(0, 2).toUpperCase(), gradId: 1 };
  const sub =
    conv.tipo === 'os' && conv.os
      ? `OS ${conv.os.numero} · ${conv.os.cliente}`
      : conv.tipo === 'team'
      ? 'Canal interno da equipe'
      : conv.cliente?.nome || 'Cliente';
  return (
    <header className="th-head">
      <div className="th-av" style={{ background: gradientFor(av.gradId) }}>
        {av.iniciais}
        {conv.online && <span className="th-online" />}
      </div>
      <div className="th-who">
        <b>{conv.titulo}</b>
        <small>{sub}</small>
      </div>
      <div className="th-actions">
        <button className="icon-btn" type="button" title="Ligar"><Phone size={14} /></button>
        <button className="icon-btn" type="button" title="Detalhes"><Info size={14} /></button>
        <button className="icon-btn" type="button" title="Mais"><MoreHorizontal size={14} /></button>
      </div>
    </header>
  );
}

function ThreadContext({ conv }: { conv: ConversaFoco }) {
  if (!conv.os) return null;
  return (
    <div className="th-context">
      <span>OS <span className="pill">{conv.os.numero}</span></span>
      <span><b>{conv.os.cliente}</b></span>
      <span className="stage">● {conv.os.estagio}</span>
      <span className="th-deadline">
        Entrega prevista: <b>{conv.os.prazo}</b>
      </span>
    </div>
  );
}

function Bubble({ m, prev }: { m: Mensagem; prev?: Mensagem }) {
  const continued =
    !!prev && prev.autor === m.autor && prev.whoNome === m.whoNome;
  if (m.autor === 'me') {
    return (
      <div className={`msg-row me ${continued ? 'continued' : ''}`}>
        <div className="bubble me">
          <div className="bubble-text">{m.texto}</div>
          <div className="meta">
            {m.hora}
            <span className="check">
              {m.lida ? <CheckCheck size={12} /> : <Check size={12} />}
            </span>
          </div>
        </div>
      </div>
    );
  }
  // them
  const av = m.whoAvatar ?? { iniciais: '??', gradId: 1 };
  return (
    <div className={`msg-row them ${continued ? 'continued' : ''}`}>
      {!continued ? (
        <div className="bubble-av" style={{ background: gradientFor(av.gradId) }}>
          {av.iniciais}
        </div>
      ) : (
        <div className="bubble-av-spacer" />
      )}
      <div className="bubble them">
        {!continued && m.whoNome && <span className="author">{m.whoNome}</span>}
        <div className="bubble-text">{m.texto}</div>
        <div className="meta">{m.hora}</div>
      </div>
    </div>
  );
}

function TypingIndicator({ avatar }: { avatar?: AvatarRef }) {
  const av = avatar ?? { iniciais: '??', gradId: 1 };
  return (
    <div className="msg-row them typing-row">
      <div className="bubble-av" style={{ background: gradientFor(av.gradId) }}>
        {av.iniciais}
      </div>
      <div className="typing">
        <span /><span /><span />
      </div>
    </div>
  );
}

function Thread({ mensagens, typing, typingAvatar }: { mensagens: Mensagem[]; typing: boolean; typingAvatar?: AvatarRef }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    ref.current?.scrollTo({ top: ref.current.scrollHeight });
  }, [mensagens, typing]);

  // agrupar por dia
  const rows: Array<{ msg: Mensagem; prev?: Mensagem; showDay: boolean }> = [];
  for (let i = 0; i < mensagens.length; i++) {
    const m = mensagens[i]!;
    const prev = mensagens[i - 1];
    rows.push({ msg: m, prev, showDay: !prev || prev.dia !== m.dia });
  }

  return (
    <div className="chat-thread" ref={ref}>
      {rows.map(({ msg, prev, showDay }) => (
        <React.Fragment key={msg.id}>
          {showDay && msg.dia && <div className="day-sep">{msg.dia}</div>}
          <Bubble m={msg} prev={prev} />
        </React.Fragment>
      ))}
      {typing && <TypingIndicator avatar={typingAvatar} />}
    </div>
  );
}

function Composer({ onSend, conv }: { onSend: (texto: string) => void; conv: ConversaFoco }) {
  const [texto, setTexto] = useState('');
  const taRef = useRef<HTMLTextAreaElement>(null);

  // Auto-grow
  useEffect(() => {
    const el = taRef.current;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
  }, [texto]);

  function submit() {
    const t = texto.trim();
    if (!t) return;
    onSend(t);
    setTexto('');
  }
  const empty = !texto.trim();
  return (
    <div className="composer">
      <div className="composer-box">
        <textarea
          ref={taRef}
          rows={1}
          placeholder={`Mensagem para ${conv.titulo}…`}
          value={texto}
          onChange={(e) => setTexto(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              submit();
            }
          }}
        />
        <div className="composer-toolbar">
          <button className="icon-btn" type="button" title="Anexo"><Paperclip size={14} /></button>
          <button className="icon-btn" type="button" title="Emoji"><Smile size={14} /></button>
          <button className="icon-btn" type="button" title="Mencionar"><Hash size={14} /></button>
          <span className="composer-spacer" />
          <span className="composer-hint-inline">
            <span className="kbd">Enter</span> envia · <span className="kbd">⇧+Enter</span> nova linha
          </span>
          <button className="send-btn" type="button" onClick={submit} disabled={empty}>
            Enviar <Send size={12} style={{ marginLeft: 4, verticalAlign: -1 }} />
          </button>
        </div>
      </div>
    </div>
  );
}

// ── LinkedApps blocks ──────────────────────────────────────────────────

function LBlock({
  title,
  origem,
  children,
  blockKey,
  ctaLabel,
  icon: Ico,
}: {
  title: string;
  origem?: string;
  children: React.ReactNode;
  blockKey: string;
  ctaLabel?: string;
  icon: React.ComponentType<{ size?: number }>;
}) {
  const lsKey = `oimpresso.linked.${blockKey}.collapsed`;
  const [collapsed, setCollapsed] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(lsKey) === '1';
  });
  useEffect(() => {
    localStorage.setItem(lsKey, collapsed ? '1' : '0');
  }, [collapsed, lsKey]);

  return (
    <section className={`lblock ${collapsed ? 'collapsed' : ''}`}>
      <header className="lblock-h" onClick={() => setCollapsed((v) => !v)}>
        <Ico size={13} />
        <b>{title}</b>
        {origem && <span className={`origin-badge o-${origem}`}>{origem}</span>}
        <span className="lblock-spacer" />
        <ChevronRight
          size={11}
          className="lblock-chev"
          style={{ transform: collapsed ? 'rotate(0)' : 'rotate(90deg)' }}
        />
      </header>
      {!collapsed && (
        <div className="lblock-b">
          {children}
          {ctaLabel && (
            <button className="lblock-cta" type="button">
              {ctaLabel} <ChevronRight size={11} />
            </button>
          )}
        </div>
      )}
    </section>
  );
}

function LinkedKv({ label, value, mono }: { label: string; value: React.ReactNode; mono?: boolean }) {
  return (
    <div className="lkv">
      <span>{label}</span>
      <b className={mono ? 'mono' : ''}>{value}</b>
    </div>
  );
}

function LinkedAppsPanel({ conv }: { conv: ConversaFoco }) {
  return (
    <aside className="apps">
      <div className="apps-h">Apps Vinculados</div>

      {conv.os && (
        <LBlock title="Ordem de Serviço" origem="OS" blockKey="os" icon={Briefcase} ctaLabel="Abrir OS">
          <LinkedKv label="Número" value={conv.os.numero} mono />
          <LinkedKv label="Cliente" value={conv.os.cliente} />
          <div className="lkv">
            <span>Estágio</span>
            <span className="lstage">● {conv.os.estagio}</span>
          </div>
          <LinkedKv label="Prazo" value={conv.os.prazo} />
        </LBlock>
      )}

      {conv.cliente && (
        <LBlock title="Cliente" origem="CRM" blockKey="client" icon={User} ctaLabel="Ligar agora">
          <LinkedKv label="Nome" value={conv.cliente.nome} />
          <LinkedKv label="Telefone" value={conv.cliente.telefone} mono />
          <div className="lkv col">
            <span>Último contato</span>
            <span className="lhint">{conv.cliente.ultimoContato}</span>
          </div>
          <div className="lrow-btns">
            <button className="lbtn-sec" type="button">
              <Phone size={11} /> Ligar
            </button>
            <button className="lbtn-sec" type="button">
              <MessageCircle size={11} /> WhatsApp
            </button>
          </div>
        </LBlock>
      )}

      {conv.financeiro && (
        <LBlock title="Financeiro" origem="FIN" blockKey="fin" icon={DollarSign} ctaLabel="Emitir cobrança">
          <LinkedKv label="Saldo cliente" value={conv.financeiro.saldo} />
          <LinkedKv label="Boletos abertos" value={conv.financeiro.boletos} />
        </LBlock>
      )}

      {conv.anexos && conv.anexos.length > 0 && (
        <LBlock title="Anexos" blockKey="att" icon={Paperclip}>
          <div className="latts">
            {conv.anexos.map((a, i) => (
              <div className="latt" key={i}>
                <FileText size={11} />
                <div className="latt-body">
                  <b>{a.nome}</b>
                  <small>{a.tamanho}</small>
                </div>
              </div>
            ))}
          </div>
        </LBlock>
      )}

      {conv.historico && conv.historico.length > 0 && (
        <LBlock title="Histórico" blockKey="hist" icon={History}>
          <ul className="lhist">
            {conv.historico.map((e, i) => (
              <li key={i}>
                <span className="lhist-when">{e.quando}</span>
                <span className="lhist-who">
                  <b>{e.quem}</b> {e.oque}
                </span>
              </li>
            ))}
          </ul>
        </LBlock>
      )}
    </aside>
  );
}

function TweaksPanel({
  vibe, onVibe,
  density, onDensity,
  hue, onHue,
  open, onToggle,
}: {
  vibe: Vibe; onVibe: (v: Vibe) => void;
  density: number; onDensity: (n: number) => void;
  hue: number; onHue: (n: number) => void;
  open: boolean; onToggle: () => void;
}) {
  if (!open) {
    return (
      <div className="cockpit-tweaks">
        <button
          className="cockpit-tweaks-fab"
          type="button"
          title="Abrir Tweaks"
          onClick={onToggle}
        >
          <Sliders size={18} />
        </button>
      </div>
    );
  }
  return (
    <div className="cockpit-tweaks">
      <div className="cockpit-tweaks-card">
        <div className="cockpit-tweaks-card-h">
          <span className="title">Tweaks</span>
          <button className="close" type="button" onClick={onToggle} title="Fechar">
            <X size={14} />
          </button>
        </div>

        <div className="cockpit-tweaks-section">
          <div className="cockpit-tweaks-label">Vibe</div>
          <div className="cockpit-tweaks-sublabel">
            <span>Atmosfera</span>
            <span style={{ color: 'var(--text-mute)' }}>{vibe}</span>
          </div>
          <div className="cockpit-tweaks-radio">
            {VIBES.map((v) => (
              <button
                key={v.id}
                type="button"
                className={vibe === v.id ? 'active' : ''}
                onClick={() => onVibe(v.id)}
              >
                {v.label}
              </button>
            ))}
          </div>
        </div>

        <div className="cockpit-tweaks-section">
          <div className="cockpit-tweaks-label">Densidade</div>
          <div className="cockpit-tweaks-sublabel">
            <span>Skim ↔ Briefing</span>
            <span style={{ color: 'var(--text-mute)' }}>{density}%</span>
          </div>
          <input
            type="range"
            className="cockpit-tweaks-slider"
            min={0}
            max={100}
            step={5}
            value={density}
            onChange={(e) => onDensity(Number(e.target.value))}
          />
        </div>

        <div className="cockpit-tweaks-section">
          <div className="cockpit-tweaks-label">Cor</div>
          <div className="cockpit-tweaks-sublabel">
            <span>Tom do accent</span>
            <span style={{ color: 'var(--text-mute)' }}>{hue}°</span>
          </div>
          <input
            type="range"
            className="cockpit-tweaks-slider"
            min={0}
            max={360}
            step={10}
            value={hue}
            onChange={(e) => onHue(Number(e.target.value))}
          />
          <div className="cockpit-tweaks-hue-preview" />
        </div>
      </div>
    </div>
  );
}

// ── página principal ────────────────────────────────────────────────────

export default function Cockpit({
  businessNome,
  businesses,
  usuarioNome,
  usuarioNomeCurto,
  usuarioEmail,
  usuarioCargo,
  usuarioIniciais,
  conversas,
  conversaFoco,
}: Props) {
  // Pega o menu compartilhado do shell (LegacyMenuAdapter já popula via Inertia share)
  const page = usePage();
  const shellMenu: ShellMenuItem[] =
    ((page.props as { shell?: { menu?: ShellMenuItem[] } })?.shell?.menu) ?? [];
  const superadminItems = shellMenu.filter((i) => isSuperadminMenu(i.label));
  const [tab, setTab] = useState<'chat' | 'menu'>(() => {
    if (typeof window === 'undefined') return 'chat';
    return (localStorage.getItem(LS_TAB) as 'chat' | 'menu') || 'chat';
  });
  const [chatTab, setChatTab] = useState<string>(() => {
    if (typeof window === 'undefined') return 'todos';
    return localStorage.getItem(LS_CHAT_TAB) || 'todos';
  });
  const [activeConvId, setActiveConvId] = useState<string>(() => {
    if (typeof window === 'undefined') return conversaFoco.id;
    return localStorage.getItem(LS_CONV) || conversaFoco.id;
  });
  const [linkedCollapsed, setLinkedCollapsed] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(LS_LINKED) === '1';
  });
  const [mensagensLocal, setMensagensLocal] = useState<Mensagem[]>(conversaFoco.mensagens);
  const [typing, setTyping] = useState<boolean>(false);

  // Tweaks (vibe / densidade / accent hue)
  const [tweaksOpen, setTweaksOpen] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(LS_TW_OPEN) === '1';
  });
  const [vibe, setVibe] = useState<Vibe>(() => {
    if (typeof window === 'undefined') return 'workspace';
    return (localStorage.getItem(LS_TW_VIBE) as Vibe) || 'workspace';
  });
  const [density, setDensity] = useState<number>(() => {
    if (typeof window === 'undefined') return 50;
    const v = Number(localStorage.getItem(LS_TW_DENSITY));
    return isFinite(v) && v > 0 ? v : 50;
  });
  const [accentHue, setAccentHue] = useState<number>(() => {
    if (typeof window === 'undefined') return 220;
    const v = Number(localStorage.getItem(LS_TW_HUE));
    return isFinite(v) && v > 0 ? v : 220;
  });

  useEffect(() => { localStorage.setItem(LS_TAB, tab); }, [tab]);
  useEffect(() => { localStorage.setItem(LS_CHAT_TAB, chatTab); }, [chatTab]);
  useEffect(() => { localStorage.setItem(LS_CONV, activeConvId); }, [activeConvId]);
  useEffect(() => {
    localStorage.setItem(LS_LINKED, linkedCollapsed ? '1' : '0');
  }, [linkedCollapsed]);

  useEffect(() => { localStorage.setItem(LS_TW_VIBE, vibe); }, [vibe]);
  useEffect(() => { localStorage.setItem(LS_TW_DENSITY, String(density)); }, [density]);
  useEffect(() => { localStorage.setItem(LS_TW_HUE, String(accentHue)); }, [accentHue]);
  useEffect(() => { localStorage.setItem(LS_TW_OPEN, tweaksOpen ? '1' : '0'); }, [tweaksOpen]);

  // Aplica densidade + accentHue como CSS vars no .cockpit
  const cockpitStyle: React.CSSProperties = {
    // Densidade afeta altura de linha e padding dos cards
    ['--row-h' as never]: `${26 + (density / 100) * 16}px`,
    ['--card-pad' as never]: `${8 + (density / 100) * 8}px`,
    // Accent hue repinta o accent + variações
    ['--accent' as never]: `oklch(0.58 0.12 ${accentHue})`,
    ['--accent-2' as never]: `oklch(0.66 0.12 ${accentHue})`,
    ['--accent-soft' as never]: `oklch(0.94 0.04 ${accentHue})`,
    ['--bubble-me' as never]: `oklch(0.58 0.12 ${accentHue})`,
  };

  const densityLabel = density < 30 ? 'skim' : density > 70 ? 'briefing' : 'normal';

  function handleSend(texto: string) {
    const novaMsg: Mensagem = {
      id: Date.now(),
      autor: 'me',
      texto,
      hora: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
      lida: false,
      dia: 'Hoje',
    };
    setMensagensLocal((arr) => [...arr, novaMsg]);
    // Simula resposta em 2-3s pra demonstrar typing indicator
    setTimeout(() => setTyping(true), 600);
    setTimeout(() => {
      setTyping(false);
      const replyAvatar = conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoAvatar;
      const replyNome = conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoNome;
      const reply: Mensagem = {
        id: Date.now() + 1,
        autor: 'them',
        whoAvatar: replyAvatar,
        whoNome: replyNome,
        texto: 'Recebido, vou verificar e te respondo já já 👍',
        hora: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
        dia: 'Hoje',
      };
      setMensagensLocal((arr) => [...arr, reply]);
    }, 2400);
  }

  return (
    <>
      <Head title="Copiloto · Cockpit" />
      <div
        className="cockpit"
        data-linked={linkedCollapsed ? 'off' : 'on'}
        data-vibe={vibe}
        data-density={densityLabel}
        style={cockpitStyle}
      >
        {/* SIDEBAR */}
        <aside className="sb">
          <div className="sb-top">
            <CompanyPicker businesses={businesses} fallbackNome={businessNome} />
          </div>
          <SidebarTabs tab={tab} onTab={setTab} />
          <div className="sb-body">
            {tab === 'chat' ? (
              <SidebarChat
                fixadas={conversas.fixadas}
                rotinas={conversas.rotinas}
                recentes={conversas.recentes}
                activeId={activeConvId}
                onSelect={setActiveConvId}
              />
            ) : (
              <SidebarMenu items={shellMenu} />
            )}
          </div>
          <SidebarFooter
            nome={usuarioNome}
            nomeCurto={usuarioNomeCurto}
            email={usuarioEmail}
            cargo={usuarioCargo}
            iniciais={usuarioIniciais}
            superadminItems={superadminItems}
          />
        </aside>

        {/* MAIN */}
        <div className="main">
          <header className="topbar">
            <div className="bc">
              <span>{businessNome}</span>
              <span className="bc-sep">/</span>
              <span className="bc-cur">Chat</span>
              <span className="bc-sep">›</span>
              <span>{conversaFoco.titulo}</span>
            </div>
            <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
              <button
                className="icon-btn"
                type="button"
                title={linkedCollapsed ? 'Mostrar Apps' : 'Esconder Apps'}
                onClick={() => setLinkedCollapsed((v) => !v)}
                style={{
                  background: 'transparent', border: 'none', cursor: 'pointer',
                  width: 28, height: 28, borderRadius: 6,
                  display: 'grid', placeItems: 'center', color: 'var(--text-dim)',
                }}
              >
                <ChevronDown
                  size={14}
                  style={{ transform: linkedCollapsed ? 'rotate(90deg)' : 'rotate(-90deg)' }}
                />
              </button>
            </div>
          </header>
          <div className="main-body">
            <ThreadHeader conv={conversaFoco} />
            <ChatTabs active={chatTab} onChange={setChatTab} />
            <ThreadContext conv={conversaFoco} />
            <Thread
              mensagens={mensagensLocal}
              typing={typing}
              typingAvatar={conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoAvatar}
            />
            <Composer onSend={handleSend} conv={conversaFoco} />
          </div>
        </div>

        {/* APPS VINCULADOS */}
        {!linkedCollapsed && <LinkedAppsPanel conv={conversaFoco} />}

        {/* TWEAKS PANEL (flutuante, fora do grid) */}
        <TweaksPanel
          vibe={vibe}
          onVibe={setVibe}
          density={density}
          onDensity={setDensity}
          hue={accentHue}
          onHue={setAccentHue}
          open={tweaksOpen}
          onToggle={() => setTweaksOpen((v) => !v)}
        />
      </div>
    </>
  );
}
