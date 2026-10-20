// NCM do produto no cadastro (UC-PCAD-13 e UC-PCAD-14 · CU-PROD-16 e CU-PROD-18 do SDD).
//
// O NCM vai na nota fiscal de mercadoria e tem 8 dígitos. O cadastro não o exige: produto de
// serviço não tem NCM, e o cadastro mínimo é nome e SKU (decisão [M] 2026-10-20). Sem NCM a
// tela só avisa.

/**
 * O NCM como o formulário guarda: só os dígitos, no máximo 8. O banco grava '0' quando o produto
 * nasce sem NCM (DEFAULT da coluna), e o NCM antigo pode vir com pontos ("4911.10.90").
 */
export function ncmDoCadastro(valor: string | null | undefined): string {
  const digitos = String(valor ?? '').replace(/\D/g, '').slice(0, 8);
  return digitos === '0' ? '' : digitos;
}

/** O aviso embaixo do campo. Nenhum quando o NCM tem os 8 dígitos. */
export function avisoNcm(ncm: string): string | null {
  if (ncm === '') {
    return 'Sem NCM a nota fiscal de mercadoria não sai. Dá para salvar e preencher depois.';
  }
  if (ncm.length < 8) {
    return 'O NCM tem 8 dígitos.';
  }
  return null;
}
