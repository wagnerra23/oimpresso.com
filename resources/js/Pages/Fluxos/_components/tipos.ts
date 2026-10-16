// Tipos da tela Sistema › Fluxos e regras. Espelham o que FluxosController manda
// (formatação de App\Domain\Fsm\Modelos\CatalogoModelos — nada é calculado aqui).

export interface Opcao {
  value: string;
  label: string;
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
