// @memcofre
//   tela: /copiloto/cockpit
//   stories: US-COPI-COCKPIT-001 (MVP)
//   adrs: 0039 (padrao "Chat Cockpit")
//   status: mvp-piloto-em-validacao
//   module: Copiloto
//   nota: rota PARALELA ao /copiloto atual; nao substitui Chat.tsx.

import { Head, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
  Bell, Check, ChevronDown, ChevronUp, Cog, Hash, Inbox, Keyboard, LogOut,
  MessageCircle, Moon, Paperclip, Phone, Pin, Plus, Search, Send, Smile, Sliders, User, X,
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
interface Mensagem {
  id: number;
  autor: 'me' | 'them';
  texto: string;
  hora: string;
  lida?: boolean;
}
interface ConversaFoco {
  id: string;
  titulo: string;
  tipo: string;
  cliente?: {
    nome: string;
    telefone: string;
    ultimoContato: string;
  };
  mensagens: Mensagem[];
}

interface Props {
  businessNome: string;
  usuarioNome: string;
  usuarioCargo: string;
  conversas: {
    fixadas: ConversaResumo[];
    rotinas: Rotina[];
    recentes: ConversaResumo[];
  };
  conversaFoco: ConversaFoco;
  conversaAtivaRealId: number | null;
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

function CompanyPicker({ businessNome }: { businessNome: string }) {
  const initials = businessNome
    .split(/\s+/)
    .map((w) => w[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
  return (
    <button className="sb-cp-btn" type="button">
      <span className="avatar">{initials}</span>
      <span className="name">{businessNome}</span>
      <ChevronDown size={14} />
    </button>
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

function SidebarMenuStub() {
  return (
    <div className="sb-menu-stub">
      <Hash size={20} style={{ opacity: 0.4, marginBottom: 8 }} />
      <p>Aba <b>Menu</b> entra na próxima fase do MVP.</p>
      <p style={{ marginTop: 8, fontSize: 11 }}>
        Será espelho fiel do AppShell atual (LegacyMenuAdapter), zero
        re-aprendizado pelo cliente final.
      </p>
    </div>
  );
}

function SidebarUser({
  nome,
  cargo,
}: {
  nome: string;
  cargo: string;
}) {
  const initials = nome
    .split(/\s+/)
    .map((w) => w[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
  return (
    <div className="sb-user">
      <button className="sb-user-btn" type="button">
        <span className="avatar">{initials}</span>
        <div className="who">
          <b>{nome}</b>
          <small>{cargo}</small>
        </div>
        <ChevronUp size={12} />
      </button>
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

function Thread({ mensagens }: { mensagens: Mensagem[] }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    ref.current?.scrollTo({ top: ref.current.scrollHeight });
  }, [mensagens]);

  return (
    <div className="chat-thread" ref={ref}>
      <div className="day-sep">Hoje</div>
      {mensagens.map((m) => (
        <div key={m.id} className={`msg-row ${m.autor === 'me' ? 'me' : ''}`}>
          <div className={`bubble ${m.autor === 'me' ? 'me' : 'them'}`}>
            <div>{m.texto}</div>
            <div className="meta">
              {m.hora} {m.autor === 'me' && (m.lida ? <Check size={12} /> : null)}
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

function Composer({ onSend }: { onSend: (texto: string) => void }) {
  const [texto, setTexto] = useState('');
  function submit() {
    const t = texto.trim();
    if (!t) return;
    onSend(t);
    setTexto('');
  }
  return (
    <div className="composer">
      <div className="composer-box">
        <button className="icon-btn" type="button" title="Anexar">
          <Paperclip size={14} />
        </button>
        <button className="icon-btn" type="button" title="Emoji">
          <Smile size={14} />
        </button>
        <button className="icon-btn" type="button" title="Tag">
          <Hash size={14} />
        </button>
        <textarea
          rows={1}
          placeholder="Mensagem para Clínica Vida — Marcos…"
          value={texto}
          onChange={(e) => setTexto(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              submit();
            }
          }}
        />
        <button className="send-btn" type="button" onClick={submit}>
          Enviar <Send size={12} style={{ marginLeft: 4, verticalAlign: -1 }} />
        </button>
      </div>
      <div className="composer-hint">
        <span><span className="kbd">Enter</span> envia</span>
        <span><span className="kbd">⇧+Enter</span> nova linha</span>
      </div>
    </div>
  );
}

function LinkedAppsPanel({ conv }: { conv: ConversaFoco }) {
  if (!conv.cliente) return null;
  return (
    <aside className="apps">
      <div className="apps-h">Apps Vinculados</div>
      <div className="linked-card">
        <div className="linked-card-h">
          <User size={14} /> Cliente <span className="badge">CRM</span>
        </div>
        <div className="linked-row"><b>Nome</b> <span>{conv.cliente.nome}</span></div>
        <div className="linked-row"><b>Telefone</b> <span>{conv.cliente.telefone}</span></div>
        <div className="linked-row"><b>Último contato</b> <span style={{ flex: 1 }}>{conv.cliente.ultimoContato}</span></div>
        <div className="linked-actions">
          <button className="linked-btn" type="button"><Phone size={12} style={{ verticalAlign: -1, marginRight: 4 }} />Ligar</button>
          <button className="linked-btn" type="button">WhatsApp</button>
        </div>
        <div className="linked-actions">
          <button className="linked-btn primary" type="button">Ligar agora ▸</button>
        </div>
      </div>
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
  usuarioNome,
  usuarioCargo,
  conversas,
  conversaFoco,
}: Props) {
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
    };
    setMensagensLocal((arr) => [...arr, novaMsg]);
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
            <CompanyPicker businessNome={businessNome} />
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
              <SidebarMenuStub />
            )}
          </div>
          <SidebarUser nome={usuarioNome} cargo={usuarioCargo} />
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
            <ChatTabs active={chatTab} onChange={setChatTab} />
            <Thread mensagens={mensagensLocal} />
            <Composer onSend={handleSend} />
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
