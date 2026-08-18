// Tipos + formatadores da seção Metas do Painel (`/ia`).
//
// Arquivo SEM componente de propósito: `Index.tsx` e `JanaMetaDrawer.tsx`
// precisam dos mesmos tipos e das mesmas funções, e exportar não-componente de
// um arquivo de componente quebra o `react-refresh` (a regressão apareceu no
// `lint:baseline:check` quando o `useJanaConfig` nasceu dentro do drawer de
// configuração — a saída certa foi separar, não regravar o baseline).
//
// ⚠️ SÓ o que tem DOIS consumidores mora aqui. `periodoLabel`/`mesAno` e o
// `Sparkline` continuam no `Index.tsx`: são do card, e mover tudo de uma vez
// engordaria o diff sem ninguém do outro lado precisando. O período chega ao
// drawer como prop, já formatado pelo card.
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
  /**
   * Projeção de fechamento, calculada pelo SERVIDOR (`ApuracaoService::projecao`)
   * e já presente no payload de `/ia` desde a extração do farol
   * (`IndexController` — `'projecao' => $apuracao->projecao($meta)`).
   *
   * Estava chegando e NINGUÉM lia: `rg projecao resources/js/Pages/Jana/` não
   * devolvia um consumidor. O `JanaMetaDrawer` recusava mostrá-la com a razão
   * certa para 2026-08-17 — *"uma projeção é veredito sobre o futuro; ela nasce
   * no servidor ou não nasce"* — mas essa razão argumenta contra **calcular no
   * frontend**, e aqui não se calcula nada: consome-se o número que o servidor
   * já manda, pela mesma porta do `farol`.
   *
   * `null` = não há base pra projetar (período sem apuração ou de duração ≤ 0) —
   * exatamente os casos em que o farol é 'cinza'.
   */
  projecao?: Projecao | null;
}

/** Shape de `ApuracaoService::projecao()`. */
export interface Projecao {
  /** Fração do período já decorrida (0–1). */
  progresso: number;
  /** Valor projetado para o fechamento, no ritmo atual. */
  projetado: number;
  /** Desvio do realizado vs o projetado, em %. Negativo = abaixo do ritmo. */
  desvio_pct: number;
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
