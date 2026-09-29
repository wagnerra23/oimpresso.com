/**
 * Cliente — tela de detalhe (read-only).
 *
 * Replica `ClienteDetalheScreen` do design v2:
 * - Hero (avatar grande, nome, doc, papéis, ações WhatsApp/fone/email)
 * - 3 KPIs (Pedidos / Ticket médio / Saldo)
 * - Linha "Dados cadastrais" → /clientes/[id]/ficha
 * - Contato
 * - Pedidos recentes
 * - CTA "Novo pedido para [nome]"
 * - Lápis no header → /clientes/[id]/edit (wizard)
 */
import { useMemo } from "react";
import { ActivityIndicator, Linking, Pressable, ScrollView, Text, View } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";

import {
  OiBtn,
  OiCard,
  OiDetailHeader,
  OiIcon,
  OiKpi,
  OiKpis,
  OiList,
  OiListRow,
  OiMoney,
  OiScreen,
  OiSection,
  OiStatus,
} from "@/components/oi";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useCustomer, usePedidos } from "@/lib/erp-queries";

const PAPEIS_LABELS: Record<string, string> = {
  cliente: "Cliente",
  fornecedor: "Fornecedor",
  funcionario: "Funcionário",
  transportadora: "Transportadora",
};

const PAPEIS_COLORS: Record<string, "accent" | "ok" | "warn" | "danger"> = {
  cliente: "accent",
  fornecedor: "ok",
  funcionario: "warn",
  transportadora: "danger",
};

function getInitials(nome: string): string {
  return nome
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? "")
    .join("");
}

function formatBRL(n: number): string {
  return new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  }).format(n);
}

