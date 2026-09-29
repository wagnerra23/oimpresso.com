import { createSeed, customer, produto, round2, stock, svgPhoto } from "./seed";
import type {
  CompanySettings,
  In,
  Out,
  PaymentRow,
  PedidoRow,
  QuoteItem,
  QuoteRow,
  ServiceOrder,
  ServiceOrderRow,
  SOItem,
  Store,
  UUID,
  VehicleRow,
} from "./types";

/**
 * Procedure handlers for the demo backend. Each one mirrors what the matching
 * router in `server/routers/*` does, against an in-browser store. Typed with
 * the router's own input/output types, so shape drift fails `tsc`.
 */

type Fn = (input: any) => unknown;
export const handlers = new Map<string, Fn>();

function on<A extends keyof Out & keyof In, B extends keyof Out[A] & keyof In[A]>(
  a: A,
  b: B,
  fn: (input: In[A][B]) => Out[A][B],
) {
  handlers.set(`${String(a)}.${String(b)}`, fn as Fn);
}

function on3<
  A extends keyof Out & keyof In,
  B extends keyof Out[A] & keyof In[A],
  C extends keyof Out[A][B] & keyof In[A][B],
>(a: A, b: B, c: C, fn: (input: In[A][B][C]) => Out[A][B][C]) {
  handlers.set(`${String(a)}.${String(b)}.${String(c)}`, fn as Fn);
}

// ─── Store ──────────────────────────────────────────────────────────────────

// Bump when the seed changes so saved demo data doesn't outlive it.
const STORAGE_KEY = "oimpresso-demo-store-v2";
let store: Store | null = null;

function db(): Store {
  if (store) return store;
  try {
    const raw = globalThis.localStorage?.getItem(STORAGE_KEY);
    if (raw) store = JSON.parse(raw) as Store;
  } catch {
    // Storage unavailable — start from the seed.
  }
  store ??= createSeed();
  return store;
}

function save() {
  try {
    globalThis.localStorage?.setItem(STORAGE_KEY, JSON.stringify(store));
  } catch {
    // Not persisted; the demo keeps working in memory.
  }
}

/** Drops the saved demo data so the next load starts from the seed. */
export function resetDemoStore() {
  store = null;
  try {
    globalThis.localStorage?.removeItem(STORAGE_KEY);
  } catch {
    // ignore
  }
}

export class DemoError extends Error {}

const fail = (message: string): never => {
  throw new DemoError(message);
};

const notInDemo = (what: string): never =>
  fail(`${what} não está disponível na demonstração — precisa do servidor real.`);

function newId(): UUID {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) return crypto.randomUUID() as UUID;
  return "xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx".replace(/x/g, () =>
    Math.floor(Math.random() * 16).toString(16),
  ) as UUID;
}

const now = () => new Date().toISOString();

/** Removes `undefined` keys so an optional input field never erases a value. */
function clean<T extends object>(obj: T): { [K in keyof T]: Exclude<T[K], undefined> } {
  return Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== undefined)) as never;
}

function find<T extends { id: string }>(rows: T[], id: string, label: string): T {
  return rows.find((r) => r.id === id) ?? fail(`${label} não encontrado.`);
}

function replace<T extends { id: string }>(rows: T[], next: T): T[] {
  return rows.map((r) => (r.id === next.id ? next : r));
}

const byDateDesc = <T,>(key: (row: T) => string) => (a: T, b: T) => key(b).localeCompare(key(a));

function customerLite(id: string | null) {
  const c = id ? db().customers.find((x) => x.id === id) : undefined;
  return c ? { id: c.id, nome: c.nome, telefone: c.telefone, email: c.email } : null;
}

// ─── Auth / companies / notifications ───────────────────────────────────────

function sessionFor(email: string, name?: string): Out["auth"]["loginWithPassword"] {
  return {
    sessionToken: "demo-session",
    user: {
      id: 1,
      openId: "demo",
      name: name ?? "Usuário Demo",
      email,
      loginMethod: "password",
      role: "admin",
      lastSignedIn: now(),
    },
  };
}

on("auth", "loginWithPassword", (i) => sessionFor(i.email));
on("auth", "registerWithPassword", (i) => sessionFor(i.email, i.name));

function companyOut(id: string) {
  const { role: _role, ...company } = find(db().companies, id, "Empresa");
  return company;
}

on("companies", "current", () => companyOut(db().currentCompanyId));
on("companies", "list", () => db().companies);
on("companies", "switch", (i) => {
  db().currentCompanyId = find(db().companies, i.id, "Empresa").id;
  save();
  return companyOut(i.id);
});
on("companies", "create", (i) => {
  const c = { id: newId(), ownerUserId: 1, nome: i.nome, vertical: i.vertical ?? "cv", ativa: true, createdAt: now(), updatedAt: now(), role: "owner" as const };
  db().companies.push(c);
  save();
  return companyOut(c.id);
});
on("companies", "update", (i) => {
  const s = db();
  s.companies = replace(s.companies, { ...find(s.companies, i.id, "Empresa"), ...clean(i), updatedAt: now() });
  save();
  return companyOut(i.id);
});
on("companies", "delete", (i) => {
  const s = db();
  if (s.currentCompanyId === i.id) fail("Não dá para excluir a empresa ativa.");
  s.companies = s.companies.filter((c) => c.id !== i.id);
  save();
  return { id: i.id, ativa: false } as const;
});

on("notifications", "registerToken", () => ({ ok: true as const }));
on("notifications", "unregisterToken", () => ({ ok: true as const }));

// ─── Customers ──────────────────────────────────────────────────────────────

