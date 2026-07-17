import { Head, useForm, usePage } from '@inertiajs/react'
import { useEffect, useState } from 'react'

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
 * e o global scope multi-tenant é no-op sem auth (ver RUNBOOK §3).
 *
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
    severity_self_reported: 2,
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
        <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
          <div className="w-full max-w-lg bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
            <div className="text-4xl mb-4" aria-hidden="true">
              ✓
            </div>
            <h1 className="text-xl font-semibold text-slate-900 mb-2">Recebido, obrigado!</h1>
            <p className="text-slate-600 mb-6">
              Sua mensagem chegou pra gente. Se for algo que trave seu dia, entramos em contato.
            </p>
            <button
              type="button"
              onClick={() => setEnviado(false)}
              className="text-sm font-medium text-violet-700 hover:text-violet-800 underline underline-offset-4"
            >
              Contar outra coisa
            </button>
          </div>
        </div>
      </>
    )
  }

  return (
    <>
      <Head title="Falar com a gente — oimpresso" />

      <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
        <div className="w-full max-w-lg bg-white rounded-xl shadow-sm border border-slate-200 p-6 sm:p-8">
          <header className="mb-6">
            <h1 className="text-xl font-semibold text-slate-900">O que não está bom?</h1>
            <p className="text-sm text-slate-600 mt-1">
              {business_nome} — conte do seu jeito. Quem lê é quem faz o sistema.
            </p>
          </header>

          <form onSubmit={enviar} className="space-y-6">
            <div>
              <label htmlFor="literal" className="block text-sm font-medium text-slate-900 mb-1.5">
                O que aconteceu?
              </label>
              <textarea
                id="literal"
                value={data.literal}
                onChange={(e) => setData('literal', e.target.value)}
                rows={5}
                autoFocus
                required
                placeholder="Ex: quando eu tento fechar a venda com desconto, o total fica errado."
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none"
                aria-describedby={errors.literal ? 'erro-literal' : undefined}
                aria-invalid={errors.literal ? true : undefined}
              />
              {errors.literal && (
                <p id="erro-literal" role="alert" className="text-sm text-red-600 mt-1.5">
                  {errors.literal}
                </p>
              )}
            </div>

            <fieldset>
              <legend className="block text-sm font-medium text-slate-900 mb-1.5">
                O quanto isso te atrapalha?
              </legend>
              <div className="space-y-1.5">
                {severidades.map((s) => (
                  <label
                    key={s.valor}
                    className="flex items-start gap-2.5 rounded-lg px-3 py-2 cursor-pointer hover:bg-slate-50 has-[:checked]:bg-violet-50 has-[:checked]:ring-1 has-[:checked]:ring-violet-200"
                  >
                    <input
                      type="radio"
                      name="severity_self_reported"
                      value={s.valor}
                      checked={data.severity_self_reported === s.valor}
                      onChange={() => setData('severity_self_reported', s.valor)}
                      className="mt-0.5 text-violet-600 focus:ring-violet-500"
                    />
                    <span className="text-sm text-slate-700">{s.label}</span>
                  </label>
                ))}
              </div>
              {errors.severity_self_reported && (
                <p role="alert" className="text-sm text-red-600 mt-1.5">
                  {errors.severity_self_reported}
                </p>
              )}
            </fieldset>

            <div>
              <label htmlFor="reporter_name" className="block text-sm font-medium text-slate-900 mb-1.5">
                Seu nome <span className="font-normal text-slate-500">(opcional)</span>
              </label>
              <input
                id="reporter_name"
                type="text"
                value={data.reporter_name}
                onChange={(e) => setData('reporter_name', e.target.value)}
                maxLength={120}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 focus:outline-none"
              />
              {errors.reporter_name && (
                <p role="alert" className="text-sm text-red-600 mt-1.5">
                  {errors.reporter_name}
                </p>
              )}
            </div>

            <button
              type="submit"
              disabled={processing}
              className="w-full rounded-lg bg-violet-700 px-4 py-2.5 font-medium text-white hover:bg-violet-800 focus:ring-2 focus:ring-violet-500/40 focus:outline-none disabled:opacity-60 disabled:cursor-not-allowed"
            >
              {processing ? 'Enviando…' : 'Enviar'}
            </button>
          </form>
        </div>
      </div>
    </>
  )
}
