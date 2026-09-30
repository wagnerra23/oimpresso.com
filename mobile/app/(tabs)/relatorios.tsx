/**
 * F3-02 — Relatórios (DRE simplificado + Vendas + Produção + Estoque)
 *
 * Gráficos: usamos uma renderização baseada em <View> + flexbox (MVP).
 * Sem dependência de victory/react-native-svg-charts — evita problemas com
 * react-native-web e mantém o bundle leve. A informação numérica é a parte
 * crítica; os gráficos servem como leitura visual rápida.
 */

import React, { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  Text,
  View,
} from "react-native";

import { OiHeader, OiScreen } from "@/components/oi";
import { MetricCard, Toast } from "@/components/erp-ui";
import {
  useDre,
  useExportReportExcel,
  useExportReportPdf,
  useReportEstoque,
  useReportProducao,
  useReportVendas,
} from "@/lib/erp-queries";

type Tab = "dre" | "vendas" | "producao" | "estoque";

const TABS: { id: Tab; label: string }[] = [
  { id: "dre", label: "DRE" },
  { id: "vendas", label: "Vendas" },
  { id: "producao", label: "Produção" },
  { id: "estoque", label: "Estoque" },
];

type PeriodId = "mes" | "trimestre" | "ano";
const PERIOD_OPTIONS: { id: PeriodId; label: string }[] = [
  { id: "mes", label: "Este mês" },
  { id: "trimestre", label: "Últimos 3 meses" },
  { id: "ano", label: "Último ano" },
];

function computePeriod(p: PeriodId): { from: string; to: string } {
  const now = new Date();
  const to = now.toISOString();
  const fromDate = new Date(now);
  if (p === "mes") fromDate.setDate(now.getDate() - 30);
  else if (p === "trimestre") fromDate.setMonth(now.getMonth() - 3);
  else fromDate.setFullYear(now.getFullYear() - 1);
  return { from: fromDate.toISOString(), to };
}

function fmtBRL(n: number) {
  return `R$ ${n.toFixed(2).replace(".", ",")}`;
}

function fmtPct(n: number) {
  return `${n.toFixed(1)}%`;
}

// ─── Simple bar chart (View-based) ──────────────────────────────────────────

function MiniBars({
  data,
  color = "#3b82f6",
  height = 120,
}: {
  data: { label: string; value: number }[];
  color?: string;
  height?: number;
}) {
  const max = Math.max(1, ...data.map((d) => d.value));
  return (
    <View className="flex-row items-end gap-2" style={{ height }}>
      {data.map((d, idx) => {
        const h = max > 0 ? (d.value / max) * (height - 24) : 0;
        return (
          <View key={`${d.label}-${idx}`} className="flex-1 items-center">
            <View
              style={{
                height: Math.max(2, h),
                width: "80%",
                backgroundColor: color,
                borderRadius: 4,
              }}
            />
            <Text className="text-[10px] text-muted mt-1" numberOfLines={1}>
              {d.label}
            </Text>
          </View>
        );
      })}
    </View>
  );
}

function ComparisonBars({
  a,
  b,
  labelA,
  labelB,
}: {
  a: number;
  b: number;
  labelA: string;
  labelB: string;
}) {
  const max = Math.max(1, a, b);
  return (
    <View className="gap-3">
      <View>
        <View className="flex-row justify-between mb-1">
          <Text className="text-xs text-foreground">{labelA}</Text>
          <Text className="text-xs text-foreground">{fmtBRL(a)}</Text>
        </View>
        <View
          className="bg-surface rounded-md overflow-hidden"
          style={{ height: 14 }}
        >
          <View
            style={{
              width: `${(a / max) * 100}%`,
              height: "100%",
              backgroundColor: "#10b981",
            }}
          />
        </View>
      </View>
      <View>
        <View className="flex-row justify-between mb-1">
          <Text className="text-xs text-foreground">{labelB}</Text>
          <Text className="text-xs text-foreground">{fmtBRL(b)}</Text>
        </View>
        <View
          className="bg-surface rounded-md overflow-hidden"
          style={{ height: 14 }}
        >
          <View
            style={{
              width: `${(b / max) * 100}%`,
              height: "100%",
              backgroundColor: "#ef4444",
            }}
          />
        </View>
      </View>
    </View>
  );
}

// ─── Export buttons ─────────────────────────────────────────────────────────