on("customers", "list", () => [...db().customers].sort((a, b) => a.nome.localeCompare(b.nome)));
on("customers", "getById", (i) => find(db().customers, i.id, "Cliente"));
on("customers", "search", (i) => {
  const q = i.q.trim().toLowerCase();
  const digits = q.replace(/\D/g, "");
  return db().customers.filter(
    (c) =>
      c.nome.toLowerCase().includes(q) ||
      (c.razaoSocial ?? "").toLowerCase().includes(q) ||
      (digits.length > 2 && ((c.documento ?? "").replace(/\D/g, "").includes(digits) || (c.telefone ?? "").replace(/\D/g, "").includes(digits))),
  );
});
on("customers", "create", (i) => {
  const c = customer({
    cidade: null,
    uf: null,
    codigoMunicipioIbge: null,
    classificacao: null,
    consentimentoData: null,
    prazoPadraoDias: null,
    whatsapp: null,
    ...clean(i),
    id: newId(),
    createdAt: now(),
    updatedAt: now(),
  });
  db().customers.push(c);
  save();
  return c;
});
on("customers", "update", (i) => {
  const s = db();
  const next = { ...find(s.customers, i.id, "Cliente"), ...clean(i), updatedAt: now() };
  s.customers = replace(s.customers, next);
  save();
  return next;
});
on("customers", "delete", (i) => {
  const s = db();
  s.customers = s.customers.filter((c) => c.id !== i.id);
  save();
  return { id: i.id };
});

// ─── Produtos ───────────────────────────────────────────────────────────────

on("produtos", "list", () => [...db().produtos].sort((a, b) => a.nome.localeCompare(b.nome)));
on("produtos", "getById", (i) => find(db().produtos, i.id, "Produto"));
on("produtos", "create", (i) => {
  const p = produto({ ...clean(i), id: newId() });
  db().produtos.push(p);
  save();
  return p;
});
on("produtos", "update", (i) => {
  const s = db();
  const next = { ...find(s.produtos, i.id, "Produto"), ...clean(i) };
  s.produtos = replace(s.produtos, next);
  save();
  return next;
});
on("produtos", "delete", (i) => {
  const s = db();
  s.produtos = s.produtos.filter((p) => p.id !== i.id);
  save();
  return { id: i.id };
});

// ─── Pedidos + OPs ──────────────────────────────────────────────────────────

function hydratePedido(row: PedidoRow): Out["pedidos"]["list"][number] {
  const c = customerLite(row.customerId);
  return {
    ...row,
    customer: c ? { id: c.id, nome: c.nome, telefone: c.telefone } : null,
    items: db().pedidoItems.filter((it) => it.pedidoId === row.id),
  };
}

function createPedido(i: In["pedidos"]["create"]) {
  const s = db();
  const id = newId();
  const items = (i.items ?? []).map((it) => {
    const quantidade = it.quantidade ?? 1;
    return {
      id: newId(),
      pedidoId: id,
      produtoId: it.produtoId ?? null,
      descricao: it.descricao,
      quantidade,
      valorUnit: it.valorUnit,
      valorTotal: round2(quantidade * it.valorUnit),
    };
  });
  const row: PedidoRow = {
    id,
    cliente: i.cliente ?? customerLite(i.customerId ?? null)?.nome ?? "Cliente avulso",
    produto: i.produto ?? items[0]?.descricao ?? "",
    valor: i.valor ?? round2(items.reduce((sum, it) => sum + it.valorTotal, 0)),
    status: i.status ?? "novo",
    data: i.data ?? now(),
    tipo: i.tipo ?? "Venda",
    customerId: i.customerId ?? null,
  };
  s.pedidos.push(row);
  s.pedidoItems.push(...items);
  save();
  return hydratePedido(row);
}

on("pedidos", "list", () => [...db().pedidos].sort(byDateDesc((p) => p.data)).map(hydratePedido));
on("pedidos", "getItems", (i) => db().pedidoItems.filter((it) => it.pedidoId === i.pedidoId));
on("pedidos", "create", createPedido);
on("pedidos", "update", (i) => {
  const s = db();
  const next: PedidoRow = { ...find(s.pedidos, i.id, "Pedido"), ...clean(i) };
  s.pedidos = replace(s.pedidos, next);
  save();
  return next;
});
on("pedidos", "delete", (i) => {
  const s = db();
  s.pedidos = s.pedidos.filter((p) => p.id !== i.id);
  s.pedidoItems = s.pedidoItems.filter((it) => it.pedidoId !== i.id);
  save();
  return { id: i.id };
});

on("ops", "list", () => db().ops);
on("ops", "create", (i) => {
  const op = { id: newId(), pedidoId: i.pedidoId, cliente: i.cliente, produto: i.produto, status: i.status ?? "fila", customerId: i.customerId ?? null };
  db().ops.push(op);
  save();
  return op;
});
on("ops", "update", (i) => {
  const s = db();
  const next = { ...find(s.ops, i.id, "OP"), ...clean(i) };
  s.ops = replace(s.ops, next);
  save();
  return next;
});
on("ops", "delete", (i) => {
  const s = db();
  s.ops = s.ops.filter((o) => o.id !== i.id);
  save();
  return { id: i.id };
});

// ─── Financeiro ─────────────────────────────────────────────────────────────

on("transacoes", "list", () => [...db().transacoes].sort(byDateDesc((t) => t.data)));
on("transacoes", "create", (i) => {
  const t = { id: newId(), tipo: i.tipo, descricao: i.descricao, valor: i.valor, categoria: i.categoria, data: i.data ?? now() };
  db().transacoes.push(t);
  save();
  return t;
});
on("transacoes", "update", (i) => {
  const s = db();
  find(s.transacoes, i.id, "Transação");
  s.transacoes = replace(s.transacoes, i);
  save();
  return i;
});
on("transacoes", "delete", (i) => {
  const s = db();
  s.transacoes = s.transacoes.filter((t) => t.id !== i.id);
  save();
  return { id: i.id };
});

function hydratePayment(row: PaymentRow): Out["payments"]["getById"] {
  const c = row.customerId ? db().customers.find((x) => x.id === row.customerId) : undefined;
  return {
    ...row,
    customer: c ? { id: c.id, nome: c.nome, telefone: c.telefone, email: c.email, documento: c.documento } : null,
  };
}

