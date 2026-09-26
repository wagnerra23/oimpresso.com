// Documentacao/Programa — carimbado do PT-04 Dashboard por criar-tela.mjs (UI-0013).
// Herda o Padrão de Tela: NÃO reinvente a estrutura — preencha os {/* TODO */}.
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

interface Props { kpis?: Record<string, number> /* TODO: agregados do dashboard */ }

export default function Programa({ kpis }: Props) {
  return (
    <AppShellV2>
      {/* `data-contract` = âncora do contrato de tela (governance/design/contracts/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <div data-contract="cabecalho">
        <PageHeader title="Programa" subtitle="TODO: descrição do painel" />
      </div>
      <div data-contract="kpis">
        <KpiGrid cols={4}>
          {/* TODO: KPIs reais do módulo */}
          <KpiCard label="TODO" value={kpis?.total ?? 0} />
        </KpiGrid>
      </div>
      {/* TODO: gráficos/tabelas de apoio abaixo dos KPIs */}
    </AppShellV2>
  );
}
