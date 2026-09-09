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
//   menu vivo  · Painel · Bens · Alocações · Devoluções · Manutenções · Configurações
//   protótipo  · Painel · Bens · Alocações · Manutenções · Garantias · Auditoria · Configurações
//
// Os RÓTULOS convergiram em 2026-09-09 por decisão [W]: a aba dizia "Ativos"/"Manutenção"
// enquanto o `PageHeader` da MESMA tela dizia "Bens"/"Manutenções". Trocado no dono ÚNICO
// (`DataController`), não aqui — este arquivo continua sem saber o nome de aba nenhuma.
//
// O que RESTA divergindo não é descuido de um dos lados — é escopo em aberto:
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
  /**
   * Contadores por `key` de ghost — o pill numérico do `PageHeaderTabs`.
   *
   * NÃO fere a regra do arquivo. A LISTA continua vindo inteira do `shell.menu`: isto
   * só ENRIQUECE, por chave, o que já foi derivado. Some a aba do menu (permissão,
   * assinatura, rename de rota) e o contador some com ela — não há como um número
   * sobreviver à aba que ele conta, que é o que um segundo dono permitiria.
   *
   * Por que vem da TELA e não do `DataController`: o menu é montado pelo middleware
   * `AdminSidebarMenu` em 1598 das 1905 rotas do app (medido 2026-09-09), das quais só
   * 40 são `asset/` — pôr a query ali cobraria 1558 rotas de outros módulos por um
   * número que só estas cinco telas mostram, e sem poder deferir. Pela tela é o padrão
   * canônico (`ContactController:523`, `tab_counts` via `Inertia::defer`).
   *
   * Chave ausente ou `undefined` = aba sem pill, que é o default de 4 das 6.
   */
  badges?: Record<string, number | undefined>;
  /** Omite o primary (a tela renderiza o dela à direita). */
  hidePrimary?: boolean;
}

/** Label literal da entry — `Resources/lang/pt/lang.php`: `asset_management => 'Gestão de ativos'`. */
const LABEL_MODULO = 'gestão de ativos';

export default function PatrimonioSubNav({
  active,
  extraOverflowItems,
  hidePrimary,
  badges,
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

  // Enriquece por CHAVE, preservando ordem e conteúdo do menu. `badge` só entra quando a
  // tela mandou um número pra AQUELA chave; as demais abas seguem byte-idênticas ao que o
  // backend declarou. Sem `badges`, o objeto do menu passa intacto.
  const ghosts = badges
    ? item.ghosts.map((g) => {
        // Extraído pra variável em vez de indexar duas vezes: em acesso indexado repetido
        // o TS não carrega o estreitamento do `!= null` para o segundo uso.
        const n = badges[g.key];
        return n != null ? { ...g, badge: n } : g;
      })
    : item.ghosts;

  return (
    <PageHeaderTabs
      primary={hidePrimary ? undefined : item.primary}
      ghosts={ghosts}
      activeGhostKey={active}
      group="operar"
      maxVisible={6}
      extraOverflowItems={extraOverflowItems}
    />
  );
}
