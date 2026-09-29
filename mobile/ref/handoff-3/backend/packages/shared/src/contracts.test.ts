import { describe, it, expect } from "vitest";
import { proximaEtapa, ETAPA_ORDER } from "./contracts";

describe("contracts — avanço de etapa DERIVADO (não digitado)", () => {
  it("avança na ordem do pipeline", () => {
    expect(proximaEtapa("orc")).toBe("aprov");
    expect(proximaEtapa("aprov")).toBe("prod");
    expect(proximaEtapa("prod")).toBe("entrega");
    expect(proximaEtapa("entrega")).toBe("done");
  });

  it("não passa de 'done'", () => {
    expect(proximaEtapa("done")).toBe("done");
  });

  it("pipeline tem 5 etapas canônicas", () => {
    expect(ETAPA_ORDER).toEqual(["orc", "aprov", "prod", "entrega", "done"]);
  });
});
