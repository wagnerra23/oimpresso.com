/**
 * NovaManutencaoScreen — abertura de OS (wizard 3 passos, Bloco 3D).
 *
 * Passos: Veículo · Cliente · Abertura. Botão "Buscar" na placa autopreenche
 * de uma OS existente (mock). Aceita `?equip=<id>` para pré-preencher do
 * equipamento (Bloco 4 cobre o flow inverso).
 */
import { useMemo, useState } from "react";
import { useLocalSearchParams, useRouter } from "expo-router";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";

import {
  OiCard,
  OiDetailHeader,
  OiIcon,
  OiScreen,
  OiSection,
  useToast,
} from "@/components/oi";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useManutencao } from "@/lib/use-manutencao";
import type { Prioridade, Veiculo } from "@/lib/manutencao-mock";

const STEPS = ["Veículo", "Cliente", "Abertura"] as const;

export default function NovaManutencaoScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const toast = useToast();
  const { oss, locais, mecanicos, createOs } = useManutencao();
  const params = useLocalSearchParams<{ equip?: string }>();

  const [step, setStep] = useState(0);
  const [veiculo, setVeiculo] = useState<Veiculo>({
    marca: "",
    modelo: "",
    tipo: "",
    ano: new Date().getFullYear(),
    cor: "",
    placa: "",
    chassi: "",
    renavam: "",
    hodometro: 0,
  });
  const [cliente, setCliente] = useState("");
  const [motorista, setMotorista] = useState("");
  const [frota, setFrota] = useState("");
  const [tipo, setTipo] = useState<"Corretiva" | "Preventiva" | "Revisão" | "Pneus">("Corretiva");
  const [prioridade, setPrioridade] = useState<Prioridade>("normal");
  const [localId, setLocalId] = useState<string>(locais[0]?.id ?? "");
  const [mecanicoId, setMecanicoId] = useState<string | null>(null);
  const [relato, setRelato] = useState("");

  // Pré-preenchimento a partir de OS existente pela placa (item 3.28)
  const searchByPlaca = () => {
    const placa = veiculo.placa.trim().toUpperCase();
    if (!placa) return;
    const found = oss.find((o) => o.veiculo.placa.toUpperCase() === placa);
    if (!found) {
      toast.show("Placa não encontrada no histórico", "warn");
      return;
    }
    setVeiculo({ ...found.veiculo });
    setCliente(found.cliente);
    if (found.veiculo.motorista) setMotorista(found.veiculo.motorista);
    if (found.veiculo.frota) setFrota(found.veiculo.frota);
    toast.show("Dados preenchidos do histórico", "ok");
  };

  const canGo0 = veiculo.placa.trim() && veiculo.marca.trim() && veiculo.modelo.trim();
  const canGo1 = cliente.trim().length > 0;
  const canSubmit = relato.trim().length > 0;

  const submit = () => {
    if (!canSubmit) return;
    const novo = createOs({
      veiculo: { ...veiculo, motorista: motorista || undefined, frota: frota || undefined },
      cliente,
      prioridade,
      tipo,
      localId,
      mecanicoId,
      abertaEm: new Date().toISOString(),
      prazoEm: new Date(Date.now() + 3 * 24 * 3600_000).toISOString(),
      relato,
    });
    toast.show("OS aberta", "ok");
    router.replace(`/manutencao/${novo.id}` as never);
  };

  return (
    <OiScreen edges={["top"]}>
      <OiDetailHeader
        title="Nova OS"
        eyebrow={`Passo ${step + 1}/${STEPS.length} · ${STEPS[step]}`}
        onBack={() => router.back()}
      />

      {/* Steps indicator */}
      <View style={{ flexDirection: "row", gap: 4, paddingHorizontal: 16, paddingTop: 10 }}>
        {STEPS.map((label, i) => (
          <View
            key={label}
            style={{
              flex: 1,
              height: 4,
              borderRadius: 999,
              backgroundColor: i <= step ? palette.accent : palette.bg2,
            }}
          />
        ))}
      </View>

      <ScrollView
        contentContainerStyle={{ paddingBottom: 32 }}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        {step === 0 ? (
          <Step1
            veiculo={veiculo}
            setVeiculo={setVeiculo}
            onSearch={searchByPlaca}
          />
        ) : step === 1 ? (
          <Step2
            cliente={cliente}
            setCliente={setCliente}
            motorista={motorista}
            setMotorista={setMotorista}
            frota={frota}
            setFrota={setFrota}
          />
        ) : (
          <Step3
            tipo={tipo}
            setTipo={setTipo}
            prioridade={prioridade}
            setPrioridade={setPrioridade}
            localId={localId}
            setLocalId={setLocalId}
            mecanicoId={mecanicoId}
            setMecanicoId={setMecanicoId}
            relato={relato}
            setRelato={setRelato}
            locais={locais}
            mecanicos={mecanicos}
          />
        )}
      </ScrollView>

      {/* Action bar */}
      <View
        style={{
          padding: 12,
          flexDirection: "row",
          gap: 8,
          backgroundColor: palette.surface,
          borderTopWidth: 1,
          borderTopColor: palette.border,
        }}
      >
        {step > 0 ? (
          <Pressable
            onPress={() => setStep((s) => s - 1)}
            style={{
              height: 48,
              paddingHorizontal: 16,
              borderRadius: radius.md,
              alignItems: "center",
              justifyContent: "center",
              borderWidth: 1,
              borderColor: palette.border,
            }}
          >
            <Text style={{ color: palette.text, fontFamily: fonts.sansSemibold, fontSize: 13 }}>Voltar</Text>
          </Pressable>
        ) : null}
        {step < STEPS.length - 1 ? (
          <Pressable
            onPress={() => setStep((s) => s + 1)}
            disabled={step === 0 ? !canGo0 : !canGo1}
            style={({ pressed }) => ({
              flex: 1,
              height: 48,
              borderRadius: radius.md,
              alignItems: "center",
              justifyContent: "center",
              flexDirection: "row",
              gap: 8,
              backgroundColor: palette.accent,
              opacity: (step === 0 ? !canGo0 : !canGo1) ? 0.4 : pressed ? 0.85 : 1,
            })}
          >
            <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold, fontSize: 14 }}>Continuar</Text>
            <OiIcon name="chev-r" size={18} color="#fff" />
          </Pressable>
        ) : (
          <Pressable
            onPress={submit}
            disabled={!canSubmit}
            style={({ pressed }) => ({
              flex: 1,
              height: 48,
              borderRadius: radius.md,
              alignItems: "center",
              justifyContent: "center",
              flexDirection: "row",
              gap: 8,
              backgroundColor: palette.ok,
              opacity: !canSubmit ? 0.4 : pressed ? 0.85 : 1,
            })}
          >
            <OiIcon name="check" size={18} color="#fff" />
            <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold, fontSize: 14 }}>Abrir OS</Text>
          </Pressable>
        )}
      </View>
    </OiScreen>
  );
}

