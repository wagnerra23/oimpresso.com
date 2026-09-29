/**
 * ManutOsDetalheScreen — detalhe da OS de manutenção (Bloco 3C).
 *
 * Hero do veículo + local/mecânico (sheets) + timeline conectada + checklist
 * X/Y + relato/diagnóstico + peças/serviços com aprovação orgânica + totais
 * + action bar (WhatsApp + Avançar).
 */
import { useMemo, useState } from "react";
import { useLocalSearchParams, useRouter } from "expo-router";
import {
  Animated,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";

import {
  OiCard,
  OiChecklist,
  OiDetailHeader,
  OiDl,
  OiDlRow,
  OiIcon,
  OiMoney,
  OiOrigin,
  OiPlaca,
  OiScreen,
  OiSection,
  OiSheet,
  OiStageStatus,
  OiTimeline,
  OiFaturarSheet,
  useToast,
} from "@/components/oi";
import { useFinanceiro } from "@/lib/use-financeiro";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { buildTimeline, formatPrazo } from "@/lib/format";
import {
  MANUT_PIPELINE,
  nextStage,
  osTotal,
  pipelineIndex,
  priorityColor,
  statusTone,
  type CatalogoItem,
  type ManutOs,
  type OsItem,
  type OsItemStatus,
} from "@/lib/manutencao-mock";
import { useManutencao } from "@/lib/use-manutencao";

export default function ManutOsDetalheScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { id } = useLocalSearchParams<{ id: string }>();
  const toast = useToast();
  const {
    byId,
    locais,
    mecanicos,
    catalogo,
    advanceStage,
    toggleChecklist,
    addChecklistItem,
    addOsItem,
    setItemStatus,
    approveAll,
    setLocal,
    setMecanico,
  } = useManutencao();
  const os = id ? byId(id) : undefined;

  const [sheet, setSheet] = useState<"local" | "mec" | "item" | null>(null);
  const [faturarOpen, setFaturarOpen] = useState(false);
  const { jaFaturado } = useFinanceiro();

  if (!os) {
    return (
      <OiScreen edges={["top"]}>
        <OiDetailHeader title="OS não encontrada" onBack={() => router.back()} />
      </OiScreen>
    );
  }

  const local = locais.find((l) => l.id === os.localId);
  const mecanico = mecanicos.find((m) => m.id === os.mecanicoId);
  const tone = statusTone(os.status);
  const idx = pipelineIndex(os.status);
  const paused = os.status === "Aguardando peça" || os.status === "Aguardando aprovação";
  const prazo = formatPrazo(os.prazoEm);
  const tot = osTotal(os);
  const proxima = nextStage(MANUT_PIPELINE[idx] ?? "Triagem");
  const aguardando = os.itens.filter(
    (it) => it.status === "aguardando" || it.status === "comprar",
  ).length;

  const events = buildTimeline({
    pipeline: MANUT_PIPELINE,
    currentStage: MANUT_PIPELINE[idx] ?? "Triagem",
    openedAt: os.abertaEm,
    paused,
    operatorPerStage: mecanico ? { Execução: mecanico.nome } : {},
  });

  const onAdvance = () => {
    if (os.status === "Pronto") {
      toast.show("Veículo entregue", "ok");
      router.back();
      return;
    }
    advanceStage(os.id);
    toast.show(`Avançado para ${proxima ?? "Pronto"}`, "ok");
  };

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader
        title={os.id}
        eyebrow={`${os.tipo} · ${os.prioridade.toUpperCase()}`}
        onBack={() => router.back()}
        right={
          <Pressable hitSlop={6} onPress={() => toast.show("Menu da OS", "default")} style={{ width: 38, height: 38, alignItems: "center", justifyContent: "center" }}>
            <OiIcon name="dots-v" size={20} color={palette.textDim} />
          </Pressable>
        }
      />

      <ScrollView contentContainerStyle={{ paddingBottom: 32 }} showsVerticalScrollIndicator={false}>
        {/* Hero veículo */}
        <OiSection>
          <OiCard variant="pad" style={{ gap: 12 }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
              <View
                style={{
                  width: 44,
                  height: 44,
                  borderRadius: 10,
                  backgroundColor: palette.bg2,
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <OiIcon name="truck" size={26} color={palette.textDim} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 16, color: palette.text }}>
                  {os.veiculo.marca} {os.veiculo.modelo}
                </Text>
                <Text style={{ fontFamily: fonts.sans, fontSize: 12, color: palette.textMute }}>
                  {os.veiculo.tipo} · {os.veiculo.ano} · {os.veiculo.cor}
                </Text>
              </View>
            </View>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6, alignItems: "center" }}>
              <OiPlaca text={os.veiculo.placa} />
              {os.veiculo.placa2 ? <OiPlaca text={os.veiculo.placa2} /> : null}
              <View style={{ flex: 1 }} />
              <OiStageStatus label={os.status} tone={tone} />
            </View>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
              <Pill label={prazo.label} tone={prazo.tone} />
              <Pill label={os.tipo} tone="accent" />
              <Pill label={os.prioridade} tone={priorityColor(os.prioridade)} />
            </View>
            <OiDl>
              <OiDlRow label="Chassi">
                <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>{os.veiculo.chassi}</Text>
              </OiDlRow>
              {os.veiculo.chassi2 ? (
                <OiDlRow label="Chassi 2">
                  <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>{os.veiculo.chassi2}</Text>
                </OiDlRow>
              ) : null}
              <OiDlRow label="Renavam">
                <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>{os.veiculo.renavam}</Text>
              </OiDlRow>
              <OiDlRow label="Hodômetro">
                <Text style={{ fontFamily: fonts.mono, fontSize: 12, color: palette.text }}>
                  {os.veiculo.hodometro.toLocaleString("pt-BR")} km
                </Text>
              </OiDlRow>
              {os.veiculo.frota ? <OiDlRow label="Frota">{os.veiculo.frota}</OiDlRow> : null}
              <OiDlRow label="Cliente">{os.cliente}</OiDlRow>
              {os.veiculo.motorista ? <OiDlRow label="Motorista">{os.veiculo.motorista}</OiDlRow> : null}
            </OiDl>
          </OiCard>
        </OiSection>

        {/* Local + Mecânico */}
        <View style={{ paddingHorizontal: 16, paddingBottom: 6 }}>
          <View style={{ flexDirection: "row", gap: 10 }}>
            <Pressable
              onPress={() => setSheet("local")}
              style={{
                flex: 1,
                padding: 12,
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: palette.border,
                backgroundColor: palette.surface,
                gap: 4,
              }}
            >
              <Text style={{ fontSize: 10, color: palette.textMute, fontFamily: fonts.sansBold, letterSpacing: 0.8 }}>
                LOCAL
              </Text>
              <Text style={{ fontSize: 13.5, color: palette.text, fontFamily: fonts.sansSemibold }}>
                {local?.nome ?? "Sem local"}
              </Text>
              <Text style={{ fontSize: 11, color: palette.textMute }}>
                {local?.tipo ?? "—"} · trocar →
              </Text>
            </Pressable>
            <Pressable
              onPress={() => setSheet("mec")}
              style={{
                flex: 1,
                padding: 12,
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: palette.border,
                backgroundColor: palette.surface,
                gap: 4,
              }}
            >
              <Text style={{ fontSize: 10, color: palette.textMute, fontFamily: fonts.sansBold, letterSpacing: 0.8 }}>
                MECÂNICO
              </Text>
              <Text style={{ fontSize: 13.5, color: palette.text, fontFamily: fonts.sansSemibold }}>
                {mecanico?.nome ?? "Atribuir"}
              </Text>
              <Text style={{ fontSize: 11, color: palette.textMute }}>
                {mecanico?.especialidade ?? "—"} · trocar →
              </Text>
            </Pressable>
          </View>
        </View>

        {/* Timeline */}
        <OiSection title="Andamento e histórico">
          <OiCard variant="pad">
            <OiTimeline events={events} />
          </OiCard>
        </OiSection>

        {/* Checklist */}
        <OiSection title="Checklist de serviço">
          <OiCard variant="pad">
            <OiChecklist
              items={os.checklist}
              onToggle={(itemId) => toggleChecklist(os.id, itemId)}
              onAdd={(label) => addChecklistItem(os.id, label)}
            />
          </OiCard>
        </OiSection>

        {/* Relato + Diagnóstico */}
        <OiSection title="Relato do motorista">
          <OiCard variant="pad">
            <Text style={{ fontSize: 13.5, color: palette.text, lineHeight: 19 }}>{os.relato}</Text>
          </OiCard>
        </OiSection>
        {os.diagnostico ? (
          <OiSection title="Diagnóstico técnico">
            <OiCard variant="pad">
              <Text style={{ fontSize: 13.5, color: palette.text, lineHeight: 19 }}>{os.diagnostico}</Text>
            </OiCard>
          </OiSection>
        ) : null}

        {/* Itens */}
        <OiSection title="Peças e serviços">
          {aguardando > 0 ? (
            <View
              style={{
                flexDirection: "row",
                alignItems: "center",
                gap: 10,
                paddingVertical: 10,
                paddingHorizontal: 12,
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: palette.warn,
                backgroundColor: hexAlpha(palette.warn, 0.12),
                marginBottom: 8,
              }}
            >
              <OiIcon name="alert" size={16} color={palette.warn} />
              <Text style={{ flex: 1, fontSize: 12.5, color: palette.text, fontFamily: fonts.sansSemibold }}>
                {aguardando} ite{aguardando > 1 ? "ns aguardam" : "m aguarda"} aprovação
              </Text>
              <Pressable
                onPress={() => {
                  approveAll(os.id);
                  toast.show("Itens aprovados", "ok");
                }}
                style={{
                  paddingHorizontal: 10,
                  paddingVertical: 5,
                  borderRadius: radius.sm,
                  backgroundColor: palette.warn,
                }}
              >
                <Text style={{ color: "#fff", fontSize: 11.5, fontFamily: fonts.sansSemibold }}>Aprovar todos</Text>
              </Pressable>
            </View>
          ) : null}
          <OiCard style={{ padding: 0, overflow: "hidden" }}>
            {os.itens.length === 0 ? (
              <View style={{ padding: 18, alignItems: "center" }}>
                <Text style={{ color: palette.textMute, fontSize: 12.5 }}>Nenhum item ainda.</Text>
              </View>
            ) : (
              os.itens.map((it, i) => (
                <ItemRow
                  key={it.id}
                  item={it}
                  showDivider={i > 0}
                  onAdvance={(novo) => {
                    setItemStatus(os.id, it.id, novo);
                    toast.show(
                      novo === "aplicado" ? "Item aplicado" : novo === "aprovado" ? "Aprovado" : "Atualizado",
                      "ok",
                    );
                  }}
                />
              ))
            )}
          </OiCard>
          <Pressable
            onPress={() => setSheet("item")}
            style={({ pressed }) => ({
              marginTop: 8,
              padding: 12,
              borderRadius: radius.md,
              borderWidth: 1,
              borderStyle: "dashed",
              borderColor: palette.accent,
              backgroundColor: pressed ? palette.accentSoft : "transparent",
              alignItems: "center",
              flexDirection: "row",
              justifyContent: "center",
              gap: 6,
            })}
          >
            <OiIcon name="plus" size={16} color={palette.accent} />
            <Text style={{ color: palette.accent, fontFamily: fonts.sansSemibold, fontSize: 13 }}>
              Adicionar peça ou serviço
            </Text>
          </Pressable>
        </OiSection>

        {/* Totais */}
        <OiSection title="Totais">
          <OiCard variant="pad">
            <OiDl>
              <OiDlRow label="Serviços">
                <OiMoney value={tot.servicos} size={13} weight="semibold" />
              </OiDlRow>
              <OiDlRow label="Peças">
                <OiMoney value={tot.pecas} size={13} weight="semibold" />
              </OiDlRow>
              <OiDlRow label="Aprovado">
                <OiMoney value={tot.aprovado} size={13} weight="semibold" color={palette.ok} />
              </OiDlRow>
              <OiDlRow label="Total da OS">
                <OiMoney value={tot.total} size={16} weight="semibold" color={palette.accent} />
              </OiDlRow>
            </OiDl>
          </OiCard>
        </OiSection>
      </ScrollView>

      {/* Action bar */}
      <View
        style={{
          padding: 12,
          gap: 8,
          flexDirection: "row",
          backgroundColor: palette.surface,
          borderTopWidth: 1,
          borderTopColor: palette.border,
        }}
      >
        <Pressable
          onPress={() => toast.show("Mensagem ao motorista enviada", "ok")}
          style={({ pressed }) => ({
            height: 48,
            paddingHorizontal: 16,
            borderRadius: radius.md,
            alignItems: "center",
            justifyContent: "center",
            flexDirection: "row",
            gap: 8,
            borderWidth: 1,
            borderColor: palette.border,
            opacity: pressed ? 0.85 : 1,
          })}
        >
          <OiIcon name="whatsapp" size={18} color={palette.text} />
          <Text style={{ color: palette.text, fontFamily: fonts.sansSemibold, fontSize: 13 }}>WhatsApp</Text>
        </Pressable>
        {os.status === "Pronto" && !jaFaturado(os.id) ? (
          <Pressable
            onPress={() => setFaturarOpen(true)}
            style={({ pressed }) => ({
              flex: 1,
              height: 48,
              borderRadius: radius.md,
              alignItems: "center",
              justifyContent: "center",
              flexDirection: "row",
              gap: 8,
              backgroundColor: palette.action,
              opacity: pressed ? 0.85 : 1,
            })}
          >
            <OiIcon name="dollar" size={18} color={palette.actionFg} />
            <Text style={{ color: palette.actionFg, fontFamily: fonts.sansSemibold, fontSize: 14 }}>
              Faturar
            </Text>
          </Pressable>
        ) : null}
        <Pressable
          onPress={onAdvance}
          style={({ pressed }) => ({
            flex: 1,
            height: 48,
            borderRadius: radius.md,
            alignItems: "center",
            justifyContent: "center",
            flexDirection: "row",
            gap: 8,
            backgroundColor: os.status === "Pronto" ? palette.ok : palette.accent,
            opacity: pressed ? 0.85 : 1,
          })}
        >
          <OiIcon name="check" size={18} color="#fff" />
          <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold, fontSize: 14 }}>
            {os.status === "Pronto" ? "Entregar veículo" : `Avançar para ${proxima ?? "Pronto"}`}
          </Text>
        </Pressable>
      </View>

      {/* Sheets */}
      <LocalSheet
        visible={sheet === "local"}
        onClose={() => setSheet(null)}
        currentId={os.localId}
        onPick={(novoId) => {
          setLocal(os.id, novoId);
          setSheet(null);
          toast.show("Local atualizado", "ok");
        }}
      />
      <MecSheet
        visible={sheet === "mec"}
        onClose={() => setSheet(null)}
        currentId={os.mecanicoId}
        onPick={(novoId) => {
          setMecanico(os.id, novoId);
          setSheet(null);
          toast.show("Mecânico atribuído", "ok");
        }}
      />
      <AddItemSheet
        visible={sheet === "item"}
        onClose={() => setSheet(null)}
        catalogo={catalogo}
        onPick={(item) => {
          addOsItem(os.id, {
            kind: item.kind,
            nome: item.nome,
            qtd: 1,
            preco: item.preco,
            status: item.kind === "peca" && (item.estoque ?? 0) === 0 ? "comprar" : "aguardando",
          });
          setSheet(null);
          toast.show("Item adicionado · aguardando aprovação", "ok");
        }}
      />
      <OiFaturarSheet
        visible={faturarOpen}
        onClose={() => setFaturarOpen(false)}
        origemId={os.id}
        parte={os.cliente}
        desc={`OS ${os.id} · ${os.veiculo.marca} ${os.veiculo.modelo}`}
        valorCents={Math.round(tot.total * 100)}
        categoria="Serviços de oficina"
      />
    </OiScreen>
  );
}

