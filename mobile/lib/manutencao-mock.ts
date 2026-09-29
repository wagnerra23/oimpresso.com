/**
 * Mock data + tipos da vertical Oficina (Bloco 3).
 *
 * Equivalente a `ref/design/oficina-data.jsx`. Quando o backend (Connector
 * ou tRPC `manutencao`) ficar pronto, este arquivo é substituído por queries
 * — a UI continua igual porque consome via `use-manutencao.ts`.
 */

import type { OiStageTone } from "@/components/oi";

// ─── Pipeline ─────────────────────────────────────────────────────────────

export const MANUT_PIPELINE = [
  "Triagem",
  "Diagnóstico",
  "Aprovação",
  "Execução",
  "Qualidade",
  "Pronto",
] as const;
export type ManutStage = (typeof MANUT_PIPELINE)[number];

export type ManutStatus =
  | ManutStage
  | "Aguardando aprovação"
  | "Aguardando peça";

/** Cor por etapa visível. */
export function statusTone(status: ManutStatus): OiStageTone {
  switch (status) {
    case "Triagem":
      return "neutral";
    case "Diagnóstico":
      return "accent";
    case "Aprovação":
      return "warn";
    case "Aguardando aprovação":
      return "warn";
    case "Execução":
      return "accent";
    case "Aguardando peça":
      return "warn";
    case "Qualidade":
      return "accent";
    case "Pronto":
      return "ok";
    default:
      return "neutral";
  }
}

/** Mapeia status (incl. especiais) ao índice do pipeline canônico. */
export function pipelineIndex(status: ManutStatus): number {
  if (status === "Aguardando aprovação") return MANUT_PIPELINE.indexOf("Aprovação");
  if (status === "Aguardando peça") return MANUT_PIPELINE.indexOf("Execução");
  return MANUT_PIPELINE.indexOf(status as ManutStage);
}

export function nextStage(stage: ManutStage): ManutStage | null {
  const i = MANUT_PIPELINE.indexOf(stage);
  if (i < 0 || i >= MANUT_PIPELINE.length - 1) return null;
  return MANUT_PIPELINE[i + 1]!;
}

// ─── Locais ────────────────────────────────────────────────────────────────

export type LocalTipo = "Rampa" | "Elevador" | "Box" | "Pátio" | "Externo";

export type ManutLocal = {
  id: string;
  nome: string;
  tipo: LocalTipo;
  capacidade: number;
  observacao?: string;
  ocupadoPor: string[]; // ids de OS
};

export const LOCAIS_SEED: ManutLocal[] = [
  { id: "L-1", nome: "Rampa 1", tipo: "Rampa", capacidade: 1, ocupadoPor: ["OS-1041"] },
  { id: "L-2", nome: "Elevador A", tipo: "Elevador", capacidade: 1, ocupadoPor: [] },
  { id: "L-3", nome: "Box 1", tipo: "Box", capacidade: 2, ocupadoPor: ["OS-1042"] },
  { id: "L-4", nome: "Box 2", tipo: "Box", capacidade: 2, ocupadoPor: [] },
  { id: "L-5", nome: "Pátio", tipo: "Pátio", capacidade: 6, ocupadoPor: ["OS-1040", "OS-1039"], observacao: "Acesso pelo portão lateral" },
  { id: "L-6", nome: "Oficina externa", tipo: "Externo", capacidade: 1, ocupadoPor: [], observacao: "Parceiro: Diesel Cruz" },
];

// ─── Mecânicos ─────────────────────────────────────────────────────────────

export type Mecanico = {
  id: string;
  nome: string;
  especialidade: string;
  ativasIds: string[];
  disponivel: boolean;
};

