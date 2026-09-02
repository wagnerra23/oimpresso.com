// Jana/Acoes — a aba Ações da área Jana (`/ia/acoes`): a fila HITL.
//
// Âncora de design: `prototipo-ui/cowork/jana-telas-novas.jsx` §`JmAcoesFila` — âncora de
// SÍMBOLO (`grep -n "function JmAcoesFila" prototipo-ui/cowork/jana-telas-novas.jsx`;
// resolva com `node prototipo-ui/ancora.mjs Jana/Acoes`). A ABA vem do `JmTabs` de
// `jana-merge.jsx` e aqui nasce do ghost `acoes` do `DataController`.
//
// TUDO QUE AFIRMA NÚMERO É DO SERVIDOR: título/CTA (`AcaoHitlService::TITULOS`/`::ACOES`),
// prévia, contexto e alcance (`AcaoHitlService::previa`, o mesmo agregado que pinta a linha do
// Painel) e o recibo (`jana_acao_aprovacoes`). A âncora traz as 5 prévias em texto FIXO com
// números do Martinho — o que se copia dela é a FORMA (fila, chips, recibo), nunca o dado.
// Aprovar reusa o `JanaAcaoModal` do Painel (mesma rota, mesmo registro, mesmo toast global).
import React, { useState } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Inline, Stack } from '@/Components/layout'
import { Settings } from 'lucide-react'
import FabJana from './_components/FabJana'
import { JanaAreaHeader } from './_components/JanaAreaHeader'
import JanaAcaoModal, { type AcaoHitl } from './_components/JanaAcaoModal'
import JanaConfigDrawer from './_components/JanaConfigDrawer'
import { JanaPlanoBadge } from './_components/JanaPlanoBadge'
import { useJanaPro } from './_components/useJanaPro'
import { useJanaConfig } from './_components/useJanaConfig'

/** Uma ação da fila — tudo vindo de `AcaoHitlService::fila()`. */
export interface AcaoFila {
  key: string
  titulo: string
  cta: string
  previa: string
  contexto: Record<string, unknown>
  /** `null` = LEITURA (não manda mensagem pra ninguém) — diferente de 0. */
  alcance: number | null
  recibo: { quem: string | null; quando: string | null; previa: string; contexto: Record<string, unknown> } | null
}

interface Props {
  acoes: AcaoFila[]
  janaContext?: { businessId: number | null; businessName: string; userName?: string | null }
}

const fmtQuando = (iso: string | null): string =>
  iso ? new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'
const fmtValor = (v: unknown): string => (typeof v === 'string' || typeof v === 'number' ? String(v) : JSON.stringify(v))

