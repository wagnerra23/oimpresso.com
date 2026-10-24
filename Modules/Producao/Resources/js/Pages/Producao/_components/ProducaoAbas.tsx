// Abas da Produção (PCP). No protótipo são 7 vistas (`producao-cv-page.jsx` VISTAS); aqui entram
// só as que já têm tela. Cada thread do playbook acrescenta a sua quando a rota existe — aba
// apontando para rota inexistente seria um 404 de enfeite.
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';

const ABAS = [
  { key: '/producao/painel', label: 'Painel' },
  { key: '/producao/quadro', label: 'Quadro' },
  { key: '/producao/linha', label: 'Linha do tempo' },
  { key: '/producao/roteiros', label: 'Roteiros' },
  { key: '/producao/postos', label: 'Postos e cadastros' },
];

export default function ProducaoAbas({ ativa }: { ativa: string }) {
  return (
    <PageHeaderTabs group="producao" activeGhostKey={ativa} maxVisible={ABAS.length}
      ghosts={ABAS.map((a) => ({ key: a.key, label: a.label, href: a.key }))}
      onGhostChange={(key) => { if (key !== ativa) window.location.assign(key); }} />
  );
}
