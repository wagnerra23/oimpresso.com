// @memcofre
//   tela: /copiloto
//   stories: US-COPI-001, US-COPI-002, US-COPI-003, US-COPI-MEM-007
//   rules: R-COPI-001, R-COPI-MEM-005
//   adrs: 0026, 0031, 0032, 0034, 0035, 0036
//   tests: tests/Feature/Modules/Copiloto/AdapterResolverTest, tests/Feature/Modules/Copiloto/BridgeMemoriaChatTest
//   status: implementada
//   module: Copiloto

import React, { useEffect, useRef, useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { Head, router } from '@inertiajs/react'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { ScrollArea } from '@/Components/ui/scroll-area'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet'
import { Textarea } from '@/Components/ui/textarea'
import { Bot, User, MessagesSquare, Plus, ChevronRight, Send } from 'lucide-react'
import { toast } from 'sonner'

interface Mensagem {
  id: number
  role: 'user' | 'assistant' | 'system'
  content: string
  created_at: string
  propostas?: Proposta[]
}

interface Proposta {
  nome: string
  metrica: string
  valor_alvo: number
  periodo: string
  dificuldade: 'facil' | 'realista' | 'ambicioso'
  racional: string
  dependencias: string[]
}

interface Sugestao {
  id: number
  payload_json: Proposta
}

interface Conversa {
  id: number
  titulo: string
  status: string
  iniciada_em: string
}

interface Props {
  conversa: Conversa & { id: number }
  conversas: Conversa[]
  mensagens: Mensagem[]
  sugestoesPendentes?: Sugestao[]
}

const DIFICULDADE_CONFIG: Record<string, { label: string; className: string }> = {
  facil:     { label: 'Fácil',     className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' },
  realista:  { label: 'Realista',  className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
  ambicioso: { label: 'Ambicioso', className: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300' },
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
}

function PropostaCard({ sugestao }: { sugestao: Sugestao }) {
  const p = sugestao.payload_json
  const dif = DIFICULDADE_CONFIG[p.dificuldade] ?? DIFICULDADE_CONFIG['realista']!

  function escolher() {
    router.post(`/copiloto/sugestoes/${sugestao.id}/escolher`, {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.success('Meta criada com sucesso!'),
      onError: () => toast.error('Erro ao escolher meta.'),
    })
  }

  function rejeitar() {
    router.post(`/copiloto/sugestoes/${sugestao.id}/rejeitar`, {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.info('Proposta rejeitada.'),
    })
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
  )
}

function MessageBubble({ msg }: { msg: Mensagem }) {
  const isUser = msg.role === 'user'

  return (
    <div className={`flex gap-3 ${isUser ? 'flex-row-reverse' : 'flex-row'}`}>
      <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted">
        {isUser ? <User className="h-4 w-4" /> : <Bot className="h-4 w-4 text-primary" />}
      </div>
      <div className={`max-w-[80%] space-y-2 ${isUser ? 'items-end' : 'items-start'} flex flex-col`}>
        <div
          className={`rounded-2xl px-4 py-2.5 text-sm leading-relaxed ${
            isUser
              ? 'rounded-tr-sm bg-primary text-primary-foreground'
              : 'rounded-tl-sm bg-muted text-foreground'
          }`}
        >
          {msg.content}
        </div>
      </div>
    </div>
  )
}

export default function Chat({ conversa, conversas, mensagens, sugestoesPendentes = [] }: Props) {
  const threadRef = useRef<HTMLDivElement>(null)
  const [texto, setTexto] = useState('')
  const [enviando, setEnviando] = useState(false)

  // Rola pra baixo ao carregar e ao receber novas mensagens
  useEffect(() => {
    if (threadRef.current) {
      threadRef.current.scrollTop = threadRef.current.scrollHeight
    }
  }, [mensagens])

  function enviar() {
    if (! texto.trim() || enviando) return

    setEnviando(true)
    router.post(
      `/copiloto/conversas/${conversa.id}/mensagens`,
      { content: texto },
      {
        onSuccess: () => {
          setTexto('')
          setEnviando(false)
        },
        onError: () => {
          toast.error('Erro ao enviar mensagem.')
          setEnviando(false)
        },
        preserveScroll: true,
      }
    )
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault()
      enviar()
    }
  }

  function novaConversa() {
    router.post('/copiloto/conversas', { titulo: 'Nova conversa' }, {
      preserveScroll: true,
      preserveState: true,
    })
  }

  // Lista lateral de conversas
  const ConversaLista = (
    <div className="flex h-full flex-col">
      <div className="flex items-center justify-between p-4 border-b border-border">
        <span className="font-semibold text-sm">Conversas</span>
        <Button size="icon" variant="ghost" onClick={novaConversa} aria-label="Nova conversa">
          <Plus className="h-4 w-4" />
        </Button>
      </div>
      <ScrollArea className="flex-1">
        <div className="space-y-1 p-2">
          {conversas.map(c => (
            <button
              key={c.id}
              onClick={() => router.get(`/copiloto/conversas/${c.id}`, {}, { preserveScroll: true, preserveState: true })}
              className={`w-full flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-muted ${
                c.id === conversa.id ? 'bg-muted font-medium' : ''
              }`}
            >
              <MessagesSquare className="h-4 w-4 shrink-0 text-muted-foreground" />
              <span className="truncate flex-1">{c.titulo}</span>
              <ChevronRight className="h-3 w-3 shrink-0 text-muted-foreground" />
            </button>
          ))}
        </div>
      </ScrollArea>
    </div>
  )

  return (
    <>
      <Head title="Copiloto — Chat" />

      <div className="flex h-[calc(100vh-4rem)] overflow-hidden">
        {/* Desktop: coluna esquerda 40% */}
        <aside className="hidden lg:flex lg:w-[40%] flex-col border-r border-border bg-background">
          {ConversaLista}
        </aside>

        {/* Mobile: sheet lateral */}
        <div className="flex lg:hidden items-center p-2 border-b border-border">
          <Sheet>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" aria-label="Abrir lista de conversas">
                <MessagesSquare className="h-5 w-5" />
              </Button>
            </SheetTrigger>
            <SheetContent side="left" className="w-72 p-0">
              <SheetHeader className="sr-only">
                <SheetTitle>Conversas</SheetTitle>
              </SheetHeader>
              {ConversaLista}
            </SheetContent>
          </Sheet>
          <span className="ml-2 text-sm font-medium truncate">{conversa.titulo}</span>
        </div>

        {/* Coluna direita: thread + composer */}
        <main className="flex flex-1 flex-col overflow-hidden">
          {/* Thread */}
          <div
            ref={threadRef}
            role="log"
            aria-live="polite"
            aria-label="Histórico de conversa com Copiloto"
            className="flex-1 overflow-y-auto p-4 space-y-4"
          >
            {mensagens.map(msg => (
              <MessageBubble key={msg.id} msg={msg} />
            ))}

            {/* Cards de propostas pendentes */}
            {sugestoesPendentes.length > 0 && (
              <div className="space-y-2">
                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                  Propostas de metas
                </p>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                  {sugestoesPendentes.map(s => (
                    <PropostaCard key={s.id} sugestao={s} />
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Composer */}
          <div className="border-t border-border bg-background p-4">
            {/* Sugestões rápidas */}
            <div className="mb-2 flex flex-wrap gap-2">
              {['Sugira metas', 'Compare com mês passado', 'Explique o desvio'].map(s => (
                <button
                  key={s}
                  onClick={() => setTexto(s)}
                  className="rounded-full border border-border bg-muted px-3 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                  {s}
                </button>
              ))}
            </div>

            <div className="flex items-end gap-2">
              <Textarea
                value={texto}
                onChange={e => setTexto(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Mensagem para o Copiloto (Cmd+Enter para enviar)"
                aria-label="Mensagem para o Copiloto"
                rows={1}
                className="min-h-[2.5rem] max-h-40 resize-none flex-1"
                disabled={enviando}
              />
              <Button
                onClick={enviar}
                disabled={! texto.trim() || enviando}
                size="icon"
                aria-label="Enviar mensagem"
              >
                <Send className="h-4 w-4" />
              </Button>
            </div>
            <p className="mt-1 text-xs text-muted-foreground">
              Cmd+Enter envia · Enter quebra linha
            </p>
          </div>
        </main>
      </div>
    </>
  )
}

Chat.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>
