import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative } from "node:path";
import { beforeEach, describe, expect, it } from "vitest";

import { handlers, resetDemoStore } from "../lib/demo/handlers";

/**
 * Regras de negócio do app exercitadas pelo backend de demonstração
 * (`lib/demo/`), que espelha os routers de `server/routers/*` sem banco.
 * Cada teste parte do seed limpo.
 */

// Chama um procedure como o `demoLink` faz (cópia do resultado, erro como exceção).
function call<T = any>(path: string, input?: unknown): T {
  const fn = handlers.get(path);
  if (!fn) throw new Error(`sem handler para ${path}`);
  return structuredClone(fn(input)) as T;
}

const sum = (xs: number[]) => Math.round(xs.reduce((a, b) => a + b, 0) * 100) / 100;

beforeEach(() => resetDemoStore());

describe("integridade — cobertura do backend de demonstração", () => {
  // Varre o código do app atrás de `trpc.<router>.<proc>(.<proc>).useQuery/useMutation`.
  function clientProcedures(): string[] {
    const root = join(__dirname, "..");
    const found = new Set<string>();
    const walk = (dir: string) => {
      for (const name of readdirSync(dir)) {
        const full = join(dir, name);
        if (statSync(full).isDirectory()) {
          if (!["node_modules", "ref", "android", "ios", "dist", "tests", "server", ".expo"].includes(name)) walk(full);
        } else if (/\.(ts|tsx)$/.test(name)) {
          const src = readFileSync(full, "utf8");
          for (const m of src.matchAll(/trpc\.([a-zA-Z]+(?:\.[a-zA-Z]+){1,2})\.(?:useQuery|useMutation|useInfiniteQuery)/g)) {
            found.add(m[1]);
          }
        }
      }
    };
    for (const dir of ["app", "components", "hooks", "lib"]) walk(join(root, dir));
    return [...found].sort();
  }

  it("toda chamada tRPC do app tem resposta no modo demonstração", () => {
    const used = clientProcedures();
    expect(used.length).toBeGreaterThan(80);
    const missing = used.filter((p) => !handlers.has(p));
    expect(missing).toEqual([]);
  });
});

describe("integridade — dados de exemplo consistentes", () => {
  it("ids são únicos e as referências apontam para registros que existem", () => {
    const clientes = call<any[]>("customers.list");
    const ids = new Set(clientes.map((c) => c.id));
    expect(ids.size).toBe(clientes.length);

    const pedidos = call<any[]>("pedidos.list");
    const pedidoIds = new Set(pedidos.map((p) => p.id));
    for (const p of pedidos) if (p.customerId) expect(ids.has(p.customerId)).toBe(true);
    for (const op of call<any[]>("ops.list")) expect(pedidoIds.has(op.pedidoId)).toBe(true);

    const veiculos = call<any[]>("vehicles.list");
    const veiculoIds = new Set(veiculos.map((v) => v.id));
    for (const v of veiculos) expect(v.customer?.id).toBe(v.customerId);
    for (const os of call<any[]>("serviceOrders.list")) {
      expect(veiculoIds.has(os.vehicleId)).toBe(true);
      expect(ids.has(os.customerId)).toBe(true);
    }
  });

  it("valor do pedido = soma dos itens", () => {
    for (const p of call<any[]>("pedidos.list")) {
      expect(p.items.length).toBeGreaterThan(0);
      expect(p.valor).toBeCloseTo(sum(p.items.map((i: any) => i.valorTotal)), 2);
      for (const i of p.items) expect(i.valorTotal).toBeCloseTo(i.quantidade * i.valorUnit, 2);
    }
  });

  it("total da OS = peças + mão de obra, cada uma somando seus itens", () => {
    for (const os of call<any[]>("serviceOrders.list")) {
      const pecas = sum(os.items.filter((i: any) => i.tipo === "peca").map((i: any) => i.valorTotal));
      const mao = sum(os.items.filter((i: any) => i.tipo === "servico").map((i: any) => i.valorTotal));
      expect(os.valorPecas).toBeCloseTo(pecas, 2);
      expect(os.valorMaoObra).toBeCloseTo(mao, 2);
      expect(os.valorTotal).toBeCloseTo(pecas + mao, 2);
    }
  });

  it("orçamento: área = largura × altura × qtd e total = área × preço/m²", () => {
    for (const q of call<any[]>("quotes.list")) {
      for (const it of q.items) {
        expect(it.areaM2).toBeCloseTo((it.larguraCm * it.alturaCm * it.quantidade) / 10_000, 2);
        expect(it.valorTotal).toBeCloseTo(it.areaM2 * it.precoPorM2, 2);
      }
      expect(q.valorTotal).toBeCloseTo(sum(q.items.map((i: any) => i.valorTotal)), 2);
    }
  });

  it("CPF/CNPJ de exemplo não passam na validação de dígito (são fictícios)", () => {
    const valid = (doc: string) => {
      const d = doc.replace(/\D/g, "");
      if (/^(\d)\1+$/.test(d)) return false;
      if (d.length === 11) {
        const dv = (n: number) => ((d.slice(0, n).split("").reduce((s, c, i) => s + +c * (n + 1 - i), 0) * 10) % 11) % 10;
        return dv(9) === +d[9] && dv(10) === +d[10];
      }
      const w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
      const dv = (w: number[], n: number) => {
        const r = d.slice(0, n).split("").reduce((s, c, i) => s + +c * w[i], 0) % 11;
        return r < 2 ? 0 : 11 - r;
      };
      return dv(w1, 12) === +d[12] && dv([6, ...w1], 13) === +d[13];
    };
    // CPFs sequenciais clássicos de teste — válidos por coincidência, reconhecidamente fictícios.
    const classicos = new Set(["123.456.789-09", "987.654.321-00"]); // pii-allowlist
    const suspeitos = call<any[]>("customers.list")
      .map((c) => c.documento)
      .filter((d): d is string => !!d && !classicos.has(d) && valid(d));
    expect(suspeitos).toEqual([]);
  });
});

