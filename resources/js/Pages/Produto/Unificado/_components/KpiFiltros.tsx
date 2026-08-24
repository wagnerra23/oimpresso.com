/**
 * Faixa de KPI-filtros do catálogo — QUATRO cards, no máximo (handoff 21/08 §4.1).
 *
 * Eram seis até o pacote 18/08. O 19/08 removeu "Itens listados" — ele não recortava nada (o
 * total da aba já É a lista sem recorte) e a contagem passou pro rodapé de paginação, onde
 * responde a pergunta certa ("de quantos?") junto do intervalo mostrado. O 21/08 removeu
 * "Ativos" pelo mesmo motivo, um passo adiante: fora da aba "Inativos" todo item listado já
 * é ativo, então o card contava a própria lista e clicar nele não mudava nada.
 *
 * Os quatro que sobraram são recortes de AÇÃO — cada um responde "o que eu preciso resolver
 * hoje?": repor, o que trava venda, o que parou de girar, o que vende sem margem.
 *
 * São TOGGLES, não placar: clicar recorta a lista, clicar de novo solta. Os valores são
 * contados sobre a ABA ATIVA, no servidor, pela mesma subconsulta da listagem — contador que
 * discorda da lista destrói a confiança na tela (handoff §9).
 *
 * Paridade: mesma anatomia de card da `KpiStripClickable` da golden master
 * (`Pages/Cliente/_components/KpiStripClickable.tsx`) — tile de ícone 36px, rótulo 10px
 * uppercase, valor tabular, sub em 10px. O que muda é o domínio, não o estilo.
 *
 * ⚠️ Os tons deixaram de ser `oklch` inline em 2026-08-24. Eram cópia literal da golden
 * master, e a paridade com ela era o motivo de não corrigir aqui — [W]/Maiara desfizeram esse
 * empate: a Consulta de Clientes é outra tela e se resolve depois. Agora a placa segue a
 * receita ÚNICA do patch de cor (§1): fundo do tom a 6%, borda a 22%, glyph no 600.
 *
 * ⚠️ "Margem baixa" só é montado pra quem pode ver custo E preço. A contagem de itens sob o
 * piso É uma leitura da estrutura de custo; gatear a coluna e deixar o contador entrega o
 * mesmo dado agregado. Mesma regra do §5 (coluna montada ou não montada), aplicada ao card.
 */

import type { ComponentType, CSSProperties } from 'react';
import { Ban, Clock, Percent, TriangleAlert } from 'lucide-react';
import { Inline } from '@/Components/layout';
import type { KpiKey, Permissoes } from './catalogo';

/**
 * A placa do ícone, e só ela, é tintada — o card fica neutro.
 *
 * O patch nomeia os tons pela paleta do Tailwind (`amber-500`, `rose-500`, `violet-500`), que
 * não é o vocabulário daqui. O equivalente registrado no DS é `warning` / `destructive` /
 * `primary` — o roxo hue 295 do acento canônico ([ADR 0190]). Usar o nome do DS é o que o §6
 * do patch manda quando o valor parece faltar: procurar com o outro nome, nunca criar token.
 * De quebra, o par claro/escuro vem junto, então o §5 (mesmas frações no escuro) sai de graça.
 */
const PLACA = {
  warning: 'bg-warning/6 border-warning/22 text-warning-fg',
  destructive: 'bg-destructive/6 border-destructive/22 text-destructive-fg',
  primary: 'bg-primary/6 border-primary/22 text-primary',
} as const;

type ToneKey = keyof typeof PLACA;

export type KpisCatalogo = {
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
  // "Abaixo do mínimo" e "Sem saldo" valem pra TODO perfil: são a pergunta do balcão.
  const cards: Card[] = [
    { key: 'min', label: 'Abaixo do mínimo', sub: 'repor', tone: 'warning', icon: TriangleAlert, valor: kpis.min },
    { key: 'zero', label: 'Sem saldo', sub: 'bloqueado', tone: 'destructive', icon: Ban, valor: kpis.zero },
  ];

  // Montado ou não montado — nunca escondido por CSS.
  //
  // "Sem venda Nd" e "Margem baixa" são recortes de GESTÃO, e o handoff de 21/08 §4.1 os
  // gateia junto com o custo: quem atende no balcão não decide o que sai de linha nem o que
  // vende sem margem, e o card ocuparia espaço com uma pergunta que não é dele.
  if (perm.custo) {
    cards.push({ key: 'parado', label: `Sem venda ${diasParado}d`, sub: 'sem giro', tone: 'primary', icon: Clock, valor: kpis.parado });
  }
  if (perm.custo && perm.preco && kpis.margem !== undefined) {
    cards.push({ key: 'margem', label: 'Margem baixa', sub: 'sob o piso', tone: 'warning', icon: Percent, valor: kpis.margem });
  }

  // `auto-fit` com piso de 170px é a grade do handoff de 21/08 §3.1: com dois cards (balcão)
  // eles se esticam; com quatro (autorizado) cabem na linha. Sem contagem fixa no código, a
  // faixa acompanha a permissão sem ninguém lembrar de ajustar o `grid-cols-N`.
  //
  // `gap: 9px` é herdado da golden master.
  return (
    <div className="grid gap-[9px] [grid-template-columns:repeat(auto-fit,minmax(170px,1fr))]">
      {cards.map((card) => {
        const Icon = card.icon;
        const on = ativo === card.key;
        return (
          <button
            key={card.key}
            type="button"
            aria-pressed={on}
            title={`Recortar por ${card.label} (${card.sub})`}
            onClick={() => onToggle(on ? null : card.key)}
            className={
              // `rounded-lg`, não o `rounded-xl` que o patch §1 pede: a regra `ds/no-rounded-xl`
              // do `eslint.config.js` cravou o teto em `rounded-lg` citando o CLAUDE_DESIGN_BRIEFING
              // §4, e o cânone do projeto ganha do patch. A placa desce pra `rounded-md` junto, pra
              // preservar a RELAÇÃO que o patch encoda (placa menos redonda que o card) dentro do teto.
              'group flex items-center gap-3 p-3 rounded-lg border bg-card text-left transition-all hover:shadow-sm ' +
              // Selecionado é ANEL, não tinta de fundo: o card é neutro e continua neutro, senão
              // a faixa ganha uma segunda cor competindo com a placa, que é quem carrega o tom.
              (on ? 'border-primary ring-1 ring-primary/40' : 'border-border')
            }
          >
            <Inline justify="center" className={'h-9 w-9 rounded-md border flex-shrink-0 ' + PLACA[card.tone]}>
              {/* Sem `color` próprio: o glyph herda o `text-*` da placa (currentColor). */}
              <Icon className="h-4 w-4" />
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
