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

import { Briefcase, DollarSign, Factory } from 'lucide-react';

export type SellsVisao = 'operacional' | 'financeira' | 'producao';

interface SellsTabsVisaoProps {
  visao: SellsVisao;
  onChange: (v: SellsVisao) => void;
  /** Desabilita as tabs (ex: durante fetch). Default: false. */
  disabled?: boolean;
}

export default function SellsTabsVisao({
  visao,
  onChange,
  disabled = false,
}: SellsTabsVisaoProps) {
  return (
    <div
      role="tablist"
      aria-label="Visão da lista de vendas"
      className="inline-flex items-center rounded-lg border border-border bg-muted/40 p-0.5"
    >
      <TabButton
        active={visao === 'operacional'}
        onClick={() => onChange('operacional')}
        disabled={disabled}
        icon={<Briefcase size={14} />}
        label="Operacional"
        title="Pipeline, fiscal, SLA, ações rápidas — visão do dia-a-dia (padrão ROTA LIVRE biz=4)"
      />
      <TabButton
        active={visao === 'financeira'}
        onClick={() => onChange('financeira')}
        disabled={disabled}
        icon={<DollarSign size={14} />}
        label="Financeira"
        title="Total, pago, a receber, comissão + multiseleção, totalizador, agrupamento"
      />
      <TabButton
        active={visao === 'producao'}
        onClick={() => onChange('producao')}
        disabled={disabled}
        icon={<Factory size={14} />}
        label="Produção"
        title="Localização, estágio FSM, sub-linha produtos — power-user OfficeImpresso migrado"
      />
    </div>
  );
}

interface TabButtonProps {
  active: boolean;
  onClick: () => void;
  disabled: boolean;
  icon: React.ReactNode;
  label: string;
  title: string;
}

function TabButton({ active, onClick, disabled, icon, label, title }: TabButtonProps) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      aria-disabled={disabled}
      onClick={onClick}
      disabled={disabled}
      title={title}
      className={
        'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed ' +
        (active
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground hover:bg-background/50')
      }
    >
      {icon}
      <span>{label}</span>
    </button>
  );
}