describe("fluxo — pedidos e produção", () => {
  it("avançar status de pedido persiste e reflete na lista", () => {
    const novo = call<any[]>("pedidos.list").find((p) => p.status === "novo");
    call("pedidos.update", { ...novo, status: "aprovado" });
    expect(call<any[]>("pedidos.list").find((p) => p.id === novo.id).status).toBe("aprovado");
  });

  it("criar pedido com itens calcula o valor e aplica os padrões do servidor", () => {
    const cliente = call<any[]>("customers.list")[0];
    const p = call<any>("pedidos.create", {
      customerId: cliente.id,
      items: [
        { descricao: "Banner 2×1", quantidade: 2, valorUnit: 55 },
        { descricao: "Instalação", valorUnit: 120 },
      ],
    });
    expect(p.valor).toBe(230);
    expect(p.status).toBe("novo");
    expect(p.tipo).toBe("Venda");
    expect(p.cliente).toBe(cliente.nome);
    expect(p.customer?.id).toBe(cliente.id);
    expect(call<any[]>("pedidos.getItems", { pedidoId: p.id })).toHaveLength(2);
  });

  it("excluir pedido remove também os itens", () => {
    const p = call<any[]>("pedidos.list")[0];
    call("pedidos.delete", { id: p.id });
    expect(call<any[]>("pedidos.list").some((x) => x.id === p.id)).toBe(false);
    expect(call<any[]>("pedidos.getItems", { pedidoId: p.id })).toEqual([]);
  });

  it("gerar OP nasce na fila e pode avançar", () => {
    const pedido = call<any[]>("pedidos.list").find((p) => p.status === "aprovado");
    const op = call<any>("ops.create", { pedidoId: pedido.id, cliente: pedido.cliente, produto: pedido.produto });
    expect(op.status).toBe("fila");
    call("ops.update", { ...op, status: "andamento" });
    expect(call<any[]>("ops.list").find((o) => o.id === op.id).status).toBe("andamento");
  });
});

