// CEST do produto no cadastro (UC-PCAD-15 e UC-PEDIT-12 · CU-PROD-16 do SDD).
//
// O CEST (Código Especificador da Substituição Tributária) tem 7 dígitos (Convênio ICMS 142/2018,
// cláusula sexta) e só é exigido na nota de mercadoria com substituição tributária. Por isso o
// cadastro não o exige nem avisa quando falta: avisa só quando está incompleto.

/** O CEST como o formulário guarda: só os dígitos, no máximo 7. O CEST antigo pode vir com pontos. */
export function cestDoCadastro(valor: string | null | undefined): string {
  const digitos = String(valor ?? '').replace(/\D/g, '').slice(0, 7);
  return digitos === '0' ? '' : digitos;
}

/** O aviso embaixo do campo: só quando o CEST começou a ser digitado e não tem os 7 dígitos. */
export function avisoCest(cest: string): string | null {
  return cest !== '' && cest.length < 7 ? 'O CEST tem 7 dígitos.' : null;
}
