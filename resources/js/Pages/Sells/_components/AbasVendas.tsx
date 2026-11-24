import { usePage } from '@inertiajs/react';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import { ABAS_VENDAS, abasVisiveis, type AbaVenda, type ItemMenu } from './abasVendasDados';

/** Barra de abas do módulo Vendas. Lista, ordem e regra de visibilidade: `abasVendasDados.ts`. */
export default function AbasVendas({ ativa }: { ativa: AbaVenda }) {
  const props = usePage().props as {
    shell?: { menu?: ItemMenu[] };
    auth?: { can?: Record<string, boolean> };
  };
  const abas = abasVisiveis(ativa, props.shell?.menu, props.auth?.can);
  return (
    <div data-contract="tabs">
      <PageHeaderTabs
        ghosts={abas.map((a) => ({ ...a }))}
        activeGhostKey={ativa}
        maxVisible={ABAS_VENDAS.length}
      />
    </div>
  );
}
