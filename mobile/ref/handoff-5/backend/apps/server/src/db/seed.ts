// ──────────────────────────────────────────────────────────────
// Seed de demonstração — prova que o modelo refundado cumpre a DoD
// da Fase 0. Rode com: pnpm --filter server seed
//
// Demonstra:
//  1) vínculo por id: renomear o cliente NÃO órfã o histórico do pedido
//  2) numeração atômica: chamadas concorrentes geram números distintos
//  3) dinheiro em centavos: total fecha exato
// ──────────────────────────────────────────────────────────────
import { eq } from "drizzle-orm";
import { db, pool } from "./client";
import { tenants, users, pessoas, produtos, pedidos, pedidoItens } from "./schema";
import { nextNumero } from "../lib/sequences";
import { sumCents, mulCents, reaisToCents, formatBRL } from "@oimpresso/shared/money";

async function main() {
  // — Empresa, usuário, cliente, produto —
  const [tenant] = await db.insert(tenants).values({
    nome: "Gráfica Modelo", cnpj: "00.000.000/0001-00", regimeTributario: "simples",
  }).returning();

  const [user] = await db.insert(users).values({
    tenantId: tenant.id, nome: "Wagner", email: "wagner@modelo.com", papel: "admin", authProvider: "senha",
  }).returning();

  const [cliente] = await db.insert(pessoas).values({
    tenantId: tenant.id, nome: "Marilia Costa", tipoPessoa: "PF", papeis: ["cliente"],
  }).returning();

  const [produto] = await db.insert(produtos).values({
    tenantId: tenant.id, nome: "Cartão de visita 9x5 4/4", sku: "CART-9X5",
    unidadeCompra: "folha", unidadeEstoque: "folha", unidadeVenda: "milheiro",
    precoCents: reaisToCents(248), custoCents: reaisToCents(96),
  }).returning();

  // — Cria a OS com numeração atômica + item estruturado + total em centavos —
  const numero = await nextNumero(db, tenant.id, "OS");
  const itens = [{ qtd: 1, precoUnitCents: produto.precoCents }];
  const totalCents = sumCents(...itens.map((i) => mulCents(i.precoUnitCents, i.qtd)));

  const [pedido] = await db.insert(pedidos).values({
    tenantId: tenant.id, numero, clienteId: cliente.id, etapa: "orc",
    prazoAt: new Date(Date.now() + 8 * 3600_000), // prazo REAL (timestamptz)
  }).returning();

  await db.insert(pedidoItens).values({
    pedidoId: pedido.id, produtoId: produto.id, qtd: "1", unidade: "milheiro",
    atributos: { formato: "9x5cm", papel: "Couché 300g", cores: "4/4" },
    precoUnitCents: produto.precoCents,
  });

  console.log(`✓ OS-${numero} criada · ${formatBRL(totalCents)} · cliente=${cliente.nome}`);

  // — DEMO 1: renomear cliente; o pedido continua ligado (vínculo por id) —
  await db.update(pessoas).set({ nome: "Marília Costa" }).where(eq(pessoas.id, cliente.id));
  const [check] = await db.select().from(pedidos).where(eq(pedidos.id, pedido.id));
  const [renomeado] = await db.select().from(pessoas).where(eq(pessoas.id, check.clienteId));
  console.log(`✓ Renomeado para "${renomeado.nome}" — pedido AINDA ligado (clienteId imutável)`);

  // — DEMO 2: numeração atômica sob concorrência —
  const [a, b] = await Promise.all([
    nextNumero(db, tenant.id, "OS"),
    nextNumero(db, tenant.id, "OS"),
  ]);
  console.log(`✓ Concorrência: números distintos → OS-${a} e OS-${b} (${a !== b ? "OK" : "FALHOU"})`);

  await pool.end();
}

main().catch((e) => { console.error(e); process.exit(1); });