export default function Acoes({ acoes, janaContext }: Props) {
  const [configAberto, setConfigAberto] = useState(false)
  const { config, alternarAnalise } = useJanaConfig()
  const pro = useJanaPro()

  const [aba, setAba] = useState<'sugeridas' | 'aprovadas'>('sugeridas')
  const [revisando, setRevisando] = useState<AcaoHitl | null>(null)
  const [recibo, setRecibo] = useState<AcaoFila | null>(null)

  const aprovadas = acoes.filter((a) => a.recibo)
  const lista = aba === 'sugeridas' ? acoes.filter((a) => !a.recibo) : aprovadas

  return (
    <>
      <JanaAreaHeader
        active="acoes"
        businessName={janaContext?.businessName || undefined}
        businessId={janaContext?.businessId ?? undefined}
        actions={
          <>
            <JanaPlanoBadge pro={pro} onConfigurar={() => setConfigAberto(true)} />
            <Button variant="outline" size="sm" onClick={() => setConfigAberto(true)} aria-haspopup="dialog" aria-expanded={configAberto}>
              <Settings className="h-3.5 w-3.5" /> Configurar
            </Button>
          </>
        }
      />
      <JanaConfigDrawer open={configAberto} onClose={() => setConfigAberto(false)} config={config} onAlternarAnalise={alternarAnalise} />

      <div className="flex flex-col gap-3 p-6">
        {/* Copy literal da âncora (`Alert tone="info"`). Os nomes de rota e a tabela são os REAIS. */}
        <Alert data-contract="acoes-aviso">
          <AlertTitle>Aprovar registra a decisão. Nada é enviado.</AlertTitle>
          <AlertDescription>
            As duas rotas que existem são prévia e aprovação (<code>jana.acoes.previa</code> ·
            <code> jana.acoes.aprovar</code>): a aprovação grava um recibo em <code>jana_acao_aprovacoes</code>
            com o texto que o servidor gerou. O disparo (WhatsApp/e-mail) é trabalho próprio — por isso o
            botão diz “Revisar”, e não “Disparar”.
          </AlertDescription>
        </Alert>

        <Inline gap={3} align="center" wrap data-contract="acoes-toolbar">
          <Inline gap={1} align="center" role="group" aria-label="Filtrar a fila">
            {(['sugeridas', 'aprovadas'] as const).map((f) => (
              <Button key={f} size="sm" variant={aba === f ? 'secondary' : 'ghost'} onClick={() => setAba(f)} aria-pressed={aba === f}>
                {f}{f === 'aprovadas' && aprovadas.length ? ` · ${aprovadas.length}` : ''}
              </Button>
            ))}
          </Inline>
          <span className="ml-auto text-sm text-muted-foreground">prévia e alcance vêm do servidor</span>
        </Inline>

        <Stack gap={2} data-contract="acoes-fila">
          {lista.length === 0 && (
            <div data-contract="acoes-vazio" className="rounded-md border border-dashed border-border p-4 text-sm">
              <b className="text-foreground">
                {aba === 'aprovadas' ? 'Nenhuma ação aprovada ainda.' : 'Todas as sugestões de hoje já foram revisadas.'}
              </b>
              <p className="mt-1 text-xs text-muted-foreground">
                {aba === 'aprovadas'
                  ? 'O recibo aparece aqui no instante em que você aprova — com quem aprovou e quando.'
                  : 'A Jana propõe de novo na próxima apuração, se o número continuar pedindo.'}
              </p>
            </div>
          )}
          {lista.map((a) => (
            <Card key={a.key} className={a.recibo ? 'bg-muted/40' : undefined}>
              <CardContent className="grid grid-cols-[1fr_auto] items-start gap-4 p-4">
                <div className="min-w-0">
                  <div className="flex items-center gap-2 text-[13px] font-semibold text-foreground">
                    {a.titulo}
                    <span className="font-mono text-[10.5px] font-normal text-muted-foreground">{a.key}</span>
                  </div>
                  <p className="mt-1 max-w-[74ch] text-xs leading-relaxed text-muted-foreground">{a.previa}</p>
                  <div className="mt-2 flex items-center gap-2.5 text-[11px] text-muted-foreground">
                    {/* Pill tintada (AP7) — "envio"/"leitura" não é `kind` do StatusBadge. */}
                    <Badge variant={a.alcance == null ? 'secondary' : 'info'}>{a.alcance == null ? 'leitura' : 'envio'}</Badge>
                    <span>{a.alcance == null ? 'não manda mensagem pra ninguém' : `${a.alcance} destinatário(s), um por cliente`}</span>
                  </div>
                </div>
                <div className="flex flex-col items-end gap-1.5">
                  {a.recibo ? (
                    <>
                      <span className="text-right font-mono text-[10.5px] leading-snug text-muted-foreground">
                        aprovada · {fmtQuando(a.recibo.quando)}<br />por {a.recibo.quem ?? '—'}
                      </span>
                      <Button variant="ghost" size="sm" onClick={() => setRecibo(a)}>Ver o recibo</Button>
                    </>
                  ) : (
                    <Button variant="outline" size="sm" onClick={() => setRevisando({ id: a.key, title: a.titulo, sub: a.alcance == null ? 'leitura' : `${a.alcance} destinatário(s)` })}>
                      {a.cta}
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </Stack>

        {/* Copy literal da âncora (`jtn-nota`). */}
        <p data-contract="acoes-nota" className="m-0 max-w-[82ch] text-[11px] leading-relaxed text-muted-foreground">
          Fila por empresa: o <code>business_id</code> vem da sessão, nunca do request. Chave desconhecida
          volta 404 no controller e no service — quem chama o service direto (job, tinker) topa no mesmo muro.
        </p>
      </div>

      {/* Aprovar = o MESMO modal do Painel: prévia buscada de novo do servidor, POST na mesma rota. */}
      <JanaAcaoModal acao={revisando} onClose={() => setRevisando(null)} />

      {/* Recibo: o texto GRAVADO (o do servidor no instante da aprovação), nunca o de agora. */}
      <Dialog open={!!recibo} onOpenChange={(aberto) => !aberto && setRecibo(null)}>
        <DialogContent className="sm:max-w-[480px]">
          <DialogHeader>
            <DialogTitle className="text-base">Recibo da aprovação</DialogTitle>
            <DialogDescription>{recibo?.titulo}</DialogDescription>
          </DialogHeader>
          <p className="text-sm leading-relaxed text-foreground">{recibo?.recibo?.previa}</p>
          <h4 className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">Contexto gravado com a aprovação</h4>
          <table className="w-full text-xs">
            <tbody>
              {Object.entries(recibo?.recibo?.contexto ?? {}).map(([k, v]) => (
                <tr key={k} className="border-t border-border">
                  <td className="py-1 pr-2"><code>{k}</code></td>
                  <td className="py-1 font-mono text-muted-foreground">{fmtValor(v)}</td>
                </tr>
              ))}
              <tr className="border-t border-border">
                <td className="py-1 pr-2"><code>alcance</code></td>
                <td className="py-1 font-mono text-muted-foreground">{recibo?.alcance == null ? 'null — é leitura' : recibo.alcance}</td>
              </tr>
            </tbody>
          </table>
          <p className="text-[11px] leading-relaxed text-muted-foreground">
            O texto acima é o do servidor no instante da leitura — a tela exibe, não calcula. Aprovar grava
            esse mesmo texto no recibo; o front não reescreve o que foi aprovado.
          </p>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setRecibo(null)}>Fechar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <FabJana contextRoute="/ia/acoes" />
    </>
  )
}

Acoes.layout = (page: React.ReactNode) => <AppShellV2 title="Jana — Ações">{page}</AppShellV2>
