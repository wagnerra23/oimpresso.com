import { Card } from '@/Components/ui/card'
import { Inline, Stack } from '@/Components/layout'
import { formatarDataHora, type OrdemServico } from '@/Types/os'

interface Props {
  os: OrdemServico
}

// O que este cartao mostra e exatamente a whitelist do servidor (RepairConsultaOsRepository):
// nº da OS, marca, aparelho, modelo, nº de serie, status com cor, previsao e atividades.
// Nao acrescente campo aqui sem acrescentar la — e la so com decisao [W] (charter, Non-Goals).

function Field({ label, value }: { label: string; value: string | null }) {
  return (
    <div>
      <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">
        {label}
      </p>
      <p className="text-sm font-medium text-foreground">{value || '—'}</p>
    </div>
  )
}

export function OsResultCard({ os }: Props) {
  const previsao = formatarDataHora(os.previsao_entrega)

  return (
    <Card className="overflow-hidden shadow-sm">
      <div className="px-6 py-5 border-b border-border grid grid-cols-2 gap-4">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">
            Nº OS
          </p>
          <p className="text-sm font-mono font-semibold text-foreground">{os.numero}</p>
        </div>
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">
            Status atual
          </p>
          <Inline gap={2} align="center">
            <span
              aria-hidden="true"
              className="inline-block w-2.5 h-2.5 rounded-full border border-border"
              style={os.status.cor ? { backgroundColor: os.status.cor } : undefined}
            />
            <span className="text-sm font-semibold text-foreground">
              {os.status.nome || 'Sem status'}
            </span>
          </Inline>
        </div>
        <Field label="Marca" value={os.marca} />
        <Field label="Aparelho" value={os.aparelho} />
        <Field label="Modelo" value={os.modelo} />
        <Field label="Nº de série" value={os.serie} />
        <Field label="Previsão de entrega" value={previsao} />
      </div>

      <div className="px-6 py-5">
        <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-3">
          Andamento
        </p>
        {os.atividades.length === 0 ? (
          <p className="text-sm text-muted-foreground">Nenhuma movimentação registrada ainda.</p>
        ) : (
          <Stack gap={0} divider>
            {os.atividades.map((a, i) => (
              <div key={i} className="py-3 first:pt-0 last:pb-0">
                <p className="text-sm font-medium text-foreground">{a.acao}</p>
                <p className="text-xs text-muted-foreground mt-0.5">
                  {formatarDataHora(a.data)}
                  {a.por ? ` · ${a.por}` : ''}
                </p>
                {a.nota && <p className="text-sm text-foreground mt-1">{a.nota}</p>}
                {(a.conclusao_de || a.conclusao_para) && (
                  <p className="text-xs text-muted-foreground mt-1">
                    Data de conclusão alterada de {formatarDataHora(a.conclusao_de) || '—'} para{' '}
                    {formatarDataHora(a.conclusao_para) || '—'}
                  </p>
                )}
              </div>
            ))}
          </Stack>
        )}
      </div>
    </Card>
  )
}