on("payments", "status", () => ({ configured: true, ambiente: "sandbox" as const }));
on("payments", "list", (i) =>
  db()
    .payments.filter(
      (p) =>
        (!i?.status || p.status === i.status) &&
        (!i?.customerId || p.customerId === i.customerId) &&
        (!i?.referenciaTipo || p.referenciaTipo === i.referenciaTipo) &&
        (!i?.referenciaId || p.referenciaId === i.referenciaId),
    )
    .sort(byDateDesc((p) => p.createdAt))
    .map(hydratePayment),
);
on("payments", "getById", (i) => hydratePayment(find(db().payments, i.id, "Cobrança")));
on("payments", "create", (i) => {
  const row: PaymentRow = {
    id: newId(),
    userId: 1,
    customerId: i.customerId ?? null,
    referenciaTipo: i.referenciaTipo,
    referenciaId: i.referenciaId,
    valor: i.valor,
    descricao: i.descricao ?? null,
    metodoPreferido: i.metodoPreferido ?? "pix",
    status: "pendente",
    providerPaymentId: `pay_demo_${Date.now()}`,
    paymentUrl: null,
    vencimento: i.vencimento,
    pagoEm: null,
    netValue: null,
    createdAt: now(),
    updatedAt: now(),
  };
  db().payments.push(row);
  save();
  return { ok: true, payment: hydratePayment(row) } as const;
});
on("payments", "cancel", (i) => {
  const s = db();
  s.payments = replace(s.payments, { ...find(s.payments, i.id, "Cobrança"), status: "cancelado", updatedAt: now() });
  save();
  return { id: i.id, status: "cancelado" as const };
});
// The demo "receives" the payment on refresh, so the whole flow can be seen.
on("payments", "refreshStatus", (i) => {
  const s = db();
  const p = find(s.payments, i.id, "Cobrança");
  const next: PaymentRow =
    p.status === "pendente" || p.status === "vencido"
      ? { ...p, status: "pago", pagoEm: now(), netValue: round2(p.valor * 0.99), updatedAt: now() }
      : p;
  s.payments = replace(s.payments, next);
  save();
  return { id: next.id, status: next.status };
});

// ─── Estoque ────────────────────────────────────────────────────────────────

const isLow = (it: { quantidade: number; estoqueMinimo: number }) => it.quantidade <= it.estoqueMinimo;

on("inventory", "list", (i) => {
  const q = i?.search?.trim().toLowerCase();
  return db()
    .inventory.filter(
      (it) =>
        (i?.includeInactive || it.ativo) &&
        (!i?.onlyLowStock || isLow(it)) &&
        (!q || it.nome.toLowerCase().includes(q) || (it.codigo ?? "").toLowerCase().includes(q)),
    )
    .sort((a, b) => a.nome.localeCompare(b.nome));
});
on("inventory", "lowStockAlert", () => db().inventory.filter((it) => it.ativo && isLow(it)));
on("inventory", "listMovements", (i) =>
  db()
    .movements.filter((m) => m.inventoryId === i.inventoryId)
    .sort(byDateDesc((m) => m.createdAt))
    .slice(0, i.limit ?? 50),
);
on("inventory", "getById", (i) => ({
  ...find(db().inventory, i.id, "Item de estoque"),
  movements: db()
    .movements.filter((m) => m.inventoryId === i.id)
    .sort(byDateDesc((m) => m.createdAt)),
}));
on("inventory", "create", (i) => {
  const it = stock({ quantidade: 0, estoqueMinimo: 0, custoUnit: 0, ...clean(i), id: newId(), createdAt: now(), updatedAt: now() });
  db().inventory.push(it);
  save();
  return it;
});
on("inventory", "update", (i) => {
  const s = db();
  const next = { ...find(s.inventory, i.id, "Item de estoque"), ...clean(i), updatedAt: now() };
  s.inventory = replace(s.inventory, next);
  save();
  return next;
});
on("inventory", "recordMovement", (i) => {
  const s = db();
  const it = find(s.inventory, i.inventoryId, "Item de estoque");
  const newSaldo = round2(
    i.tipo === "entrada" ? it.quantidade + i.quantidade : i.tipo === "ajuste" ? i.quantidade : it.quantidade - i.quantidade,
  );
  if (newSaldo < 0) fail(`Saldo insuficiente: há ${it.quantidade} ${it.unidade} de ${it.nome}.`);
  const movement = {
    id: newId(),
    inventoryId: it.id,
    tipo: i.tipo,
    quantidade: i.quantidade,
    saldoApos: newSaldo,
    motivo: i.motivo ?? null,
    referenciaTipo: i.referenciaTipo ?? null,
    referenciaId: i.referenciaId ?? null,
    custoUnitMovimento: i.custoUnitMovimento ?? null,
    createdAt: now(),
  };
  s.movements.push(movement);
  s.inventory = replace(s.inventory, { ...it, quantidade: newSaldo, updatedAt: now() });
  save();
  return { movement, newSaldo };
});
function setAtivo(id: string, ativo: boolean) {
  const s = db();
  s.inventory = replace(s.inventory, { ...find(s.inventory, id, "Item de estoque"), ativo, updatedAt: now() });
  save();
  return { id, ativo };
}
on("inventory", "softDelete", (i) => setAtivo(i.id, false));
on("inventory", "restore", (i) => setAtivo(i.id, true));

// ─── Veículos + OS (oficina) ────────────────────────────────────────────────

function hydrateVehicle(row: VehicleRow): Out["vehicles"]["getById"] {
  const c = customerLite(row.customerId);
  return { ...row, customer: c ? { id: c.id, nome: c.nome, telefone: c.telefone } : null };
}

const normPlaca = (p: string) => p.toUpperCase().replace(/[^A-Z0-9]/g, "");

