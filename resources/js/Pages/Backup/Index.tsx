import React, { useMemo, useState } from 'react'
import { Deferred, Head, router } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { PageHeader } from '@/Components/PageHeader'
import EmptyState from '@/Components/shared/EmptyState'
import KpiCard from '@/Components/shared/KpiCard'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Skeleton } from '@/Components/ui/skeleton'
import { Grid, Inline } from '@/Components/layout'

/**
 * Backup — /backup (Onda 3 do plano de migração).
 *
 * Lei da tela: `Index.charter.md` ao lado. Casos: `Index.casos.md`. Receita:
 * `memory/requisitos/Backup/RUNBOOK-index.md`.
 *
 * ⚠️ Dois fatos que a tela precisa dizer em voz alta, e que o Blade legado calava:
 *  1. o zip é dump do banco INTEIRO — todos os negócios (UC-BKP-08);
 *  2. enquanto o destino for o disco local, backup e servidor caem juntos (UC-BKP-02).
 *
 * A geração é ASSÍNCRONA desde a Onda 2 (job na fila `backups`): o retorno diz
 * "pode fechar a tela", nunca "backup pronto".
 */

type Backup = {
  file_name: string
  file_size: number
  file_size_human: string
  last_modified: string
  origem: 'agendado' | 'manual'
}

type Props = {
  backups?: Backup[]
  destino: { disk: string; remoto: boolean; pasta: string }
  retencao: { estrategia: string; manter: number }
  cron: string
  agendado_ok: boolean
  pode: { gerar: boolean; baixar: boolean; excluir: boolean; motivo?: string }
}

const fmtData = (iso: string) =>
  new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })

function idade(iso: string): string {
  const h = Math.round((Date.now() - new Date(iso).getTime()) / 36e5)
  if (h < 1) return 'agora'
  if (h < 24) return `há ${h} h`
  const d = Math.round(h / 24)
  return d === 1 ? 'há 1 dia' : `há ${d} dias`
}

