/**
 * MENU_MODULES — catálogo dos módulos que podem ocupar um slot da barra
 * inferior personalizada (Perfis de Menu, Bloco 1).
 *
 * Origem: design `ref/design/menu-perfis.jsx` (`window.MOCK.MENU_MODULES`).
 *
 * `id` é a chave canônica do módulo (também é o id usado em `MENU_PERFIS.mods`).
 * `route` é a rota dentro de `(tabs)` para a qual o slot deve apontar. Vários
 * módulos podem mapear para a mesma rota até que sejam feitas as separações
 * dos blocos 3 (Oficina) e 4 (Equipamentos) — `pedidos` e `vendas` apontam
 * ambos pra `(tabs)/vendas` por ora.
 */
import type { OiIconName } from "@/components/oi";

export type MenuModuleId =
  | "tarefas"
  | "pedidos"
  | "vendas"
  | "producao"
  | "manutencao"
  | "equipamentos"
  | "financas"
  | "relatorios"
  | "clientes"
  | "produtos";

/** Nome do arquivo dentro de `app/(tabs)/` (sem extensão). */
export type TabRouteName =
  | "index"
  | "tarefas"
  | "vendas"
  | "producao"
  | "mais"
  | "produtos"
  | "clientes"
  | "orcamentos"
  | "financeiro"
  | "chat"
  | "estoque"
  | "veiculos"
  | "oss"
  | "manutencao"
  | "equipamentos"
  | "relatorios"
  | "pagamentos"
  | "fiscal"
  | "dashboard";

export type MenuModule = {
  id: MenuModuleId;
  label: string;
  icon: OiIconName;
  desc: string;
  route: TabRouteName;
};

export const MENU_MODULES: readonly MenuModule[] = [
  { id: "tarefas",      label: "Tarefas",      icon: "inbox",   route: "tarefas",    desc: "Inbox de pendências (OS, CRM, financeiro)" },
  { id: "pedidos",      label: "Pedidos",      icon: "file",    route: "vendas",     desc: "Ordens de serviço e seu andamento" },
  { id: "vendas",       label: "Vendas",       icon: "tag",     route: "vendas",     desc: "Consulta de vendas, orçamentos e faturamento" },
  { id: "producao",     label: "Produção",     icon: "printer", route: "producao",   desc: "Fila de produção gráfica / chão de fábrica" },
  { id: "manutencao",   label: "Oficina",      icon: "wrench",  route: "manutencao", desc: "Manutenção de frota — OS, pátio e peças" },
  { id: "equipamentos", label: "Equipamentos", icon: "truck",   route: "equipamentos", desc: "Frota e máquinas vinculadas a um cliente" },
  { id: "financas",     label: "Financeiro",   icon: "dollar",  route: "financeiro", desc: "Caixa, contas a pagar e receber" },
  { id: "relatorios",   label: "Relatórios",   icon: "chart",   route: "relatorios", desc: "Vendas, fluxo, margem e produção" },
  { id: "clientes",     label: "Pessoas",      icon: "user",    route: "clientes",   desc: "Clientes, fornecedores e equipe" },
  { id: "produtos",     label: "Produtos",     icon: "box",     route: "produtos",   desc: "Catálogo e estoque" },
] as const;

export const MENU_MODULES_BY_ID: Record<MenuModuleId, MenuModule> =
  MENU_MODULES.reduce((acc, m) => {
    acc[m.id] = m;
    return acc;
  }, {} as Record<MenuModuleId, MenuModule>);

export function getMenuModule(id: MenuModuleId | string | undefined): MenuModule | undefined {
  if (!id) return undefined;
  return MENU_MODULES_BY_ID[id as MenuModuleId];
}

/** Limite de slots configuráveis (Início e Mais são fixos por fora). */
export const MAX_MENU_SLOTS = 3;
