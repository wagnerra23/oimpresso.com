import { History } from 'lucide-react'
import { useEffect, useState } from 'react'

interface Evento {
  id: number
  tipo: string
  status: string
  cstat_evento: string | null
  created_at: string
}

const TIPO_LABEL: Record<string, string> = {
  '210210': 'Ciência',
  '210200': 'Confirmação',
  '210220': 'Desconhecimento',
  '210240': 'Não realizada',
}

export function LinkedHistorico({ dfeId }: { dfeId: number }) {
  const [eventos, setEventos] = useState<Evento[] | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    fetch(`/nfe-brasil/manifestacao/${dfeId}/eventos`, {
      headers: { Accept: 'application/json' },
    })
      .then((r) => (r.ok ? r.json() : { eventos: [] }))
      .then((data) => setEventos(data.eventos ?? []))
      .catch(() => setEventos([]))
      .finally(() => setLoading(false))
  }, [dfeId])

  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center gap-2 mb-3 text-sm font-medium">
        <History size={14} className="text-muted-foreground" />
        Histórico
        {eventos && <span className="text-xs text-muted-foreground">({eventos.length})</span>}
      </div>
      {loading && <div className="text-xs text-muted-foreground">Carregando...</div>}
      {!loading && eventos?.length === 0 && (
        <div className="text-xs text-muted-foreground">Nenhuma manifestação aplicada ainda.</div>
      )}
      {!loading && eventos && eventos.length > 0 && (
        <div className="space-y-2">
          {eventos.map((evento) => (
            <div key={evento.id} className="text-sm flex items-start gap-2 py-1">
              <div
                className={`w-1.5 h-1.5 rounded-full mt-1.5 ${
                  evento.status === 'autorizado' ? 'bg-emerald-500' : 'bg-amber-500'
                }`}
              />
              <div className="flex-1 min-w-0">
                <div className="font-medium">{TIPO_LABEL[evento.tipo] ?? evento.tipo}</div>
                <div className="text-xs text-muted-foreground">
                  {new Date(evento.created_at).toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                  {evento.cstat_evento && (
                    <span className="ml-2 font-mono">cStat {evento.cstat_evento}</span>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