export default function BackupIndex({
  backups,
  destino,
  retencao,
  cron,
  agendado_ok,
  pode,
}: Props) {
  const [copiado, setCopiado] = useState(false)

  const lista = backups ?? []
  const ultimo = lista[0]
  const total = useMemo(() => lista.reduce((a, b) => a + b.file_size, 0), [lista])
  const totalHumano = useMemo(
    () => (total >= 1073741824 ? `${(total / 1073741824).toFixed(2).replace('.', ',')} GB` : `${Math.round(total / 1048576)} MB`),
    [total],
  )

  const gerar = () => {
    if (!window.confirm('Gerar backup agora?\n\nO dump segura conexões do banco enquanto roda. O agendado diário já cobre o dia.')) return
    router.post('/backup', {}, { preserveScroll: true })
  }

  const excluir = (b: Backup) => {
    if (lista.length <= 1) return
    if (!window.confirm(`Excluir este backup?\n\n${b.file_name} (${b.file_size_human}) sai do disco na hora. Não tem lixeira e não tem volta.`)) return
    router.get(`/backup/${encodeURIComponent(b.file_name)}/delete`, {}, { preserveScroll: true })
  }

  const sub = ultimo
    ? `${lista.length} de ${retencao.manter} arquivos guardados · último ${idade(ultimo.last_modified)} · ${totalHumano} no disco`
    : 'Nenhum backup no disco — nada para restaurar'

  return (
    <AppShellV2 title="Backup" breadcrumbItems={[{ label: 'Sistema' }, { label: 'Backup' }]}>
      <Head title="Backup" />

      <div data-contract="cabecalho">
        <PageHeader
          title="Backup"
          subtitle={sub}
          actions={
            <Button
              disabled={!pode.gerar}
              title={pode.gerar ? undefined : pode.motivo}
              onClick={gerar}
            >
              Gerar backup agora
            </Button>
          }
        />
      </div>

      <Grid data-contract="kpis" min="sm" gap={4}>
        <KpiCard
          label="Último backup"
          value={ultimo ? fmtData(ultimo.last_modified).slice(-5) : '—'}
          description={ultimo ? idade(ultimo.last_modified) : 'nunca — nada para restaurar'}
          tone={ultimo ? 'default' : 'danger'}
        />
        <KpiCard
          label="Backups guardados"
          value={lista.length}
          description={`de ${retencao.manter} · a retenção apaga o resto`}
        />
        <KpiCard label="Espaço ocupado" value={totalHumano} description={destino.pasta} />
        <KpiCard
          label="Agendamento diário"
          value={agendado_ok ? 'em dia' : 'parado'}
          description={agendado_ok ? 'o agendado rodou na janela' : 'só há backup manual recente'}
          tone={agendado_ok ? 'success' : 'danger'}
        />
      </Grid>

      <div data-contract="alerta-destino" className="mt-4 space-y-3">
        {/* UC-BKP-08 — a tela declara o escopo do arquivo, em vez de deixá-lo implícito. */}
        <Alert>
          <AlertTitle>O arquivo contém os dados de todos os negócios</AlertTitle>
          <AlertDescription>
            O backup é um dump do banco inteiro, não apenas do negócio atual. Quem baixa leva os dados
            de todos os clientes junto — trate o arquivo como tal.
          </AlertDescription>
        </Alert>

        {/* UC-BKP-02 */}
        {!destino.remoto && (
          <Alert variant="destructive">
            <AlertTitle>Backup no mesmo servidor não é backup</AlertTitle>
            <AlertDescription>
              O destino é o disco <code>{destino.disk}</code> (<code>{destino.pasta}</code>). Se o
              servidor cair, o backup cai junto — baixe o arquivo do dia ou aponte o destino para um
              disco remoto.
            </AlertDescription>
          </Alert>
        )}

        {!agendado_ok && lista.length > 0 && (
          <Alert variant="destructive">
            <AlertTitle>O agendado não rodou na janela recente</AlertTitle>
            <AlertDescription>
              Só existe backup manual recente. Confira se a linha de cron abaixo está no crontab e se a
              fila está de pé — enquanto isso, o backup depende de alguém clicar.
            </AlertDescription>
          </Alert>
        )}
      </div>

      <div data-contract="lista" className="mt-6">
        <Deferred data="backups" fallback={<Skeleton className="h-64 w-full" />}>
          {lista.length === 0 ? (
            <EmptyState
              title="Nenhum backup no disco."
              description={
                pode.gerar
                  ? 'Gere o primeiro agora ou espere o agendamento diário — sem arquivo, uma falha de servidor não tem volta.'
                  : 'Ninguém gerou backup ainda, e seu acesso não permite gerar.'
              }
            />
          ) : (
            <div className="overflow-x-auto rounded-lg border">
              <table className="w-full text-sm">
                <thead className="border-b border-border bg-muted/50">
                  <tr className="text-left">
                    <th className="px-4 py-2 font-semibold text-muted-foreground">Arquivo</th>
                    <th className="px-4 py-2 font-semibold text-muted-foreground">Origem</th>
                    <th className="px-4 py-2 font-semibold text-muted-foreground">Tamanho</th>
                    <th className="px-4 py-2 font-semibold text-muted-foreground">Data</th>
                    <th className="px-4 py-2 font-semibold text-muted-foreground">Idade</th>
                    <th className="px-4 py-2 text-right font-semibold text-muted-foreground">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {lista.map((b) => {
                    const unico = lista.length <= 1
                    return (
                      <tr key={b.file_name} className="border-b border-border transition-colors last:border-0 hover:bg-muted/40">
                        <td className="px-4 py-2">
                          <span className="font-mono text-sm">{b.file_name}</span>
                          <span className="block text-xs text-muted-foreground">banco + storage/app</span>
                        </td>
                        <td className="px-4 py-2">{b.origem === 'manual' ? 'Manual' : 'Agendado'}</td>
                        <td className="px-4 py-2 font-mono text-xs">{b.file_size_human}</td>
                        <td className="whitespace-nowrap px-4 py-2 font-mono text-xs">{fmtData(b.last_modified)}</td>
                        <td className="px-4 py-2 text-xs text-muted-foreground">{idade(b.last_modified)}</td>
                        <td className="px-4 py-2">
                          <Inline gap={2} justify="end">
                            <Button
                              variant="outline"
                              size="sm"
                              disabled={!pode.baixar}
                              title={pode.baixar ? undefined : pode.motivo}
                              onClick={() => { window.location.href = `/backup/download/${encodeURIComponent(b.file_name)}` }}
                            >
                              Baixar
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              disabled={unico || !pode.excluir}
                              title={unico ? 'É o único backup no disco — gere um novo antes de excluir.' : pode.motivo}
                              onClick={() => excluir(b)}
                            >
                              Excluir
                            </Button>
                          </Inline>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Deferred>
      </div>

      {cron !== '' && (
        <div data-contract="cron" className="mt-6 rounded-lg border p-4">
          <h2 className="text-sm font-semibold">Backup automático</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            O agendado só roda se esta linha existir no crontab do servidor. Sem ela, backup é só o que
            você clicar.
          </p>
          <Inline gap={2} align="center" className="mt-3">
            <code className="flex-1 overflow-x-auto rounded bg-muted px-3 py-2 text-xs">{cron}</code>
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                navigator.clipboard?.writeText(cron)
                setCopiado(true)
                setTimeout(() => setCopiado(false), 1600)
              }}
            >
              {copiado ? 'Copiado' : 'Copiar'}
            </Button>
          </Inline>
          <ul className="mt-3 space-y-1 text-xs text-muted-foreground">
            <li>Pasta: <code>{destino.pasta}</code></li>
            <li>Retenção: <code>{retencao.estrategia}</code> — guarda {retencao.manter}, apaga o resto</li>
          </ul>
        </div>
      )}
    </AppShellV2>
  )
}
