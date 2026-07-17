import { Head, useForm, usePage } from '@inertiajs/react'
import { CheckCircle2 } from 'lucide-react'
import { useEffect, useState } from 'react'

import { Inline } from '@/Components/layout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group'
import { Textarea } from '@/Components/ui/textarea'

/**
 * Canal público de sinal do cliente — US-INFRA-002 · ADR 0105 · ADR 0334.
 *
 * O órgão sensor: a Larissa (ROTA LIVRE, não-técnica) reporta a dor DELA sem depender de
 * alguém ouvir no WhatsApp e transcrever.
 *
 * Tela PÚBLICA — sem AppShellV2, sem sidebar, sem cockpit. Não segue PT-01..05 de
 * propósito (todos assumem o shell autenticado). Quem abre isto não tem login, chegou por
 * um link e quer resolver em 30 segundos: 1 pergunta, 1 escala, 1 botão.
 *
 * Write-only por design: NÃO adicione leitura de dado do business aqui — a rota é pública
 * e o global scope multi-tenant é no-op sem auth (ver RUNBOOK §3 / charter §Invariantes).
 *
 * Ser pública não a isenta do DS: composta de Button/Input/Textarea/RadioGroup/Label + o
 * primitivo Inline, e só com tokens semânticos (`primary` já é o roxo canônico da ADR
 * 0190 — não hardcodar hue). Radius máximo rounded-lg (charter DS).
 *
 * @see resources/js/Pages/Whatsapp/FeedbackPublico.charter.md
 * @see memory/requisitos/Whatsapp/RUNBOOK-feedback-publico.md
 */

interface Severidade {
  valor: number
  label: string
}

interface Props {
  business_nome: string
  submit_url: string
  severidades: Severidade[]
}

export default function FeedbackPublico({ business_nome, submit_url, severidades }: Props) {
  const { props } = usePage<{ flash?: { feedback_recebido?: boolean } }>()
  const recebido = Boolean(props.flash?.feedback_recebido)

  const [enviado, setEnviado] = useState(false)

  const { data, setData, post, processing, errors, reset } = useForm({
    literal: '',
    reporter_name: '',
    severity_self_reported: '2',
    url_seen: '',
    browser_console_dump: '',
  })

  // Contexto que o cliente não sabe informar — "sabe ONDE dói, raramente POR QUÊ"
  // (ADR 0105 §princípio 1). Capturamos o barato; o resto é do APM (US-INFRA-003).
  useEffect(() => {
    setData((atual) => ({
      ...atual,
      url_seen: document.referrer || '',
      browser_console_dump: `user-agent: ${navigator.userAgent}\nviewport: ${window.innerWidth}x${window.innerHeight}`,
    }))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (recebido) {
      setEnviado(true)
      reset('literal', 'severity_self_reported')
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [recebido])

  function enviar(e: React.FormEvent) {
    e.preventDefault()
    // Posta na MESMA URL assinada que abriu esta página (o HMAC cobre a URL, não o método).
    post(submit_url, { preserveScroll: true })
  }

  if (enviado) {
    return (
      <>
        <Head title="Recebido — oimpresso" />
        <Inline align="center" justify="center" className="min-h-screen bg-muted p-4">
          <div className="w-full max-w-lg rounded-lg border border-border bg-card p-8 text-center shadow-sm">
            <CheckCircle2 className="mx-auto mb-4 size-10 text-primary" aria-hidden="true" />
            <h1 className="mb-2 text-xl font-semibold text-foreground">Recebido, obrigado!</h1>
            <p className="mb-6 text-muted-foreground">
              Sua mensagem chegou pra gente. Se for algo que trave seu dia, entramos em contato.
            </p>
            <Button variant="ghost" onClick={() => setEnviado(false)}>
              Contar outra coisa
            </Button>
          </div>
        </Inline>
      </>
    )
  }

  return (
    <>
      <Head title="Falar com a gente — oimpresso" />

      <Inline align="center" justify="center" className="min-h-screen bg-muted p-4">
        <div className="w-full max-w-lg rounded-lg border border-border bg-card p-6 shadow-sm sm:p-8">
          <header className="mb-6">
            <h1 className="text-xl font-semibold text-foreground">O que não está bom?</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              {business_nome} — conte do seu jeito. Quem lê é quem faz o sistema.
            </p>
          </header>

          <form onSubmit={enviar} className="space-y-6">
            <div>
              <Label htmlFor="literal" className="mb-1.5 block">
                O que aconteceu?
              </Label>
              <Textarea
                id="literal"
                value={data.literal}
                onChange={(e) => setData('literal', e.target.value)}
                rows={5}
                autoFocus
                required
                placeholder="Ex: quando eu tento fechar a venda com desconto, o total fica errado."
                aria-describedby={errors.literal ? 'erro-literal' : undefined}
                aria-invalid={errors.literal ? true : undefined}
              />
              {errors.literal && (
                <p id="erro-literal" role="alert" className="mt-1.5 text-sm text-destructive">
                  {errors.literal}
                </p>
              )}
            </div>

            <fieldset>
              <legend className="mb-1.5 text-sm font-medium text-foreground">
                O quanto isso te atrapalha?
              </legend>
              <RadioGroup
                value={data.severity_self_reported}
                onValueChange={(v) => setData('severity_self_reported', v)}
              >
                {severidades.map((s) => (
                  <Label
                    key={s.valor}
                    htmlFor={`sev-${s.valor}`}
                    className="cursor-pointer rounded-lg px-3 py-2 font-normal hover:bg-accent has-[:checked]:bg-accent has-[:checked]:ring-1 has-[:checked]:ring-ring"
                  >
                    <Inline gap={2} align="start">
                      <RadioGroupItem id={`sev-${s.valor}`} value={String(s.valor)} className="mt-0.5" />
                      <span className="text-sm text-foreground">{s.label}</span>
                    </Inline>
                  </Label>
                ))}
              </RadioGroup>
              {errors.severity_self_reported && (
                <p role="alert" className="mt-1.5 text-sm text-destructive">
                  {errors.severity_self_reported}
                </p>
              )}
            </fieldset>

            <div>
              <Label htmlFor="reporter_name" className="mb-1.5 block">
                Seu nome <span className="font-normal text-muted-foreground">(opcional)</span>
              </Label>
              <Input
                id="reporter_name"
                type="text"
                value={data.reporter_name}
                onChange={(e) => setData('reporter_name', e.target.value)}
                maxLength={120}
              />
              {errors.reporter_name && (
                <p role="alert" className="mt-1.5 text-sm text-destructive">
                  {errors.reporter_name}
                </p>
              )}
            </div>

            <Button type="submit" disabled={processing} className="w-full">
              {processing ? 'Enviando…' : 'Enviar'}
            </Button>
          </form>
        </div>
      </Inline>
    </>
  )
}
