// Tipos e regras de filtro da página Documentacao/CasosPorTela. Os dados chegam do servidor
// (CasosPorTela.php, que lê os *.casos.md); aqui não existe nenhum caso, número ou texto de pedido.

export interface Selecao { tipo: string; texto: string; nota: string }

export interface Caso {
  id: string; n: number; titulo: string; marcas: string[]; texto: string; aceite: string; fazer: string; ancora: string;
  hoje: Selecao; cv: Selecao; clientes: string[]; tags: string[]; estagio: 'pedido' | 'uc' | 'testado'; uc: string;
  tela: string; arquivo: string; linha: number; regras: string[];
}
export interface Tela { id: string; nome: string; area: string; arquivo: string; url: string }
export interface Area { id: string; nome: string; telas: string[] }
export interface Regra { id: string; titulo: string; fase: string; etapa: string; efeito: string; porte: string; status: string; nota: string }
export interface Decisao { id: string; pergunta: string; estado: string; texto: string; opcoes: { nome: string; texto: string }[]; casos: number[]; arquivo: string; linha: number }
export interface Uc { id: string; titulo: string; linha: number; veredito: string }
export interface Contrato { id: string; nome: string; modulo: string; arquivo: string; url: string; ucs: Uc[]; pedidos: number }

export interface Dados {
  resumo: Record<string, number>;
  areas: Area[]; telas: Tela[]; casos: Caso[]; regras: Regra[]; decisoes: Decisao[]; contratos: Contrato[];
  proximoPedido: string; problemas: string[];
}

export interface Marca { por: string; em: string }
export interface Resposta extends Marca { opcao: string | null; nota: string | null }
export interface Marcas { conferencia: Record<string, Marca>; decisoes: Record<string, Resposta> }

export type Tom = 'success' | 'warning' | 'danger' | 'info' | 'neutral';
/** Tom do selo para o tipo que o casos.md escreve em `Hoje:` / `Comunicação visual:`. */
export const TOM: Record<string, Tom> = { ok: 'success', part: 'warning', miss: 'danger' };

export const FILTROS: [string, string, (c: Caso, conf: Record<string, Marca>) => boolean][] = [
  ['valor', 'Mexe em valor ou estoque', (c) => c.tags.includes('valor')],
  ['cv', 'Comunicação visual', (c) => c.tags.includes('cv')],
  ['part', 'Existe em parte', (c) => c.hoje.tipo === 'part'],
  ['miss', 'Não existe', (c) => c.hoje.tipo === 'miss'],
  ['decisao', 'Depende do Wagner', (c) => c.tags.includes('decisao')],
  ['semregra', 'Sem regra citada', (c) => !c.regras.length],
  ['naoconf', 'Não conferido', (c, conf) => !conf[c.id]],
  ['uc', 'Já viraram caso de uso', (c) => c.estagio !== 'pedido'],
];

export function fmtData(iso: string): string {
  const d = new Date(iso.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

/** Texto com `código` vira <code>, sem passar HTML do dado para o DOM. */
export function partesRicas(txt: string): { codigo: boolean; texto: string }[] {
  return String(txt || '').split('`').map((texto, i) => ({ codigo: i % 2 === 1, texto }));
}