export const MECANICOS_SEED: Mecanico[] = [
  { id: "M-1", nome: "André Silva", especialidade: "Suspensão / freios", ativasIds: ["OS-1041"], disponivel: true },
  { id: "M-2", nome: "Bruno Cruz", especialidade: "Motor diesel", ativasIds: ["OS-1042", "OS-1040"], disponivel: false },
  { id: "M-3", nome: "Carla Ramos", especialidade: "Elétrica", ativasIds: [], disponivel: true },
];

// ─── Catálogo de peças/serviços ────────────────────────────────────────────

export type CatalogoItem = {
  id: string;
  kind: "peca" | "servico";
  nome: string;
  preco: number;
  estoque?: number;
};

export const CATALOGO_SEED: CatalogoItem[] = [
  { id: "P-001", kind: "peca", nome: "Pastilha de freio dianteira", preco: 280, estoque: 8 },
  { id: "P-002", kind: "peca", nome: "Filtro de óleo", preco: 45, estoque: 22 },
  { id: "P-003", kind: "peca", nome: "Correia dentada", preco: 165, estoque: 0 },
  { id: "P-004", kind: "peca", nome: "Bateria 100Ah", preco: 890, estoque: 3 },
  { id: "P-005", kind: "peca", nome: "Lâmpada H4", preco: 25, estoque: 40 },
  { id: "S-001", kind: "servico", nome: "Mão de obra freios (eixo)", preco: 220 },
  { id: "S-002", kind: "servico", nome: "Troca de óleo + filtro", preco: 80 },
  { id: "S-003", kind: "servico", nome: "Alinhamento + balanceamento", preco: 180 },
  { id: "S-004", kind: "servico", nome: "Diagnóstico eletrônico", preco: 150 },
];

// ─── OS de manutenção ──────────────────────────────────────────────────────

export type OsItemStatus = "aplicado" | "aprovado" | "aguardando" | "comprar";

export type OsItem = {
  id: string;
  kind: "peca" | "servico";
  nome: string;
  qtd: number;
  preco: number;
  status: OsItemStatus;
};

export type OsChecklistItem = {
  id: string;
  label: string;
  done: boolean;
};

export type OsTimelineEvent = {
  stage: ManutStage;
  done: boolean;
  current: boolean;
  paused?: boolean;
  at?: string;
  operator?: string;
  note?: string;
};

export type Prioridade = "baixa" | "normal" | "alta" | "urgente";

export type Veiculo = {
  marca: string;
  modelo: string;
  tipo: string;
  ano: number;
  cor: string;
  placa: string;
  placa2?: string;
  chassi: string;
  chassi2?: string;
  renavam: string;
  hodometro: number;
  frota?: string;
  motorista?: string;
};

export type ManutOs = {
  id: string;
  veiculo: Veiculo;
  cliente: string;
  status: ManutStatus;
  prioridade: Prioridade;
  tipo: "Corretiva" | "Preventiva" | "Revisão" | "Pneus";
  localId: string;
  mecanicoId: string | null;
  abertaEm: string;
  prazoEm: string;
  relato: string;
  diagnostico?: string;
  checklist: OsChecklistItem[];
  itens: OsItem[];
};

const now = Date.now();
const iso = (offsetH: number) => new Date(now + offsetH * 3600_000).toISOString();

