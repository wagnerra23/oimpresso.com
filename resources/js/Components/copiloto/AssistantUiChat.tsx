// @memcofre
//   modulo: Copiloto / Chat
//   adrs: 0035, 0036, 0046, 0047, 0053
//   status: implementada (Sprint chat enterprise via assistant-ui v0.10)
//
// Integra @assistant-ui/react ao Copiloto. Usa ExternalStoreRuntime pra adaptar
// nosso endpoint SSE Laravel (/copiloto/conversas/{id}/mensagens/stream) ao
// modelo de runtime da lib. Recursos out-of-box:
//   - Streaming token-por-token (já era nosso, agora com UX rica)
//   - Markdown render + code blocks + botão Copy
//   - Edit user message + Regenerate assistant
//   - Auto-scroll, retry, attachments
//   - Stop button (interrupt)
//
// v0.10 da lib só exporta primitives (não Thread pré-cozido) — então a gente
// compõe nossa própria Thread aqui, com nossas classes Tailwind 4.

import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import {
  AssistantRuntimeProvider,
  useExternalStoreRuntime,
  ThreadPrimitive,
  MessagePrimitive,
  ComposerPrimitive,
  type AppendMessage,
  type ThreadMessageLike,
} from '@assistant-ui/react';
import { MarkdownTextPrimitive } from '@assistant-ui/react-markdown';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { ArrowDown, ArrowUp, Square } from 'lucide-react';

// ---- Tipos do nosso backend Laravel -----------------------------------

interface MensagemBackend {
  id: number;
  role: 'user' | 'assistant' | 'system';
  content: string;
  created_at: string;
}

interface Props {
  conversaId: number;
  mensagensIniciais: MensagemBackend[];
  /** Sugestões de prompt iniciais (quando conversa vazia) */
  sugestoesIniciais?: string[];
  /** Renderiza extras abaixo do Thread, antes do Composer (ex: cards) */
  belowThread?: ReactNode;
}

// ---- Mensagem interna ----------------------------------------------------

type ChatMessage = {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  createdAt: Date;
};

const convertMessage = (m: ChatMessage): ThreadMessageLike => ({
  id: m.id,
  role: m.role,
  content: [{ type: 'text', text: m.content }],
  createdAt: m.createdAt,
});