on("vehicles", "list", (i) =>
  db()
    .vehicles.filter((v) => !i?.customerId || v.customerId === i.customerId)
    .map(hydrateVehicle),
);
on("vehicles", "getById", (i) => hydrateVehicle(find(db().vehicles, i.id, "Veículo")));
on("vehicles", "searchByPlaca", (i) => {
  const q = normPlaca(i.q);
  return db()
    .vehicles.filter((v) => normPlaca(v.placa).includes(q))
    .map(hydrateVehicle);
});
on("vehicles", "getHistory", (i) =>
  db()
    .serviceOrders.filter((o) => o.vehicleId === i.vehicleId)
    .sort((a, b) => b.numero - a.numero)
    .map((o) => ({ id: o.id, numero: o.numero, status: o.status, valorTotal: o.valorTotal, dataEntrada: o.dataEntrada, dataSaida: o.dataSaida, createdAt: o.createdAt })),
);
on("vehicles", "create", (i) => {
  const v: VehicleRow = {
    id: newId(),
    customerId: i.customerId,
    placa: normPlaca(i.placa),
    chassi: i.chassi ?? null,
    marca: i.marca,
    modelo: i.modelo,
    ano: i.ano ?? null,
    cor: i.cor ?? null,
    kmAtual: i.kmAtual ?? 0,
    observacoes: i.observacoes ?? null,
    createdAt: now(),
    updatedAt: now(),
  };
  db().vehicles.push(v);
  save();
  return hydrateVehicle(v);
});
on("vehicles", "update", (i) => {
  const s = db();
  const patch = clean(i);
  const next: VehicleRow = { ...find(s.vehicles, i.id, "Veículo"), ...patch, ...(patch.placa ? { placa: normPlaca(patch.placa) } : {}), updatedAt: now() };
  s.vehicles = replace(s.vehicles, next);
  save();
  return hydrateVehicle(next);
});
on("vehicles", "delete", (i) => {
  const s = db();
  s.vehicles = s.vehicles.filter((v) => v.id !== i.id);
  save();
  return { id: i.id };
});

function hydrateOS(row: ServiceOrderRow): ServiceOrder {
  const s = db();
  const v = s.vehicles.find((x) => x.id === row.vehicleId);
  return {
    ...row,
    vehicle: v ? { id: v.id, placa: v.placa, marca: v.marca, modelo: v.modelo, ano: v.ano, cor: v.cor } : null,
    customer: customerLite(row.customerId),
    items: s.soItems.filter((it) => it.serviceOrderId === row.id),
    photos: s.soPhotos.filter((p) => p.serviceOrderId === row.id),
    publicTokens: s.soTokens.filter((t) => t.serviceOrderId === row.id),
  };
}

type SOItemInput = In["serviceOrders"]["addItem"]["item"];

function makeSOItem(serviceOrderId: string, it: SOItemInput): SOItem {
  const quantidade = it.quantidade ?? 1;
  return {
    id: newId(),
    serviceOrderId,
    tipo: it.tipo,
    codigo: it.codigo ?? null,
    descricao: it.descricao,
    quantidade,
    valorUnit: it.valorUnit,
    valorTotal: round2(quantidade * it.valorUnit),
    fornecedor: it.fornecedor ?? null,
    tempoEstimadoHoras: it.tempoEstimadoHoras ?? null,
    mecanicoResponsavel: it.mecanicoResponsavel ?? null,
    createdAt: now(),
  };
}

/** Recomputes the OS totals from its items and returns the hydrated OS. */
function retotalOS(id: string): ServiceOrder {
  const s = db();
  const row = find(s.serviceOrders, id, "OS");
  const items = s.soItems.filter((it) => it.serviceOrderId === id);
  const valorPecas = round2(items.filter((it) => it.tipo === "peca").reduce((sum, it) => sum + it.valorTotal, 0));
  const valorMaoObra = round2(items.filter((it) => it.tipo === "servico").reduce((sum, it) => sum + it.valorTotal, 0));
  const next = { ...row, valorPecas, valorMaoObra, valorTotal: round2(valorPecas + valorMaoObra), updatedAt: now() };
  s.serviceOrders = replace(s.serviceOrders, next);
  save();
  return hydrateOS(next);
}

