// SellsTabsVisao — 3 tabs de Visão (Operacional / Financeira / Produção) sobre
// a tabela unificada de /sells. Substitui o conceito viewMode=lista|grade-avancada
// do ADR 0136 por um modelo persona-aware ([ADR 0178](../../../../memory/decisions/0178-sells-unified-tabs-visao-supersede-0136.md)
// supersede 0136).
//
// Renderiza padrão inline sem dependência nova (mesmo approach do
// SellsToggleViewMode.tsx) — segmented control com 3 botões agrupados num
// pill border arredondado, botão ativo com bg-background + sombra leve.
//
// Em PR2 da Onda Unificação, este componente é montado SOMENTE quando URL
// inclui `?tabs=1` (feature-flag) — Larissa @ ROTA LIVRE biz=4 não vê
// mudança até PR4 fazer cutover do default. PR3 conecta visão → visibleColumns
// da tabela base.
//
// PT-BR enforce: labels "Operacional / Financeira / Produção" são UX visíveis.

// 2026-11-21 — desenho do protótipo (vendas-page.jsx): rótulo "Visão" + segmentos no mesmo
// `.vd-vista` do Foco, sem ícones. Antes era um pill Tailwind com ícones (Briefcase/DollarSign/Factory).

export type SellsVisao = 'operacional' | 'financeira' | 'producao';

interface SellsTabsVisaoProps {
  visao: SellsVisao;
  onChange: (v: SellsVisao) => void;
  /** Desabilita as opções (ex: durante fetch). Default: false. */
  disabled?: boolean;
}

const OPCOES: ReadonlyArray<{ key: SellsVisao; label: string; title: string }> = [
  { key: 'operacional', label: 'Operacional', title: 'Pipeline, fiscal, SLA, ações rápidas — visão do dia-a-dia (padrão ROTA LIVRE biz=4)' },
  { key: 'financeira', label: 'Financeira', title: 'Total, pago, a receber, comissão + multiseleção, totalizador, agrupamento' },
  { key: 'producao', label: 'Produção', title: 'Localização, estágio FSM, sub-linha produtos — power-user OfficeImpresso migrado' },
];

export default function SellsTabsVisao({
  visao,
  onChange,
  disabled = false,
}: SellsTabsVisaoProps) {
  return (
    <div className="vd-vista" role="group" aria-label="Visão da lista de vendas">
      <span className="vd-vista-lbl">Visão</span>
      {OPCOES.map((o) => (
        <button
          key={o.key}
          type="button"
          className={visao === o.key ? 'on' : ''}
          aria-pressed={visao === o.key}
          onClick={() => onChange(o.key)}
          disabled={disabled}
          title={o.title}
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}