const newId = () => `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

const fmtFromBackend = (m: MensagemBackend): ChatMessage => ({
  id: String(m.id),
  role: m.role === 'system' ? 'assistant' : m.role,
  content: m.content,
  createdAt: new Date(m.created_at),
});

function getCsrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

// ---- Componentes Compondo Thread ---------------------------------------

const SUGESTOES_DEFAULT = [
  'Qual o faturamento de hoje?',
  'Top 5 clientes do mês',
  'Despesas vencendo nos próximos 7 dias',
  'Criar uma meta de faturamento mensal',
];

function ThreadEmpty({ sugestoes }: { sugestoes: string[] }) {
  return (
    <ThreadPrimitive.Empty>
      <div className="flex flex-1 flex-col items-center justify-center gap-6 px-6 py-12 text-center">
        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
          <span className="text-2xl font-semibold">CP</span>
        </div>
        <div>
          <p className="text-lg font-semibold">Como posso ajudar?</p>
          <p className="text-sm text-muted-foreground mt-1">
            Pergunte sobre faturamento, clientes, despesas, metas — ou inicie com uma sugestão.
          </p>
        </div>
        <div className="grid w-full max-w-xl grid-cols-1 gap-2 sm:grid-cols-2">
          {sugestoes.map((s) => (
            <ThreadPrimitive.Suggestion
              key={s}
              prompt={s}
              method="replace"
              autoSend
              className="rounded-lg border border-border bg-card px-3 py-2.5 text-left text-sm transition hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              {s}
            </ThreadPrimitive.Suggestion>
          ))}
        </div>
      </div>
    </ThreadPrimitive.Empty>
  );
}

function UserMessage() {
  return (
    <MessagePrimitive.Root className="flex justify-end px-4 py-2">
      <div className="max-w-[80%] rounded-2xl rounded-br-sm bg-primary px-4 py-2 text-sm text-primary-foreground whitespace-pre-wrap break-words">
        <MessagePrimitive.Parts />
      </div>
    </MessagePrimitive.Root>
  );
}

function AssistantMessage() {
  return (
    <MessagePrimitive.Root className="flex gap-3 px-4 py-3">
      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
        CP
      </div>
      <div className="flex-1 max-w-[85%] rounded-2xl rounded-bl-sm bg-card border border-border px-4 py-2.5 text-sm">
        <div className="prose prose-sm dark:prose-invert max-w-none break-words leading-relaxed">
          <MessagePrimitive.Parts components={{ Text: MarkdownTextPrimitive }} />
        </div>
      </div>
    </MessagePrimitive.Root>
  );
}

function ScrollToBottomBtn() {
  return (
    <ThreadPrimitive.ScrollToBottom asChild>
      <button
        type="button"
        aria-label="Rolar pro fim"
        className="absolute bottom-24 right-6 z-10 flex h-9 w-9 items-center justify-center rounded-full border border-border bg-background shadow-md transition hover:bg-accent disabled:invisible"
      >
        <ArrowDown className="h-4 w-4" />
      </button>
    </ThreadPrimitive.ScrollToBottom>
  );
}

function Composer() {
  return (
    <ComposerPrimitive.Root className="relative mx-auto flex w-full max-w-3xl items-end gap-2 rounded-2xl border border-border bg-card p-2 shadow-sm focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-1 focus-within:ring-offset-background">
      <ComposerPrimitive.Input
        autoFocus
        rows={1}
        placeholder="Pergunte algo ao Copiloto…"
        className="min-h-[40px] max-h-40 flex-1 resize-none bg-transparent px-2 py-2 text-sm leading-relaxed outline-none placeholder:text-muted-foreground"
      />
      <ThreadPrimitive.If running={false}>
        <ComposerPrimitive.Send asChild>
          <button
            type="submit"
            aria-label="Enviar"
            className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground transition hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed"
          >
            <ArrowUp className="h-4 w-4" />
          </button>
        </ComposerPrimitive.Send>
      </ThreadPrimitive.If>
      <ThreadPrimitive.If running>
        <ComposerPrimitive.Cancel asChild>
          <button
            type="button"
            aria-label="Parar geração"
            className="flex h-9 w-9 items-center justify-center rounded-lg border border-border text-foreground transition hover:bg-accent"
          >
            <Square className="h-3.5 w-3.5" />
          </button>
        </ComposerPrimitive.Cancel>
      </ThreadPrimitive.If>
    </ComposerPrimitive.Root>
  );
}

// ---- Runtime Provider --------------------------------------------------

export function CopilotoAssistantUiChat({
  conversaId,
  mensagensIniciais,
  sugestoesIniciais,
  belowThread,
}: Props) {
  const [messages, setMessages] = useState<ChatMessage[]>(
    () => mensagensIniciais.map(fmtFromBackend),
  );
  const [isRunning, setIsRunning] = useState(false);
  const abortRef = useRef<AbortController | null>(null);

  // Sync mensagens quando vier partial reload (após stream salvo no DB)
  useEffect(() => {
    setMessages(mensagensIniciais.map(fmtFromBackend));
  }, [mensagensIniciais]);

  // Cleanup: aborta stream em andamento se user sair da página
  useEffect(
    () => () => {
      abortRef.current?.abort();
    },
    [],
  );

  const onNew = useCallback(
    async (message: AppendMessage) => {
      const part = message.content[0];
      if (!part || part.type !== 'text') {
        toast.error('Tipo de mensagem não suportado.');
        return;
      }
      const userText = part.text;

      const userMsgId = newId();
      const assistantMsgId = newId();
      setMessages((prev) => [
        ...prev,
        { id: userMsgId, role: 'user', content: userText, createdAt: new Date() },
        { id: assistantMsgId, role: 'assistant', content: '', createdAt: new Date() },
      ]);

      setIsRunning(true);
      abortRef.current?.abort();
      const ctrl = new AbortController();
      abortRef.current = ctrl;

      try {
        const resp = await fetch(`/copiloto/conversas/${conversaId}/mensagens/stream`, {
          method: 'POST',
          signal: ctrl.signal,
          headers: {
            'Content-Type':     'application/json',
            'Accept':           'text/event-stream',
            'X-CSRF-TOKEN':     getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ content: userText }),
        });
        if (!resp.ok || !resp.body) throw new Error(`HTTP ${resp.status}`);

        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });

          let idx: number;
          while ((idx = buffer.indexOf('\n\n')) !== -1) {
            const raw = buffer.slice(0, idx).trim();
            buffer = buffer.slice(idx + 2);
            if (!raw.startsWith('data:')) continue;
            const json = raw.replace(/^data:\s*/, '');
            try {
              const ev = JSON.parse(json);
              if (ev.type === 'chunk' && typeof ev.content === 'string') {
                setMessages((prev) =>
                  prev.map((m) =>
                    m.id === assistantMsgId
                      ? { ...m, content: m.content + ev.content }
                      : m,
                  ),
                );
              } else if (ev.type === 'error') {
                toast.error(ev.message ?? 'Erro no streaming.');
              }
            } catch {
              // chunk parcial, ignora
            }
          }
        }
      } catch (e: any) {
        if (e?.name !== 'AbortError') {
          toast.error('Erro ao enviar mensagem.');
        }
      } finally {
        setIsRunning(false);
        // Inertia partial reload pra trazer IDs reais do DB + sugestoesPendentes
        router.reload({
          only: ['mensagens', 'sugestoesPendentes'],
          preserveScroll: true,
          preserveState: true,
        });
      }
    },
    [conversaId],
  );

  const onCancel = useCallback(async () => {
    abortRef.current?.abort();
    setIsRunning(false);
  }, []);

  const runtime = useExternalStoreRuntime({
    isRunning,
    messages,
    convertMessage,
    onNew,
    onCancel,
  });

  const sugestoes = sugestoesIniciais ?? SUGESTOES_DEFAULT;

  return (
    <AssistantRuntimeProvider runtime={runtime}>
      <ThreadPrimitive.Root className="relative flex h-full flex-1 flex-col bg-background">
        <ThreadPrimitive.Viewport className="flex-1 overflow-y-auto">
          <ThreadEmpty sugestoes={sugestoes} />
          <ThreadPrimitive.Messages
            components={{
              UserMessage,
              AssistantMessage,
            }}
          />
          <div className="h-4" />
        </ThreadPrimitive.Viewport>

        <ScrollToBottomBtn />

        {belowThread}

        <div className="border-t border-border bg-background/80 px-4 py-3 backdrop-blur">
          <Composer />
        </div>
      </ThreadPrimitive.Root>
    </AssistantRuntimeProvider>
  );
}
