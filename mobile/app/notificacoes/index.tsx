/**
 * NotificacoesScreen — lista de notificações (Bloco 10).
 *
 * Lista por origem (OS, FIN, CRM, MFG, MAN), não-lidas destacadas, botão
 * "Marcar todas como lidas". Estado local — substitui por tabela `notifications`
 * quando o backend ficar pronto (item 11.9).
 */
import { useMemo, useState } from "react";
import { Pressable, ScrollView, Text, View } from "react-native";
import { useRouter } from "expo-router";

import {
  OiDetailHeader,
  OiIcon,
  OiOrigin,
  OiScreen,
  OiSection,
  useToast,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { formatTimestamp } from "@/lib/format";

type NotifOrigin = "OS" | "FIN" | "CRM" | "MFG" | "PNT";
type Notif = {
  id: string;
  origin: NotifOrigin;
  title: string;
  body: string;
  read: boolean;
  at: string;
};

const SEED: Notif[] = [
  {
    id: "n1",
    origin: "OS",
    title: "Cliente aprovou arte",
    body: "OS-1041 · Transportes Lima · arte v3 aprovada",
    read: false,
    at: new Date(Date.now() - 12 * 60_000).toISOString(),
  },
  {
    id: "n2",
    origin: "FIN",
    title: "Boleto vencido",
    body: "Distribuidora Sul · R$ 2.480,00",
    read: false,
    at: new Date(Date.now() - 60 * 60_000).toISOString(),
  },
  {
    id: "n3",
    origin: "MFG",
    title: "OP-301 saiu da fila",
    body: "Operador André iniciou impressão",
    read: false,
    at: new Date(Date.now() - 3 * 3600_000).toISOString(),
  },
  {
    id: "n4",
    origin: "CRM",
    title: "Pedro Alves curtiu seu orçamento",
    body: "Aguardando aprovação final",
    read: true,
    at: new Date(Date.now() - 24 * 3600_000).toISOString(),
  },
  {
    id: "n5",
    origin: "PNT",
    title: "Justifique sua marcação",
    body: "Hoje · falta volta-almoço",
    read: true,
    at: new Date(Date.now() - 36 * 3600_000).toISOString(),
  },
];

export default function NotificacoesScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const toast = useToast();
  const [items, setItems] = useState<Notif[]>(SEED);

  const unread = useMemo(() => items.filter((n) => !n.read).length, [items]);

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader
        title="Notificações"
        eyebrow={unread > 0 ? `${unread} não lidas` : "Em dia"}
        onBack={() => router.back()}
        right={
          unread > 0 ? (
            <Pressable
              onPress={() => {
                setItems((curr) => curr.map((n) => ({ ...n, read: true })));
                toast.show("Todas marcadas como lidas", "ok");
              }}
              hitSlop={6}
              style={{
                paddingHorizontal: 10,
                paddingVertical: 6,
                borderRadius: radius.sm,
                borderWidth: 1,
                borderColor: palette.border,
              }}
            >
              <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 11, color: palette.text }}>
                Marcar lidas
              </Text>
            </Pressable>
          ) : null
        }
      />
      <ScrollView contentContainerStyle={{ paddingBottom: 32 }}>
        <OiSection>
          {items.length === 0 ? (
            <View style={{ padding: 24, alignItems: "center" }}>
              <Text style={{ color: palette.textMute }}>Sem notificações.</Text>
            </View>
          ) : (
            <View style={{ gap: 8 }}>
              {items.map((n) => (
                <Pressable
                  key={n.id}
                  onPress={() => {
                    setItems((curr) =>
                      curr.map((x) => (x.id === n.id ? { ...x, read: true } : x)),
                    );
                  }}
                  style={({ pressed }) => ({
                    flexDirection: "row",
                    gap: 10,
                    padding: 12,
                    borderRadius: radius.md,
                    borderWidth: 1,
                    borderColor: n.read ? palette.border : palette.accent,
                    backgroundColor: n.read
                      ? palette.surface
                      : pressed
                        ? palette.bg2
                        : hexAlpha(palette.accent, 0.06),
                  })}
                >
                  <View style={{ paddingTop: 2 }}>
                    <OiOrigin kind={n.origin === "PNT" ? "PNT" : n.origin === "MFG" ? "MFG" : n.origin === "FIN" ? "FIN" : n.origin === "CRM" ? "CRM" : "OS"} />
                  </View>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text
                      style={{
                        fontFamily: n.read ? fonts.sans : fonts.sansSemibold,
                        fontSize: 13.5,
                        color: palette.text,
                      }}
                      numberOfLines={1}
                    >
                      {n.title}
                    </Text>
                    <Text style={{ fontSize: 12, color: palette.textDim, marginTop: 2 }} numberOfLines={2}>
                      {n.body}
                    </Text>
                    <Text
                      style={{
                        fontFamily: fonts.mono,
                        fontSize: 10.5,
                        color: palette.textMute,
                        marginTop: 4,
                      }}
                    >
                      {formatTimestamp(n.at)}
                    </Text>
                  </View>
                  {!n.read ? (
                    <View
                      style={{
                        width: 8,
                        height: 8,
                        borderRadius: 999,
                        backgroundColor: palette.accent,
                        marginTop: 6,
                      }}
                    />
                  ) : null}
                </Pressable>
              ))}
            </View>
          )}
        </OiSection>
      </ScrollView>
    </OiScreen>
  );
}