on("serviceOrders", "list", (i) =>
  db()
    .serviceOrders.filter(
      (o) =>
        (!i?.status || o.status === i.status) &&
        (!i?.vehicleId || o.vehicleId === i.vehicleId) &&
        (!i?.customerId || o.customerId === i.customerId),
    )
    .sort((a, b) => b.numero - a.numero)
    .map(hydrateOS),
);
on("serviceOrders", "getById", (i) => hydrateOS(find(db().serviceOrders, i.id, "OS")));
on("serviceOrders", "create", (i) => {
  const s = db();
  const id = newId();
  const dataEntrada = i.dataEntrada ?? now();
  s.serviceOrders.push({
    id,
    numero: s.serviceOrders.reduce((max, o) => Math.max(max, o.numero), 1000) + 1,
    customerId: i.customerId,
    vehicleId: i.vehicleId,
    queixaCliente: i.queixaCliente ?? null,
    diagnostico: i.diagnostico ?? null,
    kmEntrada: i.kmEntrada ?? null,
    kmSaida: null,
    status: "recepcao",
    valorPecas: 0,
    valorMaoObra: 0,
    valorTotal: 0,
    dataEntrada,
    dataPrevista: i.dataPrevista ?? null,
    dataSaida: null,
    aprovacaoCliente: null,
    aprovacaoData: null,
    aprovacaoIp: null,
    aprovacaoComentario: null,
    createdAt: dataEntrada,
    updatedAt: now(),
  });
  s.soItems.push(...(i.items ?? []).map((it) => makeSOItem(id, it)));
  return retotalOS(id);
});
on("serviceOrders", "update", (i) => {
  const s = db();
  const { items, ...patch } = clean(i);
  s.serviceOrders = replace(s.serviceOrders, { ...find(s.serviceOrders, i.id, "OS"), ...patch });
  if (items) {
    s.soItems = s.soItems.filter((it) => it.serviceOrderId !== i.id).concat(items.map((it) => makeSOItem(i.id, it)));
  }
  return retotalOS(i.id);
});
on("serviceOrders", "advanceStatus", (i) => {
  const s = db();
  const row = find(s.serviceOrders, i.id, "OS");
  s.serviceOrders = replace(s.serviceOrders, {
    ...row,
    status: i.status,
    dataSaida: i.status === "entregue" ? now() : row.dataSaida,
    kmSaida: i.status === "entregue" ? row.kmSaida ?? row.kmEntrada : row.kmSaida,
    aprovacaoCliente: i.status === "em_execucao" && row.aprovacaoCliente == null ? true : row.aprovacaoCliente,
  });
  return retotalOS(i.id);
});
on("serviceOrders", "addItem", (i) => {
  find(db().serviceOrders, i.serviceOrderId, "OS");
  db().soItems.push(makeSOItem(i.serviceOrderId, i.item));
  return retotalOS(i.serviceOrderId);
});
on("serviceOrders", "removeItem", (i) => {
  const s = db();
  const item = find(s.soItems, i.itemId, "Item da OS");
  s.soItems = s.soItems.filter((it) => it.id !== i.itemId);
  return retotalOS(item.serviceOrderId);
});
on("serviceOrders", "addPhoto", (i) => {
  const photo = { id: newId(), serviceOrderId: i.serviceOrderId, tipo: i.tipo, fileKey: i.fileKey, descricao: i.descricao ?? null, createdAt: now() };
  db().soPhotos.push(photo);
  save();
  return photo;
});
on("serviceOrders", "removePhoto", (i) => {
  const s = db();
  s.soPhotos = s.soPhotos.filter((p) => p.id !== i.photoId);
  save();
  return { id: i.photoId };
});
on("serviceOrders", "generateOrcamentoLink", (i) => {
  find(db().serviceOrders, i.id, "OS");
  const token = Math.random().toString(36).slice(2, 12);
  const expiresAt = new Date(Date.now() + (i.expiresInDays ?? 7) * 86_400_000).toISOString();
  const row = { id: newId(), serviceOrderId: i.id, token, expiresAt, createdAt: now() };
  db().soTokens.push(row);
  save();
  return { id: row.id, token, expiresAt, url: `https://oimpresso.exemplo.com.br/orcamento/${token}` };
});
on("serviceOrders", "delete", (i) => {
  const s = db();
  s.serviceOrders = s.serviceOrders.filter((o) => o.id !== i.id);
  s.soItems = s.soItems.filter((it) => it.serviceOrderId !== i.id);
  s.soPhotos = s.soPhotos.filter((p) => p.serviceOrderId !== i.id);
  save();
  return { id: i.id };
});

// ─── Orçamentos (m²) ────────────────────────────────────────────────────────

type QuoteItemInput = In["quotes"]["create"]["items"][number];

function makeQuoteItems(quoteId: string, items: QuoteItemInput[]): QuoteItem[] {
  return items.map((it) => {
    const quantidade = it.quantidade ?? 1;
    const areaM2 = round2((it.larguraCm * it.alturaCm * quantidade) / 10_000);
    return {
      id: newId(),
      quoteId,
      descricao: it.descricao,
      material: it.material ?? null,
      larguraCm: it.larguraCm,
      alturaCm: it.alturaCm,
      quantidade,
      areaM2,
      precoPorM2: it.precoPorM2,
      acabamento: it.acabamento ?? null,
      valorTotal: round2(areaM2 * it.precoPorM2),
    };
  });
}

function hydrateQuote(row: QuoteRow): Out["quotes"]["getById"] {
  const c = customerLite(row.customerId);
  return { ...row, customer: c, items: db().quoteItems.filter((it) => it.quoteId === row.id) };
}

function retotalQuote(id: string) {
  const s = db();
  const total = round2(s.quoteItems.filter((it) => it.quoteId === id).reduce((sum, it) => sum + it.valorTotal, 0));
  const next = { ...find(s.quotes, id, "Orçamento"), valorTotal: total, updatedAt: now() };
  s.quotes = replace(s.quotes, next);
  save();
  return hydrateQuote(next);
}

on("quotes", "list", (i) =>
  db()
    .quotes.filter((q) => !i?.status || q.status === i.status)
    .sort(byDateDesc((q) => q.createdAt))
    .map(hydrateQuote),
);
on("quotes", "getById", (i) => hydrateQuote(find(db().quotes, i.id, "Orçamento")));
on("quotes", "create", (i) => {
  const s = db();
  const id = newId();
  s.quotes.push({
    id,
    customerId: i.customerId ?? null,
    titulo: i.titulo,
    validadeDias: i.validadeDias ?? 15,
    status: i.status ?? "rascunho",
    valorTotal: 0,
    observacoes: i.observacoes ?? null,
    pdfKey: null,
    createdAt: now(),
    updatedAt: now(),
  });
  s.quoteItems.push(...makeQuoteItems(id, i.items));
  return retotalQuote(id);
});
on("quotes", "update", (i) => {
  const s = db();
  const { items, ...patch } = clean(i);
  s.quotes = replace(s.quotes, { ...find(s.quotes, i.id, "Orçamento"), ...patch });
  if (items) {
    s.quoteItems = s.quoteItems.filter((it) => it.quoteId !== i.id).concat(makeQuoteItems(i.id, items));
  }
  return retotalQuote(i.id);
});
on("quotes", "delete", (i) => {
  const s = db();
  s.quotes = s.quotes.filter((q) => q.id !== i.id);
  s.quoteItems = s.quoteItems.filter((it) => it.quoteId !== i.id);
  save();
  return { id: i.id };
});
on("quotes", "convertToPedido", (i) => {
  const s = db();
  const quote = hydrateQuote(find(s.quotes, i.id, "Orçamento"));
  if (quote.status === "convertido") fail("Este orçamento já virou pedido.");
  const pedido = createPedido({
    customerId: quote.customerId ?? undefined,
    cliente: quote.customer?.nome ?? quote.titulo,
    status: "aprovado",
    tipo: "Venda",
    items: quote.items.map((it) => ({
      descricao: `${it.descricao} ${it.larguraCm}×${it.alturaCm} cm`,
      quantidade: it.areaM2,
      valorUnit: it.precoPorM2,
    })),
  });
  s.quotes = replace(s.quotes, { ...find(s.quotes, i.id, "Orçamento"), status: "convertido", updatedAt: now() });
  save();
  return { pedidoId: pedido.id as UUID, quoteId: i.id };
});
on("quotes", "generatePdf", () => notInDemo("Gerar PDF do orçamento"));

