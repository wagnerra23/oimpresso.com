// Tipos + formatadores da seção Metas do Painel (`/ia`).
//
// Arquivo SEM componente de propósito: `Index.tsx` e `JanaMetaDrawer.tsx`
// precisam dos mesmos tipos e das mesmas funções, e exportar não-componente de
// um arquivo de componente quebra o `react-refresh` (a regressão apareceu no
// `lint:baseline:check` quando o `useJanaConfig` nasceu dentro do drawer de
// configuração — a saída certa foi separar, não regravar o baseline).
//
// ⛔ Nada aqui CALCULA veredito. O farol é do servidor (`ApuracaoService::farol`,
// charter §Goals + §Anti-hooks) e a projeção não existe nesta camada — ver a
// nota em `JanaMetaDrawer.tsx` §Situação.

export interface Apuracao {
  data_ref: string;
  valor_realizado: number;
}

export interface Periodo {
  data_ini: string;
  data_fim: string;
  valor_alvo: number;
  trajetoria: string;
}

export interface Meta {
  id: number;
  slug: string;
  nome: string;
  unidade: string;
  tipo_agregacao: string;
  periodo_atual: Periodo | null;
  ultima_apuracao: Apuracao | null;
  apuracoes_recentes: Apuracao[];
  /**
   * Veredito do farol, calculado pelo SERVIDOR (`ApuracaoService::farol`).
   * Opcional de propósito: durante a janela de deploy o payload antigo ainda
   * chega sem o campo, e `farolDaMeta()` degrada pra 'cinza' — que já é o
   * rótulo de "não dá pra dizer" na própria regra.
   */
  farol?: Farol;
}

export type Farol = 'verde' | 'amarelo' | 'vermelho' | 'cinza';

export function farolDaMeta(meta: Meta): Farol {
  return meta.farol ?? 'cinza';
}

// Tokens semânticos, não escala crua: mesma semântica (bom/atenção/ruim), com o
// hue do sistema — e o dark herda via token em vez de ficar preso no tom claro
// da paleta Tailwind.
export const FAROL_CLASSES: Record<Farol, string> = {
  verde: 'bg-success',
  amarelo: 'bg-warning',
  vermelho: 'bg-destructive',
  cinza: 'bg-muted-foreground/30',
};

export function formatValue(value: number, unidade: string): string {
  if (unidade === 'R$') {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      maximumFractionDigits: 0,
    }).format(value);
  }
  if (unidade === '%') return `${value.toFixed(1)}%`;
  return new Intl.NumberFormat('pt-BR').format(value);
}

const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/**
 * "mai/2026" a partir de uma data do payload.
 *
 * Parse por FATIA, nunca `new Date(iso)`: o campo vem de um cast `date` do
 * Eloquent, então chega como `2026-05-01T00:00:00.000000Z` — UTC. Passado ao
 * `Date`, em BRT (UTC-3) isso vira 30/abr 21h e o rótulo do mês sai um mês
 * atrasado. A fatia lê o dia civil que o servidor gravou, que é o que a meta
 * significa.
 */
export function rotuloPeriodo(periodo: Periodo | null): string | null {
  const bruto = periodo?.data_ini;
  if (!bruto) return null;
  const [ano, mes] = bruto.slice(0, 10).split('-');
  const idx = Number(mes) - 1;
  if (!ano || !MESES[idx]) return null;
  return `${MESES[idx]}/${ano}`;
}

/** Percentual do alvo já realizado — `null` quando falta alvo ou apuração. */
export function progressoDaMeta(meta: Meta): number | null {
  const realizado = meta.ultima_apuracao?.valor_realizado ?? null;
  const alvo = meta.periodo_atual?.valor_alvo ?? null;
  if (realizado === null || alvo === null || alvo === 0) return null;
  return (realizado / alvo) * 100;
}
