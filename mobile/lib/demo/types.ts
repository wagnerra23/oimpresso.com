import type { inferRouterInputs, inferRouterOutputs } from "@trpc/server";

import type { AppRouter } from "@/server/routers";

/**
 * Router input/output types used by the demo backend. Seed rows and handlers
 * are typed against these, so `tsc` flags any drift from the real server.
 */
export type Out = inferRouterOutputs<AppRouter>;
export type In = inferRouterInputs<AppRouter>;

export type UUID = `${string}-${string}-${string}-${string}-${string}`;

export type Company = Out["companies"]["list"][number];
export type Customer = Out["customers"]["getById"];
export type Produto = Out["produtos"]["getById"];
export type Pedido = Out["pedidos"]["list"][number];
export type PedidoItem = Out["pedidos"]["getItems"][number];
export type OP = Out["ops"]["list"][number];
export type Transacao = Out["transacoes"]["list"][number];
export type InventoryItem = Out["inventory"]["list"][number];
export type Movement = Out["inventory"]["listMovements"][number];
export type Vehicle = Out["vehicles"]["getById"];
export type ServiceOrder = Out["serviceOrders"]["getById"];
export type SOItem = ServiceOrder["items"][number];
export type SOPhoto = ServiceOrder["photos"][number];
export type SOToken = ServiceOrder["publicTokens"][number];
export type Quote = Out["quotes"]["getById"];
export type QuoteItem = Quote["items"][number];
export type PriceTable = Out["quotes"]["listPriceTables"][number];
export type Payment = Out["payments"]["getById"];
export type FiscalDoc = Out["fiscal"]["documents"]["getById"];
export type CompanySettings = Out["fiscal"]["companySettings"]["get"];
export type Artwork = Out["artworks"]["list"][number];
export type Approval = Out["artworks"]["getApprovalHistory"][number];
export type WaMessage = Out["whatsapp"]["messages"]["list"][number];

/**
 * Rows are stored without the relations the server joins in (customer,
 * vehicle, items…); handlers hydrate them on read.
 */
export type PedidoRow = Omit<Pedido, "customer" | "items">;
export type VehicleRow = Omit<Vehicle, "customer">;
export type ServiceOrderRow = Omit<ServiceOrder, "vehicle" | "customer" | "items" | "photos" | "publicTokens">;
export type QuoteRow = Omit<Quote, "customer" | "items">;
export type PaymentRow = Omit<Payment, "customer">;

export type Store = {
  currentCompanyId: string;
  companies: Company[];
  customers: Customer[];
  produtos: Produto[];
  pedidos: PedidoRow[];
  pedidoItems: PedidoItem[];
  ops: OP[];
  transacoes: Transacao[];
  inventory: InventoryItem[];
  movements: Movement[];
  vehicles: VehicleRow[];
  serviceOrders: ServiceOrderRow[];
  soItems: SOItem[];
  soPhotos: SOPhoto[];
  soTokens: SOToken[];
  quotes: QuoteRow[];
  quoteItems: QuoteItem[];
  priceTables: PriceTable[];
  payments: PaymentRow[];
  fiscalDocs: FiscalDoc[];
  companySettings: CompanySettings;
  artworks: Artwork[];
  approvals: Approval[];
  waMessages: WaMessage[];
};
