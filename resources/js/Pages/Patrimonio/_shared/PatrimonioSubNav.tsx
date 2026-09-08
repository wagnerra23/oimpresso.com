// PatrimonioSubNav — sub-navegação do módulo Patrimônio (Modules/AssetManagement).
//
// FUNDAÇÃO: primeiro arquivo de `Pages/Patrimonio/_shared/`. As outras 6 telas do
// módulo consomem este componente passando o `active` delas — ninguém reescreve a lista.
//
// ─── Por que ele DERIVA e não DECLARA a lista de abas ────────────────────────────
//
// O dono das abas é `Modules/AssetManagement/Http/Controllers/DataController.php`
// (`modifyAdminMenu()`, chave `ghosts[]`), que o middleware `AdminSidebarMenu` monta e o
// `ShellMenuBuilder` entrega ao React como `shell.menu` (shared prop lazy de
// `HandleInertiaRequests`). Escrever um array de abas aqui criaria um SEGUNDO dono da
// mesma lista: no primeiro rename de rota os dois divergem e ficam "coerentes entre si"
// enquanto mentem pro usuário. É o mesmo motivo pelo qual `PontoSubNav` deriva.
//
// ─── O que isso significa na prática: são 6 abas, não 7 ──────────────────────────
//
// O protótipo (`prototipo-ui/cowork/patrimonio-page.jsx:835`) declara SETE abas; o menu
// vivo declara SEIS ghosts, e as listas não coincidem:
//
//   menu vivo  · Painel · Ativos · Alocações · Devoluções · Manutenção · Configurações
//   protótipo  · Painel · Bens   · Alocações · Manutenções · Garantias · Auditoria · Configurações
//
// A diferença não é descuido de um dos lados — é escopo em aberto:
//   • **Devoluções** existe como rota real (`/asset/revocation`, `Route::resource`) e o
//     protótipo a trata como estado dentro de Alocações. A rota manda.
//   • **Garantias** e **Auditoria** são decisões de produto ABERTAS do [W] — itens 4 e 5
//     do `00-INDICE.md §6` do playbook ("Garantias é tela ou filtro de Bens?",
//     "Auditoria é aba daqui ou do Modules/Auditoria?"). Nenhuma tem rota. Renderizar
//     aba que não navega é afordância falsa; quando a decisão sair, ela entra pelo
//     `DataController` e aparece aqui sozinha, sem tocar neste arquivo.
//
// Ter protótipo não é ter autorização de escopo (`06-ui-bloqueada.md`).
//
// Hue `operar`: Patrimônio é ghost de Estoque no grupo `operar` (ADR 0180) — endereço de
// pasta (`Pages/Patrimonio/`, ADR 0394) e agrupamento de menu são eixos independentes.

import { usePage } from '@inertiajs/react';
import PageHeaderTabs, {
  type PageHeaderGhost,
  type PageHeaderPrimary,
  type PageHeaderOverflowItem,
} from '@/Components/shared/PageHeaderTabs';

interface EntradaDeMenu {
  label: string;
  group?: string;
  primary?: PageHeaderPrimary;
  ghosts?: PageHeaderGhost[];
}

interface ShellComMenu {
  menu?: EntradaDeMenu[];
}

export interface PatrimonioSubNavProps {
  /** `key` do ghost desta tela — os do DataController: `dashboard` · `assets` · `allocation` · `revocation` · `asset-maintenance` · `settings`. */
  active: string;
  /** Ações da tela que vão pro overflow `⋯ Mais`. */
  extraOverflowItems?: PageHeaderOverflowItem[];
  /** Omite o primary (a tela renderiza o dela à direita). */
  hidePrimary?: boolean;
}

/** Label literal da entry — `Resources/lang/pt/lang.php`: `asset_management => 'Gestão de ativos'`. */
const LABEL_MODULO = 'gestão de ativos';

export default function PatrimonioSubNav({
  active,
  extraOverflowItems,
  hidePrimary,
}: PatrimonioSubNavProps) {
  // O shape da shared prop, declarado aqui em vez de `as any`: o `shell.menu` e LAZY
  // (`HandleInertiaRequests`), entao tudo e opcional — quem consome tem de sobreviver a
  // ausencia, e o tipo torna isso obrigatorio em vez de confiavel.
  const { shell } = usePage<{ shell?: ShellComMenu }>().props;

  const item = shell?.menu?.find((m) => m.label?.toLowerCase() === LABEL_MODULO);

  // Degrada pra nada, nunca pra erro: sem a entry (módulo não assinado, usuário sem
  // nenhuma permission `asset.*`, ou rota sem o middleware `AdminSidebarMenu`) a tela
  // continua utilizável — só perde a navegação entre abas.
  if (!item?.ghosts?.length) return null;

  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : item.primary}
      ghosts={item.ghosts}
      activeGhostKey={active}
      group="operar"
      maxVisible={6}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
