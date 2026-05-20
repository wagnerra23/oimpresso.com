// @memcofre
//   modulo: Cockpit (LinkedApps)
//   adrs: UI-0008 (cockpit como layout-mae)
//   nota: painel direito 320px com cards colapsaveis por entidade em foco.
//         5 cards canonicos (OS / Cliente / Financeiro / Anexos / Historico).

import { useEffect, useState } from 'react';
import {
  Briefcase, ChevronRight, DollarSign, FileText, History, MessageCircle,
  Paperclip, Phone, User,
} from 'lucide-react';

import { ConversaFoco } from './shared';

// ── LBlock (card colapsavel generico) ──────────────────────────────────

export function LBlock({
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

// ── LinkedKv (label + valor monoespaçável) ──────────────────────────────

export function LinkedKv({
  label,
  value,
  mono,
}: {
  label: string;
  value: React.ReactNode;
  mono?: boolean;
}) {
  return (
    <div className="lkv">
      <span>{label}</span>
      <b className={mono ? 'mono' : ''}>{value}</b>
    </div>
  );
}

// ── LinkedAppsPanel (orquestrador dos 5 cards canônicos) ────────────────

export function LinkedAppsPanel({ conv }: { conv: ConversaFoco }) {
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