// ─── Steps ─────────────────────────────────────────────────────────────────

function FieldLabel({ children }: { children: string }) {
  const { palette } = useOiTheme();
  return (
    <Text
      style={{
        fontFamily: fonts.sansBold,
        fontSize: 10.5,
        letterSpacing: 0.8,
        textTransform: "uppercase",
        color: palette.textMute,
        marginBottom: 4,
      }}
    >
      {children}
    </Text>
  );
}

function Input({
  value,
  onChangeText,
  placeholder,
  keyboardType,
}: {
  value: string;
  onChangeText: (v: string) => void;
  placeholder?: string;
  keyboardType?: "default" | "numeric" | "email-address";
}) {
  const { palette } = useOiTheme();
  return (
    <TextInput
      value={value}
      onChangeText={onChangeText}
      placeholder={placeholder}
      placeholderTextColor={palette.textMute}
      keyboardType={keyboardType}
      style={{
        height: 44,
        paddingHorizontal: 12,
        borderRadius: radius.md,
        borderWidth: 1,
        borderColor: palette.border,
        backgroundColor: palette.bg2,
        color: palette.text,
        fontFamily: fonts.sans,
        fontSize: 14,
      }}
    />
  );
}

function ChipPicker<T extends string>({
  value,
  onChange,
  options,
}: {
  value: T;
  onChange: (v: T) => void;
  options: { id: T; label: string }[];
}) {
  const { palette } = useOiTheme();
  return (
    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
      {options.map((o) => {
        const on = value === o.id;
        return (
          <Pressable
            key={o.id}
            onPress={() => onChange(o.id)}
            style={{
              paddingHorizontal: 12,
              paddingVertical: 8,
              borderRadius: radius.pill,
              borderWidth: 1,
              borderColor: on ? palette.accent : palette.border,
              backgroundColor: on ? palette.accentSoft : palette.surface,
            }}
          >
            <Text
              style={{
                fontSize: 12.5,
                color: on ? palette.accent : palette.text,
                fontFamily: fonts.sansSemibold,
              }}
            >
              {o.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

function Step1({
  veiculo,
  setVeiculo,
  onSearch,
}: {
  veiculo: Veiculo;
  setVeiculo: (v: Veiculo) => void;
  onSearch: () => void;
}) {
  const { palette } = useOiTheme();
  const set = <K extends keyof Veiculo>(k: K, v: Veiculo[K]) =>
    setVeiculo({ ...veiculo, [k]: v });
  return (
    <View style={{ padding: 16, gap: 12 }}>
      <FieldLabel>Placa *</FieldLabel>
      <View style={{ flexDirection: "row", gap: 8 }}>
        <View style={{ flex: 1 }}>
          <Input value={veiculo.placa} onChangeText={(v) => set("placa", v.toUpperCase())} placeholder="AAA0A00" />
        </View>
        <Pressable
          onPress={onSearch}
          style={({ pressed }) => ({
            height: 44,
            paddingHorizontal: 14,
            borderRadius: radius.md,
            alignItems: "center",
            justifyContent: "center",
            flexDirection: "row",
            gap: 6,
            backgroundColor: palette.bg2,
            borderWidth: 1,
            borderColor: palette.border,
            opacity: pressed ? 0.85 : 1,
          })}
        >
          <OiIcon name="search" size={16} color={palette.text} />
          <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13, color: palette.text }}>Buscar</Text>
        </Pressable>
      </View>
      <FieldLabel>Tipo</FieldLabel>
      <Input value={veiculo.tipo} onChangeText={(v) => set("tipo", v)} placeholder="ex: Caminhão 3/4" />
      <View style={{ flexDirection: "row", gap: 8 }}>
        <View style={{ flex: 1 }}>
          <FieldLabel>Marca *</FieldLabel>
          <Input value={veiculo.marca} onChangeText={(v) => set("marca", v)} />
        </View>
        <View style={{ flex: 1 }}>
          <FieldLabel>Modelo *</FieldLabel>
          <Input value={veiculo.modelo} onChangeText={(v) => set("modelo", v)} />
        </View>
      </View>
      <View style={{ flexDirection: "row", gap: 8 }}>
        <View style={{ flex: 1 }}>
          <FieldLabel>Ano</FieldLabel>
          <Input
            value={String(veiculo.ano)}
            onChangeText={(v) => set("ano", Number(v.replace(/\D/g, "")) || 0)}
            keyboardType="numeric"
          />
        </View>
        <View style={{ flex: 1 }}>
          <FieldLabel>Cor</FieldLabel>
          <Input value={veiculo.cor} onChangeText={(v) => set("cor", v)} />
        </View>
      </View>
      <FieldLabel>Chassi</FieldLabel>
      <Input value={veiculo.chassi} onChangeText={(v) => set("chassi", v.toUpperCase())} />
      <FieldLabel>Renavam</FieldLabel>
      <Input value={veiculo.renavam} onChangeText={(v) => set("renavam", v)} keyboardType="numeric" />
      <FieldLabel>Hodômetro (km)</FieldLabel>
      <Input
        value={veiculo.hodometro ? String(veiculo.hodometro) : ""}
        onChangeText={(v) => set("hodometro", Number(v.replace(/\D/g, "")) || 0)}
        keyboardType="numeric"
      />
    </View>
  );
}

function Step2({
  cliente,
  setCliente,
  motorista,
  setMotorista,
  frota,
  setFrota,
}: {
  cliente: string;
  setCliente: (v: string) => void;
  motorista: string;
  setMotorista: (v: string) => void;
  frota: string;
  setFrota: (v: string) => void;
}) {
  return (
    <View style={{ padding: 16, gap: 12 }}>
      <FieldLabel>Cliente / transportadora *</FieldLabel>
      <Input value={cliente} onChangeText={setCliente} placeholder="Razão social ou nome" />
      <FieldLabel>Motorista</FieldLabel>
      <Input value={motorista} onChangeText={setMotorista} />
      <FieldLabel>Nº de frota</FieldLabel>
      <Input value={frota} onChangeText={setFrota} placeholder="ex: FR-12" />
    </View>
  );
}

function Step3({
  tipo,
  setTipo,
  prioridade,
  setPrioridade,
  localId,
  setLocalId,
  mecanicoId,
  setMecanicoId,
  relato,
  setRelato,
  locais,
  mecanicos,
}: {
  tipo: "Corretiva" | "Preventiva" | "Revisão" | "Pneus";
  setTipo: (v: "Corretiva" | "Preventiva" | "Revisão" | "Pneus") => void;
  prioridade: Prioridade;
  setPrioridade: (v: Prioridade) => void;
  localId: string;
  setLocalId: (v: string) => void;
  mecanicoId: string | null;
  setMecanicoId: (v: string | null) => void;
  relato: string;
  setRelato: (v: string) => void;
  locais: ReturnType<typeof useManutencao>["locais"];
  mecanicos: ReturnType<typeof useManutencao>["mecanicos"];
}) {
  const { palette } = useOiTheme();
  return (
    <View style={{ padding: 16, gap: 14 }}>
      <View>
        <FieldLabel>Tipo de manutenção</FieldLabel>
        <ChipPicker
          value={tipo}
          onChange={setTipo}
          options={[
            { id: "Corretiva", label: "Corretiva" },
            { id: "Preventiva", label: "Preventiva" },
            { id: "Revisão", label: "Revisão" },
            { id: "Pneus", label: "Pneus" },
          ]}
        />
      </View>
      <View>
        <FieldLabel>Prioridade</FieldLabel>
        <ChipPicker
          value={prioridade}
          onChange={setPrioridade}
          options={[
            { id: "baixa", label: "Baixa" },
            { id: "normal", label: "Normal" },
            { id: "alta", label: "Alta" },
            { id: "urgente", label: "Urgente" },
          ]}
        />
      </View>
      <View>
        <FieldLabel>Local inicial</FieldLabel>
        <ChipPicker
          value={localId}
          onChange={setLocalId}
          options={locais.map((l) => ({ id: l.id, label: l.nome }))}
        />
      </View>
      <View>
        <FieldLabel>Mecânico</FieldLabel>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
          <Pressable
            onPress={() => setMecanicoId(null)}
            style={{
              paddingHorizontal: 12,
              paddingVertical: 8,
              borderRadius: radius.pill,
              borderWidth: 1,
              borderColor: mecanicoId === null ? palette.accent : palette.border,
              backgroundColor: mecanicoId === null ? palette.accentSoft : palette.surface,
            }}
          >
            <Text
              style={{
                fontSize: 12.5,
                color: mecanicoId === null ? palette.accent : palette.text,
                fontFamily: fonts.sansSemibold,
              }}
            >
              Pool
            </Text>
          </Pressable>
          {mecanicos.map((m) => {
            const on = mecanicoId === m.id;
            return (
              <Pressable
                key={m.id}
                onPress={() => setMecanicoId(m.id)}
                style={{
                  paddingHorizontal: 12,
                  paddingVertical: 8,
                  borderRadius: radius.pill,
                  borderWidth: 1,
                  borderColor: on ? palette.accent : palette.border,
                  backgroundColor: on ? palette.accentSoft : palette.surface,
                }}
              >
                <Text
                  style={{
                    fontSize: 12.5,
                    color: on ? palette.accent : palette.text,
                    fontFamily: fonts.sansSemibold,
                  }}
                >
                  {m.nome.split(" ")[0]}
                </Text>
              </Pressable>
            );
          })}
        </View>
      </View>
      <View>
        <FieldLabel>Relato / sintomas *</FieldLabel>
        <TextInput
          value={relato}
          onChangeText={setRelato}
          multiline
          numberOfLines={4}
          placeholder="O que o motorista relatou?"
          placeholderTextColor={palette.textMute}
          style={{
            minHeight: 110,
            padding: 12,
            borderRadius: radius.md,
            borderWidth: 1,
            borderColor: palette.border,
            backgroundColor: palette.bg2,
            color: palette.text,
            fontFamily: fonts.sans,
            fontSize: 14,
            textAlignVertical: "top",
          }}
        />
      </View>
    </View>
  );
}
