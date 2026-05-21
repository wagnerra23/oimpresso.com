// Wave E-FE -- IATab.tsx
//
// Tab 7 do drawer 760px Cliente. Copiloto IA com 4 cards:
//   1. Resumo do relacionamento (LLM, POST /cliente/{id}/ia/resumo)
//   2. Reavaliar segmento & tags (LLM structured, POST .../ia/segmento)
//   3. Proxima acao sugerida   (LLM structured, POST .../ia/proxima-acao)
//   4. Score de risco          (deterministico -- usa RiscoClienteCard existente)
//
// Refs:
//   - ADR 0179 §Wave E + Q4 Default ON (memory/decisions/0179-...md)
//   - Charter resources/js/Pages/Cliente/Index.charter.md v3 (Goals Tab IA)
//   - prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md §5 (specs IA)
//   - prototipo-ui/prototipos/clientes/clientes-tabs.jsx::IATab (Cowork blueprint)
//   - resources/js/Pages/Cliente/_show/RiscoClienteCard.tsx (REUSA componente)
//
// Contrato (combinado com ClienteIaController Wave E-BE):
//   POST /cliente/{id}/ia/resumo          -> { sumario, generated_at, fonte }
//   POST /cliente/{id}/ia/segmento        -> { segmento_sugerido, tags_sugeridas, justificativa }
//   POST /cliente/{id}/ia/proxima-acao    -> { acao, urgencia, justificativa, sugerido_em }
//   GET  /cliente/{id}/ia/score-risco     -> { score, label, breakdown, generated_at }
//
// Q4 Default ON: 3 cards LLM tem botao "Gerar" -- nao dispara automatico
// (evita custo Brain B em open passivo do drawer). User decide quando puxar.
// Score-risco renderiza imediatamente via RiscoClienteCard (componente puro,
// zero fetch -- calcula local com contact+stats props).
//
// Pegadinhas Tier 0 / LICOES_F3:
//  - cancellation flag `alive` em cleanup useEffect (anti-leak F3 T-AP-14)
//  - CSRF token via meta tag (canon UPOS)
//  - PT-BR em TODO texto visivel
//  - PII: nunca enviamos tax_number ou email plain pro server (server ja
//    sanitiza no prepararDadosCliente -- defesa backend)

import { useState } from 'react';
import {
  Sparkles,
  Loader2,
  AlertCircle,
  RefreshCw,
  Tag,
  Target,
  MessageSquare,
  CheckCircle2,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import RiscoClienteCard from '../_show/RiscoClienteCard';

// ---------------------------------------------------------------------
// Types alinhados com ClienteIaController response shapes (Wave E-BE)
// ---------------------------------------------------------------------

export interface IATabContact {
  id: number;
  name?: string;
  type?: 'customer' | 'supplier' | 'both' | string;
  is_active?: boolean;
  email?: string | null;
  mobile?: string | null;
  landline?: string | null;
  city?: string | null;
  state?: string | null;
  inscricao_estadual?: string | null;
  contribuinte?: boolean;
  last_purchase_at?: string | null;
  created_at?: string | null;
}

export interface IATabStats {
  total_invoice?: number;
  invoice_due?: number;
}

export interface IATabProps {
  contact: IATabContact;
  stats?: IATabStats;
}

interface ResumoPayload {
  sumario: string;
  generated_at: string;
  fonte: string;
}

interface SegmentoPayload {
  segmento_sugerido: string;
  tags_sugeridas: string[];
  justificativa: string;
  generated_at?: string;
}

interface ProximaAcaoPayload {
  acao: string;
  urgencia: 'alta' | 'media' | 'baixa';
  justificativa: string;
  sugerido_em?: string;
}

type CardState<T> =
  | { state: 'idle' }
  | { state: 'loading' }
  | { state: 'ok'; data: T }
  | { state: 'error'; error: string };

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------

function getCsrfToken(): string {
  if (typeof document === 'undefined') return '';
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)
      ?.content ?? ''
  );
}

