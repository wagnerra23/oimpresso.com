// SaleAiPanel — Cowork KB-9.75 Onda 2 R2 IA (drawer ✦ IA).
// Refs:
//  - prototipo-ui/prototipos/sells-index/vendas-ai.jsx (canonical source)
//  - SellController::aiAsk (POST /sells/{id}/ai-ask)
//  - resources/css/sells-cowork-ia.css (.sells-cowork-ia-panel scope)
//  - memory/requisitos/_DesignSystem/RUNBOOK-onda-cowork.md F4
//
// 3 modos: summary (✦ Resumir pedido) · history (📜 Histórico cliente) ·
// suggest (🎯 Sugerir próxima venda). Cada modo dispara fetch independente,
// estado local (idle|loading|done|error). Resposta suggest parseada em
// PRODUTO/PREÇO/PORQUE com card visual.
//
// Backend stub (is_stub=true) nesta Onda; Onda 2.5 integra Jana real.

import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Sparkles, RotateCw } from 'lucide-react';

type AiMode = 'summary' | 'history' | 'suggest';
type BlockState = 'idle' | 'loading' | 'done' | 'error';

interface AiBlockData {
  state: BlockState;
  text: string;
  error: string | null;
  latency_ms: number | null;
  is_stub: boolean | null;
}

interface ParsedSuggest {
  produto: string;
  preco: string;
  porque: string;
}

function emptyBlock(): AiBlockData {
  return { state: 'idle', text: '', error: null, latency_ms: null, is_stub: null };
}

function parseSuggest(raw: string): ParsedSuggest | null {
  const m = raw.match(/PRODUTO:\s*(.*?)\n\s*PRE[ÇC]O:\s*(.*?)\n\s*PORQUE:\s*([\s\S]+)/i);
  if (!m || m.length < 4) return null;
  return {
    produto: (m[1] ?? '').trim(),
    preco: (m[2] ?? '').trim(),
    porque: (m[3] ?? '').trim(),
  };
}

interface Props {
  saleId: number;
  enabled: boolean; // false quando tab IA não está ativa — evita fetch antecipado
}

export default function SaleAiPanel({ saleId, enabled }: Props): ReactNode {
  const [summary, setSummary] = useState<AiBlockData>(emptyBlock());
  const [history, setHistory] = useState<AiBlockData>(emptyBlock());
  const [suggest, setSuggest] = useState<AiBlockData>(emptyBlock());

  // Reset quando muda venda OU painel é desligado.
  useEffect(() => {
    setSummary(emptyBlock());
    setHistory(emptyBlock());
    setSuggest(emptyBlock());
  }, [saleId]);

  const ask = useCallback(
    async (mode: AiMode) => {
      if (!enabled) return;
      const setter =
        mode === 'summary' ? setSummary : mode === 'history' ? setHistory : setSuggest;
      setter({ state: 'loading', text: '', error: null, latency_ms: null, is_stub: null });
      try {
        const csrf =
          (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ??
          '';
        const res = await fetch(`/sells/${saleId}/ai-ask`, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
          credentials: 'same-origin',
          body: JSON.stringify({ mode }),
        });
        if (!res.ok) {
          const errText = res.status === 422 ? 'Modo inválido.' : `Erro ${res.status}.`;
          setter({
            state: 'error',
            text: '',
            error: errText,
            latency_ms: null,
            is_stub: null,
          });
          return;
        }
        const json = await res.json();
        setter({
          state: 'done',
          text: (json.text as string) ?? '',
          error: null,
          latency_ms: typeof json.latency_ms === 'number' ? json.latency_ms : null,
          is_stub: json.is_stub === true,
        });
      } catch (e) {
        setter({
          state: 'error',
          text: '',
          error: e instanceof Error ? e.message : 'Erro de rede.',
          latency_ms: null,
          is_stub: null,
        });
      }
    },
    [saleId, enabled]
  );

  return (
    <div className="sells-cowork-ia-panel vd-ai-panel">
      <div className="vd-ai-banner">
        <span className="vd-ai-banner-ic">✦</span>
        <div>
          <b>IA da venda</b>
          <small>
            3 perguntas curadas sobre esta venda — resumir pedido, contar o histórico do cliente,
            ou sugerir próximo produto pra oferta cruzada.
          </small>
        </div>
      </div>

      <h3>Resumir pedido</h3>
      <AiBlock
        icon={<Sparkles size={14} />}
        title="✦ Resumo em 2 frases"
        subtitle="Pra colega pegar no meio do dia sem ler tudo."
        data={summary}
        onAsk={() => ask('summary')}
        ctaLabel="Resumir"
      />

      <h3>Histórico do cliente</h3>
      <AiBlock
        icon={<Sparkles size={14} />}
        title="📜 Padrão de compra"
        subtitle="Número de vendas anteriores · soma · nível de relacionamento."
        data={history}
        onAsk={() => ask('history')}
        ctaLabel="Buscar histórico"
      />

      <h3>Sugerir próxima</h3>
      <AiBlock
        icon={<Sparkles size={14} />}
        title="🎯 Oferta cruzada"
        subtitle="Produto complementar baseado no que cliente já comprou."
        data={suggest}
        onAsk={() => ask('suggest')}
        ctaLabel="Sugerir"
        renderOutput={(text) => {
          const parsed = parseSuggest(text);
          if (!parsed) return null;
          return (
            <div className="vd-ai-suggest" style={{ marginTop: 10 }}>
              <div className="vd-ai-suggest-h">
                <span className="vd-ai-suggest-ic">🎯</span>
                <div>
                  <b>{parsed.produto}</b>
                  <small>{parsed.preco}</small>
                </div>
              </div>
              <p>{parsed.porque}</p>
            </div>
          );
        }}
      />

      <small
        style={{
          display: 'block',
          marginTop: 12,
          color: 'var(--text-mute)',
          fontSize: 10.5,
          borderTop: '1px solid var(--border-2)',
          paddingTop: 8,
        }}
      >
        Respostas geradas por IA — pode ter alucinações. Sempre confirme com a venda real. (Onda 2:
        respostas deterministicas; Onda 2.5 integrará Jana Copiloto.)
      </small>
    </div>
  );
}

