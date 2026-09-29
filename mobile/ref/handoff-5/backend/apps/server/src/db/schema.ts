// ──────────────────────────────────────────────────────────────
// Oimpresso ERP · Drizzle schema (PostgreSQL)
// Fase 0 · Passo 1 — Modelo de dados (refundação)
//
// Corrige as falhas forenses dos laudos:
//  • UUID em toda entidade (fim do "cliente por nome")  — v2 §1
//  • tenant_id em tudo + RLS (ver rls.sql)              — v2 §15
//  • dinheiro em centavos (bigint), nunca float         — v2 §6
//  • datas timestamptz, nunca texto                     — v2 §4
//  • numeração via sequência server-side                — v2 §5
//  • item de pedido ESTRUTURADO (não string)            — v2 §3
//  • vínculos por id (não string)                       — v2 §1/§2
//  • version (concorrência otimista)                    — v2 §8
//  • audit_log append-only                              — v1 §04
// ──────────────────────────────────────────────────────────────
import {
  pgTable, uuid, text, timestamp, bigint, integer, numeric,
  jsonb, boolean, primaryKey, index, unique,
} from "drizzle-orm/pg-core";

// Colunas comuns a toda entidade de negócio (multi-tenant + tempo real)
const tenantCol = () => uuid("tenant_id").notNull();
const createdAt = () => timestamp("created_at", { withTimezone: true }).defaultNow().notNull();

// ── Tenancy & identidade ──────────────────────────────────────
export const tenants = pgTable("tenants", {
  id: uuid("id").defaultRandom().primaryKey(),
  nome: text("nome").notNull(),
  cnpj: text("cnpj"),
  regimeTributario: text("regime_tributario"), // simples | presumido | real
  createdAt: createdAt(),
});

export const users = pgTable("users", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  nome: text("nome").notNull(),
  email: text("email").notNull(),
  papel: text("papel").notNull(),            // admin | gerente | operador | ...
  authProvider: text("auth_provider"),       // oauth | senha
  createdAt: createdAt(),
}, (t) => ({ emailUq: unique().on(t.tenantId, t.email) }));

// ── Pessoas (cadastro unificado multi-papel) ──────────────────
export const pessoas = pgTable("pessoas", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  nome: text("nome").notNull(),
  doc: text("doc"),                          // CPF/CNPJ
  tipoPessoa: text("tipo_pessoa").notNull(), // PF | PJ
  papeis: text("papeis").array().notNull().default([]), // {cliente,fornecedor,funcionario,transportadora}
  contato: jsonb("contato"),
  endereco: jsonb("endereco"),
  consentimentoLgpd: jsonb("consentimento_lgpd"),
  createdAt: createdAt(),
}, (t) => ({ tIdx: index("pessoas_tenant_idx").on(t.tenantId) }));

// ── Produtos & estoque dimensional ────────────────────────────
export const produtos = pgTable("produtos", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  nome: text("nome").notNull(),
  sku: text("sku"),
  categoria: text("categoria"),
  // unidade tripla + conversão (compra rolo/folha, vende m²/peça) — v2 §10
  unidadeCompra: text("unidade_compra"),
  unidadeEstoque: text("unidade_estoque"),
  unidadeVenda: text("unidade_venda"),
  fatorConversao: numeric("fator_conversao"),
  perdaPct: numeric("perda_pct"),
  precoCents: bigint("preco_cents", { mode: "number" }).notNull().default(0),
  custoCents: bigint("custo_cents", { mode: "number" }).notNull().default(0),
  estoqueMin: numeric("estoque_min"),
  // margem é POLÍTICA da empresa (cadastro), não escolha do vendedor — trava o lucro mínimo
  margemPct: numeric("margem_pct").notNull().default("45"),
  createdAt: createdAt(),
}, (t) => ({ skuUq: unique().on(t.tenantId, t.sku) }));

export const estoqueMovimentos = pgTable("estoque_movimentos", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  produtoId: uuid("produto_id").notNull().references(() => produtos.id),
  lote: text("lote"),
  tipo: text("tipo").notNull(),              // entrada | saida | ajuste
  quantidade: numeric("quantidade").notNull(),
  unidade: text("unidade").notNull(),
  pedidoId: uuid("pedido_id"),               // baixa dimensional pela OS
  createdAt: createdAt(),
});

// ── Pedidos (OS) + item ESTRUTURADO ───────────────────────────
export const pedidos = pgTable("pedidos", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  numero: integer("numero").notNull(),        // via sequences (tenant, série)
  clienteId: uuid("cliente_id").notNull().references(() => pessoas.id), // VÍNCULO POR ID
  etapa: text("etapa").notNull().default("orc"),
  prazoAt: timestamp("prazo_at", { withTimezone: true }),  // data real → atraso calculado
  pagamento: text("pagamento"),
  obs: text("obs"),
  version: integer("version").notNull().default(0),         // concorrência otimista
  createdAt: createdAt(),
}, (t) => ({ numUq: unique().on(t.tenantId, t.numero), cliIdx: index("pedidos_cliente_idx").on(t.clienteId) }));

