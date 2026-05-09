import { Package } from 'lucide-react'
import { useEffect, useState } from 'react'

interface Item {
  id: number
  ncm: string | null
  cfop: string | null
  descricao: string
  quantidade: number
  valor_unitario: number
  valor_total: number
}

function formatBrl(value: number): string {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

/**
 * Carrega itens via fetch — endpoint a ser exposto em US-NFE-053+ smoke real
 * (placeholder hoje: mostra mensagem se endpoint não retorna dados).
 */
export function LinkedItens({ dfeId }: { dfeId: number }) {
  const [itens, setItens] = useState<Item[] | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    fetch(`/nfe-brasil/manifestacao/${dfeId}/itens`, {
      headers: { Accept: 'application/json' },
    })
      .then((r) => (r.ok ? r.json() : { itens: [] }))
      .then((data) => setItens(data.itens ?? []))
      .catch(() => setItens([]))
      .finally(() => setLoading(false))
  }, [dfeId])

  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center gap-2 mb-3 text-sm font-medium">
        <Package size={14} className="text-muted-foreground" />
        Itens
        {itens && <span className="text-xs text-muted-foreground">({itens.length})</span>}
      </div>
      {loading && <div className="text-xs text-muted-foreground">Carregando...</div>}
      {!loading && itens?.length === 0 && (
        <div className="text-xs text-muted-foreground">Sem itens parseados (XML resNFe não inclui detalhe).</div>
      )}
      {!loading && itens && itens.length > 0 && (
        <div className="space-y-2 max-h-64 overflow-y-auto">
          {itens.map((item) => (
            <div key={item.id} className="text-sm py-1 border-b border-border last:border-b-0">
              <div className="font-medium truncate">{item.descricao}</div>
              <div className="flex justify-between text-xs text-muted-foreground mt-0.5">
                <span>
                  NCM {item.ncm ?? '—'} · CFOP {item.cfop ?? '—'}
                </span>
                <span className="font-mono tabular-nums">{formatBrl(item.valor_total)}</span>
              </div>
              <div className="text-xs text-muted-foreground">
                Qtd <span className="font-mono">{item.quantidade}</span> ×{' '}
                <span className="font-mono">{formatBrl(item.valor_unitario)}</span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