describe("fluxo — orçamentos", () => {
  it("criar orçamento calcula área e total por item", () => {
    const q = call<any>("quotes.create", {
      titulo: "Teste",
      items: [{ descricao: "Lona", larguraCm: 300, alturaCm: 100, quantidade: 2, precoPorM2: 55 }],
    });
    expect(q.items[0].areaM2).toBe(6);
    expect(q.valorTotal).toBe(330);
    expect(q.status).toBe("rascunho");
    expect(q.validadeDias).toBe(15);
  });

  it("editar itens do orçamento recalcula o total", () => {
    const q = call<any[]>("quotes.list")[0];
    const up = call<any>("quotes.update", {
      id: q.id,
      items: [{ descricao: "Placa", larguraCm: 100, alturaCm: 50, precoPorM2: 120 }],
    });
    expect(up.items).toHaveLength(1);
    expect(up.valorTotal).toBe(60);
  });

  it("converter em pedido cria pedido aprovado e trava nova conversão", () => {
    const q = call<any[]>("quotes.list").find((x) => x.status === "aprovado");
    const antes = call<any[]>("pedidos.list").length;
    const r = call<any>("quotes.convertToPedido", { id: q.id });
    const pedido = call<any[]>("pedidos.list").find((p) => p.id === r.pedidoId);
    expect(call<any[]>("pedidos.list")).toHaveLength(antes + 1);
    expect(pedido.status).toBe("aprovado");
    expect(pedido.valor).toBeCloseTo(q.valorTotal, 2);
    expect(call<any>("quotes.getById", { id: q.id }).status).toBe("convertido");
    expect(() => call("quotes.convertToPedido", { id: q.id })).toThrow(/já virou pedido/);
  });
});

describe("fluxo — oficina (OS)", () => {
  it("adicionar e remover item recalcula peças, mão de obra e total", () => {
    const os = call<any[]>("serviceOrders.list")[0];
    const com = call<any>("serviceOrders.addItem", {
      serviceOrderId: os.id,
      item: { tipo: "peca", descricao: "Filtro", quantidade: 2, valorUnit: 45 },
    });
    expect(com.valorPecas).toBeCloseTo(os.valorPecas + 90, 2);
    expect(com.valorTotal).toBeCloseTo(os.valorTotal + 90, 2);
    const item = com.items.find((i: any) => i.descricao === "Filtro");
    const sem = call<any>("serviceOrders.removeItem", { itemId: item.id });
    expect(sem.valorTotal).toBeCloseTo(os.valorTotal, 2);
  });

  it("nova OS recebe o próximo número e começa na recepção", () => {
    const maior = Math.max(...call<any[]>("serviceOrders.list").map((o) => o.numero));
    const v = call<any[]>("vehicles.list")[0];
    const os = call<any>("serviceOrders.create", {
      customerId: v.customerId,
      vehicleId: v.id,
      items: [{ tipo: "servico", descricao: "Diagnóstico", valorUnit: 150 }],
    });
    expect(os.numero).toBe(maior + 1);
    expect(os.status).toBe("recepcao");
    expect(os.valorMaoObra).toBe(150);
  });

  it("entregar a OS registra data de saída", () => {
    const os = call<any[]>("serviceOrders.list").find((o) => o.status === "pronto");
    const entregue = call<any>("serviceOrders.advanceStatus", { id: os.id, status: "entregue" });
    expect(entregue.status).toBe("entregue");
    expect(entregue.dataSaida).toBeTruthy();
  });

  it("histórico do veículo lista as OS dele", () => {
    const os = call<any[]>("serviceOrders.list")[0];
    const hist = call<any[]>("vehicles.getHistory", { vehicleId: os.vehicleId });
    expect(hist.some((h) => h.id === os.id)).toBe(true);
  });

  it("busca por placa ignora hífen e caixa", () => {
    const v = call<any[]>("vehicles.list")[0];
    const q = `${v.placa.slice(0, 3).toLowerCase()}-${v.placa.slice(3)}`;
    expect(call<any[]>("vehicles.searchByPlaca", { q }).map((x) => x.id)).toContain(v.id);
  });
});