export const pedidoItens = pgTable("pedido_itens", {
  id: uuid("id").defaultRandom().primaryKey(),
  pedidoId: uuid("pedido_id").notNull().references(() => pedidos.id, { onDelete: "cascade" }),
  produtoId: uuid("produto_id").references(() => produtos.id),
  qtd: numeric("qtd").notNull(),
  unidade: text("unidade").notNull(),
  atributos: jsonb("atributos"),             // formato, gramatura, cores 4/4, acabamento
  fichaTecnica: jsonb("ficha_tecnica"),
  precoUnitCents: bigint("preco_unit_cents", { mode: "number" }).notNull().default(0),
});

// ── Orçamento (motor) ─────────────────────────────────────────
export const orcamentos = pgTable("orcamentos", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  pedidoId: uuid("pedido_id").references(() => pedidos.id),
  versao: integer("versao").notNull().default(1),
  fichaTecnica: jsonb("ficha_tecnica"),
  imposicao: jsonb("imposicao"),
  custoCents: bigint("custo_cents", { mode: "number" }).notNull().default(0),
  precoCents: bigint("preco_cents", { mode: "number" }).notNull().default(0),
  createdAt: createdAt(),
});

// ── Produção / PCP ────────────────────────────────────────────
export const estacoes = pgTable("estacoes", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  nome: text("nome").notNull(),
  velocidade: numeric("velocidade"),
  jornada: jsonb("jornada"),
  setupMin: integer("setup_min"),
});

export const producaoJobs = pgTable("producao_jobs", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  pedidoId: uuid("pedido_id").notNull().references(() => pedidos.id),
  estacaoId: uuid("estacao_id").references(() => estacoes.id),
  etapa: text("etapa").notNull(),
  sequencia: integer("sequencia"),
  dueAt: timestamp("due_at", { withTimezone: true }),
  tempoEstimadoMin: integer("tempo_estimado_min"),
  version: integer("version").notNull().default(0),
});

// ── Financeiro (vínculo por id, não string) ───────────────────
export const contasFinanceiras = pgTable("contas_financeiras", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  nome: text("nome").notNull(),
  tipo: text("tipo"),
});

export const titulos = pgTable("titulos", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  pedidoId: uuid("pedido_id").references(() => pedidos.id),
  pessoaId: uuid("pessoa_id").notNull().references(() => pessoas.id),
  tipo: text("tipo").notNull(),              // receber | pagar
  valorCents: bigint("valor_cents", { mode: "number" }).notNull(),
  status: text("status").notNull().default("aberto"),
  createdAt: createdAt(),
});

export const tituloParcelas = pgTable("titulo_parcelas", {
  id: uuid("id").defaultRandom().primaryKey(),
  tituloId: uuid("titulo_id").notNull().references(() => titulos.id, { onDelete: "cascade" }),
  numero: integer("numero").notNull(),
  vencimentoAt: timestamp("vencimento_at", { withTimezone: true }).notNull(),
  valorCents: bigint("valor_cents", { mode: "number" }).notNull(),
  status: text("status").notNull().default("aberto"),
});

export const baixas = pgTable("baixas", {
  id: uuid("id").defaultRandom().primaryKey(),
  tituloId: uuid("titulo_id").notNull().references(() => titulos.id),
  parcelaId: uuid("parcela_id").references(() => tituloParcelas.id),
  valorCents: bigint("valor_cents", { mode: "number" }).notNull(),
  contaId: uuid("conta_id").references(() => contasFinanceiras.id),
  meio: text("meio"),
  baixaAt: timestamp("baixa_at", { withTimezone: true }).defaultNow().notNull(),
});

// ── Fiscal ────────────────────────────────────────────────────
export const documentosFiscais = pgTable("documentos_fiscais", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  pedidoId: uuid("pedido_id").notNull().references(() => pedidos.id),
  tipo: text("tipo").notNull(),              // NFe | NFCe | NFSe
  serie: text("serie"),
  numero: integer("numero"),                 // via sequences
  status: text("status").notNull().default("rascunho"),
  natureza: text("natureza"),                // servico | mercadoria
  providerRef: text("provider_ref"),
  xml: text("xml"),
  createdAt: createdAt(),
});

// ── Notificações ──────────────────────────────────────────────
export const notificacoes = pgTable("notificacoes", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  userId: uuid("user_id").references(() => users.id),
  tipo: text("tipo"),
  refEntity: text("ref_entity"),
  refId: uuid("ref_id"),
  lidaAt: timestamp("lida_at", { withTimezone: true }),
  createdAt: createdAt(),
});

// ── Auditoria (append-only) ───────────────────────────────────
export const auditLog = pgTable("audit_log", {
  id: uuid("id").defaultRandom().primaryKey(),
  tenantId: tenantCol(),
  actorId: uuid("actor_id"),
  entity: text("entity").notNull(),
  entityId: uuid("entity_id").notNull(),
  action: text("action").notNull(),
  before: jsonb("before"),
  after: jsonb("after"),
  at: timestamp("at", { withTimezone: true }).defaultNow().notNull(),
}, (t) => ({ entIdx: index("audit_entity_idx").on(t.entity, t.entityId) }));

// ── Numeração atômica (server-side) ───────────────────────────
export const sequences = pgTable("sequences", {
  tenantId: uuid("tenant_id").notNull(),
  serie: text("serie").notNull(),
  valor: integer("valor").notNull().default(0),
}, (t) => ({ pk: primaryKey({ columns: [t.tenantId, t.serie] }) }));
