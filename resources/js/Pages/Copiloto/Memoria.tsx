// @memcofre
//   tela: /copiloto/memoria
//   stories: US-COPI-MEM-005, US-COPI-MEM-008, US-COPI-MEM-012
//   rules: R-COPI-MEM-LGPD-001, R-COPI-MEM-MULTITENANT-001
//   adrs: 0031, 0033, 0035, 0036, 0037
//   tests: tests/Feature/Modules/Copiloto/MemoriaContratoTest, tests/Feature/Modules/Copiloto/MemoriaControllerTest
//   status: implementada
//   module: Copiloto

import React, { useState } from 'react'
import AppShell from '@/Layouts/AppShell'
import { Head, router, useForm } from '@inertiajs/react'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Brain, Trash2, Pencil, Save, X } from 'lucide-react'
import FabCopiloto from './components/FabCopiloto'

interface MemoriaFato {
  id: number
  business_id: number
  user_id: number
  fato: string
  metadata: {
    categoria?: string
    relevancia?: number
    origem?: string
    [key: string]: unknown
  }
  valid_from: string | null
  valid_until: string | null
  score: number | null
}

interface Props {
  memorias: MemoriaFato[]
  businessId: number
  userId: number
}

const CATEGORIA_LABELS: Record<string, { label: string; color: string }> = {
  meta:           { label: 'Meta',           color: 'bg-blue-100 text-blue-800' },
  preferencia:    { label: 'Preferência',    color: 'bg-purple-100 text-purple-800' },
  restricao:      { label: 'Restrição',      color: 'bg-red-100 text-red-800' },
  contexto:       { label: 'Contexto',       color: 'bg-gray-100 text-gray-800' },
  acao_pendente:  { label: 'Ação pendente',  color: 'bg-amber-100 text-amber-800' },
}

function formatData(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString('pt-BR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    })
  } catch {
    return iso
  }
}

function FatoCard({ memoria }: { memoria: MemoriaFato }) {
  const [editando, setEditando] = useState(false)
  const { data, setData, patch, processing, reset } = useForm({
    fato: memoria.fato,
  })
  const cat = memoria.metadata?.categoria as string | undefined
  const catCfg = (cat && CATEGORIA_LABELS[cat]) || { label: cat || 'sem categoria', color: 'bg-gray-100 text-gray-700' }
  const rel = memoria.metadata?.relevancia as number | undefined

  const onSalvar = () => {
    patch(`/copiloto/memoria/${memoria.id}`, {
      preserveScroll: true,
      onSuccess: () => setEditando(false),
    })
  }

  const onEsquecer = () => {
    if (!confirm('Tem certeza? Essa memória será esquecida e não voltará.')) return
    router.delete(`/copiloto/memoria/${memoria.id}`, { preserveScroll: true })
  }

  return (
    <Card>
      <CardContent className="pt-6 space-y-3">
        <div className="flex items-start justify-between gap-3">
          <div className="flex items-center gap-2 flex-wrap">
            <Badge className={catCfg.color}>{catCfg.label}</Badge>
            {rel !== undefined && (
              <span className="text-xs text-muted-foreground">
                relevância {rel}/10
              </span>
            )}
            <span className="text-xs text-muted-foreground">
              · desde {formatData(memoria.valid_from)}
            </span>
          </div>
          <div className="flex gap-1">
            {!editando ? (
              <>
                <Button size="sm" variant="ghost" onClick={() => setEditando(true)} title="Editar">
                  <Pencil className="size-4" />
                </Button>
                <Button size="sm" variant="ghost" onClick={onEsquecer} title="Esquecer">
                  <Trash2 className="size-4 text-red-600" />
                </Button>
              </>
            ) : (
              <>
                <Button size="sm" variant="ghost" onClick={onSalvar} disabled={processing} title="Salvar">
                  <Save className="size-4 text-green-600" />
                </Button>
                <Button size="sm" variant="ghost" onClick={() => { reset(); setEditando(false) }} title="Cancelar">
                  <X className="size-4" />
                </Button>
              </>
            )}
          </div>
        </div>

        {!editando ? (
          <p className="text-sm">{memoria.fato}</p>
        ) : (
          <textarea
            className="w-full text-sm rounded-md border p-2 min-h-[80px]"
            value={data.fato}
            onChange={(e) => setData('fato', e.target.value)}
            disabled={processing}
          />
        )}
      </CardContent>
    </Card>
  )
}

function Memoria({ memorias }: Props) {
  // Agrupar por categoria
  const porCategoria = memorias.reduce<Record<string, MemoriaFato[]>>((acc, m) => {
    const cat = (m.metadata?.categoria as string | undefined) || 'sem_categoria'
    acc[cat] = acc[cat] || []
    acc[cat].push(m)
    return acc
  }, {})

  return (
    <>
      <Head title="O Copiloto lembra de você" />
      <div className="max-w-4xl mx-auto p-6 space-y-6">
        <div className="flex items-center gap-3">
          <Brain className="size-7 text-primary" />
          <div>
            <h1 className="text-2xl font-bold">O Copiloto lembra de você</h1>
            <p className="text-sm text-muted-foreground">
              Estes são os fatos que o Copiloto guarda sobre seu negócio. Você pode editar ou esquecer
              qualquer um a qualquer momento (LGPD).
            </p>
          </div>
        </div>

        {memorias.length === 0 ? (
          <Card>
            <CardContent className="pt-6 text-center text-muted-foreground">
              <Brain className="size-12 mx-auto mb-3 opacity-30" />
              <p>Nada lembrado ainda. Conforme você conversa, o Copiloto extrai fatos relevantes.</p>
            </CardContent>
          </Card>
        ) : (
          <>
            <div className="text-sm text-muted-foreground">
              Total: <strong>{memorias.length}</strong> {memorias.length === 1 ? 'memória ativa' : 'memórias ativas'}
            </div>

            {Object.entries(porCategoria).map(([cat, items]) => {
              const cfg = CATEGORIA_LABELS[cat] || { label: cat, color: '' }
              return (
                <div key={cat} className="space-y-3">
                  <CardHeader className="px-0 pb-2">
                    <CardTitle className="text-base flex items-center gap-2">
                      {cfg.label}
                      <span className="text-xs font-normal text-muted-foreground">
                        ({items.length})
                      </span>
                    </CardTitle>
                  </CardHeader>
                  <div className="space-y-2">
                    {items.map((m) => <FatoCard key={m.id} memoria={m} />)}
                  </div>
                </div>
              )
            })}
          </>
        )}

        <FabCopiloto />
      </div>
    </>
  )
}

Memoria.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>

export default Memoria
