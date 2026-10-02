// resources/js/Types/os.ts
// Tipos do portal publico de consulta de OS (/consulta-os).
//
// US-CONSULTA-001 (2026-10-02): os dados vem das folhas de OS reais do Modules/Repair.
// O payload e uma whitelist montada no servidor (RepairConsultaOsRepository) — so o que
// o portal antigo /repair-status ja mostrava. Nada de custo, notas internas ou dado do cliente.

export type TipoBusca = 'job_sheet_no' | 'invoice_no' | 'mobile_num'

export interface OsAtividade {
  data: string | null
  acao: string
  por: string | null
  nota: string | null
  conclusao_de: string | null
  conclusao_para: string | null
}

export interface OrdemServico {
  numero: string
  marca: string | null
  aparelho: string | null
  modelo: string | null
  serie: string | null
  status: { nome: string | null; cor: string | null }
  previsao_entrega: string | null
  atividades: OsAtividade[]
}

export interface BuscarResponse {
  found: boolean
  ordens?: OrdemServico[]
}

export const TIPO_BUSCA_LABEL: Record<TipoBusca, string> = {
  job_sheet_no: 'Nº da OS',
  invoice_no: 'Nº da venda',
  mobile_num: 'Celular',
}

/** Data/hora ISO do servidor → texto pt-BR. Vazio quando nao ha data. */
export function formatarDataHora(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}
