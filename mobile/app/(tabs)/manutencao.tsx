/**
 * ManutencaoScreen — consulta operacional da Oficina (Bloco 3A).
 *
 * 3 visões: Pátio (locais → OSs dentro), Por status (pipeline), Por prazo.
 * Triagem 4-stats tocáveis no topo. Card de OS com placa Mercosul, mini-pipeline
 * 6 segmentos, selo de status, prazo colorido, contador checklist X/Y, mecânico,
 * itens pendentes + valor + borda esquerda colorida por prioridade.
 */
import { useMemo, useState } from "react";
import { Pressable, ScrollView, Text, View } from "react-native";
import { useRouter } from "expo-router";

import {
  OiAvatar,
  OiCard,
  OiChip,
  OiChips,
  OiEmpty,
  OiFab,
  OiHeader,
  OiIcon,
  OiMiniPipeline,
  OiMoney,
  OiOrigin,
  OiPlaca,
  OiScreen,
  OiSearch,
  OiSection,
  OiStageStatus,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { formatPrazo } from "@/lib/format";
import {
  MANUT_PIPELINE,
  osTotal,
  pipelineIndex,
  priorityColor,
  statusTone,
  type ManutOs,
} from "@/lib/manutencao-mock";
import { useManutencao } from "@/lib/use-manutencao";

type View3 = "patio" | "status" | "prazo";
type Triage = "todas" | "atrasadas" | "aprovacao" | "peca" | "prontas";

export default function ManutencaoScreen() {
  const { palette } = useOiTheme();
  const router = useRouter();
  const { oss, locais, mecanicos } = useManutencao();
  const [view, setView] = useState<View3>("patio");
  const [triage, setTriage] = useState<Triage>("todas");
  const [q, setQ] = useState("");

  const counts = useMemo(() => {
    let atrasadas = 0;
    let aprovacao = 0;
    let peca = 0;
    let prontas = 0;
    for (const o of oss) {
      if (o.status === "Aguardando aprovação") aprovacao++;
      else if (o.status === "Aguardando peça") peca++;
      else if (o.status === "Pronto") prontas++;
      const pr = formatPrazo(o.prazoEm);
      if (pr.tone === "danger" && o.status !== "Pronto") atrasadas++;
    }
    return { atrasadas, aprovacao, peca, prontas };
  }, [oss]);

  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    return oss.filter((o) => {
      // busca
      if (term) {
        const hay = [
          o.id,
          o.veiculo.placa,
          o.veiculo.placa2,
          o.veiculo.chassi,
          `${o.veiculo.marca} ${o.veiculo.modelo}`,
          o.cliente,
        ]
          .filter(Boolean)
          .join(" ")
          .toLowerCase();
        if (!hay.includes(term)) return false;
      }
      // triagem
      if (triage === "atrasadas") {
        const pr = formatPrazo(o.prazoEm);
        if (pr.tone !== "danger" || o.status === "Pronto") return false;
      }
      if (triage === "aprovacao" && o.status !== "Aguardando aprovação") return false;
      if (triage === "peca" && o.status !== "Aguardando peça") return false;
      if (triage === "prontas" && o.status !== "Pronto") return false;
      return true;
    });
  }, [oss, q, triage]);

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Oficina"
        eyebrow="Manutenção de frota"
        actions={[
          { icon: "settings", onPress: () => router.push("/locais" as never) },
        ]}
      />
      <View style={{ paddingHorizontal: 16, paddingTop: 4, gap: 10 }}>
        <OiSearch
          value={q}
          onChangeText={setQ}
          placeholder="Placa, OS, cliente, chassi…"
        />
        {/* Triagem */}
        <View style={{ flexDirection: "row", gap: 6 }}>
          {([
            { id: "atrasadas", label: "Atrasadas", n: counts.atrasadas, tone: "danger" },
            { id: "aprovacao", label: "Aprovação", n: counts.aprovacao, tone: "warn" },
            { id: "peca", label: "Aguard. peça", n: counts.peca, tone: "warn" },
            { id: "prontas", label: "Prontas", n: counts.prontas, tone: "ok" },
          ] as const).map((s) => {
            const on = triage === s.id;
            const tint =
              s.tone === "danger"
                ? palette.danger
                : s.tone === "warn"
                  ? palette.warn
                  : palette.ok;
            return (
              <Pressable
                key={s.id}
                onPress={() => setTriage((curr) => (curr === s.id ? "todas" : s.id))}
                style={{
                  flex: 1,
                  paddingVertical: 8,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: on ? tint : palette.border,
                  backgroundColor: on ? hexAlpha(tint, 0.16) : palette.surface,
                  alignItems: "center",
                }}
              >
                <Text
                  style={{
                    fontFamily: fonts.sansBold,
                    fontSize: 18,
                    color: tint,
                  }}
                >
                  {s.n}
                </Text>
                <Text
                  style={{
                    fontSize: 9.5,
                    color: palette.textMute,
                    fontFamily: fonts.sansSemibold,
                    textTransform: "uppercase",
                    letterSpacing: 0.6,
                  }}
                >
                  {s.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
        {/* Toggle de visão (segmented) */}
        <View
          style={{
            flexDirection: "row",
            borderRadius: radius.md,
            backgroundColor: palette.bg2,
            padding: 3,
          }}
        >
          {([
            { id: "patio", label: "Pátio" },
            { id: "status", label: "Por status" },
            { id: "prazo", label: "Por prazo" },
          ] as const).map((opt) => {
            const on = view === opt.id;
            return (
              <Pressable
                key={opt.id}
                onPress={() => setView(opt.id)}
                style={{
                  flex: 1,
                  paddingVertical: 8,
                  alignItems: "center",
                  borderRadius: radius.sm,
                  backgroundColor: on ? palette.surface : "transparent",
                }}
              >
                <Text
                  style={{
                    fontFamily: fonts.sansSemibold,
                    fontSize: 12,
                    color: on ? palette.text : palette.textMute,
                  }}
                >
                  {opt.label}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </View>

      <ScrollView
        contentContainerStyle={{ paddingBottom: 96 }}
        showsVerticalScrollIndicator={false}
      >
        {filtered.length === 0 ? (
          <View style={{ padding: 24 }}>
            <OiEmpty
              icon="wrench"
              title="Nenhuma OS encontrada"
              subtitle="Ajuste a busca ou a triagem."
            />
          </View>
        ) : view === "patio" ? (
          <PatioView locais={locais} oss={filtered} onOpen={(id) => router.push(`/manutencao/${id}` as never)} />
        ) : view === "status" ? (
          <StatusView oss={filtered} onOpen={(id) => router.push(`/manutencao/${id}` as never)} />
        ) : (
          <PrazoView oss={filtered} onOpen={(id) => router.push(`/manutencao/${id}` as never)} />
        )}
      </ScrollView>
      <OiFab
        icon="plus"
        onPress={() => router.push("/manutencao/new" as never)}
      />
    </OiScreen>
  );
}

// ─── Card de OS ────────────────────────────────────────────────────────────

function OsCard({ os, onPress }: { os: ManutOs; onPress: () => void }) {
  const { palette } = useOiTheme();
  const idx = pipelineIndex(os.status);
  const paused = os.status === "Aguardando peça" || os.status === "Aguardando aprovação";
  const prazo = formatPrazo(os.prazoEm);
  const tot = osTotal(os);
  const checklistDone = os.checklist.filter((c) => c.done).length;
  const checklistTotal = os.checklist.length;
  const priColor = (() => {
    const t = priorityColor(os.prioridade);
    if (t === "danger") return palette.danger;
    if (t === "warn") return palette.warn;
    if (t === "accent") return palette.accent;
    return palette.border;
  })();

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => ({
        backgroundColor: palette.surface,
        borderRadius: radius.md,
        borderColor: palette.border,
        borderWidth: 1,
        padding: 12,
        gap: 8,
        opacity: pressed ? 0.85 : 1,
        borderLeftWidth: 4,
        borderLeftColor: priColor,
      })}
    >
      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
        <OiOrigin kind="OS" />
        <Text
          style={{
            fontFamily: fonts.mono,
            fontSize: 11,
            color: palette.textMute,
          }}
        >
          {os.id}
        </Text>
        <View style={{ flex: 1 }} />
        <OiPlaca text={os.veiculo.placa} size="sm" />
        <Text
          style={{
            fontFamily: fonts.sansSemibold,
            fontSize: 10.5,
            color:
              prazo.tone === "danger"
                ? palette.danger
                : prazo.tone === "warn"
                  ? palette.warn
                  : palette.textMute,
          }}
        >
          {prazo.label}
        </Text>
      </View>

      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text
            style={{
              fontFamily: fonts.sansSemibold,
              fontSize: 14,
              color: palette.text,
            }}
            numberOfLines={1}
          >
            {os.veiculo.marca} {os.veiculo.modelo}
          </Text>
          <Text
            style={{
              fontFamily: fonts.sans,
              fontSize: 11.5,
              color: palette.textMute,
            }}
            numberOfLines={1}
          >
            {os.veiculo.hodometro.toLocaleString("pt-BR")} km · {os.cliente}
          </Text>
        </View>
        <OiStageStatus label={os.status} tone={statusTone(os.status)} />
      </View>

      <OiMiniPipeline
        total={MANUT_PIPELINE.length}
        currentIndex={idx}
        paused={paused}
      />

      <View
        style={{
          flexDirection: "row",
          alignItems: "center",
          gap: 8,
          marginTop: 2,
        }}
      >
        <Text
          style={{
            fontSize: 11,
            color: palette.textDim,
            fontFamily: fonts.sansSemibold,
          }}
        >
          ✓ {checklistDone}/{checklistTotal}
        </Text>
        {tot.aguardando > 0 ? (
          <Text
            style={{
              fontSize: 10.5,
              color: palette.warn,
              fontFamily: fonts.sansSemibold,
            }}
          >
            {tot.aguardando} p/ aprovar
          </Text>
        ) : null}
        <View style={{ flex: 1 }} />
        <OiMoney value={tot.total} size={12.5} weight="semibold" />
      </View>
    </Pressable>
  );
}

// ─── Visões ────────────────────────────────────────────────────────────────

function PatioView({
  locais,
  oss,
  onOpen,
}: {
  locais: ReturnType<typeof useManutencao>["locais"];
  oss: ManutOs[];
  onOpen: (id: string) => void;
}) {
  const { palette } = useOiTheme();
  return (
    <View style={{ paddingHorizontal: 16, paddingTop: 12, gap: 14 }}>
      {locais.map((l) => {
        const inside = oss.filter((o) => o.localId === l.id);
        const cheio = inside.length >= l.capacidade;
        return (
          <View key={l.id} style={{ gap: 8 }}>
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                gap: 8,
              }}
            >
              <Text
                style={{
                  fontFamily: fonts.sansBold,
                  fontSize: 11,
                  letterSpacing: 1,
                  textTransform: "uppercase",
                  color: palette.textMute,
                }}
              >
                {l.nome}
              </Text>
              <Text
                style={{
                  fontFamily: fonts.sansSemibold,
                  fontSize: 10.5,
                  color: cheio ? palette.warn : palette.ok,
                }}
              >
                {inside.length}/{l.capacidade}
              </Text>
              <View style={{ flex: 1 }} />
              <Text style={{ fontSize: 10.5, color: palette.textMute }}>{l.tipo}</Text>
            </View>
            {inside.length === 0 ? (
              <OiCard style={{ padding: 14, alignItems: "center" }}>
                <Text style={{ color: palette.textMute, fontSize: 12 }}>
                  Livre
                </Text>
              </OiCard>
            ) : (
              inside.map((o) => (
                <OsCard key={o.id} os={o} onPress={() => onOpen(o.id)} />
              ))
            )}
          </View>
        );
      })}
    </View>
  );
}

function StatusView({ oss, onOpen }: { oss: ManutOs[]; onOpen: (id: string) => void }) {
  const { palette } = useOiTheme();
  return (
    <View style={{ paddingHorizontal: 16, paddingTop: 12, gap: 14 }}>
      {MANUT_PIPELINE.map((stage) => {
        const inside = oss.filter((o) => {
          if (o.status === stage) return true;
          if (stage === "Aprovação" && o.status === "Aguardando aprovação") return true;
          if (stage === "Execução" && o.status === "Aguardando peça") return true;
          return false;
        });
        if (inside.length === 0) return null;
        return (
          <View key={stage} style={{ gap: 8 }}>
            <Text
              style={{
                fontFamily: fonts.sansBold,
                fontSize: 11,
                letterSpacing: 1,
                textTransform: "uppercase",
                color: palette.textMute,
              }}
            >
              {stage} · {inside.length}
            </Text>
            {inside.map((o) => (
              <OsCard key={o.id} os={o} onPress={() => onOpen(o.id)} />
            ))}
          </View>
        );
      })}
    </View>
  );
}

function PrazoView({ oss, onOpen }: { oss: ManutOs[]; onOpen: (id: string) => void }) {
  const sorted = useMemo(
    () =>
      [...oss].sort((a, b) => new Date(a.prazoEm).getTime() - new Date(b.prazoEm).getTime()),
    [oss],
  );
  return (
    <View style={{ paddingHorizontal: 16, paddingTop: 12, gap: 8 }}>
      {sorted.map((o) => (
        <OsCard key={o.id} os={o} onPress={() => onOpen(o.id)} />
      ))}
    </View>
  );
}
