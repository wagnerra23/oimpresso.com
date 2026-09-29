/**
 * Tarefa — detalhe (Caixa de entrada operacional → ação específica).
 *
 * Espelha `TarefaDetalheScreen` do design v3 (`screens-home-tasks.jsx`):
 *  - Header com id da tarefa + eyebrow (origem · cliente)
 *  - Hero card com OriginBadge lg + Urgente pill + prazo mono
 *  - TaskViewer por origem (OsAprovarArte / FinBoleto / CrmContato implementados)
 *  - Histórico mock
 *  - Bottom bar: Adiar (ghost/default) + Concluir (primary block)
 *
 * Como o app não tem entidade `tarefas`, agregamos via `useTarefas()` em
 * `lib/use-tarefas.ts`. O id chega no formato `${origem}-${entityId}`.
 */
import { useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, View } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";

import {
  OiBtn,
  OiBtnRow,
  OiCard,
  OiDetailHeader,
  OiDl,
  OiDlRow,
  OiIcon,
  OiMoney,
  OiOrigin,
  OiScreen,
  OiSection,
  OiStatus,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useTarefas, type Tarefa } from "@/lib/use-tarefas";
import { useToast } from "@/components/oi";
import { TextInput } from "react-native";

const HISTORICO_MOCK = [
  { t: "12min", who: "Cliente", text: "Enviou nova versão da arte (v3)" },
  { t: "1h", who: "Você", text: "Solicitou ajuste no telefone" },
  { t: "Ontem", who: "Sistema", text: "OS criada via orçamento aprovado" },
];

export default function TarefaDetalheScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const params = useLocalSearchParams<{ id: string }>();
  const id = params.id ?? "";

  const { isLoading, byId } = useTarefas();
  const t = byId(id);

  if (isLoading) {
    return (
      <OiScreen edges={["top"]}>
        <Stack.Screen options={{ headerShown: false }} />
        <OiDetailHeader title="Carregando..." onBack={() => router.back()} />
        <View style={{ paddingVertical: 48, alignItems: "center" }}>
          <ActivityIndicator color={palette.accent} />
        </View>
      </OiScreen>
    );
  }

  if (!t) {
    return (
      <OiScreen edges={["top"]}>
        <Stack.Screen options={{ headerShown: false }} />
        <OiDetailHeader title="Tarefa não encontrada" onBack={() => router.back()} />
        <View style={{ padding: 24, alignItems: "center", gap: 8 }}>
          <OiIcon name="alert" size={32} color={palette.textMute} />
          <Text style={{ color: palette.textMute, fontFamily: fonts.sans }}>
            A tarefa pode ter sido concluída.
          </Text>
        </View>
      </OiScreen>
    );
  }

  const shortId = id.length > 12 ? id.slice(0, 12).toUpperCase() : id.toUpperCase();
  const eyebrow = `${t.origem} · ${t.cliente ?? "—"}`;

  return (
    <OiScreen edges={["top"]}>
      <Stack.Screen options={{ headerShown: false }} />
      <OiDetailHeader
        title={shortId}
        eyebrow={eyebrow}
        onBack={() => router.back()}
        right={
          <Pressable
            hitSlop={6}
            style={{
              width: 38,
              height: 38,
              alignItems: "center",
              justifyContent: "center",
              borderRadius: radius.sm,
            }}
          >
            <OiIcon name="dots-v" size={20} color={palette.textDim} />
          </Pressable>
        }
      />

      <ScrollView
        contentContainerStyle={{ paddingBottom: 24 }}
        showsVerticalScrollIndicator={false}
      >
        {/* Hero */}
        <OiSection>
          <OiCard variant="pad">
            <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
              <OiOrigin kind={t.origem} size="lg" />
              {t.urgente ? <OiStatus variant="danger" label="Urgente" /> : null}
              <View style={{ flex: 1 }} />
              <Text
                style={{
                  fontFamily: fonts.mono,
                  fontSize: 12,
                  color: palette.textMute,
                }}
              >
                {t.when}
              </Text>
            </View>
            <Text
              style={{
                marginTop: 10,
                fontFamily: fonts.sansSemibold,
                fontSize: 18,
                letterSpacing: -0.2,
                color: palette.text,
                lineHeight: 24,
              }}
            >
              {t.title}
            </Text>
            <Text
              style={{
                marginTop: 4,
                fontFamily: fonts.sans,
                fontSize: 13,
                color: palette.textDim,
                lineHeight: 19,
              }}
            >
              {t.subtitle}
            </Text>
          </OiCard>
        </OiSection>

        {/* Viewer */}
        <TaskViewer task={t} />

        {/* Histórico */}
        <OiSection title="Histórico">
          <OiCard style={{ padding: 0, gap: 0 }}>
            {HISTORICO_MOCK.map((h, i) => (
              <View
                key={i}
                style={{
                  flexDirection: "row",
                  gap: 10,
                  paddingHorizontal: 14,
                  paddingVertical: 10,
                  borderBottomColor: palette.border2,
                  borderBottomWidth: i < HISTORICO_MOCK.length - 1 ? 1 : 0,
                }}
              >
                <Text
                  style={{
                    minWidth: 48,
                    fontFamily: fonts.mono,
                    fontSize: 12,
                    color: palette.textMute,
                  }}
                >
                  {h.t}
                </Text>
                <Text style={{ flex: 1, fontSize: 12.5, color: palette.textDim }}>
                  <Text style={{ fontFamily: fonts.sansSemibold, color: palette.text }}>
                    {h.who}:
                  </Text>{" "}
                  {h.text}
                </Text>
              </View>
            ))}
          </OiCard>
        </OiSection>

        <View style={{ height: 16 }} />
      </ScrollView>

      {/* Bottom action bar */}
      <View
        style={{
          paddingHorizontal: 12,
          paddingVertical: 10,
          backgroundColor: palette.surface,
          borderTopColor: palette.border,
          borderTopWidth: 1,
          flexDirection: "row",
          gap: 8,
        }}
      >
        <OiBtn
          label="Adiar"
          leftIcon="clock"
          onPress={() => router.back()}
        />
        <View style={{ flex: 1 }}>
          <OiBtn
            label="Concluir"
            variant="primary"
            leftIcon="check"
            block
            onPress={() => router.back()}
          />
        </View>
      </View>
    </OiScreen>
  );
}

