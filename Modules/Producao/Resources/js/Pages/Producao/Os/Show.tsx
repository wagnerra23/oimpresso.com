// @memcofre
//   tela: /producao/ordens/{id}
//   module: Producao
//   stories: UC-PCV-10 (Ficha da OS) · UC-PCOSF-01..05 · auditoria Producao lote 3.2
//   permissao: producao.access · Liberar e Refazer com producao.liberar · Cancelar item com vendas.gerente
//
// Ficha da OS por item como página. O conteúdo é o `FichaDaOs`, o mesmo do painel lateral do Quadro (decisão [W] no
// #10850). Aqui as abas chegam deferidas (custo, materiais, artes, pessoas): por isso `carregar` não faz nada, e
// `recarregar` refaz só as props pedidas.
import '../../../../../../../resources/css/cowork-producao-bundle.css';
import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import ProducaoAbas from '../_components/ProducaoAbas';
import FichaDaOs, { type AbaNome, type FichaAbas, type FichaBase } from '../_components/FichaDaOs';

type Props = FichaBase & FichaAbas;

export default function OsShow(props: Props) {
  const { os, etapas, pode, custo, materiais, artes, pessoas } = props;
  const recarregar = (abas?: AbaNome[]) => router.reload(abas ? { only: abas } : {});
  return (
    <div className="pcv-root" data-screen-label="Produção · Ficha da OS">
      <div className="pcv-ph"><PageHeader title={os.numero} suffix={os.produto ? ' · ' + os.produto : ''} below={<ProducaoAbas ativa="/producao/quadro" />} /></div>
      <div className="pcv-body">
        <FichaDaOs ficha={{ os, etapas, pode }} abas={{ custo, materiais, artes, pessoas }} carregar={() => {}} recarregar={recarregar} />
      </div>
    </div>
  );
}

OsShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