on("quotes", "listPriceTables", () => db().priceTables);
on("quotes", "createPriceTable", (i) => {
  const t = { id: newId(), material: i.material, precoPorM2: i.precoPorM2, acabamentoExtra: i.acabamentoExtra ?? 0, ativo: i.ativo ?? true, createdAt: now() };
  db().priceTables.push(t);
  save();
  return t;
});
on("quotes", "updatePriceTable", (i) => {
  const s = db();
  const next = { ...find(s.priceTables, i.id, "Tabela de preço"), ...clean(i) };
  s.priceTables = replace(s.priceTables, next);
  save();
  return next;
});
on("quotes", "deletePriceTable", (i) => {
  const s = db();
  s.priceTables = s.priceTables.filter((t) => t.id !== i.id);
  save();
  return { id: i.id };
});

// ─── Arte digital ───────────────────────────────────────────────────────────

on("artworks", "list", (i) =>
  db()
    .artworks.filter((a) => (!i?.opId || a.opId === i.opId) && (!i?.quoteId || a.quoteId === i.quoteId))
    .sort(byDateDesc((a) => a.createdAt)),
);
on("artworks", "getApprovalHistory", (i) =>
  db()
    .approvals.filter((a) => a.artworkId === i.artworkId)
    .sort(byDateDesc((a) => a.createdAt)),
);
on("artworks", "requestUploadUrl", () => notInDemo("Enviar arquivos"));
on("artworks", "create", (i) => {
  const a = {
    id: newId(),
    opId: i.opId ?? null,
    quoteId: i.quoteId ?? null,
    titulo: i.titulo,
    descricao: i.descricao ?? null,
    fileKey: i.fileKey,
    mimeType: i.mimeType,
    fileSizeBytes: i.fileSizeBytes,
    status: "pendente" as const,
    publicToken: Math.random().toString(36).slice(2, 12),
    createdAt: now(),
    updatedAt: now(),
    fileUrl: svgPhoto(i.titulo, "#5a2f6e"),
  };
  db().artworks.push(a);
  save();
  return a;
});
on("artworks", "delete", (i) => {
  const s = db();
  s.artworks = s.artworks.filter((a) => a.id !== i.id);
  save();
  return { id: i.id };
});

// ─── Fiscal ─────────────────────────────────────────────────────────────────

on("fiscal", "status", () => ({ configured: true, ambiente: "homologacao" as const }));
on3("fiscal", "companySettings", "get", () => db().companySettings);
on3("fiscal", "companySettings", "upsert", (i) => {
  const s = db();
  const current = s.companySettings ?? fail("Dados fiscais da empresa não encontrados.");
  const next: CompanySettings = { ...current, ...clean(i), updatedAt: now() };
  s.companySettings = next;
  save();
  return next;
});
on3("fiscal", "documents", "list", (i) =>
  db()
    .fiscalDocs.filter(
      (d) =>
        (!i?.status || d.status === i.status) &&
        (!i?.tipo || d.tipo === i.tipo) &&
        (!i?.referenciaTipo || d.referenciaTipo === i.referenciaTipo) &&
        (!i?.referenciaId || d.referenciaId === i.referenciaId),
    )
    .sort(byDateDesc((d) => d.createdAt)),
);
on3("fiscal", "documents", "getById", (i) => find(db().fiscalDocs, i.id, "Nota fiscal"));
on3("fiscal", "documents", "downloadUrl", () => ({ pdfUrl: null, xmlUrl: null }));
on3("fiscal", "documents", "emit", (i) => {
  const s = db();
  const valor =
    i.referenciaTipo === "pedido"
      ? s.pedidos.find((p) => p.id === i.referenciaId)?.valor
      : i.referenciaTipo === "os"
        ? s.serviceOrders.find((o) => o.id === i.referenciaId)?.valorTotal
        : s.quotes.find((q) => q.id === i.referenciaId)?.valorTotal;
  const doc = {
    id: newId(),
    tipo: i.tipo,
    referenciaTipo: i.referenciaTipo,
    referenciaId: i.referenciaId,
    customerId: i.customerId ?? null,
    status: "processando" as const,
    chaveAcesso: null,
    numero: null,
    serie: i.tipo === "NFSe" ? null : "1",
    providerRef: `demo-${Date.now()}`,
    valor: valor ?? 0,
    pdfKey: null,
    xmlKey: null,
    erroMensagem: null,
    ambiente: "homologacao" as const,
    createdAt: now(),
    updatedAt: now(),
  };
  s.fiscalDocs.push(doc);
  save();
  return doc;
});
// Consulting a "processando" note authorizes it, like SEFAZ would.
on3("fiscal", "documents", "consultStatus", (i) => {
  const s = db();
  const doc = find(s.fiscalDocs, i.id, "Nota fiscal");
  if (doc.status !== "processando") return doc;
  const numero = String(422 + s.fiscalDocs.filter((d) => d.numero).length);
  const next = {
    ...doc,
    status: "autorizado" as const,
    numero,
    chaveAcesso: doc.tipo === "NFSe" ? null : `3526091122233300018155001${numero.padStart(9, "0")}1${numero.padStart(8, "0")}`,
    updatedAt: now(),
  };
  s.fiscalDocs = replace(s.fiscalDocs, next);
  save();
  return next;
});
on3("fiscal", "documents", "cancel", (i) => {
  const s = db();
  const next = { ...find(s.fiscalDocs, i.id, "Nota fiscal"), status: "cancelado" as const, erroMensagem: `Cancelada: ${i.justificativa}`, updatedAt: now() };
  s.fiscalDocs = replace(s.fiscalDocs, next);
  save();
  return next;
});

