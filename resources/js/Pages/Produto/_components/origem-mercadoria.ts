// Origem da mercadoria — tabela A da NF-e (`orig`, códigos 0 a 8). AR-PROD-128.
// Arquivo próprio para o componente do campo exportar só componente (react-refresh).
export const ORIGENS_MERCADORIA: ReadonlyArray<readonly [number, string]> = [
  [0, 'Nacional'],
  [1, 'Estrangeira — importação direta'],
  [2, 'Estrangeira — adquirida no mercado interno'],
  [3, 'Nacional — conteúdo de importação acima de 40% e até 70%'],
  [4, 'Nacional — produzida conforme processos produtivos básicos'],
  [5, 'Nacional — conteúdo de importação até 40%'],
  [6, 'Estrangeira — importação direta, sem similar nacional (lista CAMEX)'],
  [7, 'Estrangeira — mercado interno, sem similar nacional (lista CAMEX)'],
  [8, 'Nacional — conteúdo de importação acima de 70%'],
];