function ExportButtons({
  tipo,
  period,
  onError,
}: {
  tipo: "dre" | "vendas" | "producao" | "estoque";
  period: { from?: string; to?: string };
  onError: (msg: string) => void;
}) {
  const pdfMut = useExportReportPdf();
  const xlsxMut = useExportReportExcel();

  async function handle(kind: "pdf" | "xlsx") {
    try {
      const input =
        tipo === "estoque" ? { tipo } : { tipo, from: period.from, to: period.to };
      const res =
        kind === "pdf"
          ? await pdfMut.mutateAsync(input as any)
          : await xlsxMut.mutateAsync(input as any);
      if (res?.url) {
        try {
          await Linking.openURL(res.url);
        } catch {
          // ignore — URL pode requerer prefix do servidor em alguns ambientes
        }
      }
    } catch (err) {
      onError(err instanceof Error ? err.message : "Falha ao exportar");
    }
  }

  return (
    <View className="flex-row gap-2 mt-3">
      <Pressable
        onPress={() => handle("pdf")}
        disabled={pdfMut.isPending}
        className="flex-1 bg-primary rounded-md py-2 items-center"
      >
        <Text className="text-white font-semibold text-sm">
          {pdfMut.isPending ? "Gerando…" : "PDF"}
        </Text>
      </Pressable>
      <Pressable
        onPress={() => handle("xlsx")}
        disabled={xlsxMut.isPending}
        className="flex-1 bg-success rounded-md py-2 items-center"
        style={{ backgroundColor: "#0f766e" }}
      >
        <Text className="text-white font-semibold text-sm">
          {xlsxMut.isPending ? "Gerando…" : "Excel"}
        </Text>
      </Pressable>
    </View>
  );
}

// ─── Tabs Content ───────────────────────────────────────────────────────────

function DreTab({ period }: { period: { from: string; to: string } }) {
  const { data, isLoading } = useDre(period);
  const [err, setErr] = useState<string | null>(null);

  if (isLoading) return <ActivityIndicator className="mt-6" />;
  if (!data) return <Text className="text-muted mt-4">Sem dados.</Text>;

  return (
    <View className="gap-4">
      {err && <Toast tipo="erro" mensagem={err} />}
      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">
          Receitas vs. Despesas
        </Text>
        <ComparisonBars
          a={data.totalReceitas}
          b={data.totalDespesas}
          labelA="Receitas"
          labelB="Despesas"
        />
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">
          Receitas por Categoria
        </Text>
        {data.receitasPorCategoria.length === 0 ? (
          <Text className="text-xs text-muted">Sem receitas no período.</Text>
        ) : (
          data.receitasPorCategoria.map((r) => (
            <View
              key={`r-${r.categoria}`}
              className="flex-row justify-between py-1"
            >
              <Text className="text-sm text-foreground">{r.categoria}</Text>
              <Text className="text-sm font-medium text-foreground">
                {fmtBRL(r.valor)}
              </Text>
            </View>
          ))
        )}
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">
          Despesas por Categoria
        </Text>
        {data.despesasPorCategoria.length === 0 ? (
          <Text className="text-xs text-muted">Sem despesas no período.</Text>
        ) : (
          data.despesasPorCategoria.map((r) => (
            <View
              key={`d-${r.categoria}`}
              className="flex-row justify-between py-1"
            >
              <Text className="text-sm text-foreground">{r.categoria}</Text>
              <Text className="text-sm font-medium text-foreground">
                {fmtBRL(r.valor)}
              </Text>
            </View>
          ))
        )}
      </View>

      <ExportButtons tipo="dre" period={period} onError={setErr} />
    </View>
  );
}