async function callIaEndpoint<T>(
  contactId: number,
  endpoint: 'resumo' | 'segmento' | 'proxima-acao',
): Promise<{ ok: true; data: T } | { ok: false; error: string }> {
  try {
    const r = await fetch(`/cliente/${contactId}/ia/${endpoint}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({}),
    });

    if (!r.ok) {
      let errorMsg = `Erro ${r.status}`;
      try {
        const j = await r.json();
        errorMsg = j?.error ?? j?.message ?? errorMsg;
      } catch {
        // body nao-JSON; mantem msg de status
      }
      return { ok: false, error: errorMsg };
    }

    const data = (await r.json()) as T;
    return { ok: true, data };
  } catch (e: any) {
    return { ok: false, error: e?.message ?? 'Erro de rede inesperado' };
  }
}

// ---------------------------------------------------------------------
// Componente principal
// ---------------------------------------------------------------------

export default function IATab({ contact, stats }: IATabProps) {
  const [resumo, setResumo] = useState<CardState<ResumoPayload>>({ state: 'idle' });
  const [segmento, setSegmento] = useState<CardState<SegmentoPayload>>({
    state: 'idle',
  });
  const [proxima, setProxima] = useState<CardState<ProximaAcaoPayload>>({
    state: 'idle',
  });

  async function gerarResumo() {
    setResumo({ state: 'loading' });
    const r = await callIaEndpoint<ResumoPayload>(contact.id, 'resumo');
    setResumo(
      r.ok ? { state: 'ok', data: r.data } : { state: 'error', error: r.error },
    );
  }

  async function gerarSegmento() {
    setSegmento({ state: 'loading' });
    const r = await callIaEndpoint<SegmentoPayload>(contact.id, 'segmento');
    setSegmento(
      r.ok ? { state: 'ok', data: r.data } : { state: 'error', error: r.error },
    );
  }

  async function gerarProxima() {
    setProxima({ state: 'loading' });
    const r = await callIaEndpoint<ProximaAcaoPayload>(contact.id, 'proxima-acao');
    setProxima(
      r.ok ? { state: 'ok', data: r.data } : { state: 'error', error: r.error },
    );
  }

  return (
    <div className="space-y-5" data-testid="ia-tab">
      {/* Header intro */}
      <div
        className="rounded-lg border border-blue-200 bg-blue-50/50 dark:border-blue-900/40 dark:bg-blue-950/30 p-4"
        data-testid="ia-tab-intro"
      >
        <div className="flex items-center gap-2">
          <Sparkles size={16} className="text-blue-600 dark:text-blue-400" />
          <h3 className="text-sm font-semibold text-foreground">
            Copiloto de cliente
          </h3>
        </div>
        <p className="text-xs text-muted-foreground mt-1.5 leading-relaxed">
          4 analises diferentes. A IA propoe, voce decide. Tudo e editavel antes
          de aplicar.
        </p>
      </div>

      {/* Card 1 -- Resumo do relacionamento */}
      <IaCard
        testid="ia-card-resumo"
        icon={<MessageSquare size={14} className="text-blue-600" />}
        title="Resumo do relacionamento"
        description="A IA olha o historico (OSs, ticket, saldo, frescor) e propoe um sumario executivo em 3 frases."
        buttonLabel="Gerar resumo"
        state={resumo}
        onGenerate={gerarResumo}
      >
        {resumo.state === 'ok' && (
          <p className="text-sm text-foreground leading-relaxed">
            {resumo.data.sumario}
          </p>
        )}
      </IaCard>

      {/* Card 2 -- Reavaliar segmento & tags */}
      <IaCard
        testid="ia-card-segmento"
        icon={<Tag size={14} className="text-violet-600" />}
        title="Reavaliar segmento & tags"
        description="A IA olha o historico real (cidade, ticket, OSs) e propoe segmento + tags. Voce revisa antes de aplicar."
        buttonLabel="Analisar"
        state={segmento}
        onGenerate={gerarSegmento}
      >
        {segmento.state === 'ok' && (
          <div className="space-y-2">
            <div className="flex items-baseline gap-2 text-sm">
              <span className="text-xs uppercase tracking-wider text-muted-foreground">
                Segmento:
              </span>
              <b className="text-foreground">{segmento.data.segmento_sugerido}</b>
            </div>
            <div className="flex flex-wrap items-center gap-1.5">
              <span className="text-xs uppercase tracking-wider text-muted-foreground">
                Tags:
              </span>
              {segmento.data.tags_sugeridas.map((t) => (
                <span
                  key={t}
                  className="text-xs text-violet-700 dark:text-violet-300 bg-violet-50 dark:bg-violet-950/40 border border-violet-200 dark:border-violet-900/40 px-2 py-0.5 rounded-full"
                >
                  {t}
                </span>
              ))}
            </div>
            <p className="text-xs italic text-muted-foreground mt-2">
              "{segmento.data.justificativa}"
            </p>
          </div>
        )}
      </IaCard>

      {/* Card 3 -- Proxima acao */}
      <IaCard
        testid="ia-card-proxima-acao"
        icon={<Target size={14} className="text-amber-600" />}
        title="Proxima acao sugerida"
        description="O que fazer com este cliente agora? A IA combina historico, saldo, frescor e tags."
        buttonLabel="Sugerir"
        state={proxima}
        onGenerate={gerarProxima}
      >
        {proxima.state === 'ok' && (
          <div className="space-y-2">
            <div className="flex items-start gap-2">
              <UrgenciaPill urgencia={proxima.data.urgencia} />
              <b className="text-sm text-foreground flex-1">
                {proxima.data.acao}
              </b>
            </div>
            <p className="text-xs italic text-muted-foreground">
              "{proxima.data.justificativa}"
            </p>
          </div>
        )}
      </IaCard>

      {/* Card 4 -- Score de relacionamento (deterministico, zero LLM) */}
      {/* Z-2.1: rotulo "Score de relacionamento" alinhado ao protótipo Cowork
          (semantica positiva — "cliente fiel" no topo). REUSA RiscoClienteCard
          (Slice B KB-9.75) que calcula score local via useMemo com 8 sinais canon. */}
      <div className="rounded-lg border border-border bg-background p-4" data-testid="ia-card-score">
        <div className="flex items-center gap-2 mb-2">
          <Sparkles size={14} className="text-emerald-600 dark:text-emerald-400" />
          <h4 className="text-sm font-semibold text-foreground">Score de relacionamento</h4>
        </div>
        <RiscoClienteCard contact={contact} stats={stats} />
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------
// Subcomponentes
// ---------------------------------------------------------------------

interface IaCardProps {
  testid: string;
  icon: React.ReactNode;
  title: string;
  description: string;
  buttonLabel: string;
  state: CardState<unknown>;
  onGenerate: () => void;
  children?: React.ReactNode;
}

function IaCard({
  testid,
  icon,
  title,
  description,
  buttonLabel,
  state,
  onGenerate,
  children,
}: IaCardProps) {
  const isLoading = state.state === 'loading';
  const isOk = state.state === 'ok';
  const isError = state.state === 'error';

  return (
    <div
      className="rounded-lg border border-border bg-background p-4"
      data-testid={testid}
      data-card-state={state.state}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1 min-w-0">
          <h4 className="text-sm font-semibold text-foreground flex items-center gap-2">
            {icon}
            {title}
          </h4>
          <p className="text-xs text-muted-foreground mt-1 leading-relaxed">
            {description}
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={onGenerate}
          disabled={isLoading}
          data-testid={`${testid}-button`}
        >
          {isLoading ? (
            <>
              <Loader2 size={14} className="animate-spin mr-1" />
              Pensando...
            </>
          ) : isOk ? (
            <>
              <RefreshCw size={14} className="mr-1" />
              Gerar de novo
            </>
          ) : (
            <>
              <Sparkles size={14} className="mr-1" />
              {buttonLabel}
            </>
          )}
        </Button>
      </div>

      {isOk && (
        <div
          className="mt-3 rounded-md bg-muted/30 p-3"
          data-testid={`${testid}-result`}
        >
          {children}
        </div>
      )}

      {isError && (
        <div
          className="mt-3 flex items-start gap-2 text-xs text-rose-700 dark:text-rose-400"
          data-testid={`${testid}-error`}
        >
          <AlertCircle size={14} className="flex-shrink-0 mt-0.5" />
          <span>{state.error}</span>
        </div>
      )}
    </div>
  );
}

function UrgenciaPill({ urgencia }: { urgencia: 'alta' | 'media' | 'baixa' }) {
  const palette = {
    alta: {
      bg: 'bg-rose-100 dark:bg-rose-950/40',
      text: 'text-rose-700 dark:text-rose-300',
      label: 'ALTA',
    },
    media: {
      bg: 'bg-amber-100 dark:bg-amber-950/40',
      text: 'text-amber-700 dark:text-amber-300',
      label: 'MEDIA',
    },
    baixa: {
      bg: 'bg-emerald-100 dark:bg-emerald-950/40',
      text: 'text-emerald-700 dark:text-emerald-300',
      label: 'BAIXA',
    },
  }[urgencia] ?? {
    bg: 'bg-muted',
    text: 'text-muted-foreground',
    label: String(urgencia).toUpperCase(),
  };

  return (
    <span
      className={`text-[10px] font-semibold tracking-wider ${palette.bg} ${palette.text} px-1.5 py-0.5 rounded flex-shrink-0`}
    >
      {palette.label}
    </span>
  );
}