export default function ClienteDetalheScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const params = useLocalSearchParams<{ id: string }>();
  const id = params.id;

  const customerQuery = useCustomer(id);
  const pedidosQuery = usePedidos();

  const cliente = customerQuery.data;

  const pedidosCli = useMemo(() => {
    if (!cliente || !pedidosQuery.data) return [];
    return pedidosQuery.data
      .filter(
        (p) =>
          p.customerId === cliente.id ||
          (p.cliente && p.cliente.toLowerCase() === cliente.nome.toLowerCase()),
      )
      .slice(0, 20);
  }, [cliente, pedidosQuery.data]);

  const ticketMedio = useMemo(() => {
    if (pedidosCli.length === 0) return 0;
    const sum = pedidosCli.reduce((s, p) => s + (p.valor ?? 0), 0);
    return sum / pedidosCli.length;
  }, [pedidosCli]);

  // Loading
  if (customerQuery.isLoading || !cliente) {
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

  const papeis = (Array.isArray(cliente.papeis) ? cliente.papeis : []) as string[];
  const fonePrincipal = cliente.telefone ?? cliente.whatsapp ?? "";
  const whatsapp = cliente.whatsapp ?? cliente.telefone ?? "";
  const cidadeUf =
    cliente.cidade && cliente.uf
      ? `${cliente.cidade} · ${cliente.uf}`
      : cliente.cidade ?? cliente.endereco ?? "—";
  const initials = getInitials(cliente.nome);

  const openWhatsapp = () => {
    if (!whatsapp) return;
    const digits = whatsapp.replace(/\D/g, "");
    const phone = digits.startsWith("55") ? digits : `55${digits}`;
    Linking.openURL(`https://wa.me/${phone}`).catch(() => undefined);
  };
  const openPhone = () => {
    if (fonePrincipal) Linking.openURL(`tel:${fonePrincipal}`).catch(() => undefined);
  };
  const openMail = () => {
    if (cliente.email) Linking.openURL(`mailto:${cliente.email}`).catch(() => undefined);
  };

  return (
    <OiScreen edges={["top"]}>
      <Stack.Screen options={{ headerShown: false }} />
      <OiDetailHeader
        title={cliente.nome}
        onBack={() => router.back()}
        right={
          <Pressable
            onPress={() => router.push(`/clientes/${id}/edit` as never)}
            style={{
              width: 38,
              height: 38,
              alignItems: "center",
              justifyContent: "center",
              borderRadius: radius.sm,
            }}
          >
            <OiIcon name="edit" size={20} color={palette.accent} />
          </Pressable>
        }
      />

      <ScrollView
        contentContainerStyle={{ paddingBottom: 24 }}
        showsVerticalScrollIndicator={false}
      >
        {/* Hero card */}
        <OiSection>
          <OiCard variant="pad" style={{ alignItems: "center", paddingVertical: 20 }}>
            <View
              style={{
                width: 72,
                height: 72,
                borderRadius: 36,
                backgroundColor: palette.accentSoft,
                alignItems: "center",
                justifyContent: "center",
                marginBottom: 8,
              }}
            >
              <Text
                style={{
                  fontFamily: fonts.sansSemibold,
                  fontSize: 22,
                  color: palette.accent,
                }}
              >
                {initials}
              </Text>
            </View>
            <Text
              style={{
                fontFamily: fonts.sansSemibold,
                fontSize: 18,
                letterSpacing: -0.2,
                color: palette.text,
                textAlign: "center",
              }}
            >
              {cliente.nome}
            </Text>
            <Text
              style={{
                fontFamily: fonts.mono,
                fontSize: 12,
                color: palette.textMute,
                marginTop: 2,
              }}
            >
              {cliente.tipo} {cliente.documento ? `· ${cliente.documento}` : ""}
            </Text>

            {/* Papéis multi */}
            {papeis.length > 0 && (
              <View
                style={{
                  flexDirection: "row",
                  flexWrap: "wrap",
                  gap: 6,
                  justifyContent: "center",
                  marginTop: 8,
                }}
              >
                {papeis.map((p) => (
                  <OiStatus
                    key={p}
                    variant={PAPEIS_COLORS[p] ?? "accent"}
                    label={PAPEIS_LABELS[p] ?? p}
                  />
                ))}
              </View>
            )}

            {/* Quick actions */}
            <View style={{ flexDirection: "row", gap: 8, width: "100%", marginTop: 14 }}>
              <Pressable
                onPress={openWhatsapp}
                disabled={!whatsapp}
                style={{
                  flex: 1,
                  height: 44,
                  borderRadius: radius.md,
                  backgroundColor: whatsapp ? palette.accent : palette.surface2,
                  borderWidth: 1,
                  borderColor: whatsapp ? palette.accent : palette.border,
                  alignItems: "center",
                  justifyContent: "center",
                  flexDirection: "row",
                  gap: 6,
                  opacity: whatsapp ? 1 : 0.5,
                }}
              >
                <OiIcon name="whatsapp" size={16} color={whatsapp ? "#fff" : palette.textMute} />
                <Text
                  style={{
                    color: whatsapp ? "#fff" : palette.textMute,
                    fontFamily: fonts.sansSemibold,
                    fontSize: 13,
                  }}
                >
                  WhatsApp
                </Text>
              </Pressable>
              <Pressable
                onPress={openPhone}
                disabled={!fonePrincipal}
                style={{
                  width: 48,
                  height: 44,
                  borderRadius: radius.md,
                  backgroundColor: palette.surface,
                  borderWidth: 1,
                  borderColor: palette.border,
                  alignItems: "center",
                  justifyContent: "center",
                  opacity: fonePrincipal ? 1 : 0.5,
                }}
              >
                <OiIcon name="phone" size={16} color={palette.text} />
              </Pressable>
              <Pressable
                onPress={openMail}
                disabled={!cliente.email}
                style={{
                  width: 48,
                  height: 44,
                  borderRadius: radius.md,
                  backgroundColor: palette.surface,
                  borderWidth: 1,
                  borderColor: palette.border,
                  alignItems: "center",
                  justifyContent: "center",
                  opacity: cliente.email ? 1 : 0.5,
                }}
              >
                <OiIcon name="mail" size={16} color={palette.text} />
              </Pressable>
            </View>
          </OiCard>
        </OiSection>

        {/* KPIs */}
        <OiSection>
          <OiKpis>
            <OiKpi
              label="Pedidos"
              value={String(pedidosCli.length)}
              trend={pedidosCli.length > 0 ? "histórico" : undefined}
            />
            <OiKpi label="Ticket médio" value={ticketMedio > 0 ? formatBRL(ticketMedio) : "—"} />
            <OiKpi
              label="Limite"
              value={
                cliente.limiteCredito && cliente.limiteCredito > 0
                  ? formatBRL(cliente.limiteCredito)
                  : "—"
              }
            />
          </OiKpis>
        </OiSection>

        {/* Acesso à ficha cadastral */}
        <OiSection>
          <OiList card>
            <OiListRow
              title="Dados cadastrais"
              subtitle="Documento, endereço fiscal, comercial e LGPD"
              left={
                <View
                  style={{
                    width: 32,
                    height: 32,
                    borderRadius: 8,
                    backgroundColor: palette.accentSoft,
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <OiIcon name="file" size={16} color={palette.accent} />
                </View>
              }
              right={<OiIcon name="chev-r" size={16} color={palette.textMute} />}
              onPress={() => router.push(`/clientes/${id}/ficha` as never)}
              last
            />
          </OiList>
        </OiSection>

        {/* Contato */}
        <OiSection title="Contato">
          <OiCard variant="pad" style={{ gap: 0 }}>
            <ContactRow
              icon="phone"
              label={fonePrincipal || "—"}
              palette={palette}
              onPress={openPhone}
            />
            <ContactRow
              icon="mail"
              label={cliente.email ?? "—"}
              palette={palette}
              onPress={openMail}
            />
            <ContactRow icon="location" label={cidadeUf} palette={palette} last />
          </OiCard>
        </OiSection>

        {/* Pedidos recentes */}
        <OiSection
          title="Pedidos recentes"
          more={
            pedidosCli.length > 4
              ? {
                  label: `Ver todos (${pedidosCli.length})`,
                  onPress: () => router.push("/(tabs)/vendas" as never),
                }
              : undefined
          }
        >
          {pedidosCli.length === 0 ? (
            <OiCard variant="pad" style={{ alignItems: "center", paddingVertical: 24 }}>
              <OiIcon name="inbox" size={26} color={palette.textMute} />
              <Text
                style={{
                  fontFamily: fonts.sans,
                  fontSize: 12,
                  color: palette.textMute,
                  marginTop: 6,
                }}
              >
                Sem pedidos ainda
              </Text>
            </OiCard>
          ) : (
            <OiList card>
              {pedidosCli.slice(0, 4).map((p, idx, arr) => (
                <OiListRow
                  key={p.id}
                  title={p.produto ?? "Pedido"}
                  subtitle={`${p.id.slice(0, 8)} · ${p.status}`}
                  right={<OiMoney value={p.valor ?? 0} size={13} weight="semibold" />}
                  onPress={() => router.push("/(tabs)/vendas" as never)}
                  last={idx === Math.min(arr.length, 4) - 1}
                />
              ))}
            </OiList>
          )}
        </OiSection>

        {/* CTA novo pedido */}
        <OiSection>
          <OiBtn
            label={`Novo pedido para ${cliente.nome.split(" ")[0] ?? "cliente"}`}
            variant="primary"
            block
            leftIcon="plus"
            onPress={() => router.push("/(tabs)/vendas" as never)}
          />
        </OiSection>
      </ScrollView>
    </OiScreen>
  );
}

function ContactRow({
  icon,
  label,
  palette,
  onPress,
  last,
}: {
  icon: "phone" | "mail" | "location";
  label: string;
  palette: ReturnType<typeof useOiTheme>["palette"];
  onPress?: () => void;
  last?: boolean;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={!onPress}
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 12,
        paddingVertical: 10,
        borderBottomColor: palette.border2,
        borderBottomWidth: last ? 0 : 1,
      }}
    >
      <OiIcon name={icon} size={18} color={palette.textMute} />
      <Text
        style={{
          flex: 1,
          fontFamily: fonts.sans,
          fontSize: 13.5,
          color: palette.text,
        }}
        numberOfLines={1}
      >
        {label}
      </Text>
      {onPress ? (
        <OiIcon name="chev-r" size={16} color={palette.textMute} />
      ) : null}
    </Pressable>
  );
}
