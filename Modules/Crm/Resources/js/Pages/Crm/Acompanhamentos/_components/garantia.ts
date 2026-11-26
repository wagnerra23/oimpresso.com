// Aba Garantia (US-CRM-094, fatia 3; CRM-30) — tipos e rótulos que a tela e a janela dividem.
// Módulo de dados, sem componente (react-refresh).

import type { TomPilula } from '../../_components/CrmBlocos';

export interface Chamado {
  id: number; prazo: 'ok' | 'vencendo' | 'vencido' | 'encerrado'; aberto: string | null; vence: string | null;
  contato: string; origem: string; tipo: string; fila: string | null; responsavel: string; status: string | null; relato: string;
}
export interface Politica { tipo_id: string | null; tipo: string; horas: number; fila: string | null; responsavel: string | null }
export interface Garantia {
  itens: Chamado[];
  contagem: { abertos: number; ok: number; vencendo: number; vencido: number };
  politicas: Politica[];
  prazo_padrao_horas: number;
  filas: string[];
}

/** Selo do prazo, nas cores do protótipo (`SLA` em crm-blade.jsx). */
export const PRAZO: Record<Chamado['prazo'], { rotulo: string; tom: TomPilula }> = {
  ok: { rotulo: 'No prazo', tom: 'ok' },
  vencendo: { rotulo: 'Vencendo', tom: 'warn' },
  vencido: { rotulo: 'Vencido', tom: 'neg' },
  encerrado: { rotulo: 'Encerrado', tom: 'mute' },
};

/** A política do tipo, senão a padrão da empresa, senão o prazo da configuração (como o serviço). */
export function politicaDe(g: Garantia | undefined, tipoId: string | null): Politica {
  const doTipo = tipoId ? g?.politicas.find((p) => p.tipo_id === tipoId) : undefined;
  return doTipo ?? g?.politicas.find((p) => p.tipo_id === null)
    ?? { tipo_id: null, tipo: 'Padrão da empresa', horas: g?.prazo_padrao_horas ?? 48, fila: null, responsavel: null };
}
