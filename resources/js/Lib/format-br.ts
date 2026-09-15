// Helpers de formatação BR — máscaras dinâmicas pra inputs.
// Slice 2 da restauração dos campos fiscais BR (PR pós #1313).
//
// Não fazem validação mod-11 — Rule\BR\CpfCnpj cuida disso no backend.
// Aqui só aplica/remove máscara visual.

/**
 * Retorna apenas dígitos do valor (remove pontuação, espaços, etc).
 *   "123.456.789-09" → "12345678909"   // pii-allowlist (placeholder de formato sintetico, docblock)
 */
export function unmaskDigits(value: string | null | undefined): string {
  if (!value) return '';
  return value.replace(/\D/g, '');
}

/**
 * Máscara dinâmica CPF (11 dígitos) ou CNPJ (14 dígitos).
 *   "12345678909"     → "123.456.789-09"   (CPF)   // pii-allowlist (placeholder de formato sintetico, docblock)
 *   "12345678000195"  → "12.345.678/0001-95" (CNPJ)   // pii-allowlist (placeholder de formato sintetico, docblock)
 *   "123"             → "123"               (incomplete, no mask)
 *
 * Detecção pelo comprimento numérico:
 *   ≤ 11 dígitos → formata progressivamente como CPF
 *   > 11 dígitos → formata progressivamente como CNPJ (até 14)
 *
 * Trunca em 14 dígitos (máx CNPJ).
 */
export function formatCpfCnpj(value: string | null | undefined): string {
  const digits = unmaskDigits(value).slice(0, 14);
  if (digits.length === 0) return '';

  // CPF: até 11 dígitos
  if (digits.length <= 11) {
    return digits
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
  }

  // CNPJ: 12 a 14 dígitos
  return digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
}

/**
 * Máscara CEP — 8 dígitos → "00000-000"
 */
export function formatCep(value: string | null | undefined): string {
  const digits = unmaskDigits(value).slice(0, 8);
  if (digits.length === 0) return '';
  if (digits.length <= 5) return digits;
  return digits.replace(/^(\d{5})(\d)/, '$1-$2');
}

/**
 * Máscara telefone BR — (XX) XXXX-XXXX ou (XX) 9XXXX-XXXX.
 *
 *   "11999998888"  → "(11) 99999-8888"   (celular 11d)
 *   "1133334444"   → "(11) 3333-4444"    (fixo 10d)
 *   "11"           → "(11) "
 */
export function formatPhone(value: string | null | undefined): string {
  const digits = unmaskDigits(value).slice(0, 11);
  if (digits.length === 0) return '';
  if (digits.length <= 2) return `(${digits}`;
  if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  if (digits.length <= 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
  }
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

/**
 * Labels canônicos do `indicador_ie` (NFe SEFAZ).
 * 1 = Contribuinte ICMS regularmente inscrito
 * 2 = Contribuinte isento de inscrição estadual
 * 9 = Não contribuinte (pessoa física, etc)
 */
export const INDICADOR_IE_OPTIONS: Array<{ value: string; label: string }> = [
  { value: '', label: '— Selecione —' },
  { value: '1', label: '1 — Contribuinte ICMS' },
  { value: '2', label: '2 — Contribuinte isento de inscrição' },
  { value: '9', label: '9 — Não contribuinte' },
];

/**
 * Labels canônicos do `regime` tributário.
 */
export const REGIME_TRIBUTARIO_OPTIONS: Array<{ value: string; label: string }> = [
  { value: '', label: '— Selecione —' },
  { value: 'simples', label: 'Simples Nacional' },
  { value: 'presumido', label: 'Lucro Presumido' },
  { value: 'real', label: 'Lucro Real' },
  { value: 'mei', label: 'MEI' },
];

/**
 * REDAÇÃO pra EXIBIÇÃO — mostra só os últimos `visiveis` dígitos, o resto vira `•`.
 *
 * ⚠️ Não é máscara de input, e é por isso que vive aqui embaixo com aviso próprio: o resto deste
 * arquivo (e o `br-mask.ts`) formata o que o usuário DIGITA. Isto faz o oposto — esconde o que o
 * usuário NÃO precisa ver. Misturar os dois conceitos foi o que me fez procurar `maskCPF` pra
 * redigir e achar que já existia: `maskCPF` insere pontos e hífen, nunca esconde dígito.
 *
 * Origem: `D-COLAB-CPF` ([W] 2026-09-14) na lista de colaboradores do Ponto — lista é tela de
 * varredura, então minimização de dado é o default (LGPD Art. 6º III); o documento inteiro só
 * aparece no form de edição. A forma (últimos 3) vem do protótipo
 * `prototipo-ui/cowork/Wagner/ponto-telas.jsx`, que é soberano no eixo FORMA (ADR UI-0029).
 *
 * NÃO é controle de acesso: quem não pode ver o dado não deve receber o dado do backend. Isto
 * reduz exposição acidental de tela (ombro, print, screenshare), nada mais — o controle real é
 * permissão, como [W] fixou em 2026-08-21 ("o controle é por permissão de acesso").
 *
 *   redigirDigitos("312.884.907-21")      → "••••••••721"   // pii-allowlist (sintético; saída conferida rodando, não de cabeça)
 *   redigirDigitos("12345678901")         → "••••••••901"
 *   redigirDigitos("12345678901", 4)      → "•••••••8901"
 *   redigirDigitos(null)                  → "—"
 *   redigirDigitos("12")                  → "••"   (nunca revela mais do que tem)
 */
export function redigirDigitos(value: string | null | undefined, visiveis = 3): string {
  const d = unmaskDigits(value);
  if (!d) return '—';
  // Valor mais curto que a janela visível seria revelado INTEIRO se a subtração ficasse negativa —
  // e `slice(-3)` de "12" devolve "12". Então quando não dá pra esconder, esconde tudo.
  if (d.length <= visiveis) return '•'.repeat(d.length);
  return '•'.repeat(d.length - visiveis) + d.slice(-visiveis);
}
