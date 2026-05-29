import type { DadosFiscaisBRData } from "./DadosFiscaisBRSection"

/**
 * Campos do formulário compartilhados por Cliente/Create e Cliente/Edit
 * (os dois dividem ~90%). Cada página estende este shape com o que é seu
 * (ex.: Create tem `prefix`). Usado por ClienteForm/ClienteRail genéricos.
 */
export type ClienteFormShared = DadosFiscaisBRData & {
  type: string
  contact_type_radio: string
  first_name: string
  middle_name: string
  last_name: string
  supplier_business_name: string
  tax_number: string
  mobile: string
  landline: string
  email: string
  address_line_1: string
  city: string
  state: string
  zip_code: string
  customer_group_id: string
  opening_balance: string
  credit_limit: string
}

export interface CustomerGroup {
  id: number
  name: string
}
