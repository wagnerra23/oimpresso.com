// @docvault
//   tela: /docs/chat
//   module: DocVault
//   status: implementada
//   stories: US-DOCVAULT-005
//   rules: R-DOCVAULT-005
//   adrs: 0003, tech/0001, ui/0001
//   tests: Modules/DocVault/Tests/Feature/ChatTest

import AppShell from '@/Layouts/AppShell';
import { Link, router } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import {
  ArrowLeft,
  Bot,
  MessageSquare,
  Plus,
  Send,
  Sparkles,
  User as UserIcon,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Textarea } from '@/Components/ui/textarea';

interface Source {
  module: string;
  source: string;
}

interface Message {
  id: number;
  role: 'user' | 'assistant' | 'system';
  content: string;
  module_context: string | null;
  sources: Source[] | null;
  mode: 'offline' | 'ai';
  created_at: string;
}

interface RecentSession {
  session_id: string;
  last_at: string;
  msg_count: number;
  preview: string;
}

interface Props {
  session_id: string;
  history: Message[];
  recent: RecentSession[];
  modules: string[];
  ai_enabled: boolean;
}

export default function DocVaultChat({ session_id, history, recent, modules, ai_enabled }: Props) {
  const [messages, setMessages] = useState<Message[]>(history);
  const [input, setInput] = useState('');
  const [moduleCtx, setModuleCtx] = useState<string>('');
  const [sending, setSending] = useState(false);
  const bottomRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    const question = input.trim();
    if (!question || sending) return;

    setSending(true);
    setInput('');

    // Otimista: mostra mensagem do usuário imediatamente
    const userMsg: Message = {
      id: Date.now(),
      role: 'user',
      content: question,
      module_context: moduleCtx || null,
      sources: null,
      mode: 'offline',
      created_at: new Date().toTimeString().slice(0, 5),
    };
    setMessages((prev) => [...prev, userMsg]);

    try {
      const res = await fetch('/docs/chat/ask', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          session_id,
          question,
          module_context: moduleCtx || null,
        }),
      });
      const data = await res.json();
      if (data.reply) {
        setMessages((prev) => [...prev, data.reply]);
      }
    } catch (err) {
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now() + 1,
          role: 'system',
          content: 'Erro ao contactar o assistente. Tente novamente.',
          module_context: null,
          sources: null,
          mode: 'offline',
          created_at: new Date().toTimeString().slice(0, 5),
        },
      ]);
    } finally {
      setSending(false);
    }
  };

  const newSession = async () => {
    const res = await fetch('/docs/chat/new', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
        'Accept': 'application/json',
      },
    });
    const data = await res.json();
    router.visit(`/docs/chat?session=${data.session_id}`);
  };

  return (
    <AppShell
      title="DocVault — Chat"
      breadcrumb={[
        { label: 'DocVault', href: '/docs' },
        { label: 'Chat' },
      ]}
    >
      <div className="mx-auto max-w-7xl p-6">
        <header className="flex items-start justify-between gap-3 mb-4">
          <div className="min-w-0">
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <Sparkles size={22} /> Chat
              <Badge variant={ai_enabled ? 'default' : 'outline'} className="text-[10px]">
                {ai_enabled ? 'IA ativa' : 'modo offline'}
              </Badge>
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Converse com o conhecimento consolidado no DocVault — specs, arquiteturas, ADRs e changelogs.
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" asChild>
              <Link href="/docs">
                <ArrowLeft size={14} className="mr-1.5" /> Voltar
              </Link>
            </Button>
            <Button size="sm" onClick={newSession}>
              <Plus size={14} className="mr-1.5" /> Nova conversa
            </Button>
          </div>
        </header>

        <div className="grid md:grid-cols-[260px_1fr] gap-4">
          {/* Sidebar de sessões */}
          <div className="space-y-3">
            <Card>
              <CardContent className="p-3">
                <div className="text-xs text-muted-foreground mb-2">Escopo</div>
                <select
                  value={moduleCtx}
                  onChange={(e) => setModuleCtx(e.target.value)}
                  className="w-full border border-border rounded px-2 py-1.5 text-sm bg-background"
                >
                  <option value="">Todos os módulos</option>
                  {modules.map((m) => (
                    <option key={m} value={m}>{m}</option>
                  ))}
                </select>
                <div className="mt-2 text-[10px] text-muted-foreground">
                  Limita a busca a um módulo específico — deixe vazio pra procurar em tudo.
                </div>
              </CardContent>
            </Card>

            {recent.length > 0 && (
              <Card>
                <CardContent className="p-0">
                  <div className="px-3 py-2 border-b border-border text-xs text-muted-foreground flex items-center gap-1">
                    <MessageSquare size={12} /> Conversas recentes
                  </div>
                  <ul className="divide-y divide-border max-h-96 overflow-y-auto">
                    {recent.map((r) => (
                      <li key={r.session_id}>
                        <Link
                          href={`/docs/chat?session=${r.session_id}`}
                          className={`block p-2 text-xs hover:bg-accent/30 ${r.session_id === session_id ? 'bg-accent/40' : ''}`}
                        >
                          <div className="font-medium truncate">{r.preview}</div>
                          <div className="text-[10px] text-muted-foreground flex justify-between mt-0.5">
                            <span>{r.msg_count} msgs</span>
                          </div>
                        </Link>
                      </li>
                    ))}
                  </ul>
                </CardContent>
              </Card>
            )}
          </div>

          {/* Área principal do chat */}
          <Card className="flex flex-col" style={{ minHeight: '70vh', maxHeight: '85vh' }}>
            <CardContent className="flex-1 overflow-y-auto p-4 space-y-4">
              {messages.length === 0 && (
                <div className="text-center text-sm text-muted-foreground py-12">
                  <Bot size={40} className="mx-auto mb-2 text-muted-foreground/50" />
                  <p className="font-medium">Pergunta alguma coisa sobre o sistema.</p>
                  <p className="text-xs mt-2">
                    Exemplos: <em>"Por que escolhemos MySQL?"</em> · <em>"Quais as regras do Ponto?"</em> ·{' '}
                    <em>"O que tem no Essentials?"</em>
                  </p>
                </div>
              )}

              {messages.map((m) => (
                <div
                  key={m.id}
                  className={`flex gap-3 ${m.role === 'user' ? 'flex-row-reverse' : ''}`}
                >
                  <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${m.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}`}>
                    {m.role === 'user' ? <UserIcon size={14} /> : <Bot size={14} />}
                  </div>
                  <div className={`flex-1 min-w-0 ${m.role === 'user' ? 'text-right' : ''}`}>
                    <div className={`inline-block rounded-lg px-3 py-2 text-sm ${m.role === 'user' ? 'bg-primary text-primary-foreground' : m.role === 'system' ? 'bg-destructive/10 text-destructive' : 'bg-muted/50'}`}>
                      <pre className="whitespace-pre-wrap font-sans text-sm break-words" style={{ maxWidth: '55ch' }}>
                        {m.content}
                      </pre>
                    </div>
                    <div className="text-[10px] text-muted-foreground mt-1 flex gap-2 items-center">
                      <span>{m.created_at}</span>
                      {m.module_context && (
                        <Badge variant="outline" className="text-[9px]">{m.module_context}</Badge>
                      )}
                      {m.mode === 'ai' && (
                        <Badge variant="default" className="text-[9px]">IA</Badge>
                      )}
                    </div>
                  </div>
                </div>
              ))}

              {sending && (
                <div className="flex gap-3">
                  <div className="flex-shrink-0 w-8 h-8 rounded-full bg-muted text-muted-foreground flex items-center justify-center">
                    <Bot size={14} />
                  </div>
                  <div className="flex items-center gap-1 text-muted-foreground text-sm">
                    <span className="animate-pulse">●</span>
                    <span className="animate-pulse" style={{ animationDelay: '0.2s' }}>●</span>
                    <span className="animate-pulse" style={{ animationDelay: '0.4s' }}>●</span>
                  </div>
                </div>
              )}

              <div ref={bottomRef} />
            </CardContent>

            <form onSubmit={handleSubmit} className="border-t border-border p-3 flex gap-2 items-end">
              <Textarea
                value={input}
                onChange={(e) => setInput(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    handleSubmit(e as unknown as FormEvent);
                  }
                }}
                placeholder="Escreva sua pergunta... (Enter envia, Shift+Enter quebra linha)"
                disabled={sending}
                rows={2}
                className="flex-1 resize-none"
              />
              <Button type="submit" disabled={sending || !input.trim()}>
                <Send size={14} />
              </Button>
            </form>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
