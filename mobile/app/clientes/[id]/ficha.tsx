/**
 * Cliente — ficha cadastral completa (read-only).
 *
 * Réplica de `ClienteDadosScreen` do design v2: 5 seções (Identificação /
 * Contato / Endereço fiscal / Comercial / LGPD), cada uma com botão "Editar"
 * que abre o wizard direto na etapa correspondente.
 */
import { ActivityIndicator, ScrollView, Text, View } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";

import {
  OiBtn,
  OiCard,
  OiDetailHeader,
  OiDl,
  OiDlRow,
  OiIcon,
  OiScreen,
  OiSection,
  OiSectionHeader,
} from "@/components/oi";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useCustomer } from "@/lib/erp-queries";

const PAPEIS_LABELS: Record<string, string> = {
  cliente: "Cliente",
  fornecedor: "Fornecedor",
  funcionario: "Funcionário",
  transportadora: "Transportadora",
};

const INDICADOR_IE_LABEL: Record<string, string> = {
  contribuinte: "Contribuinte ICMS",
  isento: "Isento",
  nao_contribuinte: "Não contribuinte",
};

function formatBRL(n: number): string {
  return new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
  }).format(n);
}

function formatDateBR(iso: string | Date | null | undefined): string {
  if (!iso) return "—";
  try {
    const d = typeof iso === "string" ? new Date(iso) : iso;
    return d.toLocaleDateString("pt-BR");
  } catch {
    return String(iso);
  }
}

