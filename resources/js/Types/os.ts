// resources/js/types/os.ts
// Tipos compartilhados para o portal publico de consulta de OS.

export type OsStage =
  | 'rascunho'
  | 'orcado'
  | 'aprovacao'
  | 'producao'
  | 'acabamento'
  | 'expedicao'
  | 'entregue'
  | 'cancelado'

export interface OsItem {
  desc: string
  qty: number
  unit: string
  stage: OsStage
}

export interface OrdemServico {
  id: string
  client: string
  contact: string
  vendedor: string
  designer: string
  created: string
  updated: string
  stage: OsStage
  items: OsItem[]
}

export interface BuscarResponse {
  found: boolean
  os?: OrdemServico
}

export const STAGE_META: Record<OsStage, { label: string; variant: string }> = {
  rascunho:   { label: 'Rascunho',           variant: 'secondary'   },
  orcado:     { label: 'Orçado',             variant: 'warning'     },
  aprovacao:  { label: 'Aprovação de arte',  variant: 'warning'     },
  producao:   { label: 'Em produção',        variant: 'info'        },
  acabamento: { label: 'Acabamento',         variant: 'info'        },
  expedicao:  { label: 'Pronto p/ retirada', variant: 'cyan'        },
  entregue:   { label: 'Entregue',           variant: 'success'     },
  cancelado:  { label: 'Cancelado',          variant: 'destructive' },
}

export const PIPELINE_STEPS: { id: OsStage; label: string }[] = [
  { id: 'orcado',     label: 'Orçado' },
  { id: 'aprovacao',  label: 'Arte em aprovação' },
  { id: 'producao',   label: 'Em produção' },
  { id: 'acabamento', label: 'Acabamento' },
  { id: 'expedicao',  label: 'Pronto p/ retirada' },
  { id: 'entregue',   label: 'Entregue' },
]

export const FILTER_OPTIONS = [
  { value: 'todos',     label: 'Todos os estágios' },
  { value: 'aprovacao', label: 'Aprovação de arte' },
  { value: 'producao',  label: 'Material pronto' },
  { value: 'expedicao', label: 'Pronto para retirada' },
  { value: 'entregue',  label: 'Entregue ao cliente' },
] as const
