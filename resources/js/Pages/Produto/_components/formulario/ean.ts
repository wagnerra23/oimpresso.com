// Código de barras EAN/UPC do produto (UC-PCAD-17 e UC-PEDIT-15 · CU-PROD-18 do SDD).
//
// O código de barras do produto é o SKU; o "Tipo de código de barras" só diz como desenhar a
// etiqueta. Com EAN-13, EAN-8 ou UPC-A, a biblioteca da etiqueta (milon/barcode v13.1) recusa
// SKU com letra e SKU com o último dígito errado: a etiqueta não sai. Código mais curto ela
// completa com zeros. O aviso não impede salvar.
//
// A nota fiscal não usa este código hoje: o emissor do NfeBrasil manda "SEM GTIN".

interface RegraGtin {
  nome: string;
  digitos: number;
}

const REGRAS: Record<string, RegraGtin> = {
  EAN13: { nome: 'EAN-13', digitos: 13 },
  EAN8: { nome: 'EAN-8', digitos: 8 },
  UPCA: { nome: 'UPC-A', digitos: 12 },
};

/** Dígito verificador GS1: pesos 3 e 1 alternados, da direita para a esquerda. */
export function digitoVerificadorGtin(corpo: string): number {
  let soma = 0;
  for (let i = 0; i < corpo.length; i++) {
    const digito = corpo.charCodeAt(corpo.length - 1 - i) - 48;
    soma += i % 2 === 0 ? digito * 3 : digito;
  }
  return (10 - (soma % 10)) % 10;
}

/** O aviso embaixo do tipo de código de barras. Nenhum para Code 128, Code 39 e UPC-E. */
export function avisoCodigoBarras(sku: string | null | undefined, tipo: string | null | undefined): string | null {
  const regra = tipo ? REGRAS[tipo] : undefined;
  const codigo = String(sku ?? '').trim();
  if (!regra || codigo === '') {
    return null;
  }
  if (!/^\d+$/.test(codigo)) {
    return `O ${regra.nome} só tem números, e este SKU tem letra ou símbolo. A etiqueta com esse tipo de código não sai.`;
  }
  if (codigo.length > regra.digitos) {
    return `O ${regra.nome} tem ${regra.digitos} dígitos, e este SKU tem ${codigo.length}.`;
  }
  if (codigo.length === regra.digitos) {
    const esperado = digitoVerificadorGtin(codigo.slice(0, -1));
    if (codigo.charCodeAt(codigo.length - 1) - 48 !== esperado) {
      return `O último dígito do ${regra.nome} não confere com os outros (devia ser ${esperado}). A etiqueta com esse tipo de código não sai.`;
    }
  }
  return null;
}