// ─── Pill (etiqueta colorida pequena) ──────────────────────────────────────
function Pill({
  label,
  tone,
}: {
  label: string;
  tone: "ok" | "warn" | "danger" | "accent" | "neutral";
}) {
  const { palette } = useOiTheme();
  const color =
    tone === "ok"
      ? palette.ok
      : tone === "warn"
        ? palette.warn
        : tone === "danger"
          ? palette.danger
          : tone === "accent"
            ? palette.accent
            : palette.textDim;
  return (
    <View
      style={{
        paddingHorizontal: 8,
        paddingVertical: 3,
        borderRadius: radius.pill,
        backgroundColor: hexAlpha(color, 0.16),
      }}
    >
      <Text style={{ color, fontFamily: fonts.sansSemibold, fontSize: 11, textTransform: "capitalize" }}>
        {label}
      </Text>
    </View>
  );
}

// ─── ItemRow ───────────────────────────────────────────────────────────────
function ItemRow({
  item,
  showDivider,
  onAdvance,
}: {
  item: OsItem;
  showDivider: boolean;
  onAdvance: (novo: OsItemStatus) => void;
}) {
  const { palette } = useOiTheme();
  const fade = useState(() => new Animated.Value(1))[0];

  const onPress = () => {
    let novo: OsItemStatus | null = null;
    if (item.status === "aguardando") novo = "aprovado";
    else if (item.status === "aprovado") novo = "aplicado";
    else if (item.status === "comprar") novo = "aprovado";
    if (!novo) return;
    Animated.sequence([
      Animated.timing(fade, { toValue: 0.3, duration: 120, useNativeDriver: true }),
      Animated.timing(fade, { toValue: 1, duration: 200, useNativeDriver: true }),
    ]).start();
    onAdvance(novo);
  };

  const tone =
    item.status === "aplicado"
      ? palette.ok
      : item.status === "aprovado"
        ? palette.accent
        : item.status === "comprar"
          ? palette.danger
          : palette.warn;
  const ctaLabel =
    item.status === "aguardando"
      ? "Aprovar"
      : item.status === "aprovado"
        ? "Aplicar"
        : item.status === "comprar"
          ? "Receber"
          : "—";

  return (
    <Animated.View
      style={{
        opacity: fade,
        flexDirection: "row",
        alignItems: "center",
        gap: 10,
        paddingHorizontal: 12,
        paddingVertical: 10,
        borderTopWidth: showDivider ? 1 : 0,
        borderTopColor: palette.border,
      }}
    >
      <View
        style={{
          width: 28,
          height: 28,
          borderRadius: 8,
          backgroundColor: hexAlpha(tone, 0.14),
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <OiIcon name={item.kind === "peca" ? "box" : "settings"} size={14} color={tone} />
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={{ fontSize: 13, color: palette.text, fontFamily: fonts.sansSemibold }} numberOfLines={1}>
          {item.nome}
        </Text>
        <Text style={{ fontFamily: fonts.mono, fontSize: 11, color: palette.textMute }}>
          {item.qtd}x · R${" "}
          {item.preco.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
        </Text>
      </View>
      <Pill label={item.status} tone={item.status === "aplicado" ? "ok" : item.status === "aprovado" ? "accent" : item.status === "comprar" ? "danger" : "warn"} />
      {item.status !== "aplicado" ? (
        <Pressable
          onPress={onPress}
          style={{
            paddingHorizontal: 10,
            paddingVertical: 5,
            borderRadius: radius.sm,
            backgroundColor: tone,
          }}
        >
          <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold, fontSize: 11 }}>{ctaLabel}</Text>
        </Pressable>
      ) : null}
    </Animated.View>
  );
}

// ─── Sheets ────────────────────────────────────────────────────────────────

function LocalSheet({
  visible,
  onClose,
  currentId,
  onPick,
}: {
  visible: boolean;
  onClose: () => void;
  currentId: string;
  onPick: (id: string) => void;
}) {
  const { palette } = useOiTheme();
  const { locais, oss } = useManutencao();
  return (
    <OiSheet visible={visible} onClose={onClose} title="Trocar de local">
      <View style={{ gap: 6 }}>
        {locais.map((l) => {
          const on = l.id === currentId;
          const ocupados = oss.filter((o) => o.localId === l.id).length;
          const cheio = !on && ocupados >= l.capacidade;
          return (
            <Pressable
              key={l.id}
              onPress={() => !cheio && onPick(l.id)}
              disabled={cheio}
              style={({ pressed }) => ({
                padding: 12,
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: on ? palette.accent : palette.border,
                backgroundColor: pressed ? palette.bg2 : palette.surface,
                opacity: cheio ? 0.4 : 1,
              })}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Text style={{ flex: 1, fontFamily: fonts.sansSemibold, fontSize: 13.5, color: palette.text }}>
                  {l.nome}
                </Text>
                <Text style={{ fontFamily: fonts.mono, fontSize: 11.5, color: cheio ? palette.danger : palette.textMute }}>
                  {ocupados}/{l.capacidade}
                </Text>
              </View>
              <Text style={{ fontSize: 11, color: palette.textMute, marginTop: 2 }}>
                {l.tipo}
                {l.observacao ? ` · ${l.observacao}` : ""}
              </Text>
            </Pressable>
          );
        })}
      </View>
    </OiSheet>
  );
}

function MecSheet({
  visible,
  onClose,
  currentId,
  onPick,
}: {
  visible: boolean;
  onClose: () => void;
  currentId: string | null;
  onPick: (id: string | null) => void;
}) {
  const { palette } = useOiTheme();
  const { mecanicos } = useManutencao();
  return (
    <OiSheet visible={visible} onClose={onClose} title="Atribuir mecânico">
      <View style={{ gap: 6 }}>
        <Pressable
          onPress={() => onPick(null)}
          style={({ pressed }) => ({
            padding: 12,
            borderRadius: radius.md,
            borderWidth: 1,
            borderColor: currentId === null ? palette.accent : palette.border,
            backgroundColor: pressed ? palette.bg2 : palette.surface,
          })}
        >
          <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13.5, color: palette.text }}>Sem atribuição</Text>
          <Text style={{ fontSize: 11, color: palette.textMute, marginTop: 2 }}>Voltar pro pool</Text>
        </Pressable>
        {mecanicos.map((m) => {
          const on = m.id === currentId;
          return (
            <Pressable
              key={m.id}
              onPress={() => onPick(m.id)}
              style={({ pressed }) => ({
                padding: 12,
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: on ? palette.accent : palette.border,
                backgroundColor: pressed ? palette.bg2 : palette.surface,
              })}
            >
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <Text style={{ flex: 1, fontFamily: fonts.sansSemibold, fontSize: 13.5, color: palette.text }}>
                  {m.nome}
                </Text>
                <Pill label={m.disponivel ? "Livre" : `${m.ativasIds.length} OS`} tone={m.disponivel ? "ok" : "warn"} />
              </View>
              <Text style={{ fontSize: 11, color: palette.textMute, marginTop: 2 }}>{m.especialidade}</Text>
            </Pressable>
          );
        })}
      </View>
    </OiSheet>
  );
}

