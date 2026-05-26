// NotaDrawerV2.tsx — Slide-in 480px com detalhe completo da NF-e/NFC-e.
//
// Port do fiscal-page.jsx §NotaDrawer. Determinístico, sem IA.
// Substitui NotaDrawer.tsx anterior (que era stub). V2 traz:
//  - Status SEFAZ + prazoCancel + prazoCCe pills
//  - SefazActionCard (receita guiada por cstat — dicionário sefaz-actions)
//  - Seções: Operação · Eventos vinculados · Itens · Boleto · Arquivos · E-mails · Auditoria
//  - Atalhos R (reconsultar) / X (cancelar) / C (CC-e) com flash feedback
//  - Header is-scrolled (compactify ao rolar)

import { useEffect, useRef, useState } from 'react';
import { RefreshCw } from 'lucide-react';

import { brl, formatDoc, prazoCancel, prazoCCe, type Urgency } from '../_lib/fiscal-helpers';
import { sefazHint, sefazLabel, sefazTone } from '../_lib/sefaz-codes';
import { getRecipe, type SefazActionRecipe } from '../_lib/sefaz-actions';

export interface NotaDrawerItem {
  nome: string;
  codigo: string;
  qtd: number;
  vl: number;
}

export interface NotaDrawerBoleto {
  id: string;
  venc: string;
  valor: number;
  status: 'pago' | 'pendente' | 'vencido';
}

export interface NotaDrawerArquivo {
  tipo: string;
  nome: string;
  tamanho: string;
  status: 'gerado' | 'enviado' | 'erro' | string;
}

export interface NotaDrawerEmail {
  tipo: string;
  para: string;
  quando: string;
  status: 'entregue' | 'bouncing' | 'pendente' | string;
}

export interface NotaDrawerAuditoria {
  quando: string;
  autor: string;
  acao: string;
}

export interface NotaDrawerEvento {
  id: string | number;
  tipo: 'Cancelamento' | 'Carta de Correção' | 'Inutilização' | 'EPEC' | 'Manifestação' | string;
  sequencia?: number;
  descricao: string;
  emit: string;
  autor: string;
  sefaz: number;
}

export interface NotaDrawerData {
  id: string | number;
  num: string;
  serie: string | number;
  modelo: 55 | 65;
  key?: string | null;
  status: number; // cstat
  rejMsg?: string | null;
  dest: string;
  cnpj?: string | null;
  cpf?: string | null;
  uf: string;
  venda?: string | null;
  when: string;
  emittedAtIso?: string | null;
  value: number;
  itens?: NotaDrawerItem[];
  boleto?: NotaDrawerBoleto | null;
  arquivos?: NotaDrawerArquivo[];
  emails?: NotaDrawerEmail[];
  auditoria?: NotaDrawerAuditoria[];
  eventos?: NotaDrawerEvento[];
}

interface NotaDrawerProps {
  nota: NotaDrawerData | null;
  onClose: () => void;
  onQuickFilterCliente?: (cliente: string) => void;
}

const REJECTED_CODES = [110, 204, 217, 220, 301, 539, 691, 778];

function SefazActionCard({ recipe, status }: { recipe: SefazActionRecipe; status: number }) {
  return (
    <section className="fx-action-card" role="region" aria-label={`Como corrigir SEFAZ ${status}`}>
      <div className="fx-action-h">
        <span className="fx-action-spark" aria-hidden="true">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
            <path d="M12 2v6m0 8v6M2 12h6m8 0h6" />
          </svg>
        </span>
        <div>
          <b>Como corrigir · SEFAZ {status}</b>
          <small>{recipe.headline}</small>
        </div>
      </div>
      <ol className="fx-action-steps">
        {recipe.steps.map((s, i) => (
          <li key={i}>
            <span className="fx-action-n">{i + 1}</span>
            <span>{s}</span>
          </li>
        ))}
      </ol>
      {(recipe.primary || recipe.secondary) && (
        <div className="fx-action-btns">
          {recipe.primary && (
            <button type="button" className={`fx-btn ${recipe.primary.kind}`} disabled title="Wire-up no PR seguinte">
              {recipe.primary.label} <kbd>⏎</kbd>
            </button>
          )}
          {recipe.secondary && (
            <button type="button" className="fx-btn ghost" disabled title="Wire-up no PR seguinte">
              {recipe.secondary.label}
            </button>
          )}
        </div>
      )}
      <small className="fx-action-foot">
        Receita determinística (dicionário canônico). Revisar com contador antes de aplicar.
      </small>
    </section>
  );
}