// ─── WhatsApp ───────────────────────────────────────────────────────────────

function logWhatsapp(i: { mensagem: string; customerId?: string | null; telefone?: string | null; referenciaTipo?: string; referenciaId?: string }) {
  const telefone = i.telefone ?? customerLite(i.customerId ?? null)?.telefone ?? fail("Cliente sem telefone cadastrado.");
  const providerMessageId = `wa-demo-${Date.now()}`;
  db().waMessages.push({
    id: newId(),
    customerId: i.customerId ?? null,
    telefone,
    mensagem: i.mensagem,
    tipo: "enviada",
    referenciaTipo: i.referenciaTipo ?? null,
    referenciaId: i.referenciaId ?? null,
    providerMessageId,
    erro: null,
    createdAt: now(),
  });
  save();
  return providerMessageId;
}

const WA_TEMPLATES: Record<In["whatsapp"]["sendTemplate"]["template"], (p: Record<string, string>) => string> = {
  pedido_em_producao: (p) => `Olá${p.nome ? `, ${p.nome}` : ""}! Seu pedido ${p.pedido ?? ""} entrou em produção.`.replace(/\s+\./, "."),
  os_pronta: (p) => `Olá${p.nome ? `, ${p.nome}` : ""}! Seu veículo ${p.placa ?? ""} está pronto para retirada.`.replace(/\s+\./, "."),
  aprovacao_orcamento: (p) => `Olá${p.nome ? `, ${p.nome}` : ""}! Seu orçamento está disponível para aprovação${p.link ? `: ${p.link}` : "."}`,
  entrega_agendada: (p) => `Olá${p.nome ? `, ${p.nome}` : ""}! Sua entrega foi agendada${p.data ? ` para ${p.data}` : ""}.`,
};

on3("whatsapp", "messages", "list", (i) =>
  db()
    .waMessages.filter((m) => !i?.customerId || m.customerId === i.customerId)
    .sort(byDateDesc((m) => m.createdAt))
    .slice(0, i?.limit ?? 50),
);
on("whatsapp", "sendCustom", (i) => ({ ok: true, providerMessageId: logWhatsapp(i) }));
on("whatsapp", "sendTemplate", (i) => {
  const mensagem = WA_TEMPLATES[i.template](i.params);
  return { mensagem, ok: true, providerMessageId: logWhatsapp({ ...i, mensagem }) };
});

// ─── IA (respostas prontas) ─────────────────────────────────────────────────

on("ai", "suggestDiagnosis", (i) => {
  const q = i.queixaCliente.toLowerCase();
  if (q.includes("freio") || q.includes("frear"))
    return { diagnosticoSugerido: "Desgaste das pastilhas ou discos empenados.", pecasProvaveis: ["Pastilha de freio", "Disco de freio"], servicosProvaveis: ["Troca de pastilhas", "Retífica de discos"], tempoEstimadoHoras: 2, confianca: "alta" as const };
  if (q.includes("ar") && q.includes("gela"))
    return { diagnosticoSugerido: "Baixa carga de gás ou vazamento no sistema de A/C.", pecasProvaveis: ["Gás R134a", "Válvula de expansão"], servicosProvaveis: ["Teste de vazamento", "Recarga de gás"], tempoEstimadoHoras: 1.5, confianca: "media" as const };
  return { diagnosticoSugerido: "Necessário diagnóstico eletrônico para confirmar a causa.", pecasProvaveis: [], servicosProvaveis: ["Scanner OBD2", "Inspeção visual"], tempoEstimadoHoras: 1, confianca: "baixa" as const };
});
on("ai", "suggestPricing", (i) => {
  const itensSugeridos = i.items.map((it) => {
    const m = (it.material ?? it.descricao).toLowerCase();
    const preco = m.includes("acm") ? 380 : m.includes("pvc") ? 120 : m.includes("vinil") || m.includes("adesivo") ? 48 : 55;
    const area = (it.larguraCm * it.alturaCm * it.quantidade) / 10_000;
    return { descricao: it.descricao, precoPorM2Sugerido: preco, totalItem: round2(area * preco), justificativa: `Preço médio da sua tabela para ${m.includes("acm") ? "ACM" : m.includes("pvc") ? "PVC" : m.includes("vinil") || m.includes("adesivo") ? "vinil" : "lona"}.` };
  });
  return { itensSugeridos, totalGeral: round2(itensSugeridos.reduce((s, it) => s + it.totalItem, 0)), margemSugerida: 55, observacoes: "Sugestão calculada a partir das tabelas de preço cadastradas." };
});

// ─── Relatórios ─────────────────────────────────────────────────────────────

function range(i?: { from?: string; to?: string } | void) {
  const to = i?.to ?? now();
  const from = i?.from ?? new Date(Date.now() - 30 * 86_400_000).toISOString();
  return { from, to, inRange: (d: string) => d >= from && d <= to };
}

function sumBy<T>(rows: T[], key: (r: T) => string, val: (r: T) => number) {
  const m = new Map<string, number>();
  rows.forEach((r) => m.set(key(r), round2((m.get(key(r)) ?? 0) + val(r))));
  return [...m.entries()].map(([categoria, valor]) => ({ categoria, valor })).sort((a, b) => b.valor - a.valor);
}

