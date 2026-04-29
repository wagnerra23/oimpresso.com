// @memcofre
//   modulo: Copiloto / Chat
//   adrs: 0035, 0036, 0046, 0047, 0053
//   status: implementada (Sprint chat enterprise via assistant-ui)
//
// Integra @assistant-ui/react ao Copiloto. Usa ExternalStoreRuntime pra adaptar
// nosso endpoint SSE Laravel (/copiloto/conversas/{id}/mensagens/stream) ao
// modelo de runtime da lib. Recursos out-of-box:
//   - Streaming token-por-token (já era nosso, agora com UX rica)
//   - Markdown render + code blocks com syntax highlight + botão Copy
//   - Edit user message + Regenerate assistant
//   - Auto-scroll, retry, attachments (futuro)
//   - Stop button (interrupt)
//
// Backend SSE protocol custom (linha JSON):
//   data: {"type":"start","user_message_id":42}
//   data: {"type":"chunk","content":"Olá"}
//   data: {"type":"end","assistant_message_id":43,"chars":120}

import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import {
  AssistantRuntimeProvider,
  useExternalStoreRuntime,
  Thread,
  type AppendMessage,
  type ThreadMessageLike,
} from '@assistant-ui/react';
import { MarkdownTextPrimitive } from '@assistant-ui/react-markdown';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';

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
  /** Renderiza um header customizado acima do Thread (ex: cards de propostas) */
  headerExtra?: ReactNode;
  /** Renderiza extras abaixo do Thread, antes do Composer */
  belowThread?: ReactNode;
}

// ---- Mensagem interna (formato externo do runtime) ---------------------

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
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return meta?.content ?? '';
}

// ---- Runtime Provider --------------------------------------------------

export function CopilotoAssistantUiChat({
  conversaId,
  mensagensIniciais,
  sugestoesIniciais,
  headerExtra,
  belowThread,
}: Props) {
  const [messages, setMessages] = useState<ChatMessage[]>(
    () => mensagensIniciais.map(fmtFromBackend),
  );
  const [isRunning, setIsRunning] = useState(false);
  const abortRef = useRef<AbortController | null>(null);

  // Sync mensagens vindas do backend (Inertia partial reload pós-stream)
  useEffect(() => {
    setMessages(mensagensIniciais.map(fmtFromBackend));
  }, [mensagensIniciais]);

  // Cleanup: aborta stream em andamento se user sair da página/conversa
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

      // 1. Adiciona bolha user otimista
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
                // Atualiza assistant in-place — assistant-ui detecta via re-render
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

  return (
    <AssistantRuntimeProvider runtime={runtime}>
      {headerExtra}
      <div className="flex-1 min-h-0 flex flex-col aui-root-wrapper">
        <Thread
          welcome={{
            message: 'Como posso ajudar com a gestão da sua empresa hoje?',
            suggestions: (sugestoesIniciais ?? [
              'Qual o faturamento de hoje?',
              'Top 5 clientes do mês',
              'Despesas vencendo nos próximos 7 dias',
              'Criar uma meta de faturamento mensal',
            ]).map((s) => ({ prompt: s })),
          }}
          assistantMessage={{
            components: {
              Text: MarkdownTextPrimitive,
            },
          }}
        />
      </div>
      {belowThread}
    </AssistantRuntimeProvider>
  );
}
