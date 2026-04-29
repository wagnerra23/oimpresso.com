// @memcofre
//   tela: /copiloto
//   stories: US-COPI-001, US-COPI-002, US-COPI-003, US-COPI-MEM-007
//   rules: R-COPI-001, R-COPI-MEM-005
//   adrs: 0026, 0031, 0032, 0034, 0035, 0036, 0039 (Cockpit), UI-0008
//   tests: tests/Feature/Modules/Copiloto/AdapterResolverTest, tests/Feature/Modules/Copiloto/BridgeMemoriaChatTest
//   status: implementada (Sprint 1: migrada pra AppShellV2)
//   module: Copiloto

import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';

import AppShellV2 from '@/Layouts/AppShellV2';
import { Composer, Thread, ThreadHeader } from '@/Components/cockpit/Thread';
import {
  AvatarRef,
  BusinessOpt,
  ConversaFoco,
  ConversaResumo,
  Mensagem as CockpitMensagem,
  Rotina,
} from '@/Components/cockpit/shared';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  Card, CardContent, CardFooter, CardHeader, CardTitle,
} from '@/Components/ui/card';

// ── tipos do backend Copiloto ──────────────────────────────────────────

interface MensagemBackend {
  id: number;
  role: 'user' | 'assistant' | 'system';
  content: string;
  created_at: string;
  propostas?: Proposta[];
}

interface Proposta {
  nome: string;
  metrica: string;
  valor_alvo: number;
  periodo: string;
  dificuldade: 'facil' | 'realista' | 'ambicioso';
  racional: string;
  dependencias: string[];
}

interface Sugestao {
  id: number;
  payload_json: Proposta;
}

interface ConversaBackend {
  id: number;
  titulo: string;
  status: string;
  iniciada_em: string;
}

interface Props {
  // Shell props (vindos do shellPropsFor() do controller)
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
  // Props específicos do Copiloto Chat
  conversa: ConversaBackend;
  mensagens: MensagemBackend[];
  sugestoesPendentes?: Sugestao[];
}

// ── helpers ────────────────────────────────────────────────────────────