export const MANUT_OS_SEED: ManutOs[] = [
  {
    id: "OS-1041",
    veiculo: {
      marca: "Mercedes-Benz",
      modelo: "Atego 1719",
      tipo: "Caminhão 3/4",
      ano: 2019,
      cor: "Branco",
      placa: "ABC1D23",
      chassi: "9BM958134KB123456",
      renavam: "01234567890",
      hodometro: 184523,
      frota: "FR-12",
      motorista: "José Carlos",
    },
    cliente: "Transportes Lima Ltda",
    status: "Execução",
    prioridade: "alta",
    tipo: "Corretiva",
    localId: "L-1",
    mecanicoId: "M-1",
    abertaEm: iso(-8),
    prazoEm: iso(-1),
    relato: "Motorista relata barulho ao frear, pedal baixo. Ouvi chiado no eixo dianteiro.",
    diagnostico: "Pastilhas dianteiras gastas. Discos OK, sem necessidade de retífica.",
    checklist: [
      { id: "c1", label: "Inspeção visual pastilhas", done: true },
      { id: "c2", label: "Medir disco com paquímetro", done: true },
      { id: "c3", label: "Trocar pastilhas dianteiras", done: false },
      { id: "c4", label: "Sangrar sistema", done: false },
      { id: "c5", label: "Teste de freada em pátio", done: false },
    ],
    itens: [
      { id: "i1", kind: "peca", nome: "Pastilha de freio dianteira", qtd: 1, preco: 280, status: "aprovado" },
      { id: "i2", kind: "servico", nome: "Mão de obra freios (eixo)", qtd: 1, preco: 220, status: "aplicado" },
    ],
  },
  {
    id: "OS-1042",
    veiculo: {
      marca: "Volkswagen",
      modelo: "Constellation 24.280",
      tipo: "Caminhão",
      ano: 2021,
      cor: "Cinza",
      placa: "DEF2G34",
      placa2: "GHI3J45",
      chassi: "9BWZZZ24MMK654321",
      chassi2: "REB987654321",
      renavam: "98765432100",
      hodometro: 92010,
      frota: "FR-04",
      motorista: "Pedro Alves",
    },
    cliente: "Transportes Lima Ltda",
    status: "Aguardando peça",
    prioridade: "urgente",
    tipo: "Corretiva",
    localId: "L-3",
    mecanicoId: "M-2",
    abertaEm: iso(-30),
    prazoEm: iso(36),
    relato: "Falha de partida intermitente. Já trocou bateria há 6 meses.",
    diagnostico: "Motor de partida com escovas gastas. Aguardando substituto.",
    checklist: [
      { id: "c1", label: "Testar bateria carga + tensão", done: true },
      { id: "c2", label: "Medir consumo motor partida", done: true },
      { id: "c3", label: "Desmontar motor partida", done: true },
      { id: "c4", label: "Substituir motor partida", done: false },
      { id: "c5", label: "Teste de partidas (10 ciclos)", done: false },
    ],
    itens: [
      { id: "i1", kind: "peca", nome: "Motor de partida 24V", qtd: 1, preco: 1450, status: "comprar" },
      { id: "i2", kind: "servico", nome: "Diagnóstico eletrônico", qtd: 1, preco: 150, status: "aplicado" },
      { id: "i3", kind: "servico", nome: "Troca de motor partida", qtd: 1, preco: 320, status: "aguardando" },
    ],
  },
  {
    id: "OS-1040",
    veiculo: {
      marca: "Iveco",
      modelo: "Daily 70C17",
      tipo: "VUC",
      ano: 2017,
      cor: "Branco",
      placa: "JKL4M56",
      chassi: "93ZA1NFH08842221",
      renavam: "11223344556",
      hodometro: 245901,
      frota: "FR-19",
    },
    cliente: "Distribuidora Sul SA",
    status: "Aguardando aprovação",
    prioridade: "normal",
    tipo: "Revisão",
    localId: "L-5",
    mecanicoId: "M-2",
    abertaEm: iso(-50),
    prazoEm: iso(72),
    relato: "Revisão dos 250 mil km — checagem geral.",
    diagnostico: "Correia dentada com sinais de desgaste, recomendado trocar.",
    checklist: [
      { id: "c1", label: "Troca óleo motor + filtro", done: true },
      { id: "c2", label: "Inspeção suspensão", done: true },
      { id: "c3", label: "Inspeção freios", done: true },
      { id: "c4", label: "Inspeção correia dentada", done: true },
      { id: "c5", label: "Alinhamento", done: false },
    ],
    itens: [
      { id: "i1", kind: "peca", nome: "Filtro de óleo", qtd: 1, preco: 45, status: "aplicado" },
      { id: "i2", kind: "servico", nome: "Troca de óleo + filtro", qtd: 1, preco: 80, status: "aplicado" },
      { id: "i3", kind: "peca", nome: "Correia dentada", qtd: 1, preco: 165, status: "aguardando" },
      { id: "i4", kind: "servico", nome: "Troca correia dentada", qtd: 1, preco: 240, status: "aguardando" },
      { id: "i5", kind: "servico", nome: "Alinhamento + balanceamento", qtd: 1, preco: 180, status: "aguardando" },
    ],
  },
  {
    id: "OS-1039",
    veiculo: {
      marca: "Scania",
      modelo: "R 450",
      tipo: "Cavalo mecânico",
      ano: 2022,
      cor: "Vermelho",
      placa: "NOP5Q67",
      placa2: "QRS6T78",
      chassi: "9BSR4X20003111111",
      chassi2: "REB111222333",
      renavam: "55667788990",
      hodometro: 312455,
      frota: "FR-01",
      motorista: "Wagner Souza",
    },
    cliente: "Rotas BR Express",
    status: "Triagem",
    prioridade: "baixa",
    tipo: "Preventiva",
    localId: "L-5",
    mecanicoId: null,
    abertaEm: iso(-2),
    prazoEm: iso(96),
    relato: "Veículo entrou para revisão preventiva agendada.",
    checklist: [
      { id: "c1", label: "Verificar histórico de manutenção", done: false },
      { id: "c2", label: "Checagem inicial 360°", done: false },
    ],
    itens: [],
  },
  {
    id: "OS-1038",
    veiculo: {
      marca: "Mercedes-Benz",
      modelo: "Sprinter 415",
      tipo: "Van",
      ano: 2020,
      cor: "Prata",
      placa: "UVW7X89",
      chassi: "8AC906633LE445566",
      renavam: "33445566778",
      hodometro: 78920,
      frota: "FR-08",
    },
    cliente: "Comercial Norte",
    status: "Pronto",
    prioridade: "normal",
    tipo: "Corretiva",
    localId: "L-2",
    mecanicoId: "M-3",
    abertaEm: iso(-72),
    prazoEm: iso(-24),
    relato: "Vidro elétrico do motorista não desce.",
    diagnostico: "Motor do vidro queimado. Substituído.",
    checklist: [
      { id: "c1", label: "Diagnóstico circuito vidro", done: true },
      { id: "c2", label: "Trocar motor do vidro", done: true },
      { id: "c3", label: "Teste 5 ciclos sobe/desce", done: true },
    ],
    itens: [
      { id: "i1", kind: "peca", nome: "Motor vidro elétrico", qtd: 1, preco: 320, status: "aplicado" },
      { id: "i2", kind: "servico", nome: "MO troca motor vidro", qtd: 1, preco: 140, status: "aplicado" },
    ],
  },
];

// ─── Totais / helpers ──────────────────────────────────────────────────────

export function osTotal(os: ManutOs): {
  pecas: number;
  servicos: number;
  aprovado: number;
  total: number;
  aguardando: number;
} {
  let pecas = 0;
  let servicos = 0;
  let aprovado = 0;
  let aguardando = 0;
  for (const it of os.itens) {
    const valor = it.qtd * it.preco;
    if (it.kind === "peca") pecas += valor;
    else servicos += valor;
    if (it.status === "aprovado" || it.status === "aplicado") aprovado += valor;
    if (it.status === "aguardando" || it.status === "comprar") aguardando += 1;
  }
  return { pecas, servicos, aprovado, aguardando, total: pecas + servicos };
}

export function priorityColor(p: Prioridade): "neutral" | "warn" | "danger" | "accent" {
  switch (p) {
    case "urgente":
      return "danger";
    case "alta":
      return "warn";
    case "normal":
      return "accent";
    default:
      return "neutral";
  }
}