export default function ClienteFichaScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { id } = useLocalSearchParams<{ id: string }>();
  const customerQuery = useCustomer(id);
  const cliente = customerQuery.data;

  const editAt = (step: number) =>
    router.push(`/clientes/${id}/edit?startStep=${step}` as never);

  // Local helpers using current palette
  const MonoText = ({ children }: { children: React.ReactNode }) => (
    <Text
      style={{
        fontFamily: fonts.mono,
        fontSize: 13,
        color: palette.text,
        fontVariant: ["tabular-nums"],
      }}
    >
      {children}
    </Text>
  );

  if (customerQuery.isLoading || !cliente) {
    return (
      <OiScreen edges={["top"]}>
        <Stack.Screen options={{ headerShown: false }} />
        <OiDetailHeader title="Dados cadastrais" onBack={() => router.back()} />
        <View style={{ paddingVertical: 48, alignItems: "center" }}>
          <ActivityIndicator color={palette.accent} />
        </View>
      </OiScreen>
    );
  }

  const papeis = (Array.isArray(cliente.papeis) ? cliente.papeis : []) as string[];
  const papeisStr =
    papeis.map((p) => PAPEIS_LABELS[p] ?? p).join(" · ") || "—";

  const isPj = cliente.tipo === "PJ";
  const enderecoLine = [
    cliente.logradouro,
    cliente.numero ? `, ${cliente.numero}` : "",
  ]
    .filter(Boolean)
    .join("")
    || cliente.endereco
    || "—";
  const cidadeUf =
    cliente.cidade && cliente.uf
      ? `${cliente.cidade} · ${cliente.uf}`
      : cliente.cidade ?? "—";

  return (
    <OiScreen edges={["top"]}>
      <Stack.Screen options={{ headerShown: false }} />
      <OiDetailHeader
        title="Dados cadastrais"
        eyebrow={cliente.nome}
        onBack={() => router.back()}
      />

      <ScrollView
        contentContainerStyle={{ paddingBottom: 88 }}
        showsVerticalScrollIndicator={false}
      >
        {/* Identificação */}
        <OiSection>
          <OiSectionHeader
            title="Identificação"
            more={{ label: "Editar", onPress: () => editAt(0) }}
          />
          <OiCard variant="pad">
            <OiDl>
              <OiDlRow label="Classificação">{papeisStr}</OiDlRow>
              <OiDlRow label="Tipo">{isPj ? "Pessoa Jurídica" : "Pessoa Física"}</OiDlRow>
              <OiDlRow label={isPj ? "Razão social" : "Nome"}>{cliente.nome}</OiDlRow>
              {isPj && cliente.nomeFantasia ? (
                <OiDlRow label="Fantasia">{cliente.nomeFantasia}</OiDlRow>
              ) : null}
              <OiDlRow label={isPj ? "CNPJ" : "CPF"}>
                <MonoText>{cliente.documento ?? "—"}</MonoText>
              </OiDlRow>
              {cliente.indicadorIe ? (
                <OiDlRow label="Contribuinte">
                  {INDICADOR_IE_LABEL[cliente.indicadorIe] ?? cliente.indicadorIe}
                </OiDlRow>
              ) : null}
              {cliente.inscricaoEstadual ? (
                <OiDlRow label="Insc. estadual">
                  <MonoText>{cliente.inscricaoEstadual}</MonoText>
                </OiDlRow>
              ) : null}
              <OiDlRow label="Cliente desde">{formatDateBR(cliente.createdAt)}</OiDlRow>
            </OiDl>
          </OiCard>
        </OiSection>

        {/* Contato */}
        <OiSection>
          <OiSectionHeader
            title="Contato"
            more={{ label: "Editar", onPress: () => editAt(1) }}
          />
          <OiCard variant="pad">
            <OiDl>
              <OiDlRow label="WhatsApp">
                <MonoText>{cliente.whatsapp ?? "—"}</MonoText>
              </OiDlRow>
              <OiDlRow label="Telefone">
                <MonoText>{cliente.telefone ?? "—"}</MonoText>
              </OiDlRow>
              <OiDlRow label="E-mail">{cliente.email ?? "—"}</OiDlRow>
              <OiDlRow label="E-mail NF-e">{cliente.emailNfe ?? "—"}</OiDlRow>
            </OiDl>
          </OiCard>
        </OiSection>

        {/* Endereço fiscal */}
        <OiSection>
          <OiSectionHeader
            title="Endereço fiscal"
            more={{ label: "Editar", onPress: () => editAt(2) }}
          />
          <OiCard variant="pad">
            <OiDl>
              <OiDlRow label="CEP">
                <MonoText>{cliente.cep ?? "—"}</MonoText>
              </OiDlRow>
              <OiDlRow label="Logradouro">{enderecoLine}</OiDlRow>
              {cliente.complemento ? (
                <OiDlRow label="Compl.">{cliente.complemento}</OiDlRow>
              ) : null}
              <OiDlRow label="Bairro">{cliente.bairro ?? "—"}</OiDlRow>
              <OiDlRow label="Cidade/UF">{cidadeUf}</OiDlRow>
              {cliente.codigoMunicipioIbge ? (
                <OiDlRow label="IBGE">
                  <MonoText>{cliente.codigoMunicipioIbge}</MonoText>
                </OiDlRow>
              ) : null}
            </OiDl>
          </OiCard>
        </OiSection>

        {/* Comercial */}
        <OiSection>
          <OiSectionHeader
            title="Comercial"
            more={{ label: "Editar", onPress: () => editAt(3) }}
          />
          <OiCard variant="pad">
            <OiDl>
              <OiDlRow label="Classificação">{cliente.classificacao ?? "C"}</OiDlRow>
              <OiDlRow label="Limite">
                <MonoText>
                  {cliente.limiteCredito && cliente.limiteCredito > 0
                    ? formatBRL(cliente.limiteCredito)
                    : "—"}
                </MonoText>
              </OiDlRow>
              <OiDlRow label="Prazo padrão">
                {cliente.prazoPadraoDias && cliente.prazoPadraoDias > 0
                  ? `${cliente.prazoPadraoDias} dias`
                  : "À vista"}
              </OiDlRow>
            </OiDl>
          </OiCard>
        </OiSection>

        {/* LGPD */}
        <OiSection>
          <OiSectionHeader
            title="Consentimento (LGPD)"
            more={{ label: "Editar", onPress: () => editAt(4) }}
          />
          <OiCard variant="pad" style={{ gap: 8 }}>
            {[
              { label: "Contato via WhatsApp", on: !!cliente.aceitaWhatsapp },
              { label: "Envio de NF-e por e-mail", on: !!cliente.aceitaEmail },
              { label: "Comunicações por SMS", on: !!cliente.aceitaSms },
            ].map((x, i) => (
              <View
                key={i}
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 10,
                  paddingVertical: 4,
                }}
              >
                <OiIcon
                  name={x.on ? "check-circle" : "x"}
                  size={18}
                  color={x.on ? palette.ok : palette.textMute}
                />
                <Text
                  style={{
                    flex: 1,
                    fontFamily: fonts.sans,
                    fontSize: 13,
                    color: x.on ? palette.text : palette.textMute,
                  }}
                >
                  {x.label}
                </Text>
                <Text
                  style={{
                    fontFamily: fonts.sansSemibold,
                    fontSize: 11,
                    color: x.on ? palette.ok : palette.textMute,
                  }}
                >
                  {x.on ? "Autorizado" : "Não"}
                </Text>
              </View>
            ))}
            {cliente.consentimentoData ? (
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 6,
                  marginTop: 4,
                  paddingHorizontal: 10,
                  paddingVertical: 8,
                  backgroundColor: palette.bg2,
                  borderRadius: radius.sm,
                }}
              >
                <OiIcon name="shield" size={13} color={palette.textDim} />
                <Text
                  style={{
                    flex: 1,
                    fontFamily: fonts.sans,
                    fontSize: 11.5,
                    color: palette.textDim,
                  }}
                >
                  Registrado em {formatDateBR(cliente.consentimentoData)}
                  {cliente.consentimentoIp ? ` · IP ${cliente.consentimentoIp}` : ""}
                </Text>
              </View>
            ) : null}
          </OiCard>
        </OiSection>
      </ScrollView>

      {/* Bottom bar */}
      <View
        style={{
          padding: 12,
          backgroundColor: palette.surface,
          borderTopColor: palette.border,
          borderTopWidth: 1,
        }}
      >
        <OiBtn
          label="Editar cadastro"
          variant="primary"
          block
          leftIcon="edit"
          onPress={() => router.push(`/clientes/${id}/edit` as never)}
        />
      </View>
    </OiScreen>
  );
}
