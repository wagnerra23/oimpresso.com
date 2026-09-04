// Construtor da URL de uma aba do painel "Visão geral".
//
// Vive em módulo próprio, e não dentro do `GradesPainel.tsx`, por dois motivos:
// o lint `react-refresh/only-export-components` avisa que exportar função de um
// arquivo de componente derruba o fast refresh dele; e o consumidor não é só a
// barra de abas — o painel de Pendências navega pras MESMAS abas.
//
// Duas construções do mesmo link drifam na primeira vez que um filtro novo entrar
// na tela: um dos dois caminhos perde o filtro e ninguém percebe até o usuário
// reclamar que "clicar na pendência esquece a loja".

/** Período + loja: o que qualquer link desta tela tem de carregar junto. */
export type Filtros = Record<string, string | number | null | undefined>;

/**
 * URL de uma aba, preservando os filtros da tela. O estado inteiro mora na query
 * string — anti-hook do charter: "estado do período e da loja em QUERY STRING,
 * nunca em session". A aba entra na mesma regra.
 */
export function hrefDaAba(filtros: Filtros, key: string): string {
  const params = new URLSearchParams();
  Object.entries(filtros).forEach(([k, v]) => {
    if (v !== null && v !== undefined && v !== '') params.set(k, String(v));
  });
  params.set('aba', key);
  return `${window.location.pathname}?${params.toString()}`;
}