describe("fluxo — estoque", () => {
  const item = () => call<any[]>("inventory.list").find((i) => i.quantidade > 5);

  it("entrada soma, saída subtrai e ajuste define o saldo", () => {
    const it0 = item();
    const e = call<any>("inventory.recordMovement", { inventoryId: it0.id, tipo: "entrada", quantidade: 10 });
    expect(e.newSaldo).toBeCloseTo(it0.quantidade + 10, 2);
    const s = call<any>("inventory.recordMovement", { inventoryId: it0.id, tipo: "saida", quantidade: 4 });
    expect(s.newSaldo).toBeCloseTo(it0.quantidade + 6, 2);
    const a = call<any>("inventory.recordMovement", { inventoryId: it0.id, tipo: "ajuste", quantidade: 7 });
    expect(a.newSaldo).toBe(7);
    expect(call<any[]>("inventory.listMovements", { inventoryId: it0.id })[0].saldoApos).toBe(7);
  });

  it("não deixa o saldo ficar negativo", () => {
    const it0 = item();
    expect(() =>
      call("inventory.recordMovement", { inventoryId: it0.id, tipo: "saida", quantidade: it0.quantidade + 1 }),
    ).toThrow(/Saldo insuficiente/);
  });

  it("alerta de estoque baixo acompanha o saldo e ignora inativos", () => {
    const baixo = call<any[]>("inventory.lowStockAlert");
    expect(baixo.length).toBeGreaterThan(0);
    for (const i of baixo) expect(i.quantidade).toBeLessThanOrEqual(i.estoqueMinimo);
    const alvo = baixo[0];
    call("inventory.recordMovement", { inventoryId: alvo.id, tipo: "entrada", quantidade: alvo.estoqueMinimo * 2 + 1 });
    expect(call<any[]>("inventory.lowStockAlert").some((i) => i.id === alvo.id)).toBe(false);
    const outro = call<any[]>("inventory.lowStockAlert")[0];
    call("inventory.softDelete", { id: outro.id });
    expect(call<any[]>("inventory.lowStockAlert").some((i) => i.id === outro.id)).toBe(false);
    expect(call<any[]>("inventory.list").some((i) => i.id === outro.id)).toBe(false);
    expect(call<any[]>("inventory.list", { includeInactive: true }).some((i) => i.id === outro.id)).toBe(true);
  });
});

