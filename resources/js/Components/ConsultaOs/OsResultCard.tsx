import { Clock } from 'lucide-react'
import { Card, CardContent } from '@/Components/ui/card'
import { OsStageBadge } from './OsStageBadge'
import { OsPipeline } from './OsPipeline'
import type { OrdemServico } from '@/Types/os'

interface Props {
  os: OrdemServico
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">
        {label}
      </p>
      <p className="text-sm font-medium text-foreground">{value}</p>
    </div>
  )
}

export function OsResultCard({ os }: Props) {
  return (
    <Card className="overflow-hidden shadow-sm">
      <div className="px-6 py-5 border-b border-border grid grid-cols-2 gap-4">
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">
            Nº OS
          </p>
          <p className="text-sm font-mono font-semibold text-foreground">{os.id}</p>
        </div>
        <div>
          <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-1">
            Estágio atual
          </p>
          <OsStageBadge stage={os.stage} />
        </div>
        <Field label="Cliente" value={os.client} />
        <Field label="Contato" value={os.contact} />
        <Field label="Vendedor" value={os.vendedor} />
        <Field label="Designer" value={os.designer} />
      </div>

      <OsPipeline currentStage={os.stage} />

      <div className="px-6 py-5 border-b border-border">
        <p className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground mb-3">
          Itens da OS
        </p>
        <div className="flex flex-col divide-y divide-border">
          {os.items.map((item, i) => (
            <div key={i} className="py-3 first:pt-0 last:pb-0">
              <p className="text-sm font-medium text-foreground">{item.desc}</p>
              <p className="text-xs text-muted-foreground mt-0.5">
                Qtd: {item.qty} {item.unit}
              </p>
              <div className="mt-2">
                <OsStageBadge stage={item.stage} />
              </div>
            </div>
          ))}
        </div>
      </div>

      <CardContent className="px-6 py-4 bg-muted/40 flex items-center gap-2">
        <Clock className="w-3.5 h-3.5 text-muted-foreground flex-shrink-0" />
        <p className="text-xs text-muted-foreground">
          Última atualização:{' '}
          <span className="font-semibold text-foreground">{os.updated}</span>
        </p>
      </CardContent>
    </Card>
  )
}
