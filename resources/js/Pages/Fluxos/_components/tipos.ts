// Tipos da tela Sistema › Fluxos e regras. Espelham o que FluxosController manda
// (formatação de App\Domain\Fsm\Modelos\CatalogoModelos — nada é calculado aqui).

export interface Opcao {
  value: string;
  label: string;
  /** Só nos portes: "6 a 20 pessoas". */
  descricao?: string;
}

export interface Regra {
  etapa: string;
  efeito: string;
  uc: string;
}

export interface ProcessoDoModelo {
  dominio: string;
  nome: string;
  etapas: string[];
  regras: Regra[];
}

export interface Modelo {
  id: string;
  nome: string;
  para: string;
  portes: string[];
  atividades: string[];
  casos: string[];
  /** Sistemas que inspiraram o modelo (ids de `fontes`). */
  insp: string[];
  processos: ProcessoDoModelo[];
}

export interface Encaixe {
  n: number;
  rotulo: string;
  motivo: string;
}

/** encaixe[modeloId][porte][atividade] */
export type MapaEncaixe = Record<string, Record<string, Record<string, Encaixe>>>;

export interface Perfil {
  porte: string;
  ativ: string;
}

/** id do caso (UC-FLX-NN) => título e domínio, de CatalogoModelos::CASOS. */
export type MapaCasos = Record<string, { titulo: string; dominio: string }>;

/** Modelo em uso na empresa da sessão; `modelo = 'legado'` = processos de antes do catálogo. */
export interface EmpresaModelo {
  modelo: string;
  porte: string | null;
  atividade: string | null;
  aplicado_em: string | null;
}

/** Processo do CatalogoProcessos (aba Processos). `tipo` do estado: ini | nor | esp | ok | x. */
export interface ProcessoCatalogo {
  id: string;
  area: string;
  nome: string;
  doc: string;
  porque: string;
  liga: string[];
  roteiro: { titulo: string; trilhas: string[][]; junta: string[] } | null;
  estados: { chave: string; nome: string; tipo: string; lado: boolean }[];
  /** `de`/`para` são chaves de estado; `para` null = a ação não muda o estado. */
  transicoes: { de: string; para: string | null; rotulo: string; guarda: string; efeito: string; quem: string }[];
}

/** Caso de uso do catálogo (aba Casos de uso). `processo` é o domínio; `porte` = a partir de. */
export interface CasoCatalogo {
  id: string;
  titulo: string;
  processo: string;
  etapa: string;
  regra: string;
  fontes: string[];
  porte: string;
  estado: string;
  us: string;
  /** Ids dos modelos que usam o caso. */
  modelos: string[];
}

/** id da fonte => nome e endereço (url vazia = proposta própria). */
export type MapaFontes = Record<string, { nome: string; url: string }>;

/** estado do caso no oimpresso => rótulo e tom (ok · accent · warn · danger · mute). */
export type MapaEstadosCaso = Record<string, { rotulo: string; tom: string }>;

/** domínio => ligada de cada regra do modelo em uso, na ordem do catálogo (RegrasDaEmpresa::ligadas). */
export type RegrasLigadas = Record<string, boolean[]>;

/** Regra das etapas da Compra com o que a empresa gravou (RegrasCompra::daEmpresa). */
export interface RegraCompra {
  id: string;
  titulo: string;
  desc: string;
  trava: boolean;
  ativo: boolean;
  params: Record<string, string | number> | null;
  /** Etapa onde a regra dispara (a do custo pode ir para Conferido). */
  etapa_efetiva: string;
}

/** parâmetro => rótulo e opções (RegrasCompra::opcoes). */
export type MapaOpcoesCompra = Record<string, { rotulo: string; opcoes: Opcao[] }>;
