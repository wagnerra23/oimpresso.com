/**
 * MENU_PERFIS — perfis de menu pré-cadastrados (seed).
 *
 * Origem: design `ref/design/menu-perfis.jsx` (`window.MOCK.MENU_PERFIS`).
 *
 * Perfis com `sys: true` não podem ser excluídos e são re-injetados se algum
 * dia ficarem ausentes do storage (defensivo).
 */
import type { MenuModuleId } from "./menu-modules";

export type MenuPerfil = {
  id: string;
  nome: string;
  funcao: string;
  /** Ordem importa — define a posição dos slots na tabbar. Máx 3. */
  mods: MenuModuleId[];
  /** `true` ⇒ perfil do sistema (não-editável a fundo, não-excluível). */
  sys: boolean;
};

export const SEED_PERFIS: readonly MenuPerfil[] = [
  {
    id: "comvis",
    nome: "Comunicação visual",
    funcao: "Produção gráfica",
    mods: ["tarefas", "pedidos", "producao"],
    sys: true,
  },
  {
    id: "oficina",
    nome: "Oficina / Mecânica",
    funcao: "Manutenção de frota",
    mods: ["manutencao", "pedidos", "clientes"],
    sys: true,
  },
  {
    id: "faturamento",
    nome: "Faturamento",
    funcao: "Financeiro / fiscal",
    mods: ["vendas", "financas", "relatorios"],
    sys: true,
  },
  {
    id: "balcao",
    nome: "Balcão / Vendas",
    funcao: "Atendimento",
    mods: ["vendas", "clientes", "produtos"],
    sys: true,
  },
] as const;

/** Perfil aplicado quando ainda não há nada persistido. */
export const DEFAULT_PERFIL_ID = "comvis";
