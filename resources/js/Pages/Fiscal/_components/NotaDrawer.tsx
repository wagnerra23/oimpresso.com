// NotaDrawer.tsx — slide-in lateral com detalhe da nota + mapa SEFAZ guiado
// Port do design fiscal-page.jsx §5 (NotaDrawer + SefazActionCard)
// "Jana sugere": receita determinística por cstat — substitui IA real (R#2 KB-9.75)

import { router } from '@inertiajs/react';
import { Bot, FileText, RefreshCw, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import {
  brl,
  formatDoc,
  prazoCancel,
  prazoCCe,
  type SefazCodesMap,
} from '../_lib/fiscal-helpers';

export interface NotaRow {
  id: number;
  num: number;
  serie: number;
  modelo: number;
  key: string | null;
  status: string;
  cstat: number;
  motivo: string | null;
  value: number;
  emittedAtIso: string | null;
  when: string | null;
  transactionId: number | null;
  dest: string;
  cnpj: string | null;
  cpf: string | null;
  uf: string | null;
  items: number | null;
  cancelavel: boolean;
}

interface NotaDrawerProps {
  nota: NotaRow | null;
  sefazCodes: SefazCodesMap;
  onClose: () => void;
}

// Receita guiada SEFAZ ("Jana sugere") — determinística por cstat de rejeição.
// Port do fiscal-data.jsx SEFAZ_ACTIONS R#1 KB-9.75.
// Não usa LLM — economia + previsibilidade pro contador (Eliana persona).
interface SefazActionRecipe {
  headline: string;
  steps: string[];
  primary?: { label: string; kind: 'danger' | 'warn' | 'primary' };
  secondary?: { label: string; kind: 'ghost' };
}

const SEFAZ_ACTIONS: Record<number, SefazActionRecipe> = {
  110: {
    headline: 'Operação irregular — NÃO retransmitir',
    steps: [
      'Confira o CNPJ do destinatário na Receita Federal.',
      'Se inscrição estiver baixada ou suspensa, contate o cliente.',
      'Esta nota não pode ser reemitida com este destinatário.',
    ],
    primary:   { label: 'Marcar como bloqueada',     kind: 'danger' },
    secondary: { label: 'Abrir cadastro do cliente', kind: 'ghost'  },
  },
  220: {
    headline: 'Numeração colidiu — inutilize e retransmita',
    steps: [
      'Inutilize a faixa atual no SEFAZ (mantém o histórico legal).',
      'Revise o gerador de cNF (8 dígitos aleatórios não-sequenciais).',
      'Retransmita usando o próximo número da série.',
    ],
    primary:   { label: 'Inutilizar e retransmitir', kind: 'warn'  },
    secondary: { label: 'Ver detalhes técnicos',     kind: 'ghost' },
  },
  539: {
    headline: 'Chave de acesso duplicada',
    steps: [
      'A combinação CNPJ + nº + cNF + modelo + série já existe.',
      'Inutilize esta faixa de numeração.',
      'Verifique se há reenvio paralelo (queue duplicada) — checar logs.',
      'Retransmita com cNF regenerado.',
    ],
    primary:   { label: 'Inutilizar faixa',         kind: 'warn'  },
    secondary: { label: 'Investigar fila SEFAZ',    kind: 'ghost' },
  },
  691: {
    headline: 'NCM divergente — revisar cadastro',
    steps: [
      'O NCM enviado não bate com o cadastro do produto.',
      'Abra o produto e confira o código NCM correto.',
      'Ajuste o item da nota e retransmita.',
    ],
    primary: { label: 'Abrir produto e corrigir', kind: 'primary' },
  },
  778: {
    headline: 'CST/CFOP inválido para a UF destino',
    steps: [
      'Confira a UF do destinatário (regra muda por estado).',
      'Verifique tabela CST × CFOP do regime tributário atual.',
      'Ajuste no cadastro do produto ou direto na nota e retransmita.',
    ],
    primary:   { label: 'Ajustar tributação',     kind: 'primary' },
    secondary: { label: 'Ver matriz CST/CFOP',    kind: 'ghost'   },
  },
};

function SefazActionCard({ cstat }: { cstat: number }) {
  const recipe = SEFAZ_ACTIONS[cstat];
  if (!recipe) return null;

  return (
    <div className="fx-action-card">
      <div className="fx-action-h">
        <span className="fx-action-spark">
          <Bot size={14}/>
        </span>
        <div>
          <b>Jana sugere · SEFAZ {cstat}</b>
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
      <div className="fx-action-btns">
        {recipe.primary && (
          <button className={`fx-btn ${recipe.primary.kind}`} disabled title="Ação em PR seguinte">
            {recipe.primary.label}
          </button>
        )}
        {recipe.secondary && (
          <button className="fx-btn ghost" disabled title="Ação em PR seguinte">
            {recipe.secondary.label}
          </button>
        )}
      </div>
      <small className="fx-action-foot">fonte: receita SEFAZ-SP · revisada por contadora</small>
    </div>
  );
}

export default function NotaDrawer({ nota, sefazCodes, onClose }: NotaDrawerProps) {
  const [cancelOpen, setCancelOpen] = useState(false);
  const [motivo, setMotivo] = useState('');
  const [busy, setBusy] = useState(false);

  // ESC fecha (drawer ou modal cancel)
  useEffect(() => {
    if (!nota) return;
    const h = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        if (cancelOpen) setCancelOpen(false);
        else onClose();
      }
    };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [nota, onClose, cancelOpen]);

  // Reset modal state quando trocar de nota
  useEffect(() => {
    setCancelOpen(false);
    setMotivo('');
  }, [nota?.id]);

  const handleCancelar = () => {
    if (motivo.trim().length < 15) return;
    if (!nota) return;
    setBusy(true);
    router.post(`/fiscal/acoes/nfe/${nota.id}/cancelar`, { motivo }, {
      preserveScroll: true,
      onFinish: () => {
        setBusy(false);
        setCancelOpen(false);
        setMotivo('');
        onClose();
      },
    });
  };

  if (!nota) return null;

  const cancel = prazoCancel(nota);
  const cce = prazoCCe(nota);
  const sefaz = sefazCodes[nota.cstat] ?? { tone: 'warn', label: `Status ${nota.cstat}`, hint: '' };

  return (
    <>
      <div className="fx-drawer-bg" onClick={onClose} aria-hidden="true"/>
      <aside className="fx-drawer" role="dialog" aria-label="Detalhe da nota">
        <header className="fx-drawer-h">
          <div>
            <small>{nota.modelo === 65 ? 'NFC-e' : 'NF-e'} · série {nota.serie}</small>
            <h2>{nota.modelo === 65 ? 'NFC-e ' : 'NFe '}{nota.num}</h2>
            {nota.key && <code className="fx-drawer-key">{nota.key}</code>}
          </div>
          <button className="fx-drawer-x" onClick={onClose} aria-label="Fechar (ESC)">
            <X size={16}/>
            <kbd>esc</kbd>
          </button>
        </header>

        <div className="fx-drawer-body">

          <section className="fx-drawer-sec">
            <h4>Status SEFAZ</h4>
            <div className="fx-drawer-status-row">
              <span className={`fx-sefaz ${sefaz.tone}`} title={sefaz.hint}>
                <span className="code">{nota.cstat || '—'}</span>
                <span className="lbl">{sefaz.label}</span>
              </span>
              {cancel && (
                <span className={`fx-timepill u-${cancel.urgency}`}>
                  <RefreshCw size={10}/>
                  <span className="lbl">cancelar em <b>{cancel.h}h{cancel.m.toString().padStart(2, '0')}</b></span>
                </span>
              )}
              {cce && (
                <span className={`fx-timepill u-${cce.urgency}`}>
                  <FileText size={10}/>
                  <span className="lbl">CC-e em <b>{cce.d}d</b></span>
                </span>
              )}
            </div>
            <p className="fx-drawer-hint">{sefaz.hint}</p>
            {nota.motivo && (
              <div className="fx-drawer-rej">↳ {nota.motivo}</div>
            )}
          </section>

          {/* Mapa SEFAZ guiado — só em rejeitadas com receita cadastrada */}
          <SefazActionCard cstat={nota.cstat}/>

          <section className="fx-drawer-sec">
            <h4>Destinatário</h4>
            <dl className="fx-kv">
              <dt>Nome</dt><dd>{nota.dest}</dd>
              <dt>{nota.cnpj ? 'CNPJ' : nota.cpf ? 'CPF' : 'Documento'}</dt>
              <dd>{formatDoc(nota.cnpj, nota.cpf)}</dd>
              <dt>UF</dt><dd>{nota.uf || '—'}</dd>
              <dt>Itens</dt><dd>{nota.items ?? '—'}</dd>
            </dl>
          </section>

          <section className="fx-drawer-sec">
            <h4>Operação</h4>
            <dl className="fx-kv">
              <dt>Venda</dt>
              <dd>
                {nota.transactionId
                  ? <a className="fx-link" href={`/sells/${nota.transactionId}`}>V-{nota.transactionId}</a>
                  : '—'}
              </dd>
              <dt>Emissão</dt><dd>{nota.when ?? '—'}</dd>
              <dt>Valor</dt><dd className="fx-strong">{brl(nota.value)}</dd>
              <dt>Modelo</dt>
              <dd>{nota.modelo} · {nota.modelo === 65 ? 'consumidor' : 'B2B'}</dd>
            </dl>
          </section>

        </div>

        <footer className="fx-drawer-f">
          <button className="fx-btn ghost" disabled title="PR seguinte">
            <RefreshCw size={12}/> Reconsultar SEFAZ <kbd className="fx-kbd-inline">R</kbd>
          </button>
          <div className="fx-drawer-f-r">
            <button className="fx-btn" disabled title="PR seguinte">XML</button>
            <button className="fx-btn" disabled title="PR seguinte">DANFE</button>
            {nota.status === 'autorizada' && cancel && (
              <button
                className="fx-btn danger"
                onClick={() => setCancelOpen(true)}
                disabled={busy}
                title="Cancela NFe — FSM cascade ADR 0143"
              >
                Cancelar <kbd className="fx-kbd-inline">X</kbd>
              </button>
            )}
            {['rejeitada', 'denegada'].includes(nota.status) && (
              <button className="fx-btn primary" disabled title="Retransmitir em PR seguinte">
                Retransmitir <kbd className="fx-kbd-inline">⏎</kbd>
              </button>
            )}
          </div>
        </footer>

        {/* Modal cancel motivo */}
        {cancelOpen && (
          <div className="fx-drawer-bg" onClick={() => !busy && setCancelOpen(false)}>
            <div
              role="dialog"
              aria-label="Confirmar cancelamento"
              onClick={(e) => e.stopPropagation()}
              style={{
                background: 'white',
                borderRadius: 10,
                padding: 22,
                width: 440,
                maxWidth: '90vw',
                margin: '15vh auto',
                boxShadow: '0 12px 40px rgba(0,0,0,.2)',
              }}
            >
              <h3 style={{ margin: '0 0 8px', fontSize: 16, fontWeight: 700 }}>
                Cancelar {nota.modelo === 65 ? 'NFC-e' : 'NF-e'} {nota.num}
              </h3>
              <p style={{ fontSize: 12.5, color: 'var(--fx-text-dim)', margin: '0 0 14px' }}>
                Justificativa obrigatória (mín. 15 chars · regra CONFAZ).
                Cancelamento aciona FSM cascade ADR 0143 — refund gateway + notif cliente (se biz=1).
              </p>
              <textarea
                value={motivo}
                onChange={(e) => setMotivo(e.target.value)}
                placeholder="Ex: cliente desistiu pós-emissão, refaturado em V-NNNN"
                rows={3}
                disabled={busy}
                autoFocus
                style={{
                  width: '100%',
                  padding: 10,
                  fontSize: 12.5,
                  border: '1px solid var(--fx-border)',
                  borderRadius: 7,
                  fontFamily: 'inherit',
                  resize: 'vertical',
                }}
              />
              <div style={{ fontSize: 11, color: 'var(--fx-text-mute)', margin: '4px 0 14px' }}>
                {motivo.length}/255 · {motivo.trim().length < 15 ? `faltam ${15 - motivo.trim().length} chars` : '✅ ok'}
              </div>
              <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                <button className="fx-btn ghost" onClick={() => setCancelOpen(false)} disabled={busy}>
                  Voltar
                </button>
                <button
                  className="fx-btn danger"
                  onClick={handleCancelar}
                  disabled={busy || motivo.trim().length < 15}
                >
                  {busy ? 'Cancelando…' : 'Confirmar cancelamento'}
                </button>
              </div>
            </div>
          </div>
        )}
      </aside>
    </>
  );
}