function VendasTab({ period }: { period: { from: string; to: string } }) {
  const [groupBy, setGroupBy] = useState<"dia" | "semana" | "mes">("dia");
  const { data, isLoading } = useReportVendas({ ...period, groupBy });
  const [err, setErr] = useState<string | null>(null);

  if (isLoading) return <ActivityIndicator className="mt-6" />;
  if (!data) return <Text className="text-muted mt-4">Sem dados.</Text>;

  const seriesBars = data.series.slice(-14).map((s) => ({
    label: s.date.slice(-5),
    value: s.total,
  }));

  return (
    <View className="gap-4">
      {err && <Toast tipo="erro" mensagem={err} />}

      <View className="flex-row gap-2">
        {(["dia", "semana", "mes"] as const).map((g) => (
          <Pressable
            key={g}
            onPress={() => setGroupBy(g)}
            className={`flex-1 py-2 rounded-md items-center ${
              groupBy === g ? "bg-primary" : "bg-surface"
            }`}
          >
            <Text
              className={`text-xs font-medium ${
                groupBy === g ? "text-white" : "text-foreground"
              }`}
            >
              {g === "dia" ? "Dia" : g === "semana" ? "Semana" : "Mês"}
            </Text>
          </Pressable>
        ))}
      </View>

      <View className="flex-row gap-2">
        <MetricCard label="Total Vendas" value={fmtBRL(data.totalVendas)} />
        <MetricCard label="Ticket Médio" value={fmtBRL(data.ticketMedio)} />
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">
          Receita por {groupBy === "dia" ? "dia" : groupBy === "semana" ? "semana" : "mês"}
        </Text>
        {seriesBars.length === 0 ? (
          <Text className="text-xs text-muted">Sem vendas no período.</Text>
        ) : (
          <MiniBars data={seriesBars} />
        )}
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">Top 5 Clientes</Text>
        {data.topClientes.slice(0, 5).map((c, i) => (
          <View
            key={`${c.customerId ?? c.nome}-${i}`}
            className="flex-row justify-between py-1"
          >
            <Text className="text-sm text-foreground flex-1" numberOfLines={1}>
              {i + 1}. {c.nome}
            </Text>
            <Text className="text-sm font-medium text-foreground">
              {fmtBRL(c.valor)}
            </Text>
          </View>
        ))}
        {data.topClientes.length === 0 && (
          <Text className="text-xs text-muted">Sem clientes no período.</Text>
        )}
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">Top 5 Produtos</Text>
        {data.topProdutos.slice(0, 5).map((p, i) => (
          <View key={`${p.produto}-${i}`} className="flex-row justify-between py-1">
            <Text className="text-sm text-foreground flex-1" numberOfLines={1}>
              {i + 1}. {p.produto}
            </Text>
            <Text className="text-sm font-medium text-foreground">
              {fmtBRL(p.valor)}
            </Text>
          </View>
        ))}
        {data.topProdutos.length === 0 && (
          <Text className="text-xs text-muted">Sem produtos vendidos.</Text>
        )}
      </View>

      <ExportButtons tipo="vendas" period={period} onError={setErr} />
    </View>
  );
}

function ProducaoTab({ period }: { period: { from: string; to: string } }) {
  const { data, isLoading } = useReportProducao(period);
  const [err, setErr] = useState<string | null>(null);

  if (isLoading) return <ActivityIndicator className="mt-6" />;
  if (!data) return <Text className="text-muted mt-4">Sem dados.</Text>;

  return (
    <View className="gap-4">
      {err && <Toast tipo="erro" mensagem={err} />}

      <View className="flex-row gap-2">
        <MetricCard label="Total OPs" value={String(data.totalOPs)} />
        <MetricCard label="Total OSs" value={String(data.totalOSs)} />
        <MetricCard
          label="Tempo médio"
          value={`${data.tempoMedioConclusaoDias.toFixed(1)}d`}
        />
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">OPs por Status</Text>
        {Object.entries(data.countOPsPorStatus).map(([k, v]) => (
          <View key={`op-${k}`} className="flex-row justify-between py-1">
            <Text className="text-sm text-foreground capitalize">{k}</Text>
            <Text className="text-sm font-medium text-foreground">{v}</Text>
          </View>
        ))}
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">OSs por Status</Text>
        {Object.entries(data.countOSsPorStatus).length === 0 ? (
          <Text className="text-xs text-muted">Sem OSs no período.</Text>
        ) : (
          Object.entries(data.countOSsPorStatus).map(([k, v]) => (
            <View key={`os-${k}`} className="flex-row justify-between py-1">
              <Text className="text-sm text-foreground capitalize">
                {k.replace(/_/g, " ")}
              </Text>
              <Text className="text-sm font-medium text-foreground">{v}</Text>
            </View>
          ))
        )}
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">
          Mecânicos mais ativos
        </Text>
        {data.mecanicosMaisAtivos.length === 0 ? (
          <Text className="text-xs text-muted">Sem dados.</Text>
        ) : (
          data.mecanicosMaisAtivos.map((m, i) => (
            <View key={`mec-${i}`} className="flex-row justify-between py-1">
              <Text className="text-sm text-foreground">
                {i + 1}. {m.mecanico}
              </Text>
              <Text className="text-sm font-medium text-foreground">
                {m.count} itens
              </Text>
            </View>
          ))
        )}
      </View>

      <ExportButtons tipo="producao" period={period} onError={setErr} />
    </View>
  );
}

