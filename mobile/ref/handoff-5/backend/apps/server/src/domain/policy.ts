// ──────────────────────────────────────────────────────────────
// Policy — autorização NO SERVIDOR (RBAC). Corrige v2 §15:
// "permissão = esconder menu" → controle real por papel.
// O menu configurável por perfil é PROJEÇÃO desta política.
// ──────────────────────────────────────────────────────────────

export type Papel = "admin" | "gerente" | "operador" | "financeiro" | "vendedor";
export type Recurso =
  | "pedido" | "produto" | "pessoa" | "titulo"
  | "documento_fiscal" | "producao" | "config" | "audit";
export type Acao = "ler" | "criar" | "editar" | "excluir" | "avancar" | "emitir" | "baixar";

// Matriz de habilidades por papel. "*" = qualquer ação no recurso.
const ABILITIES: Record<Papel, Partial<Record<Recurso, Acao[] | "*">>> = {
  admin: {
    pedido: "*", produto: "*", pessoa: "*", titulo: "*",
    documento_fiscal: "*", producao: "*", config: "*", audit: ["ler"],
  },
  gerente: {
    pedido: "*", produto: "*", pessoa: "*", titulo: "*",
    documento_fiscal: ["ler", "emitir"], producao: "*", audit: ["ler"],
  },
  vendedor: {
    pedido: ["ler", "criar", "editar", "avancar"], produto: ["ler"],
    pessoa: ["ler", "criar", "editar"], titulo: ["ler"], documento_fiscal: ["ler"],
  },
  financeiro: {
    titulo: "*", documento_fiscal: ["ler", "emitir"], pedido: ["ler"], pessoa: ["ler"],
  },
  operador: {
    producao: ["ler", "avancar"], pedido: ["ler"], produto: ["ler"],
  },
};

/** Pode o papel executar a ação no recurso? */
export function can(papel: Papel, acao: Acao, recurso: Recurso): boolean {
  const r = ABILITIES[papel]?.[recurso];
  if (!r) return false;
  return r === "*" || r.includes(acao);
}

/** Lança se não autorizado — use no início de cada procedure. */
export function assertCan(papel: Papel, acao: Acao, recurso: Recurso): void {
  if (!can(papel, acao, recurso)) {
    throw new Error(`FORBIDDEN: ${papel} não pode ${acao} ${recurso}`);
  }
}
