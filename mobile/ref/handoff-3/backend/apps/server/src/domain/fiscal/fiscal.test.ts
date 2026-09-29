import { describe, it, expect } from "vitest";
import { naturezaDe, tipoDocumento, tributar } from "./natureza";
import { podeTransicionar, transicionar, TransicaoInvalidaError } from "./documentoStateMachine";
import { makeMockProvider } from "./provider";

describe("natureza ISS × ICMS (setor gráfico)", () => {
  it("impresso sob encomenda = serviço → NFS-e/ISS", () => {
    const item = { sobEncomenda: true, consumidorFinal: false };
    expect(naturezaDe(item)).toBe("servico");
    expect(tipoDocumento(item)).toBe("NFSe");
    expect(tributar(item, "simples").imposto).toBe("ISS");
  });
  it("produto pronto a consumidor final = mercadoria → NFC-e/ICMS", () => {
    const item = { sobEncomenda: false, consumidorFinal: true };
    expect(naturezaDe(item)).toBe("mercadoria");
    expect(tipoDocumento(item)).toBe("NFCe");
    expect(tributar(item, "simples").imposto).toBe("ICMS");
  });
  it("produto pronto a empresa = NF-e", () => {
    expect(tipoDocumento({ sobEncomenda: false, consumidorFinal: false })).toBe("NFe");
  });
});

describe("máquina de estados do documento fiscal", () => {
  it("permite o fluxo feliz: rascunho → enviando → autorizado", () => {
    expect(podeTransicionar("rascunho", "enviando")).toBe(true);
    expect(podeTransicionar("enviando", "autorizado")).toBe(true);
  });
  it("rejeição volta para correção e reenvio", () => {
    expect(podeTransicionar("enviando", "rejeitado")).toBe(true);
    expect(podeTransicionar("rejeitado", "enviando")).toBe(true);
  });
  it("contingência quando a SEFAZ cai", () => {
    expect(podeTransicionar("rascunho", "contingencia")).toBe(true);
    expect(podeTransicionar("contingencia", "autorizado")).toBe(true);
  });
  it("bloqueia pulo ilegal rascunho → autorizado", () => {
    expect(podeTransicionar("rascunho", "autorizado")).toBe(false);
    expect(() => transicionar("rascunho", "autorizado")).toThrow(TransicaoInvalidaError);
  });
  it("cancelado é terminal", () => {
    expect(podeTransicionar("cancelado", "enviando")).toBe(false);
  });
});

describe("provider (porta anticorrupção)", () => {
  const nota = {
    tipo: "NFSe" as const, serie: "1", numero: 1, naturezaOperacao: "Serviço gráfico",
    emitenteTenantId: "t1", destinatario: { nome: "Cliente", documento: "000" },
    itens: [{ descricao: "1000 cartões", quantidade: 1, valorUnitCents: 24800 }],
    impostoPct: 3, totalCents: 24800,
  };
  it("emite com sucesso no mock", async () => {
    const p = makeMockProvider();
    const r = await p.emitir(nota);
    expect(r.ok).toBe(true);
    expect(r.providerRef).toMatch(/MOCK-1-1/);
  });
  it("propaga rejeição da SEFAZ (caminho infeliz)", async () => {
    const p = makeMockProvider({ rejeitarSe: () => "Destinatário sem inscrição municipal" });
    const r = await p.emitir(nota);
    expect(r.ok).toBe(false);
    expect(r.rejeicao?.motivo).toMatch(/inscrição/);
  });
});
