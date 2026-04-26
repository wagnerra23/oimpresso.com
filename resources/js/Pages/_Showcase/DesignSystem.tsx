// @memcofre
//   tela: /showcase/design-system
//   module: _DesignSystem
//   status: showcase

import { Head } from '@inertiajs/react'
import { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip'
import { Separator } from '@/Components/ui/separator'
import { Kbd } from '@/Components/ui/kbd'
import { Spinner } from '@/Components/ui/spinner'
import { Empty } from '@/Components/ui/empty'
import { CodeBlock } from '@/Components/ui/code-block'
import { Inbox, Search, Sparkles, Check, AlertCircle, Info } from 'lucide-react'

const swatches = [
  { name: 'background', token: '--color-background' },
  { name: 'foreground', token: '--color-foreground' },
  { name: 'card', token: '--color-card' },
  { name: 'surface-1', token: '--color-surface-1' },
  { name: 'surface-2', token: '--color-surface-2' },
  { name: 'border', token: '--color-border' },
  { name: 'primary', token: '--color-primary' },
  { name: 'accent', token: '--color-accent' },
  { name: 'muted', token: '--color-muted' },
  { name: 'success', token: '--color-success' },
  { name: 'warning', token: '--color-warning' },
  { name: 'info', token: '--color-info' },
  { name: 'destructive', token: '--color-destructive' },
]

const typeScale = [
  { token: 'display', sample: 'oimpresso.', cls: 'text-display' },
  { token: 'h1', sample: 'Sistema de design enterprise', cls: 'text-h1' },
  { token: 'h2', sample: 'Tipografia em hierarquia', cls: 'text-h2' },
  { token: 'h3', sample: 'Componentes coesos', cls: 'text-h3' },
  { token: 'h4', sample: 'Detalhes que importam', cls: 'text-h4' },
  {
    token: 'body',
    sample:
      'Texto corrido para leitura confortável em painéis e dashboards. Inter variable com features tipográficas modernas (cv02, cv03, cv04, cv11).',
    cls: 'text-body',
  },
  { token: 'small', sample: 'Texto auxiliar / labels secundárias.', cls: 'text-small' },
  { token: 'caption', sample: 'CAPTION · LABEL', cls: 'text-caption uppercase tracking-wider' },
]

function DesignSystem() {
  const [demoInput, setDemoInput] = useState('')

  return (
    <>
      <Head title="Design System · oimpresso" />
      <TooltipProvider>
        <div className="mx-auto max-w-6xl px-6 py-12 space-y-16">
          {/* Header */}
          <header className="space-y-3">
            <Badge variant="secondary" className="gap-1.5">
              <Sparkles className="size-3" />
              v2 enterprise
            </Badge>
            <h1
              className="text-display tabular-nums"
              style={{ fontSize: 'var(--text-display)', lineHeight: 'var(--text-display--line-height)', letterSpacing: 'var(--text-display--letter-spacing)' }}
            >
              Design System
            </h1>
            <p className="max-w-xl text-body text-muted-foreground">
              Tokens, tipografia e componentes do oimpresso. Base shadcn new-york
              + Inter variable + paleta refinada com camadas semânticas.
            </p>
          </header>

          <Separator />

          {/* Typography */}
          <section className="space-y-6">
            <div>
              <h2 style={{ fontSize: 'var(--text-h2)', fontWeight: 600, letterSpacing: 'var(--text-h2--letter-spacing)' }}>Tipografia</h2>
              <p className="mt-1 text-small text-muted-foreground">
                Inter variable com 6 níveis hierárquicos + caption. Tabular-nums
                disponível pra dashboards e tabelas financeiras.
              </p>
            </div>
            <Card>
              <CardContent className="divide-y p-0">
                {typeScale.map((row) => (
                  <div key={row.token} className="grid grid-cols-[140px_1fr] items-baseline gap-6 px-6 py-5">
                    <code className="text-caption uppercase tracking-wider text-muted-foreground">
                      {row.token}
                    </code>
                    <div className={row.cls}>{row.sample}</div>
                  </div>
                ))}
              </CardContent>
            </Card>
          </section>

          {/* Cores */}
          <section className="space-y-6">
            <div>
              <h2 style={{ fontSize: 'var(--text-h2)', fontWeight: 600, letterSpacing: 'var(--text-h2--letter-spacing)' }}>Cores · tokens semânticos</h2>
              <p className="mt-1 text-small text-muted-foreground">
                Vars CSS — light/dark automático. Tema custom é só sobrescrever no <code className="rounded bg-muted px-1 py-0.5 text-caption">@theme</code>.
              </p>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
              {swatches.map((s) => (
                <div key={s.name} className="space-y-2">
                  <div
                    className="h-16 rounded-lg border"
                    style={{ backgroundColor: `var(${s.token})` }}
                  />
                  <div className="space-y-0.5">
                    <div className="text-small font-medium">{s.name}</div>
                    <code className="text-caption text-muted-foreground">{s.token}</code>
                  </div>
                </div>
              ))}
            </div>
          </section>

          {/* Componentes */}
          <section className="space-y-6">
            <div>
              <h2 style={{ fontSize: 'var(--text-h2)', fontWeight: 600, letterSpacing: 'var(--text-h2--letter-spacing)' }}>Componentes</h2>
              <p className="mt-1 text-small text-muted-foreground">
                19 shadcn já existentes + 4 novos enterprise (Kbd, Spinner, Empty, CodeBlock).
              </p>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle>Buttons</CardTitle>
                  <CardDescription>6 variants × 8 tamanhos.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex flex-wrap gap-2">
                    <Button>Default</Button>
                    <Button variant="secondary">Secondary</Button>
                    <Button variant="outline">Outline</Button>
                    <Button variant="ghost">Ghost</Button>
                    <Button variant="link">Link</Button>
                    <Button variant="destructive">Destructive</Button>
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    <Button size="xs">XS</Button>
                    <Button size="sm">SM</Button>
                    <Button size="default">Default</Button>
                    <Button size="lg">LG</Button>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Input + Kbd</CardTitle>
                  <CardDescription>Atalho de teclado inline.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      value={demoInput}
                      onChange={(e) => setDemoInput(e.target.value)}
                      placeholder="Buscar memória, ADRs, sessões…"
                      className="pl-9 pr-12"
                    />
                    <Kbd className="absolute right-3 top-1/2 -translate-y-1/2">⌘K</Kbd>
                  </div>
                  <div className="flex items-center gap-2 text-small text-muted-foreground">
                    Pressione <Kbd>Cmd</Kbd>+<Kbd>Enter</Kbd> pra enviar.
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Badges + tons semânticos</CardTitle>
                  <CardDescription>Status visual claro.</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                  <Badge>default</Badge>
                  <Badge variant="secondary">secondary</Badge>
                  <Badge variant="outline">outline</Badge>
                  <Badge variant="destructive">destructive</Badge>
                  <Badge className="bg-success text-success-foreground">
                    <Check className="size-3" /> success
                  </Badge>
                  <Badge className="bg-warning text-warning-foreground">
                    <AlertCircle className="size-3" /> warning
                  </Badge>
                  <Badge className="bg-info text-info-foreground">
                    <Info className="size-3" /> info
                  </Badge>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Avatares + Tooltip</CardTitle>
                  <CardDescription>Identidade visual de usuários.</CardDescription>
                </CardHeader>
                <CardContent className="flex items-center gap-3">
                  <Tooltip>
                    <TooltipTrigger asChild>
                      <Avatar>
                        <AvatarImage src="https://github.com/wagnerra23.png" alt="Wagner" />
                        <AvatarFallback>WR</AvatarFallback>
                      </Avatar>
                    </TooltipTrigger>
                    <TooltipContent>Wagner</TooltipContent>
                  </Tooltip>
                  <Avatar><AvatarFallback>EL</AvatarFallback></Avatar>
                  <Avatar><AvatarFallback className="bg-primary text-primary-foreground">CL</AvatarFallback></Avatar>
                  <Spinner size="sm" tone="primary" />
                  <Spinner size="default" />
                </CardContent>
              </Card>
            </div>

            {/* CodeBlock */}
            <Card>
              <CardHeader>
                <CardTitle>CodeBlock</CardTitle>
                <CardDescription>
                  Visual coeso pra snippets. Syntax highlight via shiki entra no PR 2 (Copiloto chat).
                </CardDescription>
              </CardHeader>
              <CardContent>
                <CodeBlock
                  language="bash"
                  code={`composer require prism-php/prism
php artisan migrate
php artisan evolution:index`}
                />
              </CardContent>
            </Card>

            {/* Empty */}
            <Card>
              <CardHeader>
                <CardTitle>Empty state</CardTitle>
                <CardDescription>Mensagem clara quando não há dado a exibir.</CardDescription>
              </CardHeader>
              <CardContent>
                <Empty
                  icon={<Inbox className="size-7" />}
                  title="Nenhuma conversa ainda"
                  description="Quando você começar uma nova conversa com o Copiloto, ela aparece aqui."
                  action={<Button size="sm">Iniciar conversa</Button>}
                />
              </CardContent>
            </Card>
          </section>

          <Separator />

          {/* Tabular nums showcase */}
          <section className="space-y-4">
            <div>
              <h2 style={{ fontSize: 'var(--text-h2)', fontWeight: 600, letterSpacing: 'var(--text-h2--letter-spacing)' }}>Tabular numerals</h2>
              <p className="mt-1 text-small text-muted-foreground">
                Obrigatório em valores monetários e dashboards.
              </p>
            </div>
            <Card>
              <CardContent className="grid grid-cols-2 gap-6 p-6">
                <div>
                  <div className="text-caption uppercase tracking-wider text-muted-foreground">Sem tabular</div>
                  <div className="mt-1 font-mono text-h2">1.234,56</div>
                  <div className="font-mono text-h2">9.999,00</div>
                </div>
                <div>
                  <div className="text-caption uppercase tracking-wider text-muted-foreground">Com tabular-nums</div>
                  <div className="tabular-nums mt-1 font-mono text-h2">1.234,56</div>
                  <div className="tabular-nums font-mono text-h2">9.999,00</div>
                </div>
              </CardContent>
            </Card>
          </section>
        </div>
      </TooltipProvider>
    </>
  )
}

DesignSystem.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>

export default DesignSystem