function EstoqueTab() {
  const { data, isLoading } = useReportEstoque();
  const [err, setErr] = useState<string | null>(null);

  if (isLoading) return <ActivityIndicator className="mt-6" />;
  if (!data) return <Text className="text-muted mt-4">Sem dados.</Text>;

  return (
    <View className="gap-4">
      {err && <Toast tipo="erro" mensagem={err} />}

      <View className="flex-row gap-2">
        <MetricCard label="Itens Ativos" value={String(data.itensAtivos)} />
        <MetricCard
          label="Valor em Estoque"
          value={fmtBRL(data.valorTotalEstoque)}
        />
      </View>

      <View className="flex-row gap-2">
        <MetricCard
          label="Movimentos 30d"
          value={String(data.movimentosUltimos30Dias)}
        />
        <MetricCard
          label="Estoque Baixo"
          value={String(data.itensComEstoqueBaixo.length)}
        />
      </View>

      <View className="bg-surface rounded-lg p-4">
        <Text className="font-semibold text-foreground mb-3">
          Itens com estoque baixo
        </Text>
        {data.itensComEstoqueBaixo.length === 0 ? (
          <Text className="text-xs text-muted">
            Nenhum item abaixo do mínimo.
          </Text>
        ) : (
          data.itensComEstoqueBaixo.map((i) => (
            <View key={i.id} className="flex-row justify-between py-1">
              <Text
                className="text-sm text-foreground flex-1"
                numberOfLines={1}
              >
                {i.nome}
              </Text>
              <Text className="text-sm font-medium" style={{ color: "#dc2626" }}>
                {i.quantidade.toFixed(2)} / min {i.estoqueMinimo.toFixed(2)}
              </Text>
            </View>
          ))
        )}
      </View>

      <ExportButtons tipo="estoque" period={{}} onError={setErr} />
    </View>
  );
}

// ─── Screen ─────────────────────────────────────────────────────────────────

export default function RelatoriosScreen() {
  const [tab, setTab] = useState<Tab>("dre");
  const [periodId, setPeriodId] = useState<PeriodId>("mes");
  const period = useMemo(() => computePeriod(periodId), [periodId]);
  const { data: dre } = useDre(period);

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Relatórios"
        eyebrow="DRE · vendas · produção · estoque"
      />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 48 }}>

        {/* Period selector */}
        <View className="flex-row gap-2 mb-4">
          {PERIOD_OPTIONS.map((p) => (
            <Pressable
              key={p.id}
              onPress={() => setPeriodId(p.id)}
              className={`flex-1 py-2 rounded-md items-center ${
                periodId === p.id ? "bg-primary" : "bg-surface"
              }`}
            >
              <Text
                className={`text-xs font-medium ${
                  periodId === p.id ? "text-white" : "text-foreground"
                }`}
              >
                {p.label}
              </Text>
            </Pressable>
          ))}
        </View>

        {/* DRE Summary cards (always visible) */}
        <View className="gap-2 mb-4">
          <View className="flex-row gap-2">
            <MetricCard
              label="Receitas"
              value={dre ? fmtBRL(dre.totalReceitas) : "—"}
            />
            <MetricCard
              label="Despesas"
              value={dre ? fmtBRL(dre.totalDespesas) : "—"}
            />
          </View>
          <View className="flex-row gap-2">
            <MetricCard
              label="Saldo"
              value={dre ? fmtBRL(dre.resultadoLiquido) : "—"}
            />
            <MetricCard
              label="Margem"
              value={dre ? fmtPct(dre.margemPercent) : "—"}
            />
          </View>
        </View>

        {/* Tabs */}
        <View className="flex-row gap-2 mb-4">
          {TABS.map((t) => (
            <Pressable
              key={t.id}
              onPress={() => setTab(t.id)}
              className={`flex-1 py-2 rounded-md items-center ${
                tab === t.id ? "bg-primary" : "bg-surface"
              }`}
            >
              <Text
                className={`text-xs font-medium ${
                  tab === t.id ? "text-white" : "text-foreground"
                }`}
              >
                {t.label}
              </Text>
            </Pressable>
          ))}
        </View>

        {tab === "dre" && <DreTab period={period} />}
        {tab === "vendas" && <VendasTab period={period} />}
        {tab === "producao" && <ProducaoTab period={period} />}
        {tab === "estoque" && <EstoqueTab />}
      </ScrollView>
    </OiScreen>
  );
}