function AddItemSheet({
  visible,
  onClose,
  catalogo,
  onPick,
}: {
  visible: boolean;
  onClose: () => void;
  catalogo: CatalogoItem[];
  onPick: (item: CatalogoItem) => void;
}) {
  const { palette } = useOiTheme();
  const [tab, setTab] = useState<"peca" | "servico">("peca");
  const [q, setQ] = useState("");
  const filtered = useMemo(
    () =>
      catalogo
        .filter((c) => c.kind === tab)
        .filter((c) => {
          const term = q.trim().toLowerCase();
          return !term || c.nome.toLowerCase().includes(term);
        }),
    [catalogo, tab, q],
  );
  return (
    <OiSheet visible={visible} onClose={onClose} title="Adicionar item">
      <View style={{ gap: 10 }}>
        <View
          style={{
            flexDirection: "row",
            backgroundColor: palette.bg2,
            borderRadius: radius.md,
            padding: 3,
          }}
        >
          {(["peca", "servico"] as const).map((t) => {
            const on = tab === t;
            return (
              <Pressable
                key={t}
                onPress={() => setTab(t)}
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
                    fontSize: 12.5,
                    color: on ? palette.text : palette.textMute,
                  }}
                >
                  {t === "peca" ? "Peças" : "Serviços"}
                </Text>
              </Pressable>
            );
          })}
        </View>
        <TextInput
          value={q}
          onChangeText={setQ}
          placeholder={`Buscar ${tab === "peca" ? "peça" : "serviço"}…`}
          placeholderTextColor={palette.textMute}
          style={{
            height: 40,
            paddingHorizontal: 12,
            borderRadius: radius.md,
            borderWidth: 1,
            borderColor: palette.border,
            backgroundColor: palette.bg2,
            color: palette.text,
            fontFamily: fonts.sans,
            fontSize: 13,
          }}
        />
        <View style={{ gap: 6, maxHeight: 320 }}>
          {filtered.map((c) => (
            <Pressable
              key={c.id}
              onPress={() => onPick(c)}
              style={({ pressed }) => ({
                flexDirection: "row",
                alignItems: "center",
                gap: 10,
                padding: 10,
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: palette.border,
                backgroundColor: pressed ? palette.bg2 : palette.surface,
              })}
            >
              <View style={{ flex: 1 }}>
                <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13, color: palette.text }} numberOfLines={1}>
                  {c.nome}
                </Text>
                {c.estoque !== undefined ? (
                  <Text style={{ fontSize: 11, color: (c.estoque ?? 0) === 0 ? palette.danger : palette.textMute }}>
                    Estoque: {c.estoque}
                  </Text>
                ) : null}
              </View>
              <OiMoney value={c.preco} size={13} weight="semibold" />
              <OiIcon name="plus" size={16} color={palette.accent} />
            </Pressable>
          ))}
        </View>
      </View>
    </OiSheet>
  );
}
