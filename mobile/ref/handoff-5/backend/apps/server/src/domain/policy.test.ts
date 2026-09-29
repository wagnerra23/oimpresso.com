import { describe, it, expect } from "vitest";
import { can } from "./policy";

describe("policy — autorização no servidor (RBAC)", () => {
  it("admin pode tudo nos recursos principais", () => {
    expect(can("admin", "criar", "pedido")).toBe(true);
    expect(can("admin", "emitir", "documento_fiscal")).toBe(true);
  });

  it("vendedor cria pedido mas não emite fiscal", () => {
    expect(can("vendedor", "criar", "pedido")).toBe(true);
    expect(can("vendedor", "emitir", "documento_fiscal")).toBe(false);
  });

  it("operador só lê/avança produção, não mexe em financeiro", () => {
    expect(can("operador", "avancar", "producao")).toBe(true);
    expect(can("operador", "baixar", "titulo")).toBe(false);
  });

  it("financeiro domina títulos, mas não cria pedido", () => {
    expect(can("financeiro", "baixar", "titulo")).toBe(true);
    expect(can("financeiro", "criar", "pedido")).toBe(false);
  });

  it("vendedor NÃO edita produto (margem é política da empresa, não do vendedor)", () => {
    expect(can("vendedor", "ler", "produto")).toBe(true);    // pode consultar
    expect(can("vendedor", "editar", "produto")).toBe(false); // não muda margem/cadastro
    expect(can("admin", "editar", "produto")).toBe(true);     // só admin/gerente
    expect(can("gerente", "editar", "produto")).toBe(true);
  });

  it("nega recurso desconhecido por padrão", () => {
    // @ts-expect-error recurso inexistente
    expect(can("admin", "ler", "inexistente")).toBe(false);
  });
});