describe("fluxo — financeiro, cobrança e fiscal", () => {
  it("cobrança pendente vira paga ao atualizar e pode ser cancelada", () => {
    const pend = call<any[]>("payments.list", { status: "pendente" });
    expect(pend.length).toBeGreaterThan(1);
    const r = call<any>("payments.refreshStatus", { id: pend[0].id });
    expect(r.status).toBe("pago");
    const pago = call<any>("payments.getById", { id: pend[0].id });
    expect(pago.pagoEm).toBeTruthy();
    expect(pago.netValue).toBeLessThan(pago.valor);
    expect(call<any>("payments.cancel", { id: pend[1].id }).status).toBe("cancelado");
  });

  it("nova cobrança nasce pendente e ligada ao cliente", () => {
    const c = call<any[]>("customers.list")[0];
    const r = call<any>("payments.create", {
      referenciaTipo: "pedido",
      referenciaId: "x",
      valor: 150,
      vencimento: "2026-10-10",
      customerId: c.id,
    });
    expect(r.ok).toBe(true);
    expect(r.payment.status).toBe("pendente");
    expect(r.payment.customer.id).toBe(c.id);
  });

  it("nota fiscal: emitir fica processando e a consulta autoriza com número", () => {
    const pedido = call<any[]>("pedidos.list")[0];
    const doc = call<any>("fiscal.documents.emit", { tipo: "NFe", referenciaTipo: "pedido", referenciaId: pedido.id });
    expect(doc.status).toBe("processando");
    expect(doc.valor).toBe(pedido.valor);
    const ok = call<any>("fiscal.documents.consultStatus", { id: doc.id });
    expect(ok.status).toBe("autorizado");
    expect(ok.numero).toBeTruthy();
    expect(ok.chaveAcesso).toMatch(/^\d{44}$/);
    // Dígito verificador da chave (módulo 11, pesos 2..9 da direita p/ esquerda).
    const corpo = ok.chaveAcesso.slice(0, 43);
    const soma = corpo.split("").reverse().reduce((s: number, d: string, i: number) => s + +d * ((i % 8) + 2), 0);
    const dv = 11 - (soma % 11);
    expect(+ok.chaveAcesso[43]).toBe(dv >= 10 ? 0 : dv);
    expect(call<any>("fiscal.documents.cancel", { id: doc.id, justificativa: "Erro de digitação no valor" }).status).toBe("cancelado");
  });

  it("DRE bate com as transações do período", () => {
    const dre = call<any>("reports.dre", {});
    const desde = new Date(Date.now() - 30 * 86_400_000).toISOString();
    const noPeriodo = call<any[]>("transacoes.list").filter((t) => t.data >= desde);
    const rec = sum(noPeriodo.filter((t) => t.tipo === "receita").map((t) => t.valor));
    const desp = sum(noPeriodo.filter((t) => t.tipo === "despesa").map((t) => t.valor));
    expect(dre.totalReceitas).toBeCloseTo(rec, 2);
    expect(dre.totalDespesas).toBeCloseTo(desp, 2);
    expect(dre.resultadoLiquido).toBeCloseTo(rec - desp, 2);
    expect(sum(dre.receitasPorCategoria.map((c: any) => c.valor))).toBeCloseTo(rec, 2);
  });

  it("nova transação entra no DRE", () => {
    const antes = call<any>("reports.dre", {}).totalReceitas;
    call("transacoes.create", { tipo: "receita", descricao: "Teste", valor: 100, categoria: "Vendas" });
    expect(call<any>("reports.dre", {}).totalReceitas).toBeCloseTo(antes + 100, 2);
  });

  it("relatório de vendas: total, ticket médio e série somam igual", () => {
    const r = call<any>("reports.vendas", { groupBy: "dia" });
    expect(sum(r.series.map((s: any) => s.total))).toBeCloseTo(r.totalVendas, 2);
    const n = sum(Object.values(r.countPedidos) as number[]);
    expect(r.ticketMedio).toBe(Math.round((r.totalVendas / n) * 100) / 100);
  });

  it("relatório de estoque conta os itens com saldo baixo", () => {
    const r = call<any>("reports.estoque");
    expect(r.itensComEstoqueBaixo).toHaveLength(call<any[]>("inventory.lowStockAlert").length);
    expect(r.valorTotalEstoque).toBeGreaterThan(0);
  });
});

describe("fluxo — cadastros e empresas", () => {
  it("cliente: criar, buscar por nome/documento, editar e excluir", () => {
    const c = call<any>("customers.create", { nome: "Cliente Teste QA", documento: "000.111.222-33", telefone: "(19) 90000-0000" }); // pii-allowlist (CPF fictício do teste)
    expect(c.aceitaWhatsapp).toBe(true);
    expect(call<any[]>("customers.search", { q: "teste qa" }).map((x) => x.id)).toContain(c.id);
    expect(call<any[]>("customers.search", { q: "00011122233" }).map((x) => x.id)).toContain(c.id);
    const up = call<any>("customers.update", { id: c.id, email: "qa@exemplo.com" });
    expect(up.email).toBe("qa@exemplo.com");
    expect(up.nome).toBe("Cliente Teste QA");
    call("customers.delete", { id: c.id });
    expect(() => call("customers.getById", { id: c.id })).toThrow(/não encontrado/);
  });

  it("campo opcional não enviado não apaga valor existente", () => {
    const p = call<any[]>("produtos.list")[0];
    const up = call<any>("produtos.update", { id: p.id, preco: 99, sku: undefined });
    expect(up.preco).toBe(99);
    expect(up.sku).toBe(p.sku);
  });

  it("trocar de empresa muda a empresa atual; a ativa não pode ser excluída", () => {
    const [a, b] = call<any[]>("companies.list");
    expect(call<any>("companies.current").id).toBe(a.id);
    call("companies.switch", { id: b.id });
    expect(call<any>("companies.current").id).toBe(b.id);
    expect(() => call("companies.delete", { id: b.id })).toThrow(/ativa/);
  });

  it("funções que dependem do servidor real avisam com mensagem clara", () => {
    expect(() => call("reports.exportPdf", { tipo: "dre" })).toThrow(/não está disponível na demonstração/);
    expect(() => call("quotes.generatePdf", { id: "x" })).toThrow(/servidor real/);
  });
});