// ─── Viewers ────────────────────────────────────────────────────────────────

function TaskViewer({ task }: { task: Tarefa }) {
  switch (task.viewer) {
    case "OsAprovarArte":
      return <OsAprovarArte t={task} />;
    case "FinBoleto":
      return <FinBoleto t={task} />;
    case "CrmContato":
      return <CrmContato t={task} />;
    case "MfgLiberar":
      return <MfgLiberar t={task} />;
    case "OsEntrega":
      return <OsEntrega t={task} />;
    case "PntJustificar":
      return <PntJustificar t={task} />;
    case "CrmOrcamento":
      return <CrmOrcamento t={task} />;
    case "FinConciliar":
      return <FinConciliar t={task} />;
    default:
      return <GenericPlaceholder t={task} />;
  }
}

function VwCard({
  title,
  children,
  pad = true,
}: {
  title?: string;
  children: React.ReactNode;
  pad?: boolean;
}) {
  return (
    <OiSection title={title}>
      <OiCard variant={pad ? "pad" : "default"}>{children}</OiCard>
    </OiSection>
  );
}

function OsAprovarArte({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  const [decision, setDecision] = useState<string>("");
  const opts = [
    { v: "ok", l: "Aprovar e liberar produção", ic: "check-circle" as const },
    { v: "ajuste", l: "Pedir ajuste ao cliente", ic: "edit" as const },
    { v: "reprovar", l: "Reprovar arte", ic: "x" as const },
  ];

  return (
    <>
      <VwCard title="Arte para aprovação">
        <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
          <View
            style={{
              width: 56,
              height: 56,
              borderRadius: 10,
              backgroundColor: palette.bg2,
              borderColor: palette.border,
              borderWidth: 1,
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <OiIcon name="image" size={26} color={palette.textMute} />
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text
              style={{
                fontFamily: fonts.sansSemibold,
                fontSize: 14,
                color: palette.text,
              }}
              numberOfLines={1}
            >
              arte_{t.refId.slice(0, 6)}_v3.pdf
            </Text>
            <Text
              style={{
                fontFamily: fonts.mono,
                fontSize: 11,
                color: palette.textMute,
                marginTop: 2,
              }}
            >
              v3 · 2.4 MB · CMYK 300dpi
            </Text>
          </View>
          <OiBtn size="sm" label="Ver" leftIcon="eye" />
        </View>
      </VwCard>

      <VwCard title="Detalhes da OS">
        <OiDl>
          <OiDlRow label="OS">
            <Text style={{ fontFamily: fonts.mono, fontSize: 13, color: palette.text }}>
              {t.refId.slice(0, 8).toUpperCase()}
            </Text>
          </OiDlRow>
          <OiDlRow label="Cliente">{t.cliente ?? "—"}</OiDlRow>
          {t.valor !== undefined ? (
            <OiDlRow label="Valor">
              <OiMoney value={t.valor} size={13} weight="semibold" />
            </OiDlRow>
          ) : null}
          {t.prazo ? (
            <OiDlRow label="Prazo">
              <Text
                style={{
                  fontFamily: fonts.sansSemibold,
                  fontSize: 13,
                  color: t.urgente ? palette.danger : palette.text,
                }}
              >
                {t.prazo}
              </Text>
            </OiDlRow>
          ) : null}
        </OiDl>
      </VwCard>

      <VwCard title="Decisão">
        <View style={{ gap: 6 }}>
          {opts.map((o) => {
            const active = decision === o.v;
            return (
              <Pressable
                key={o.v}
                onPress={() => setDecision(o.v)}
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 10,
                  paddingHorizontal: 12,
                  height: 44,
                  borderRadius: radius.md,
                  borderWidth: 1,
                  borderColor: active ? palette.accent : palette.border,
                  backgroundColor: active ? palette.accentSoft : palette.surface,
                }}
              >
                <OiIcon
                  name={o.ic}
                  size={18}
                  color={active ? palette.accent : palette.text}
                />
                <Text
                  style={{
                    fontFamily: fonts.sansMedium,
                    fontSize: 13.5,
                    color: active ? palette.accent : palette.text,
                  }}
                >
                  {o.l}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </VwCard>
    </>
  );
}

function FinBoleto({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  return (
    <>
      <VwCard>
        <View style={{ alignItems: "center", paddingVertical: 8 }}>
          <Text
            style={{
              fontFamily: fonts.sansBold,
              fontSize: 11,
              letterSpacing: 1,
              textTransform: "uppercase",
              color: palette.textMute,
            }}
          >
            {t.urgente ? "Boleto vencido" : "Boleto pendente"}
          </Text>
          <OiMoney
            value={t.valor ?? 0}
            size={34}
            weight="semibold"
            color={palette.danger}
            style={{ marginTop: 4 }}
          />
        </View>
        <OiDl>
          <OiDlRow label="Sacado">{t.cliente ?? "—"}</OiDlRow>
          {t.prazo ? (
            <OiDlRow label="Vencimento">
              <Text
                style={{
                  fontFamily: fonts.sansSemibold,
                  fontSize: 13,
                  color: palette.danger,
                }}
              >
                {t.prazo}
              </Text>
            </OiDlRow>
          ) : null}
          <OiDlRow label="Linha">
            <Text
              style={{
                fontFamily: fonts.mono,
                fontSize: 11,
                color: palette.text,
              }}
              numberOfLines={2}
            >
              34191.79001 01043.510047 91020.150008 1 91120000024800
            </Text>
          </OiDlRow>
        </OiDl>
      </VwCard>

      <VwCard title="Ações">
        <OiBtn
          label="Reenviar via WhatsApp"
          variant="primary"
          leftIcon="whatsapp"
          block
        />
        <View style={{ height: 8 }} />
        <OiBtnRow>
          <OiBtn label="PIX" leftIcon="qr" />
          <OiBtn label="Prorrogar" leftIcon="calendar" />
        </OiBtnRow>
      </VwCard>
    </>
  );
}

function CrmContato({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  const initials = (t.cliente ?? "?")
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? "")
    .join("");
  return (
    <>
      <VwCard>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
          <View
            style={{
              width: 48,
              height: 48,
              borderRadius: 24,
              backgroundColor: palette.accentSoft,
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <Text
              style={{
                fontFamily: fonts.sansSemibold,
                fontSize: 16,
                color: palette.accent,
              }}
            >
              {initials}
            </Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text
              style={{
                fontSize: 16,
                fontFamily: fonts.sansSemibold,
                color: palette.text,
              }}
            >
              {t.cliente ?? "Cliente"}
            </Text>
            <Text
              style={{
                fontSize: 11,
                color: palette.textMute,
                marginTop: 2,
              }}
            >
              Último contato há 4 dias
            </Text>
          </View>
        </View>
      </VwCard>
      <VwCard title="Como contatar">
        <OiBtnRow>
          <OiBtn label="WhatsApp" leftIcon="whatsapp" variant="primary" />
          <OiBtn label="Ligar" leftIcon="phone" />
        </OiBtnRow>
      </VwCard>
    </>
  );
}

// ── MfgLiberar ───────────────────────────────────────────────────────────────
function MfgLiberar({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  const stations = [
    { name: "Impressão", load: 78 },
    { name: "Corte", load: 45 },
    { name: "Acabamento", load: 90 },
  ];
  return (
    <>
      <VwCard title="Resumo da OS">
        <OiDl>
          <OiDlRow label="OS">
            <Text style={{ fontFamily: fonts.mono, fontSize: 13, color: palette.text }}>
              {t.refId.slice(0, 8).toUpperCase()}
            </Text>
          </OiDlRow>
          <OiDlRow label="Cliente">{t.cliente ?? "—"}</OiDlRow>
          {t.valor !== undefined ? (
            <OiDlRow label="Valor">
              <OiMoney value={t.valor} size={13} weight="semibold" />
            </OiDlRow>
          ) : null}
        </OiDl>
      </VwCard>
      <VwCard title="Carga das estações">
        <View style={{ gap: 10 }}>
          {stations.map((s) => (
            <View key={s.name}>
              <View
                style={{
                  flexDirection: "row",
                  justifyContent: "space-between",
                  marginBottom: 4,
                }}
              >
                <Text style={{ fontSize: 12.5, color: palette.text, fontFamily: fonts.sansSemibold }}>
                  {s.name}
                </Text>
                <Text style={{ fontFamily: fonts.mono, fontSize: 11.5, color: palette.textMute }}>
                  {s.load}%
                </Text>
              </View>
              <View style={{ height: 6, borderRadius: 999, backgroundColor: palette.bg2, overflow: "hidden" }}>
                <View
                  style={{
                    width: `${s.load}%`,
                    height: "100%",
                    backgroundColor:
                      s.load >= 85 ? palette.danger : s.load >= 60 ? palette.warn : palette.ok,
                  }}
                />
              </View>
            </View>
          ))}
        </View>
      </VwCard>
    </>
  );
}

// ── OsEntrega ────────────────────────────────────────────────────────────────
function OsEntrega({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  const toast = useToast();
  return (
    <>
      <VwCard title="Entrega">
        <OiDl>
          <OiDlRow label="Cliente">{t.cliente ?? "—"}</OiDlRow>
          <OiDlRow label="Endereço">
            <Text style={{ fontSize: 13, color: palette.text }}>
              Rua das Flores, 123 · Centro · 13560-000
            </Text>
          </OiDlRow>
          <OiDlRow label="Distância">
            <Text style={{ fontFamily: fonts.mono, fontSize: 12.5, color: palette.text }}>
              4,2 km · ~14 min
            </Text>
          </OiDlRow>
          {t.prazo ? (
            <OiDlRow label="Janela">
              <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13, color: palette.text }}>
                {t.prazo}
              </Text>
            </OiDlRow>
          ) : null}
        </OiDl>
      </VwCard>
      <VwCard title="Ações">
        <OiBtnRow>
          <OiBtn
            label="Abrir no mapa"
            variant="primary"
            leftIcon="location"
            onPress={() => toast.show("Abrindo mapa...", "default")}
          />
          <OiBtn label="WhatsApp" leftIcon="whatsapp" onPress={() => toast.show("Cliente avisado", "ok")} />
        </OiBtnRow>
      </VwCard>
    </>
  );
}

// ── PntJustificar ────────────────────────────────────────────────────────────
function PntJustificar({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  const [motivo, setMotivo] = useState<string | null>(null);
  const [obs, setObs] = useState("");
  const toast = useToast();
  const punches = [
    { label: "Entrada", hora: "08:12", ok: true },
    { label: "Saída almoço", hora: "12:03", ok: true },
    { label: "Volta almoço", hora: "—", ok: false },
    { label: "Saída", hora: "—", ok: false },
  ];
  const motivos = ["Atestado", "Trânsito", "Pessoal", "Outro"];
  return (
    <>
      <VwCard title="Marcações de hoje">
        <View style={{ gap: 6 }}>
          {punches.map((p) => (
            <View
              key={p.label}
              style={{
                flexDirection: "row",
                justifyContent: "space-between",
                alignItems: "center",
              }}
            >
              <Text style={{ fontSize: 13, color: palette.text }}>{p.label}</Text>
              <Text
                style={{
                  fontFamily: fonts.mono,
                  fontSize: 13,
                  color: p.ok ? palette.ok : palette.danger,
                }}
              >
                {p.hora}
              </Text>
            </View>
          ))}
        </View>
      </VwCard>
      <VwCard title="Motivo">
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
          {motivos.map((m) => {
            const on = motivo === m;
            return (
              <Pressable
                key={m}
                onPress={() => setMotivo(m)}
                style={{
                  paddingHorizontal: 12,
                  paddingVertical: 6,
                  borderRadius: radius.pill,
                  borderWidth: 1,
                  borderColor: on ? palette.accent : palette.border,
                  backgroundColor: on ? palette.accentSoft : "transparent",
                }}
              >
                <Text
                  style={{
                    fontSize: 12.5,
                    color: on ? palette.accent : palette.text,
                    fontFamily: fonts.sansSemibold,
                  }}
                >
                  {m}
                </Text>
              </Pressable>
            );
          })}
        </View>
        <TextInput
          value={obs}
          onChangeText={setObs}
          multiline
          numberOfLines={3}
          placeholder="Observação (opcional)…"
          placeholderTextColor={palette.textMute}
          style={{
            marginTop: 10,
            minHeight: 80,
            padding: 12,
            borderRadius: radius.md,
            borderWidth: 1,
            borderColor: palette.border,
            backgroundColor: palette.bg2,
            color: palette.text,
            fontFamily: fonts.sans,
            fontSize: 13,
            textAlignVertical: "top",
          }}
        />
      </VwCard>
      <VwCard>
        <OiBtn
          label="Enviar justificativa"
          variant="primary"
          leftIcon="send"
          block
          disabled={!motivo}
          onPress={() => toast.show("Justificativa enviada ao RH", "ok")}
        />
      </VwCard>
    </>
  );
}

// ── CrmOrcamento ─────────────────────────────────────────────────────────────
function CrmOrcamento({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  const toast = useToast();
  const modelos = [
    { id: "cv1", label: "Cartão visita 9x5 · 1000un", preco: 89 },
    { id: "fl1", label: "Flyer A5 4x4 · 500un", preco: 240 },
    { id: "ad1", label: "Adesivo recorte · 100un", preco: 180 },
  ];
  return (
    <>
      <VwCard title="Pedido do cliente">
        <Text style={{ fontSize: 13.5, color: palette.text, lineHeight: 19 }}>
          {t.subtitle}
        </Text>
        <View style={{ marginTop: 10 }}>
          <OiDl>
            <OiDlRow label="Cliente">{t.cliente ?? "—"}</OiDlRow>
            <OiDlRow label="Recebido em">{t.when}</OiDlRow>
          </OiDl>
        </View>
      </VwCard>
      <VwCard title="Modelos rápidos">
        <View style={{ gap: 6 }}>
          {modelos.map((m) => (
            <Pressable
              key={m.id}
              onPress={() => toast.show(`+ ${m.label}`, "ok")}
              style={({ pressed }) => ({
                flexDirection: "row",
                alignItems: "center",
                gap: 10,
                paddingHorizontal: 12,
                paddingVertical: 10,
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: palette.border,
                backgroundColor: pressed ? palette.bg2 : palette.surface,
              })}
            >
              <View style={{ flex: 1 }}>
                <Text style={{ fontSize: 13, color: palette.text, fontFamily: fonts.sansSemibold }}>
                  {m.label}
                </Text>
              </View>
              <OiMoney value={m.preco} size={13} weight="semibold" />
              <OiIcon name="plus" size={16} color={palette.accent} />
            </Pressable>
          ))}
        </View>
      </VwCard>
    </>
  );
}

// ── FinConciliar ─────────────────────────────────────────────────────────────
function FinConciliar({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  const toast = useToast();
  const sugestoes = [
    { id: "s1", label: "Pedido #1042 · Lucas R.", valor: t.valor ?? 240 },
    { id: "s2", label: "Pedido #1037 · Lucas Ribeiro", valor: t.valor ?? 240 },
  ];
  return (
    <>
      <VwCard title="PIX recebido">
        <View style={{ alignItems: "center", paddingVertical: 8 }}>
          <OiMoney
            value={t.valor ?? 0}
            size={32}
            weight="semibold"
            color={palette.ok}
          />
          <Text
            style={{
              marginTop: 4,
              fontFamily: fonts.sansMedium,
              fontSize: 12,
              color: palette.textMute,
            }}
          >
            Sem cliente vinculado · {t.when}
          </Text>
        </View>
      </VwCard>
      <VwCard title="Possíveis vínculos">
        <View style={{ gap: 6 }}>
          {sugestoes.map((s) => (
            <View
              key={s.id}
              style={{
                flexDirection: "row",
                alignItems: "center",
                paddingHorizontal: 12,
                paddingVertical: 10,
                borderRadius: radius.md,
                borderWidth: 1,
                borderColor: palette.border,
              }}
            >
              <View style={{ flex: 1 }}>
                <Text style={{ fontSize: 13, color: palette.text, fontFamily: fonts.sansSemibold }}>
                  {s.label}
                </Text>
                <OiMoney value={s.valor} size={11.5} color={palette.textMute} />
              </View>
              <OiBtn
                size="sm"
                label="Vincular"
                variant="primary"
                onPress={() => toast.show("PIX vinculado ao pedido", "ok")}
              />
            </View>
          ))}
        </View>
      </VwCard>
    </>
  );
}

function GenericPlaceholder({ t }: { t: Tarefa }) {
  const { palette } = useOiTheme();
  return (
    <OiSection>
      <OiCard variant="pad" style={{ alignItems: "center", paddingVertical: 28 }}>
        <View
          style={{
            width: 48,
            height: 48,
            borderRadius: 24,
            backgroundColor: hexAlpha(palette.textMute, 0.15),
            alignItems: "center",
            justifyContent: "center",
          }}
        >
          <OiIcon name="more" size={26} color={palette.textMute} />
        </View>
        <Text
          style={{
            marginTop: 8,
            fontFamily: fonts.sansMedium,
            fontSize: 13,
            color: palette.textDim,
            textAlign: "center",
          }}
        >
          Visualizador para {t.origem} ainda não implementado.
        </Text>
        <Text
          style={{
            marginTop: 4,
            fontFamily: fonts.sans,
            fontSize: 12,
            color: palette.textMute,
            textAlign: "center",
          }}
        >
          Use Adiar ou Concluir abaixo.
        </Text>
      </OiCard>
    </OiSection>
  );
}
