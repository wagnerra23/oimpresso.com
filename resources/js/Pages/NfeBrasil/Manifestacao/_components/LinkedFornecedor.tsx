import { Building2 } from 'lucide-react'

interface Dfe {
  cnpj_emitente: string
  nome_emitente: string | null
  valor_total: number
}

function formatCnpj(cnpj: string): string {
  if (!cnpj || cnpj.length !== 14) return cnpj
  return `${cnpj.slice(0, 2)}.${cnpj.slice(2, 5)}.${cnpj.slice(5, 8)}/${cnpj.slice(8, 12)}-${cnpj.slice(12)}`
}

function formatBrl(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export function LinkedFornecedor({ dfe }: { dfe: Dfe }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center gap-2 mb-3 text-sm font-medium">
        <Building2 size={14} className="text-muted-foreground" />
        Fornecedor
      </div>
      <div className="space-y-1 text-sm">
        <div className="font-medium">{dfe.nome_emitente ?? '—'}</div>
        <div className="font-mono text-xs text-muted-foreground">{formatCnpj(dfe.cnpj_emitente)}</div>
      </div>
      <div className="mt-3 pt-3 border-t border-border text-xs text-muted-foreground">
        <div>Valor desta NF-e</div>
        <div className="font-mono tabular-nums text-base text-foreground mt-0.5">{formatBrl(dfe.valor_total)}</div>
      </div>
    </div>
  )
}
