// Rótulos dos marcadores de condição (lista fechada em Patient::CONDITION_FLAGS).
// O código vem do servidor; aqui só o texto que o fisioterapeuta lê.
export const ROTULO_CONDICAO: Record<string, string> = {
  protese_quadril_posterior: 'Prótese de quadril (via posterior)',
  protese_quadril_anterior: 'Prótese de quadril (via anterior)',
  pos_op_manguito: 'Pós-operatório de manguito rotador',
  pos_lca: 'Pós-operatório de LCA',
  gestante: 'Gestante',
  osteoporose: 'Osteoporose',
  risco_queda: 'Risco de queda',
  hipertensao_nao_controlada: 'Hipertensão não controlada',
};

export const rotuloCondicao = (codigo: string) => ROTULO_CONDICAO[codigo] ?? codigo;

/** Remove linhas vazias dos objetivos antes de enviar. */
export const limparObjetivos = (goals: string[]) => goals.map((g) => g.trim()).filter(Boolean);
