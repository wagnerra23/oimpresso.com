// OfficeimpressoSubNav — abas do módulo Office Impresso, abaixo do PageHeader de cada tela.
//
// Fonte de design: `prototipo-ui/cowork/Wagner/officeimpresso-page.jsx` (`PageHead` + `OI_ABAS`):
// o protótipo põe as abas logo abaixo do cabeçalho, em todas as telas do módulo. Sem elas, quem
// estava em Licenças só voltava a Empresas ou a Máquinas pelo menu lateral.
//
// A LISTA não é declarada aqui: vem do `shell.menu`, montado a partir dos `ghosts` de
// `Modules/Officeimpresso/Http/Controllers/DataController.php`, que já filtra por permissão
// (`officeimpresso.access` × `officeimpresso.clientes.liberar`). Uma lista escrita neste
// arquivo seria um segundo dono e mostraria aba que dá 403. Mesmo desenho de `PatrimonioSubNav`.

import { usePage } from '@inertiajs/react';
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';

interface EntradaDeMenu {
  label: string;
  ghosts?: PageHeaderGhost[];
}

/** Label da entry — `officeimpresso::lang.officeimpresso` ("Office Impresso"). */
const LABEL_MODULO = 'office impresso';

export interface OfficeimpressoSubNavProps {
  /** `key` do ghost desta tela: `businessall` · `computadores` · `licenca_computador` · `client` · `licenca_log`. */
  active: string;
}

export default function OfficeimpressoSubNav({ active }: OfficeimpressoSubNavProps) {
  // `shell.menu` é lazy: tudo é opcional, e a tela sobrevive à ausência.
  const { shell } = usePage<{ shell?: { menu?: EntradaDeMenu[] } }>().props;
  const item = shell?.menu?.find((m) => m.label?.toLowerCase() === LABEL_MODULO);

  // Sem a entry (módulo não instalado, usuário sem permissão do módulo, rota sem o
  // middleware AdminSidebarMenu) a tela segue utilizável, só sem as abas.
  if (!item?.ghosts?.length) return null;

  return (
    <PageHeaderTabs
      ghosts={item.ghosts}
      activeGhostKey={active}
      group="sistema"
      maxVisible={6}
    />
  );
}
