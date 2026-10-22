// Quantidade do material na tela do operador (US-PCP-021). Mexe em ESTOQUE: o texto que sai daqui é o
// que o servidor baixa. Contrato do endpoint: ponto decimal e até 4 casas (ConsumoMaterialService::quantidade).

// Do jeito que o operador digita (vírgula) para o contrato. Com vírgula, ponto é milhar e sai; sem
// vírgula, o ponto já é o decimal. Inválido ou zero → null (não envia).
export function quantidadeParaEnvio(texto: string): string | null {
  const t = texto.trim();
  const q = t.includes(',') ? t.replace(/\./g, '').replace(',', '.') : t;
  return /^\d{1,14}(\.\d{1,4})?$/.test(q) && /[1-9]/.test(q) ? q : null;
}

// Para mostrar: "2.5000" → "2,5" · "10" → "10" · "3.0000" → "3". Só corta zero depois do ponto.
export function quantidadeBr(q: string): string {
  const semZeros = q.includes('.') ? q.replace(/0+$/, '').replace(/\.$/, '') : q;
  return semZeros.replace('.', ',');
}
