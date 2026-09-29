/**
 * Financeiro + Faturamento (Fase 2) — modelo título/parcela em centavos.
 *
 * Une o ledger (`finance-data.jsx`) e o motor de faturamento (`oi-flow.jsx`)
 * num provider só, pois `faturar` grava no próprio ledger. Persistido via
 * AsyncStorage (mesmo padrão dos outros stores locais). Dinheiro SEMPRE em
 * centavos (`lib/money.ts`). Vínculo por ID (campo `origem`), idempotente.
 *
 * Quando o Connector do Wagner existir, trocar o storage por
 * `routers/financeiro` — o shape já espelha `domain/finance/titulos.ts`.
 */
import AsyncStorage from "@react-native-async-storage/async-storage";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import { gerarParcelasCents, sumCents, type Cents } from "@/lib/money";

const KEY = "oi-financeiro";

export type LancTipo = "receber" | "pagar";
export type LancStatus = "aberto" | "vencido" | "liquidado";

export type Lancamento = {
  id: string;
  tipo: LancTipo;
  desc: string;
  parte: string;
  parteId: string | null;
  categoria: string;
  /** Vínculo por ID da OS/pedido (nunca string solta). */
  origem: string;
  valorCents: Cents;
  vencDias: number;
  vencLabel: string;
  status: LancStatus;
  meio: string;
  conta?: string;
  liqLabel?: string;
  emitidoPor?: string;
};

export type ContaFinanceira = {
  id: string;
  nome: string;
  banco: string;
  saldoCents: Cents;
  ic: string;
};

const R = (reais: number): Cents => Math.round(reais * 100);

export const CONTAS_SEED: ContaFinanceira[] = [
  { id: "cc", nome: "Conta Corrente", banco: "Bradesco Ag. 2210", saldoCents: R(18420.5), ic: "card" },
  { id: "pix", nome: "PIX · Inter", banco: "Banco Inter", saldoCents: R(7235), ic: "qr" },
  { id: "cielo", nome: "Cielo (cartões)", banco: "Recebíveis cartão", saldoCents: R(3110), ic: "card" },
  { id: "dinheiro", nome: "Dinheiro (gaveta)", banco: "Espécie", saldoCents: R(840), ic: "dollar" },
];

export const FIN_CATEGORIAS = {
  receita: ["Venda de impressos", "Sinalização", "Serviços de oficina", "Peças", "Outras receitas"],
  custo: ["Insumos gráficos", "Peças (revenda)", "Terceirização"],
  despesa: ["Folha de pagamento", "Aluguel", "Energia / Água", "Combustível", "Impostos", "Frete", "Internet / Telefonia", "Marketing", "Outras despesas"],
} as const;

const venc = (d: number): string =>
  d === 0 ? "Hoje" : d === 1 ? "Amanhã" : d < 0 ? `${Math.abs(d)} dias atrás` : `Em ${d} dias`;