const DIFICULDADE_CONFIG: Record<string, { label: string; className: string }> = {
  facil:     { label: 'Fácil',     className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' },
  realista:  { label: 'Realista',  className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
  ambicioso: { label: 'Ambicioso', className: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300' },
};

function formatCurrency(value: number) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

// Gradiente do avatar do Copiloto (usado em todas msgs do assistant)
const COPILOTO_AVATAR: AvatarRef = { iniciais: 'CP', gradId: 17 };

// Adaptador: converte Mensagem do backend (role/content) → Mensagem do Cockpit (autor/texto)
function adaptarMensagem(m: MensagemBackend): CockpitMensagem {
  const dt = new Date(m.created_at);
  const hora = dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  // Day separator: usa "Hoje" se a msg é do mesmo dia que agora; senão data dd/mm
  const hoje = new Date();
  const sameDay =
    dt.getFullYear() === hoje.getFullYear() &&
    dt.getMonth() === hoje.getMonth() &&
    dt.getDate() === hoje.getDate();
  const dia = sameDay
    ? 'Hoje'
    : dt.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });

  if (m.role === 'user') {
    return {
      id: m.id,
      autor: 'me',
      texto: m.content,
      hora,
      dia,
      lida: true,
    };
  }
  // assistant ou system → them, com avatar do Copiloto
  return {
    id: m.id,
    autor: 'them',
    texto: m.content,
    hora,
    dia,
    whoAvatar: COPILOTO_AVATAR,
    whoNome: 'Copiloto',
  };
}

// ── PropostaCard (componente especifico do Copiloto, mantido inline) ───

function PropostaCard({ sugestao }: { sugestao: Sugestao }) {
  const p = sugestao.payload_json;
  const dif = DIFICULDADE_CONFIG[p.dificuldade] ?? DIFICULDADE_CONFIG['realista']!;

  function escolher() {
    router.post(`/copiloto/sugestoes/${sugestao.id}/escolher`, {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.success('Meta criada com sucesso!'),
      onError: () => toast.error('Erro ao escolher meta.'),
    });
  }

  function rejeitar() {
    router.post(`/copiloto/sugestoes/${sugestao.id}/rejeitar`, {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.info('Proposta rejeitada.'),
    });
  }

  return (
    <Card className="flex flex-col gap-2 bg-card">
      <CardHeader className="pb-2">
        <div className="flex items-start justify-between gap-2">
          <CardTitle className="text-base leading-tight">{p.nome}</CardTitle>
          <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${dif.className}`}>
            {dif.label}
          </span>
        </div>
        <div className="flex flex-wrap gap-1 pt-1">
          <Badge variant="outline">{p.metrica}</Badge>
          <Badge variant="outline">{p.periodo}</Badge>
          <Badge variant="secondary">{formatCurrency(p.valor_alvo)}</Badge>
        </div>
      </CardHeader>
      <CardContent className="pb-2">
        <p className="line-clamp-3 text-sm text-muted-foreground">{p.racional}</p>
        {p.dependencias.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-1">
            {p.dependencias.map((dep, i) => (
              <span key={i} className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                {dep}
              </span>
            ))}
          </div>
        )}
      </CardContent>
      <CardFooter className="flex gap-2 pt-0">
        <Button
          size="sm"
          className="flex-1"
          onClick={escolher}
          aria-label={`Escolher meta ${p.nome}, dificuldade ${p.dificuldade}`}
        >
          Escolher esta meta
        </Button>
        <Button size="sm" variant="outline" onClick={rejeitar} aria-label="Rejeitar proposta">
          Rejeitar
        </Button>
      </CardFooter>
    </Card>
  );
}

// ── pagina ──────────────────────────────────────────────────────────────

export default function Chat({
  businessNome,
  businesses,
  usuarioNome,
  usuarioNomeCurto,
  usuarioEmail,
  usuarioCargo,
  usuarioIniciais,
  conversas,
  conversa,
  mensagens,
  sugestoesPendentes = [],
}: Props) {
  // streaming: optimistic UI + SSE — UX Claude-style (token por token)
  const [enviando, setEnviando] = useState(false);
  const [streamingTexto, setStreamingTexto] = useState('');           // chunks acumulados
  const [optimisticUserMsg, setOptimisticUserMsg] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  // Hora "agora" formatada pra mensagens otimistas (re-calculada a cada render).
  const agoraHora = useMemo(
    () => new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
    [streamingTexto, optimisticUserMsg],
  );

  // CSRF token pra fetch (Inertia injeta via meta)
  function getCsrfToken(): string {
    const m = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
    return m?.content ?? '';
  }

  // Adapta mensagens backend → formato Cockpit + injeta bolhas otimistas
  const mensagensCockpit = useMemo(() => {
    const reais = mensagens.map(adaptarMensagem);

    // Enquanto streaming: adiciona bolha "user" otimista (se ainda não voltou
    // do backend) + bolha "assistant" parcial com o texto acumulado.
    const extras: CockpitMensagem[] = [];

    if (optimisticUserMsg !== null) {
      extras.push({
        id: -1,
        autor: 'me',
        texto: optimisticUserMsg,
        hora: agoraHora,
        dia: 'Hoje',
        lida: true,
      });
    }
    if (streamingTexto !== '') {
      extras.push({
        id: -2,
        autor: 'them',
        texto: streamingTexto,
        hora: agoraHora,
        dia: 'Hoje',
        whoAvatar: COPILOTO_AVATAR,
        whoNome: 'Copiloto',
      });
    }
    return [...reais, ...extras];
  }, [mensagens, optimisticUserMsg, streamingTexto, agoraHora]);

  // ConversaFoco da conversa atual (fora do mock — vem do backend real agora)
  const conversaFoco: ConversaFoco = useMemo(() => ({
    id: String(conversa.id),
    titulo: conversa.titulo,
    tipo: 'copiloto',
    online: true,
    avatar: COPILOTO_AVATAR,
    mensagens: mensagensCockpit,
  }), [conversa, mensagensCockpit]);

  // Cleanup: abortar stream se user sair da página
  useEffect(() => () => {
    abortRef.current?.abort();
  }, []);

  /**
   * Streaming SSE — UX Claude-style. Lê o ReadableStream chunk-by-chunk e
   * atualiza streamingTexto pra renderização token-por-token. Ao fim do
   * stream, reload Inertia das mensagens reais (com IDs do DB) e limpa a
   * bolha otimista.
   */
  async function handleSend(texto: string) {
    if (!texto.trim() || enviando) return;

    setEnviando(true);
    setOptimisticUserMsg(texto);
    setStreamingTexto('');

    // AbortController pra suportar Stop button + cleanup
    abortRef.current?.abort();
    const ctrl = new AbortController();
    abortRef.current = ctrl;

    try {
      const resp = await fetch(`/copiloto/conversas/${conversa.id}/mensagens/stream`, {
        method: 'POST',
        signal: ctrl.signal,
        headers: {
          'Content-Type':     'application/json',
          'Accept':           'text/event-stream',
          'X-CSRF-TOKEN':     getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ content: texto }),
      });

      if (!resp.ok || !resp.body) {
        throw new Error(`HTTP ${resp.status}`);
      }

      const reader  = resp.body.getReader();
      const decoder = new TextDecoder();
      let buffer    = '';

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        // SSE: cada evento separado por linha em branco; data: <json>
        let idx: number;
        while ((idx = buffer.indexOf('\n\n')) !== -1) {
          const raw = buffer.slice(0, idx).trim();
          buffer = buffer.slice(idx + 2);
          if (!raw.startsWith('data:')) continue;

          const json = raw.replace(/^data:\s*/, '');
          try {
            const ev = JSON.parse(json);
            if (ev.type === 'chunk' && typeof ev.content === 'string') {
              setStreamingTexto((t) => t + ev.content);
            } else if (ev.type === 'error') {
              toast.error(ev.message ?? 'Erro no streaming.');
            }
            // 'start' e 'end' são informativos — não atualizamos UI direto deles.
          } catch {
            // chunk parcial, ignora — vai juntar com o próximo decode()
          }
        }
      }
    } catch (e: any) {
      if (e?.name !== 'AbortError') {
        toast.error('Erro ao enviar mensagem.');
      }
    } finally {
      setEnviando(false);
      // Recarrega só as mensagens reais — limpa otimismo
      router.reload({
        only: ['mensagens', 'sugestoesPendentes'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
          setOptimisticUserMsg(null);
          setStreamingTexto('');
        },
      });
    }
  }

  /** Cancela streaming em andamento (UX Claude-style stop button). */
  function handleStop() {
    abortRef.current?.abort();
  }

  function selectConv(id: string) {
    router.get(`/copiloto/conversas/${id}`, {}, {
      preserveScroll: true,
      preserveState: true,
    });
  }

  return (
    <AppShellV2
      title="Copiloto · Chat"
      business={{ nome: businessNome, opcoes: businesses }}
      user={{
        nome: usuarioNome,
        nomeCurto: usuarioNomeCurto,
        email: usuarioEmail,
        cargo: usuarioCargo,
        iniciais: usuarioIniciais,
      }}
      conversas={conversas}
      conversaFoco={conversaFoco}
      activeConvId={String(conversa.id)}
      onSelectConv={selectConv}
    >
      <Head title="Copiloto · Chat" />

      <ThreadHeader conv={conversaFoco} />

      {/* typing dots aparece somente enquanto enviando E ainda não chegou
          o primeiro chunk (depois disso a bolha streamingTexto já mostra texto). */}
      <Thread
        mensagens={mensagensCockpit}
        typing={enviando && streamingTexto === ''}
        typingAvatar={COPILOTO_AVATAR}
      />

      {/* Cards de propostas pendentes — específico Copiloto, não estão no Components/cockpit */}
      {sugestoesPendentes.length > 0 && (
        <div className="px-5 pb-3 space-y-2">
          <p className="text-xs font-medium uppercase tracking-wide" style={{ color: 'var(--text-mute)' }}>
            Propostas de metas
          </p>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            {sugestoesPendentes.map((s) => (
              <PropostaCard key={s.id} sugestao={s} />
            ))}
          </div>
        </div>
      )}

      {/* Stop button — UX Claude-style: cancela streaming em andamento */}
      {enviando && (
        <div className="px-5 pb-2 flex justify-center">
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={handleStop}
            className="gap-2 text-xs"
            aria-label="Parar geração da resposta"
          >
            <span className="inline-block w-2 h-2 rounded-sm bg-current" />
            Parar resposta
          </Button>
        </div>
      )}

      <Composer onSend={handleSend} conv={conversaFoco} />
    </AppShellV2>
  );
}