function TimePill({
  kind, value, urgency,
}: { kind: 'cancel' | 'cce'; value: string; urgency: Urgency }) {
  const label = kind === 'cancel' ? 'cancelar em' : 'CC-e em';
  return (
    <span className={`fx-timepill u-${urgency}`}>
      {kind === 'cancel' && <RefreshCw size={10} />} {label} <b>{value}</b>
    </span>
  );
}

export default function NotaDrawerV2({ nota, onClose, onQuickFilterCliente }: NotaDrawerProps) {
  const [actionFlash, setActionFlash] = useState<string | null>(null);
  const [scrolled, setScrolled] = useState(false);
  const bodyRef = useRef<HTMLDivElement | null>(null);

  // Hooks SEMPRE chamados (ordem estável, mesmo com nota null)
  useEffect(() => {
    if (!nota) return;
    const flash = (label: string) => {
      setActionFlash(label);
      window.setTimeout(() => setActionFlash(null), 1400);
    };
    const cancelW = prazoCancel({
      emittedAtIso: nota.emittedAtIso ?? undefined,
      modelo: nota.modelo,
      status: nota.status === 100 ? 'autorizada' : '',
    });
    const cceW = prazoCCe({
      emittedAtIso: nota.emittedAtIso ?? undefined,
      modelo: nota.modelo,
      status: nota.status === 100 ? 'autorizada' : '',
    });

    const h = (e: KeyboardEvent) => {
      const t = e.target as HTMLElement | null;
      const typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);
      if (typing) return;
      if (e.key === 'Escape') { e.preventDefault(); onClose(); return; }
      if ((e.key === 'r' || e.key === 'R') && !e.metaKey && !e.ctrlKey) {
        e.preventDefault();
        flash('Reconsultando SEFAZ…');
      } else if ((e.key === 'x' || e.key === 'X') && !e.metaKey && !e.ctrlKey) {
        if (nota.status === 100 && cancelW) {
          e.preventDefault();
          flash('Cancelamento solicitado · janela 24h');
        }
      } else if ((e.key === 'c' || e.key === 'C') && !e.metaKey && !e.ctrlKey) {
        if (nota.status === 100 && cceW) {
          e.preventDefault();
          flash('Iniciando CC-e (Carta de Correção)');
        }
      }
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [nota, onClose]);

  useEffect(() => {
    const el = bodyRef.current;
    if (!el) return;
    const onScroll = () => setScrolled(el.scrollTop > 20);
    el.addEventListener('scroll', onScroll);
    return () => el.removeEventListener('scroll', onScroll);
  }, [nota?.id]);

  if (!nota) return null;

  const fakeStatus = nota.status === 100 ? 'autorizada' : '';
  const cancelW = prazoCancel({
    emittedAtIso: nota.emittedAtIso ?? undefined,
    modelo: nota.modelo,
    status: fakeStatus,
  });
  const cceW = prazoCCe({
    emittedAtIso: nota.emittedAtIso ?? undefined,
    modelo: nota.modelo,
    status: fakeStatus,
  });
  const rejected = REJECTED_CODES.includes(nota.status);
  const recipe = rejected ? getRecipe(nota.status) : null;
  const tone = sefazTone(nota.status);

  const itens = nota.itens ?? [];
  const arquivos = nota.arquivos ?? [];
  const emails = nota.emails ?? [];
  const auditoria = nota.auditoria ?? [];
  const eventos = nota.eventos ?? [];

  return (
    <>
      <div className="fx-drawer-bg" onClick={onClose} />
      <aside
        className={`fx-drawer${scrolled ? ' is-scrolled' : ''}`}
        role="dialog"
        aria-label={`Detalhe ${nota.modelo === 65 ? 'NFC-e' : 'NF-e'} ${nota.num}`}
      >
        <header className="fx-drawer-h">
          <div>
            <small>{nota.modelo === 65 ? 'NFC-e' : 'NF-e'} · série {nota.serie}</small>
            <h2>
              {nota.modelo === 65 ? 'NFC-e ' : 'NFe '}{nota.num}
              {scrolled && (
                <span className={`fx-sefaz ${tone}`} style={{ marginLeft: 8 }}>
                  <span className="code">{nota.status}</span>
                </span>
              )}
            </h2>
            <code className="fx-drawer-key">{nota.key ?? '—'}</code>
          </div>
          <button type="button" className="fx-drawer-x" onClick={onClose} aria-label="Fechar (ESC)">×</button>
        </header>

        <div className="fx-drawer-body" ref={bodyRef}>
          {/* Status SEFAZ + prazos */}
          <section className="fx-drawer-sec">
            <h4>Status SEFAZ</h4>
            <div className="fx-drawer-status-row">
              <span className={`fx-sefaz ${tone}`}>
                <span className="code">{nota.status}</span>
                <span className="lbl">{sefazLabel(nota.status)}</span>
              </span>
              {cancelW && <TimePill kind="cancel" value={`${cancelW.h}h${cancelW.m.toString().padStart(2, '0')}`} urgency={cancelW.urgency} />}
              {cceW && <TimePill kind="cce" value={`${cceW.d}d`} urgency={cceW.urgency} />}
            </div>
            <p className="fx-drawer-hint">{sefazHint(nota.status)}</p>
            {nota.rejMsg && <div className="fx-drawer-rej">↳ {nota.rejMsg}</div>}
          </section>

          {/* Receita guiada (só se cstat tem receita mapeada) */}
          {recipe && <SefazActionCard recipe={recipe} status={nota.status} />}

          {/* Operação */}
          <section className="fx-drawer-sec">
            <h4>Operação</h4>
            <dl className="fx-kv">
              <dt>Destinatário</dt>
              <dd>
                <a
                  href="#"
                  className="fx-link"
                  onClick={(e) => {
                    e.preventDefault();
                    onQuickFilterCliente?.(nota.dest);
                    onClose();
                  }}
                  title="Filtrar lista por este cliente"
                >
                  <b>{nota.dest}</b>
                </a>
              </dd>
              <dt>{nota.cnpj ? 'CNPJ' : 'CPF'}</dt>
              <dd className="fx-mono">{formatDoc(nota.cnpj, nota.cpf)} · {nota.uf}</dd>
              <dt>Venda</dt>
              <dd>{nota.venda ?? '—'}</dd>
              <dt>Emissão</dt>
              <dd>{nota.when}</dd>
              <dt>Valor</dt>
              <dd className="fx-strong">{brl(nota.value)}</dd>
            </dl>
          </section>

          {/* Eventos vinculados (timeline reversa) */}
          {eventos.length > 0 && (
            <section className="fx-drawer-sec">
              <h4>Eventos vinculados <span className="fx-sec-count">{eventos.length}</span></h4>
              <ul className="fx-tl-list">
                {eventos.map((e) => {
                  const eTone = e.tipo === 'Cancelamento' ? 'bad'
                    : e.tipo === 'Inutilização' ? 'warn'
                    : e.tipo === 'Carta de Correção' ? 'ok' : 'neutral';
                  return (
                    <li key={e.id} className={`fx-tl-item-mini t-${eTone}`}>
                      <span className="fx-tl-dot" />
                      <div className="fx-tl-body">
                        <div className="fx-tl-head">
                          <span className={`fx-tl-badge ${eTone === 'bad' ? 'cancel' : eTone === 'warn' ? 'epec' : eTone === 'ok' ? 'cce' : 'manifest'}`}>
                            {e.tipo}{e.sequencia ? ` seq ${e.sequencia}` : ''}
                          </span>
                          <span className="when">{e.emit}</span>
                          <span className="when">· {e.autor}</span>
                        </div>
                        <div className="fx-tl-desc">{e.descricao}</div>
                        <span className={`fx-sefaz ${sefazTone(e.sefaz)} compact`} style={{ marginTop: 4 }}>
                          <span className="code">{e.sefaz}</span>
                          <span className="lbl">{sefazLabel(e.sefaz)}</span>
                        </span>
                      </div>
                    </li>
                  );
                })}
              </ul>
            </section>
          )}

          {/* Itens */}
          {itens.length > 0 && (
            <section className="fx-drawer-sec">
              <h4>Itens <span className="fx-sec-count">{itens.length}</span></h4>
              <ul className="fx-itens">
                {itens.map((it, i) => (
                  <li key={i}>
                    <div>
                      <b>{it.nome}</b>
                      <small>{it.codigo} · qtd {it.qtd}</small>
                    </div>
                    <span className="fx-itens-vl">{brl(it.vl)}</span>
                  </li>
                ))}
              </ul>
            </section>
          )}

          {/* Boleto vinculado */}
          {nota.boleto && (
            <section className="fx-drawer-sec">
              <h4>Título financeiro</h4>
              <div className="fx-boleto">
                <div className="fx-boleto-body">
                  <b>Boleto {nota.boleto.id}</b>
                  <small>venc. {nota.boleto.venc} · {brl(nota.boleto.valor)}</small>
                </div>
                <span className={`fx-sefaz ${nota.boleto.status === 'pago' ? 'ok' : nota.boleto.status === 'vencido' ? 'bad' : 'warn'}`}>
                  <span className="lbl">{nota.boleto.status}</span>
                </span>
              </div>
            </section>
          )}

          {/* Arquivos */}
          {arquivos.length > 0 && (
            <section className="fx-drawer-sec">
              <h4>Arquivos <span className="fx-sec-count">{arquivos.length}</span></h4>
              <ul className="fx-arqs">
                {arquivos.map((a, i) => (
                  <li key={i}>
                    <span className="fx-arq-tag">{a.tipo}</span>
                    <div>
                      <b>{a.nome}</b>
                      <small>{a.tamanho}</small>
                    </div>
                    <span className={`fx-arq-st s-${a.status}`}>{a.status}</span>
                  </li>
                ))}
              </ul>
            </section>
          )}

          {/* E-mails */}
          {emails.length > 0 && (
            <section className="fx-drawer-sec">
              <h4>E-mails enviados <span className="fx-sec-count">{emails.length}</span></h4>
              <ul className="fx-emails">
                {emails.map((e, i) => (
                  <li key={i}>
                    <div>
                      <b>{e.tipo}</b>
                      <small>para {e.para}</small>
                    </div>
                    <div className="fx-email-meta">
                      <span className="fx-mut">{e.quando}</span>
                      <span className={`fx-sefaz ${e.status === 'entregue' ? 'ok' : 'warn'} compact`}>
                        <span className="lbl">{e.status}</span>
                      </span>
                    </div>
                  </li>
                ))}
              </ul>
            </section>
          )}

          {/* Auditoria */}
          {auditoria.length > 0 && (
            <section className="fx-drawer-sec">
              <h4>Auditoria <span className="fx-sec-count">{auditoria.length}</span></h4>
              <ul className="fx-audit">
                {auditoria.map((a, i) => (
                  <li key={i}>
                    <span className="fx-audit-when">{a.quando}</span>
                    <span className="fx-audit-author">{a.autor}</span>
                    <span className="fx-audit-acao">{a.acao}</span>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>

        {/* Flash feedback dos atalhos R/X/C */}
        {actionFlash && (
          <div className="fx-action-flash" role="status" aria-live="polite">
            <RefreshCw size={13} /> {actionFlash}
          </div>
        )}

        <footer className="fx-drawer-f">
          <button type="button" className="fx-btn ghost" disabled title="Wire-up no PR seguinte">
            Reconsultar <kbd>R</kbd>
          </button>
          <div className="fx-drawer-f-r">
            <button type="button" className="fx-btn ghost" disabled title="Download XML">XML</button>
            <button type="button" className="fx-btn ghost" disabled title="Download DANFE">DANFE</button>
            {nota.status === 100 && cancelW && (
              <button type="button" className="fx-btn danger" disabled title="Wire-up no PR seguinte">
                Cancelar <kbd>X</kbd>
              </button>
            )}
            {rejected && (
              <button type="button" className="fx-btn primary" disabled title="Wire-up no PR seguinte">
                Retransmitir <kbd>⏎</kbd>
              </button>
            )}
          </div>
        </footer>
      </aside>
    </>
  );
}