const LANC_SEED: Lancamento[] = [
  // A receber (aberto/vencido)
  { id: "R-3041", tipo: "receber", desc: "1.000 cartões 9x5 4/4", parte: "Marília Costa", parteId: "c1", categoria: "Venda de impressos", origem: "OS-3041", valorCents: R(248), vencDias: -1, vencLabel: "Ontem", status: "vencido", meio: "Boleto" },
  { id: "R-2036", tipo: "receber", desc: "Freios + pneus (semirreboque)", parte: "Sul Cargas Transportes", parteId: "c12", categoria: "Serviços de oficina", origem: "MAN-2036", valorCents: R(1840), vencDias: -5, vencLabel: "5 dias atrás", status: "vencido", meio: "Boleto" },
  { id: "R-3043", tipo: "receber", desc: "Cardápio QR de mesa", parte: "Restaurante 88", parteId: "c2", categoria: "Venda de impressos", origem: "OS-3043", valorCents: R(360), vencDias: 2, vencLabel: "Em 2 dias", status: "aberto", meio: "PIX" },
  { id: "R-3033", tipo: "receber", desc: "200 receituários A5", parte: "Clínica Vita", parteId: "c3", categoria: "Venda de impressos", origem: "OS-3033", valorCents: R(612), vencDias: 12, vencLabel: "Em 12 dias", status: "aberto", meio: "Boleto" },
  { id: "R-2041", tipo: "receber", desc: "Injeção + filtros (Volvo FH)", parte: "Transportes Andorinha", parteId: "c11", categoria: "Serviços de oficina", origem: "MAN-2041", valorCents: R(1280), vencDias: 1, vencLabel: "Amanhã", status: "aberto", meio: "Boleto" },
  // Recebidas (liquidado)
  { id: "R-3035", tipo: "receber", desc: "Banner 3x1m vinil", parte: "Restaurante 88", parteId: "c2", categoria: "Sinalização", origem: "OS-3035", valorCents: R(480), vencDias: 0, vencLabel: "Hoje", status: "liquidado", liqLabel: "Hoje 09:32", conta: "pix", meio: "PIX" },
  { id: "R-3025", tipo: "receber", desc: "Etiqueta térmica 5cm", parte: "Açougue Premium", parteId: "c6", categoria: "Venda de impressos", origem: "OS-3025", valorCents: R(92), vencDias: -1, vencLabel: "Ontem", status: "liquidado", liqLabel: "Ontem 16:50", conta: "cielo", meio: "Crédito" },
  // A pagar
  { id: "P-alug", tipo: "pagar", desc: "Aluguel do galpão", parte: "Imobiliária Centro", parteId: null, categoria: "Aluguel", origem: "Recorrente", valorCents: R(3800), vencDias: 4, vencLabel: "Em 4 dias", status: "aberto", meio: "Boleto" },
  { id: "P-ener", tipo: "pagar", desc: "Energia elétrica", parte: "Enel SP", parteId: null, categoria: "Energia / Água", origem: "Recorrente", valorCents: R(1180), vencDias: -1, vencLabel: "Ontem", status: "vencido", meio: "Boleto" },
  { id: "P-das", tipo: "pagar", desc: "Simples Nacional (DAS)", parte: "Receita Federal", parteId: null, categoria: "Impostos", origem: "Imposto", valorCents: R(2240), vencDias: 6, vencLabel: "Em 6 dias", status: "aberto", meio: "Guia" },
  // Pagas
  { id: "P-suz", tipo: "pagar", desc: "Insumos gráficos (papel/tinta)", parte: "Suzano Papéis S.A.", parteId: "c8", categoria: "Insumos gráficos", origem: "Compra", valorCents: R(1290), vencDias: 0, vencLabel: "Hoje", status: "liquidado", liqLabel: "Hoje 08:14", conta: "cc", meio: "Boleto" },
  { id: "P-folha", tipo: "pagar", desc: "Folha — quinzena", parte: "Folha de pagamento", parteId: null, categoria: "Folha de pagamento", origem: "Pessoal", valorCents: R(4280), vencDias: -3, vencLabel: "Sex 09:00", status: "liquidado", liqLabel: "Sex 09:00", conta: "cc", meio: "TED" },
];

export type FaturarOpts = {
  origemId: string;
  parte: string;
  parteId?: string | null;
  desc: string;
  categoria?: string;
  /** Total em centavos. */
  valorCents: Cents;
  parcelas?: number;
  primeiroVencDias?: number;
  meio?: string;
};

export type FaturarResult =
  | { ok: true; origemId: string; parcelas: Lancamento[]; totalCents: Cents }
  | { ok: false; motivo: string; origemId: string };

export type FinResumo = {
  aReceberCents: Cents;
  aPagarCents: Cents;
  vencidoReceberCents: Cents;
  recebidoMesCents: Cents;
  pagoMesCents: Cents;
  saldoContasCents: Cents;
};

type Ctx = {
  ready: boolean;
  lancamentos: Lancamento[];
  contas: ContaFinanceira[];
  resumo: FinResumo;
  byOrigem: (origemId: string) => Lancamento[];
  jaFaturado: (origemId: string) => boolean;
  faturar: (opts: FaturarOpts) => FaturarResult;
  estornar: (origemId: string) => void;
  liquidar: (lancId: string, contaId: string) => void;
  addLancamento: (l: Omit<Lancamento, "id">) => Lancamento;
};

const fallback: Ctx = {
  ready: false,
  lancamentos: LANC_SEED,
  contas: CONTAS_SEED,
  resumo: emptyResumo(),
  byOrigem: () => [],
  jaFaturado: () => false,
  faturar: (o) => ({ ok: false, motivo: "não pronto", origemId: o.origemId }),
  estornar: () => undefined,
  liquidar: () => undefined,
  addLancamento: () => LANC_SEED[0]!,
};

function emptyResumo(): FinResumo {
  return {
    aReceberCents: 0,
    aPagarCents: 0,
    vencidoReceberCents: 0,
    recebidoMesCents: 0,
    pagoMesCents: 0,
    saldoContasCents: 0,
  };
}