function countBy<T>(rows: T[], key: (r: T) => string): Record<string, number> {
  return rows.reduce<Record<string, number>>((acc, r) => ({ ...acc, [key(r)]: (acc[key(r)] ?? 0) + 1 }), {});
}

on("reports", "dre", (i) => {
  const { from, to, inRange } = range(i);
  const rows = db().transacoes.filter((t) => inRange(t.data));
  const rec = rows.filter((t) => t.tipo === "receita");
  const desp = rows.filter((t) => t.tipo === "despesa");
  const totalReceitas = round2(rec.reduce((s, t) => s + t.valor, 0));
  const totalDespesas = round2(desp.reduce((s, t) => s + t.valor, 0));
  const resultadoLiquido = round2(totalReceitas - totalDespesas);
  return {
    from,
    to,
    totalReceitas,
    totalDespesas,
    resultadoLiquido,
    margemPercent: totalReceitas ? round2((resultadoLiquido / totalReceitas) * 100) : 0,
    receitasPorCategoria: sumBy(rec, (t) => t.categoria, (t) => t.valor),
    despesasPorCategoria: sumBy(desp, (t) => t.categoria, (t) => t.valor),
  };
});

on("reports", "vendas", (i) => {
  const { from, to, inRange } = range(i);
  const groupBy = i?.groupBy ?? "dia";
  const pedidos = db().pedidos.filter((p) => inRange(p.data));
  const bucket = (iso: string) => {
    const d = new Date(iso);
    if (groupBy === "mes") return iso.slice(0, 7);
    if (groupBy === "semana") {
      d.setDate(d.getDate() - d.getDay());
      return d.toISOString().slice(0, 10);
    }
    return iso.slice(0, 10);
  };
  const series = new Map<string, { date: string; total: number; count: number }>();
  pedidos.forEach((p) => {
    const k = bucket(p.data);
    const cur = series.get(k) ?? { date: k, total: 0, count: 0 };
    series.set(k, { date: k, total: round2(cur.total + p.valor), count: cur.count + 1 });
  });
  const totalVendas = round2(pedidos.reduce((s, p) => s + p.valor, 0));
  const clientes = new Map<string, { customerId: string | null; nome: string; valor: number; count: number }>();
  pedidos.forEach((p) => {
    const k = p.customerId ?? p.cliente;
    const cur = clientes.get(k) ?? { customerId: p.customerId, nome: p.cliente, valor: 0, count: 0 };
    clientes.set(k, { ...cur, valor: round2(cur.valor + p.valor), count: cur.count + 1 });
  });
  const ids = new Set(pedidos.map((p) => p.id));
  const prods = new Map<string, { produto: string; valor: number; qtdItens: number }>();
  db()
    .pedidoItems.filter((it) => ids.has(it.pedidoId))
    .forEach((it) => {
      const nome = (it.produtoId && db().produtos.find((p) => p.id === it.produtoId)?.nome) || it.descricao;
      const cur = prods.get(nome) ?? { produto: nome, valor: 0, qtdItens: 0 };
      prods.set(nome, { ...cur, valor: round2(cur.valor + it.valorTotal), qtdItens: round2(cur.qtdItens + it.quantidade) });
    });
  return {
    from,
    to,
    groupBy,
    series: [...series.values()].sort((a, b) => a.date.localeCompare(b.date)),
    totalVendas,
    ticketMedio: pedidos.length ? round2(totalVendas / pedidos.length) : 0,
    countPedidos: countBy(pedidos, (p) => p.status),
    topClientes: [...clientes.values()].sort((a, b) => b.valor - a.valor).slice(0, 5),
    topProdutos: [...prods.values()].sort((a, b) => b.valor - a.valor).slice(0, 5),
  };
});

on("reports", "producao", (i) => {
  const { from, to, inRange } = range(i);
  const s = db();
  const oss = s.serviceOrders.filter((o) => inRange(o.dataEntrada));
  const mecanicos = new Map<string, number>();
  s.soItems
    .filter((it) => it.mecanicoResponsavel && oss.some((o) => o.id === it.serviceOrderId))
    .forEach((it) => mecanicos.set(it.mecanicoResponsavel!, (mecanicos.get(it.mecanicoResponsavel!) ?? 0) + 1));
  const concluidas = oss.filter((o) => o.dataSaida);
  const dias = concluidas.map((o) => (new Date(o.dataSaida!).getTime() - new Date(o.dataEntrada).getTime()) / 86_400_000);
  return {
    from,
    to,
    countOPsPorStatus: countBy(s.ops, (o) => o.status),
    countOSsPorStatus: countBy(oss, (o) => o.status),
    tempoMedioConclusaoDias: dias.length ? round2(dias.reduce((a, b) => a + b, 0) / dias.length) : 0,
    mecanicosMaisAtivos: [...mecanicos.entries()].map(([mecanico, count]) => ({ mecanico, count })).sort((a, b) => b.count - a.count),
    totalOPs: s.ops.length,
    totalOSs: oss.length,
  };
});

on("reports", "estoque", () => {
  const s = db();
  const ativos = s.inventory.filter((it) => it.ativo);
  const cutoff = new Date(Date.now() - 30 * 86_400_000).toISOString();
  return {
    itensAtivos: ativos.length,
    itensTotal: s.inventory.length,
    valorTotalEstoque: round2(ativos.reduce((sum, it) => sum + it.quantidade * it.custoUnit, 0)),
    itensComEstoqueBaixo: ativos.filter(isLow).map((it) => ({ id: it.id, nome: it.nome, quantidade: it.quantidade, estoqueMinimo: it.estoqueMinimo })),
    movimentosUltimos30Dias: s.movements.filter((m) => m.createdAt >= cutoff).length,
  };
});
on("reports", "exportPdf", () => notInDemo("Exportar PDF"));
on("reports", "exportExcel", () => notInDemo("Exportar Excel"));
