// Abas do WooCommerce — Produtos (painel) · Log de sincronização · Configuração. As três telas viram juntas
// (flag `useV2WoocommercePainel`, threads woocommerce/playbook/01 e 02); com a flag desligada as rotas devolvem a Blade.
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';

export type AbaWoocommerce = '/woocommerce' | '/woocommerce/view-sync-log' | '/woocommerce/api-settings';

export default function WoocommerceAbas({ ativa, podeConfigurar }: { ativa: AbaWoocommerce; podeConfigurar: boolean }) {
  const ghosts: PageHeaderGhost[] = [
    { key: '/woocommerce', label: 'Produtos', href: '/woocommerce' },
    { key: '/woocommerce/view-sync-log', label: 'Log de sincronização', href: '/woocommerce/view-sync-log' },
    ...(podeConfigurar ? [{ key: '/woocommerce/api-settings', label: 'Configuração', href: '/woocommerce/api-settings' }] : []),
  ];

  return (
    <PageHeaderTabs ghosts={ghosts} activeGhostKey={ativa} group="sistema" maxVisible={3}
      onGhostChange={(key) => { if (key !== ativa) window.location.assign(key); }} />
  );
}
