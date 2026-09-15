
/** Every domain the component actually maps (11). */
export type StatusKind =
  | 'documento'      // ERP approval workflow: rascunho | pendente | aprovado | rejeitado | aplicado | cancelado
  | 'fiscal'         // NF-e/NFC-e/MDF-e: rascunho | emitida | autorizada | cancelada | denegada | rejeitada | inutilizada
  | 'os'             // Ordem de serviço FSM: aberta | orcamento | aprovada | em_servico | em_producao | concluida | entregue | cancelada | atrasada
  | 'intercorrencia' // Ponto/RH: rascunho | pendente | aprovada | rejeitada | aplicada | cancelada
  | 'prioridade'     // baixa | normal | alta | urgente (urgente pulses)
  | 'payment'        // pending | partial | paid | due | overdue
  | 'rep'            // Ponto/RH device: rep_p | rep_c | rep_a
  | 'sla'            // freshness: fresh | aging | late | expired
  | 'atendimento'    // channel: email | instagram | facebook | mercadolivre | whatsapp
  | 'frescor'        // CRM recency: recente | fresc | frio | distante
  | 'tipo';          // registry type: pj | pf (mono pill)

/** Visual tones. Solid/outline are generic; the prefixed ones are domain-tinted soft pills. */
export type StatusTone =
  | 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'outline'
  | 'sla-fresh' | 'sla-aging' | 'sla-late' | 'sla-expired'
  | 'canal-email' | 'canal-ig' | 'canal-fb' | 'canal-ml'
  | 'fresc-hot' | 'fresc-warm' | 'fresc-cold'
  | 'tipo-pj' | 'tipo-pf';

export interface StatusBadgeProps {
  /** Domain whose value→label/tone mapping to use. */
  kind: StatusKind;
  /** Raw status value (case-insensitive), e.g. "aprovada", "overdue", "urgente". */
  value: string;
  /** Optional label override (when the DB returns a different string). */
  label?: string;
  /** Optional relative-time suffix (frescor), e.g. "há 1sem". */
  rel?: string;
  /**
   * Force the visual tone, overriding the domain mapping. Use for a status the
   * domain map doesn't know yet (pass `label` too) — not to re-color a mapped one.
   */
  tone?: StatusTone;
}

/** Domain-mapped status pill (DS v6). Unmapped value → outline pill with the raw value as label. */
export declare function StatusBadge(props: StatusBadgeProps): JSX.Element;
