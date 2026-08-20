/**
 * Faixa de KPI-filtros do catálogo — CINCO cards em UMA linha (handoff V2 §4.2).
 *
 * Eram seis até o pacote 18/08. O 19/08 removeu "Itens listados": ele não recortava nada
 * (o total da aba já É a lista sem recorte) e agora a contagem vive no rodapé de paginação,
 * onde ela responde a pergunta certa — "de quantos?" — junto do intervalo mostrado.
 *
 * São TOGGLES, não placar: clicar recorta a lista, clicar de novo solta. Os valores são
 * contados sobre a ABA ATIVA, no servidor, pela mesma subconsulta da listagem — contador que
 * discorda da lista destrói a confiança na tela (handoff §9).
 *
 * Paridade: mesma anatomia de card da `KpiStripClickable` da golden master
 * (`Pages/Cliente/_components/KpiStripClickable.tsx`) — tile de ícone 36px, rótulo 10px
 * uppercase, valor tabular, sub em 10px. O que muda é o domínio, não o estilo.
 *
 * ⚠️ Os tons `oklch` inline são cópia literal dos da golden master, INCLUSIVE a limitação
 * dela: os valores de card ativo são claros demais pro tema escuro. Corrigir só aqui quebraria
 * a paridade que a catraca protege — a correção pertence às duas telas de uma vez.
 *
 * ⚠️ "Margem baixa" só é montado pra quem pode ver custo E preço. A contagem de itens sob o
 * piso É uma leitura da estrutura de custo; gatear a coluna e deixar o contador entrega o
 * mesmo dado agregado. Mesma regra do §5 (coluna montada ou não montada), aplicada ao card.
 */

import type { ComponentType, CSSProperties } from 'react';
import { Boxes, CircleSlash, Clock, Percent, TriangleAlert } from 'lucide-react';
import { Inline } from '@/Components/layout';
import type { KpiKey, Permissoes } from './catalogo';

const TONE_STYLES = {
  primary: { border: 'oklch(0.62 0.18 250)', bgActive: 'oklch(0.95 0.04 250)', iconBg: 'oklch(0.92 0.05 250)', iconFg: 'oklch(0.45 0.18 250)' },
  amber: { border: 'oklch(0.72 0.15 70)', bgActive: 'oklch(0.96 0.05 70)', iconBg: 'oklch(0.93 0.07 70)', iconFg: 'oklch(0.50 0.15 70)' },
  rose: { border: 'oklch(0.65 0.20 20)', bgActive: 'oklch(0.96 0.04 20)', iconBg: 'oklch(0.93 0.06 20)', iconFg: 'oklch(0.50 0.20 20)' },
  emerald: { border: 'oklch(0.65 0.14 155)', bgActive: 'oklch(0.95 0.04 155)', iconBg: 'oklch(0.92 0.06 155)', iconFg: 'oklch(0.45 0.14 155)' },
  violet: { border: 'oklch(0.60 0.18 295)', bgActive: 'oklch(0.96 0.04 295)', iconBg: 'oklch(0.93 0.06 295)', iconFg: 'oklch(0.50 0.18 295)' },
} as const;

type ToneKey = keyof typeof TONE_STYLES;

export type KpisCatalogo = {
  ativos: number;
  min: number;
  zero: number;
  parado: number;
  /** Total da aba. Não vira mais card (V2 §4.2) — quem o consome é o rodapé de paginação. */
  total: number;
  /** Ausente pra perfil sem direito a custo — a chave não é emitida pelo servidor. */
  margem?: number;
};

type Card = {
  key: KpiKey;
  label: string;
  sub: string;
  tone: ToneKey;
  icon: ComponentType<{ className?: string; style?: CSSProperties }>;
  valor: number;
};

export interface KpiFiltrosProps {
  kpis: KpisCatalogo;
  /** KPI-filtro ativo, ou `null`. */
  ativo: KpiKey | null;
  onToggle: (key: KpiKey | null) => void;
  perm: Permissoes;
  /** Janela do KPI de item parado, em dias — vem do servidor, a tela não redeclara. */
  diasParado: number;
}

export function KpiFiltros({ kpis, ativo, onToggle, perm, diasParado }: KpiFiltrosProps) {
  const cards: Card[] = [
    { key: 'ativos', label: 'Ativos', sub: 'em uso', tone: 'primary', icon: Boxes, valor: kpis.ativos },
    { key: 'min', label: 'Estoque baixo', sub: 'repor', tone: 'amber', icon: TriangleAlert, valor: kpis.min },
    { key: 'zero', label: 'Sem estoque', sub: 'bloqueado', tone: 'rose', icon: CircleSlash, valor: kpis.zero },
    { key: 'parado', label: `Sem venda ${diasParado}d`, sub: `${diasParado} dias`, tone: 'violet', icon: Clock, valor: kpis.parado },
  ];

  // Montado ou não montado — nunca escondido por CSS.
  if (perm.custo && perm.preco && kpis.margem !== undefined) {
    cards.push({ key: 'margem', label: 'Margem baixa', sub: 'sob o piso', tone: 'emerald', icon: Percent, valor: kpis.margem });
  }

  // Cinco cards em UMA linha no desktop declarado (exceção de domínio 1). Abaixo dele a grade
  // quebra em 3/2 colunas em vez de transbordar na horizontal como o protótipo: aqui a tela
  // vive dentro do AppShellV2, e rolagem horizontal do conteúdo esconderia os KPIs da direita
  // sem nenhum affordance. `gap: 9px` é herdado da golden master.
  //
  // `lg:grid-cols-5` acompanha a contagem real: com 4 cards (perfil sem custo) a grade cai
  // pra 4 e nenhum fica órfão numa segunda linha.
  return (
    <div className={'grid gap-[9px] grid-cols-2 sm:grid-cols-3 ' + (cards.length === 5 ? 'lg:grid-cols-5' : 'lg:grid-cols-4')}>
      {cards.map((card) => {
        const Icon = card.icon;
        const tone = TONE_STYLES[card.tone];
        const on = ativo === card.key;
        return (
          <button
            key={card.key}
            type="button"
            aria-pressed={on}
            title={`Recortar por ${card.label} (${card.sub})`}
            onClick={() => onToggle(on ? null : card.key)}
            className={
              'group flex items-center gap-3 p-3 rounded-md border text-left transition-all ' +
              (on ? 'shadow-sm' : 'bg-card hover:shadow-sm')
            }
            style={on ? { borderColor: tone.border, backgroundColor: tone.bgActive } : { borderColor: 'var(--border)' }}
          >
            <Inline justify="center" className="h-9 w-9 rounded-md flex-shrink-0" style={{ backgroundColor: tone.iconBg }}>
              <Icon className="h-4 w-4" style={{ color: tone.iconFg }} />
            </Inline>
            <div className="flex-1 min-w-0">
              <p className="text-[10px] font-semibold tracking-wider uppercase text-muted-foreground truncate leading-none">{card.label}</p>
              <p className="text-lg font-semibold text-foreground tabular-nums leading-tight mt-1">{card.valor.toLocaleString('pt-BR')}</p>
              <p className="text-[10px] text-muted-foreground truncate leading-none mt-0.5">{card.sub}</p>
            </div>
          </button>
        );
      })}
    </div>
  );
}

export default KpiFiltros;
