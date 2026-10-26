// Filtro da produção (UC-PCV-06): uma regra só para o Quadro e para a Linha do tempo (thread 07).
// O filtro mora na URL — `?q=&so=&resp=&setor=` — para trocar de vista sem perder o que se filtrou.
// O servidor normaliza os mesmos parâmetros (QuadroController::index) e devolve em `filtro`.
// Quem filtrar OS na Produção importa daqui; um segundo `passa` em outra tela é a regra divergindo.

export type Situacao = 'todas' | 'atrasadas' | 'arte';
export interface Filtro { q: string; so: Situacao; resp: string; setor: string }
export const LIMPO: Filtro = { q: '', so: 'todas', resp: '', setor: '' };
export const SEM_SETOR = 'Sem setor';

/** O mínimo que uma OS precisa ter para passar no filtro (cartão do quadro ou bloco da linha). */
export interface Filtravel {
  numero: string; cliente: string | null; titulo: string | null;
  atraso: string | null; arte: string | null;
  etapa: { responsavel: string | null; setor: string | null };
  /** Coluna do quadro quando o PCP moveu a OS à mão (UC-PCV-07); sem ela vale o setor da etapa. */
  coluna?: string | null;
}

export const setorDe = (e: { setor: string | null }) => e.setor?.trim() || SEM_SETOR;
export const colunaDe = (c: Filtravel) => c.coluna || setorDe(c.etapa);

export function passa(c: Filtravel, f: Filtro): boolean {
  if (f.q) {
    const s = f.q.toLowerCase();
    if (![c.numero, c.cliente, c.titulo].some((v) => v?.toLowerCase().includes(s))) return false;
  }
  if (f.so === 'atrasadas' && c.atraso === null) return false;
  if (f.so === 'arte' && c.arte === null) return false;
  if (f.resp && c.etapa.responsavel !== f.resp) return false;
  if (f.setor && colunaDe(c) !== f.setor) return false;
  return true;
}

export const filtrado = (f: Filtro) => !!(f.q || f.so !== 'todas' || f.resp || f.setor);

/** Querystring do filtro, sem os campos vazios ("Limpar" devolve ''). */
export function paraUrl(f: Filtro, extra: Record<string, string> = {}): string {
  const u = new URLSearchParams();
  if (f.q) u.set('q', f.q);
  if (f.so !== 'todas') u.set('so', f.so);
  if (f.resp) u.set('resp', f.resp);
  if (f.setor) u.set('setor', f.setor);
  Object.entries(extra).forEach(([k, v]) => v && u.set(k, v));
  const s = u.toString();
  return s ? '?' + s : '';
}
