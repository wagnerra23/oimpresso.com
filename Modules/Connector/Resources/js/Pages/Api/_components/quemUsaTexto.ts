// CONN-O7 · tipo e texto do "quem usa" (UC-CONN-21). Fora do QuemUsa.tsx para o arquivo
// do componente exportar só componente (react-refresh/only-export-components).

export interface TokenUso { user_name: string; last_used_at: string | null; expires_at: string | null }

/** "Ana, Bruno e mais 3" — usado na confirmação de excluir para dizer QUEM perde acesso. */
export function nomesQuePerdem(tokens: TokenUso[], resto: number): string {
  const nomes = Array.from(new Set(tokens.map((t) => t.user_name || 'usuário sem nome')));
  if (nomes.length === 0) return '';
  const mais = resto > 0 ? ` e mais ${resto} acesso${resto > 1 ? 's' : ''}` : '';
  if (nomes.length === 1) return `${nomes[0]}${mais}`;
  return resto > 0 ? `${nomes.join(', ')}${mais}` : `${nomes.slice(0, -1).join(', ')} e ${nomes[nomes.length - 1]}`;
}