function computeResumo(lancs: Lancamento[], contas: ContaFinanceira[]): FinResumo {
  const aReceber = lancs.filter((l) => l.tipo === "receber" && l.status !== "liquidado");
  const aPagar = lancs.filter((l) => l.tipo === "pagar" && l.status !== "liquidado");
  const recebido = lancs.filter((l) => l.tipo === "receber" && l.status === "liquidado");
  const pago = lancs.filter((l) => l.tipo === "pagar" && l.status === "liquidado");
  const vencidoR = aReceber.filter((l) => l.status === "vencido");
  return {
    aReceberCents: sumCents(...aReceber.map((l) => l.valorCents)),
    aPagarCents: sumCents(...aPagar.map((l) => l.valorCents)),
    vencidoReceberCents: sumCents(...vencidoR.map((l) => l.valorCents)),
    recebidoMesCents: sumCents(...recebido.map((l) => l.valorCents)),
    pagoMesCents: sumCents(...pago.map((l) => l.valorCents)),
    saldoContasCents: sumCents(...contas.map((c) => c.saldoCents)),
  };
}

const FinContext = createContext<Ctx>(fallback);

export function FinanceiroProvider({ children }: { children: ReactNode }) {
  const [lancamentos, setLancamentos] = useState<Lancamento[]>(LANC_SEED);
  const [contas] = useState<ContaFinanceira[]>(CONTAS_SEED);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const raw = await AsyncStorage.getItem(KEY);
        if (!cancelled && raw) {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed) && parsed.length > 0) setLancamentos(parsed);
        }
      } catch {
        /* ignore */
      } finally {
        if (!cancelled) setReady(true);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const persist = useCallback((next: Lancamento[]) => {
    void AsyncStorage.setItem(KEY, JSON.stringify(next)).catch(() => undefined);
  }, []);

  const byOrigem = useCallback(
    (origemId: string) => lancamentos.filter((l) => l.origem === origemId),
    [lancamentos],
  );

  const jaFaturado = useCallback(
    (origemId: string) => lancamentos.some((l) => l.origem === origemId),
    [lancamentos],
  );

  const faturar = useCallback<Ctx["faturar"]>(
    (opts) => {
      if (jaFaturado(opts.origemId)) {
        return { ok: false, motivo: "OS já faturada", origemId: opts.origemId };
      }
      const parcelas = opts.parcelas ?? 1;
      const primeiroVenc = opts.primeiroVencDias ?? 7;
      const partes = gerarParcelasCents(opts.valorCents, parcelas);
      const novos: Lancamento[] = partes.map((cents, i) => {
        const vencDias = primeiroVenc + i * 30;
        return {
          id: `R-${opts.origemId}-${i + 1}`,
          tipo: "receber",
          desc: parcelas > 1 ? `${opts.desc} (${i + 1}/${parcelas})` : opts.desc,
          parte: opts.parte,
          parteId: opts.parteId ?? null,
          categoria: opts.categoria ?? "Venda de impressos",
          origem: opts.origemId,
          valorCents: cents,
          vencDias,
          vencLabel: venc(vencDias),
          status: vencDias < 0 ? "vencido" : "aberto",
          meio: opts.meio ?? "Boleto",
          emitidoPor: "faturamento",
        };
      });
      setLancamentos((curr) => {
        const next = [...novos, ...curr];
        persist(next);
        return next;
      });
      return { ok: true, origemId: opts.origemId, parcelas: novos, totalCents: opts.valorCents };
    },
    [jaFaturado, persist],
  );

  const estornar = useCallback<Ctx["estornar"]>(
    (origemId) => {
      setLancamentos((curr) => {
        const next = curr.filter((l) => l.origem !== origemId || l.status === "liquidado");
        persist(next);
        return next;
      });
    },
    [persist],
  );

  const liquidar = useCallback<Ctx["liquidar"]>(
    (lancId, contaId) => {
      setLancamentos((curr) => {
        const next = curr.map((l) =>
          l.id === lancId
            ? { ...l, status: "liquidado" as const, conta: contaId, liqLabel: "Agora" }
            : l,
        );
        persist(next);
        return next;
      });
    },
    [persist],
  );

  const addLancamento = useCallback<Ctx["addLancamento"]>(
    (l) => {
      const novo: Lancamento = { ...l, id: `L-${Date.now().toString(36)}` };
      setLancamentos((curr) => {
        const next = [novo, ...curr];
        persist(next);
        return next;
      });
      return novo;
    },
    [persist],
  );

  const resumo = useMemo(() => computeResumo(lancamentos, contas), [lancamentos, contas]);

  const value = useMemo<Ctx>(
    () => ({
      ready,
      lancamentos,
      contas,
      resumo,
      byOrigem,
      jaFaturado,
      faturar,
      estornar,
      liquidar,
      addLancamento,
    }),
    [ready, lancamentos, contas, resumo, byOrigem, jaFaturado, faturar, estornar, liquidar, addLancamento],
  );

  return <FinContext.Provider value={value}>{children}</FinContext.Provider>;
}

export function useFinanceiro() {
  return useContext(FinContext);
}