function AiBlock({
  icon,
  title,
  subtitle,
  data,
  onAsk,
  ctaLabel,
  renderOutput,
}: {
  icon: ReactNode;
  title: string;
  subtitle: string;
  data: AiBlockData;
  onAsk: () => void;
  ctaLabel: string;
  renderOutput?: (text: string) => ReactNode;
}): ReactNode {
  return (
    <div className="vd-ai-block">
      <div className="vd-ai-block-h">
        <span className="vd-ai-ic">{icon}</span>
        <div className="vd-ai-block-tx">
          <b>{title}</b>
          <small>{subtitle}</small>
        </div>
        {data.state === 'idle' && (
          <button type="button" className="vd-ai-cta" onClick={onAsk}>
            {ctaLabel}
          </button>
        )}
        {data.state === 'loading' && (
          <span className="vd-ai-loader" aria-label="carregando">
            <span />
            <span />
            <span />
          </span>
        )}
        {(data.state === 'done' || data.state === 'error') && (
          <button
            type="button"
            className="vd-ai-retry"
            onClick={onAsk}
            title="Re-perguntar"
            aria-label="Re-perguntar"
          >
            <RotateCw size={12} />
          </button>
        )}
      </div>

      {data.state === 'loading' && (
        <div className="vd-ai-skel" aria-hidden="true">
          <div className="vd-ai-skel-line" style={{ width: '90%' }} />
          <div className="vd-ai-skel-line" style={{ width: '75%' }} />
          <div className="vd-ai-skel-line" style={{ width: '85%' }} />
        </div>
      )}
      {data.state === 'error' && data.error && <div className="vd-ai-out err">{data.error}</div>}
      {data.state === 'done' && data.text && (
        <>
          {renderOutput ? renderOutput(data.text) : <div className="vd-ai-out">{data.text}</div>}
          {data.latency_ms != null && (
            <small
              style={{
                display: 'block',
                marginTop: 4,
                fontSize: 10,
                color: 'var(--text-mute)',
                textAlign: 'right',
              }}
            >
              {data.latency_ms}ms{data.is_stub ? ' · stub' : ''}
            </small>
          )}
        </>
      )}
    </div>
  );
}
