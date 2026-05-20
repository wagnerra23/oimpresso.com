// @memcofre
//   modulo: Cockpit (Thread)
//   adrs: UI-0008 (cockpit como layout-mae)
//   nota: thread + composer + bolhas. Reusavel por qualquer pagina React do
//         cockpit que tem chat (Copiloto, MemCofre, Atendimento, Equipe).

import React, { useEffect, useRef, useState } from 'react';
import {
  Check, CheckCheck, Hash, Info, MoreHorizontal, Paperclip, Phone, Search,
  Send, Smile,
} from 'lucide-react';

import {
  AvatarRef,
  CHAT_TABS,
  ConversaFoco,
  Mensagem,
  gradientFor,
} from './shared';

// ── ChatTabs (Todos / OS / Equipe / Clientes) ───────────────────────────

export function ChatTabs({
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

// ── ThreadHeader (avatar + nome + dot online + ações) ───────────────────

export function ThreadHeader({ conv }: { conv: ConversaFoco }) {
  const av = conv.avatar ?? { iniciais: conv.titulo.slice(0, 2).toUpperCase(), gradId: 1 };
  const sub =
    conv.tipo === 'os' && conv.os
      ? `OS ${conv.os.numero} · ${conv.os.cliente}`
      : conv.tipo === 'team'
      ? 'Canal interno da equipe'
      : conv.tipo === 'copiloto'
      ? 'Assistente IA · Copiloto'
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

// ── ThreadContext (faixa OS pill + cliente + estágio + prazo) ───────────

export function ThreadContext({ conv }: { conv: ConversaFoco }) {
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

// ── Bubble (mensagem individual com agrupamento continued) ──────────────

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

// ── TypingIndicator (3 dots animados) ───────────────────────────────────

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

// ── Thread (lista de bolhas + day separators + typing) ──────────────────

export function Thread({
  mensagens,
  typing,
  typingAvatar,
}: {
  mensagens: Mensagem[];
  typing: boolean;
  typingAvatar?: AvatarRef;
}) {
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

// ── Composer (textarea auto-grow + send button) ─────────────────────────

export function Composer({
  onSend,
  conv,
}: {
  onSend: (texto: string) => void;
  conv: ConversaFoco;
}) {
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
