// Tipos e rótulos da tela de associações (US-ASSOC-002 · ADR 0451). Rótulos em PT-BR; os
// valores (situação, tipo de documento, estado da pendência) vêm do servidor e são listas fechadas.

export type EstadoPendencia = 'em_dia' | 'pendente' | 'atrasada';

export interface Pendencia {
  estado: EstadoPendencia;
  mudou_em: string | null;
  vence_em: string | null;
  dias_atraso: number;
}

export interface LinhaAssociacao {
  id: number;
  nome: string;
  sigla: string | null;
  cidade: string;
  regiao: string | null;
  situacao: string;
  representante: string | null;
  documentos: number;
  pendencia: Pendencia;
}

export interface DocumentoFicha {
  id: number;
  tipo: string;
  descricao: string | null;
  validade: string | null;
  vencido: boolean;
  enviado_em: string;
  url: string;
}

export interface Ficha {
  id: number;
  nome: string;
  sigla: string | null;
  cidade: string;
  regiao: string | null;
  cnpj: string | null;
  situacao: string;
  diretoria_atualizada_em: string | null;
  pendencia: Pendencia;
  representantes: number[];
  usuarios: { id: number; nome: string }[];
  documentos: DocumentoFicha[];
}

export const SITUACOES: Record<string, string> = {
  em_adesao: 'Em adesão',
  ativa: 'Ativa',
  desligada: 'Desligada',
};

export const TIPOS_DOCUMENTO: Record<string, string> = {
  estatuto: 'Estatuto',
  ata_diretoria: 'Ata da diretoria',
  certidao: 'Certidão',
  outro: 'Outro',
};

export const dataBR = (iso: string | null) =>
  iso ? new Date(`${iso.slice(0, 10)}T12:00:00`).toLocaleDateString('pt-BR') : '—';

/** Texto curto da pendência de diretoria (D7). Avisa; nunca bloqueia. */
export function rotuloPendencia(p: Pendencia): string {
  if (p.estado === 'em_dia') return 'Em dia';
  if (p.estado === 'atrasada') return `Ata atrasada (${p.dias_atraso} ${p.dias_atraso === 1 ? 'dia' : 'dias'})`;
  return `Aguardando ata até ${dataBR(p.vence_em)}`;
}

type VarianteEstado = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export const VARIANTE_SITUACAO: Record<string, VarianteEstado> = { ativa: 'success', em_adesao: 'info', desligada: 'neutral' };

export const VARIANTE_PENDENCIA: Record<EstadoPendencia, VarianteEstado> = { em_dia: 'neutral', pendente: 'warning', atrasada: 'danger' };
