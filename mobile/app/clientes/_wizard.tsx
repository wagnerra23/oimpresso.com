/**
 * Cliente / Pessoa wizard (v2).
 *
 * 5 etapas: Dados · Contato · Endereço · Comercial · LGPD.
 * Suporta criação (sem `id`) e edição (`id` carregado via tRPC.getById).
 *
 * Replicação fiel de ref/design-v2/screens-novo-cliente.jsx.
 */
import { useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Text,
  View,
} from "react-native";
import { useRouter } from "expo-router";

import {
  OiCard,
  OiCheckRow,
  OiDetailHeader,
  OiDoneCard,
  OiField,
  OiIcon,
  OiInput,
  OiPillChip,
  OiScreen,
  OiSection,
  OiSeg,
  OiStatus,
  OiSwitchRow,
  OiWizardBottomBar,
  OiWizardSteps,
  type WizardStep,
} from "@/components/oi";
import { useERP } from "@/lib/erp-context";
import {
  useCreateCustomer,
  useCustomer,
  useUpdateCustomer,
} from "@/lib/erp-queries";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

// ─── Steps ──────────────────────────────────────────────────────────────────

const STEPS: ReadonlyArray<WizardStep> = [
  { id: "dados", label: "Dados" },
  { id: "contato", label: "Contato" },
  { id: "endereco", label: "Endereço" },
  { id: "comercial", label: "Comercial" },
  { id: "lgpd", label: "LGPD" },
];

const PAPEIS = [
  { id: "cliente", label: "Cliente" },
  { id: "fornecedor", label: "Fornecedor" },
  { id: "funcionario", label: "Funcionário" },
  { id: "transportadora", label: "Transportadora" },
] as const;

type PapelId = (typeof PAPEIS)[number]["id"];

type Tipo = "PF" | "PJ";
type IndicadorIe = "contribuinte" | "isento" | "nao_contribuinte";
type Classificacao = "A" | "B" | "C" | "D";

interface WizardForm {
  papeis: PapelId[];
  tipo: Tipo;
  // Dados
  nome: string;
  nomeFantasia: string;
  documento: string;
  indicadorIe: IndicadorIe;
  inscricaoEstadual: string;
  // Contato
  telefone: string;
  whatsapp: string;
  email: string;
  emailNfe: string;
  // Endereço
  cep: string;
  logradouro: string;
  numero: string;
  complemento: string;
  bairro: string;
  cidade: string;
  uf: string;
  codigoMunicipioIbge: string;
  // Comercial
  classificacao: Classificacao;
  limiteCredito: string;
  prazoPadraoDias: string;
  observacoes: string;
  // LGPD
  aceitaWhatsapp: boolean;
  aceitaEmail: boolean;
  aceitaSms: boolean;
}

const EMPTY_FORM: WizardForm = {
  papeis: ["cliente"],
  tipo: "PF",
  nome: "",
  nomeFantasia: "",
  documento: "",
  indicadorIe: "nao_contribuinte",
  inscricaoEstadual: "",
  telefone: "",
  whatsapp: "",
  email: "",
  emailNfe: "",
  cep: "",
  logradouro: "",
  numero: "",
  complemento: "",
  bairro: "",
  cidade: "",
  uf: "",
  codigoMunicipioIbge: "",
  classificacao: "C",
  limiteCredito: "",
  prazoPadraoDias: "",
  observacoes: "",
  aceitaWhatsapp: true,
  aceitaEmail: true,
  aceitaSms: false,
};

// ─── ViaCEP integration (mobile-friendly fetch) ─────────────────────────────

type ViaCepResp = {
  logradouro?: string;
  bairro?: string;
  localidade?: string;
  uf?: string;
  ibge?: string;
  erro?: boolean;
};

async function fetchViaCep(cep: string): Promise<ViaCepResp | null> {
  const digits = cep.replace(/\D/g, "");
  if (digits.length !== 8) return null;
  try {
    const res = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
    if (!res.ok) return null;
    const data = (await res.json()) as ViaCepResp;
    if (data.erro) return null;
    return data;
  } catch {
    return null;
  }
}

// ─── Component ──────────────────────────────────────────────────────────────

export interface ClienteWizardProps {
  /** When set, loads the customer and starts in edit mode. */
  customerId?: string;
  /** Optional starting step (deep-link from "Editar" section action). */
  initialStep?: number;
}

export default function ClienteWizard({ customerId, initialStep = 0 }: ClienteWizardProps) {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { addToast } = useERP();
  const editing = !!customerId;

  const existing = useCustomer(customerId);
  const createCustomer = useCreateCustomer();
  const updateCustomer = useUpdateCustomer();

  const [step, setStep] = useState(initialStep);
  const [maxReached, setMaxReached] = useState(editing ? STEPS.length - 1 : 0);
  const [form, setForm] = useState<WizardForm>(EMPTY_FORM);
  const [done, setDone] = useState(false);
  const [savedName, setSavedName] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [searching, setSearching] = useState<"cep" | null>(null);

  // Hydrate form when editing
  useEffect(() => {
    if (!editing || !existing.data) return;
    const c = existing.data;
    setForm({
      papeis: ((c.papeis as PapelId[] | null) ?? ["cliente"]) as PapelId[],
      tipo: c.tipo,
      nome: c.nome ?? "",
      nomeFantasia: c.nomeFantasia ?? "",
      documento: c.documento ?? "",
      indicadorIe: (c.indicadorIe ?? "nao_contribuinte") as IndicadorIe,
      inscricaoEstadual: c.inscricaoEstadual ?? "",
      telefone: c.telefone ?? "",
      whatsapp: c.whatsapp ?? "",
      email: c.email ?? "",
      emailNfe: c.emailNfe ?? "",
      cep: c.cep ?? "",
      logradouro: c.logradouro ?? "",
      numero: c.numero ?? "",
      complemento: c.complemento ?? "",
      bairro: c.bairro ?? "",
      cidade: c.cidade ?? "",
      uf: c.uf ?? "",
      codigoMunicipioIbge: c.codigoMunicipioIbge ?? "",
      classificacao: (c.classificacao ?? "C") as Classificacao,
      limiteCredito: c.limiteCredito != null ? String(c.limiteCredito) : "",
      prazoPadraoDias: c.prazoPadraoDias != null ? String(c.prazoPadraoDias) : "",
      observacoes: c.observacoes ?? "",
      aceitaWhatsapp: c.aceitaWhatsapp ?? true,
      aceitaEmail: c.aceitaEmail ?? true,
      aceitaSms: c.aceitaSms ?? false,
    });
  }, [editing, existing.data]);

  const set = <K extends keyof WizardForm>(key: K, value: WizardForm[K]) =>
    setForm((s) => ({ ...s, [key]: value }));

  const go = (i: number) => {
    setStep(i);
    setMaxReached((m) => Math.max(m, i));
  };

  const validateStep = (): boolean => {
    const next: Record<string, string> = {};
    if (step === 0) {
      if (form.papeis.length === 0) next.papeis = "Selecione ao menos 1 papel";
      if (!form.nome.trim()) next.nome = "Nome é obrigatório";
    }
    if (step === 1 && form.email.trim()) {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
        next.email = "Email inválido";
      }
    }
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const buscarCep = async () => {
    setSearching("cep");
    const data = await fetchViaCep(form.cep);
    setSearching(null);
    if (!data) {
      addToast("erro", "CEP não encontrado");
      return;
    }
    setForm((s) => ({
      ...s,
      logradouro: data.logradouro ?? s.logradouro,
      bairro: data.bairro ?? s.bairro,
      cidade: data.localidade ?? s.cidade,
      uf: data.uf ?? s.uf,
      codigoMunicipioIbge: data.ibge ?? s.codigoMunicipioIbge,
    }));
  };

  // Build payload accepted by tRPC create/update
  const buildPayload = () => ({
    nome: form.nome.trim(),
    tipo: form.tipo,
    documento: form.documento.trim() || null,
    telefone: form.telefone.trim() || null,
    email: form.email.trim() || null,
    observacoes: form.observacoes.trim() || null,
    papeis: form.papeis,
    nomeFantasia: form.nomeFantasia.trim() || null,
    razaoSocial: form.tipo === "PJ" ? form.nome.trim() : null,
    inscricaoEstadual: form.inscricaoEstadual.trim() || null,
    indicadorIe: form.indicadorIe,
    cep: form.cep.trim() || null,
    logradouro: form.logradouro.trim() || null,
    numero: form.numero.trim() || null,
    complemento: form.complemento.trim() || null,
    bairro: form.bairro.trim() || null,
    cidade: form.cidade.trim() || null,
    uf: form.uf.trim().toUpperCase().slice(0, 2) || null,
    codigoMunicipioIbge: form.codigoMunicipioIbge.trim() || null,
    whatsapp: form.whatsapp.trim() || null,
    emailNfe: form.emailNfe.trim() || null,
    aceitaWhatsapp: form.aceitaWhatsapp,
    aceitaEmail: form.aceitaEmail,
    aceitaSms: form.aceitaSms,
    consentimentoData: new Date().toISOString(),
    consentimentoIp: null, // captured server-side in a future iteration
    classificacao: form.classificacao,
    limiteCredito: parseFloat(form.limiteCredito.replace(",", ".")) || 0,
    prazoPadraoDias: parseInt(form.prazoPadraoDias, 10) || 0,
  });

  const saving = createCustomer.isPending || updateCustomer.isPending;

  const handleNext = async () => {
    if (!validateStep()) return;
    if (step < STEPS.length - 1) {
      go(step + 1);
      return;
    }
    // Last step → save
    try {
      const payload = buildPayload();
      if (editing && customerId) {
        await updateCustomer.mutateAsync({ id: customerId, ...payload });
        addToast("sucesso", "Cadastro atualizado!");
      } else {
        await createCustomer.mutateAsync(payload);
        addToast("sucesso", "Cadastro criado!");
      }
      setSavedName(form.nome.trim() || "Sem nome");
      setDone(true);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao salvar");
    }
  };

  const togglePapel = (id: PapelId) => {
    const has = form.papeis.includes(id);
    set("papeis", has ? form.papeis.filter((x) => x !== id) : [...form.papeis, id]);
  };

  // ─── Render: success state ───
  if (done) {
    return (
      <OiScreen edges={["top", "bottom"]}>
        <OiDetailHeader
          title={editing ? "Cadastro atualizado" : "Cadastro criado"}
          onBack={() => router.back()}
        />
        <ScrollView contentContainerStyle={{ paddingBottom: 24 }}>
          <OiDoneCard
            title={editing ? "Alterações salvas!" : "Cadastro salvo!"}
            subtitle={savedName}
          >
            <View
              style={{ flexDirection: "row", marginTop: 10, gap: 6, flexWrap: "wrap", justifyContent: "center" }}
            >
              <OiStatus
                variant="accent"
                label={form.tipo === "PJ" ? "Pessoa Jurídica" : "Pessoa Física"}
              />
              {form.papeis.map((p) => (
                <OiStatus key={p} variant="neutral" label={p} />
              ))}
            </View>
          </OiDoneCard>

          <OiSection>
            {editing ? (
              <Pressable
                onPress={() => router.back()}
                style={{
                  backgroundColor: palette.accent,
                  borderRadius: radius.md,
                  paddingVertical: 12,
                  alignItems: "center",
                  marginTop: 12,
                }}
              >
                <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold }}>
                  Voltar para a ficha
                </Text>
              </Pressable>
            ) : (
              <>
                <Pressable
                  onPress={() => router.back()}
                  style={{
                    backgroundColor: palette.accent,
                    borderRadius: radius.md,
                    paddingVertical: 12,
                    alignItems: "center",
                    marginTop: 12,
                  }}
                >
                  <Text style={{ color: "#fff", fontFamily: fonts.sansSemibold }}>
                    Concluir
                  </Text>
                </Pressable>
              </>
            )}
          </OiSection>
        </ScrollView>
      </OiScreen>
    );
  }

  // ─── Render: wizard ───
  return (
    <OiScreen edges={["top", "bottom"]}>
      <OiDetailHeader
        title={editing ? "Editar pessoa" : "Nova pessoa"}
        onBack={() => router.back()}
      />

      <OiWizardSteps
        steps={STEPS}
        current={step}
        maxReached={maxReached}
        onGo={go}
      />

      {editing && existing.isLoading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color={palette.accent} />
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={{ paddingBottom: 24 }}
          keyboardShouldPersistTaps="handled"
        >
          {step === 0 && (
            <>
              <OiSection title="Classificação">
                <OiCard variant="pad">
                  <OiField
                    hint="Uma pessoa pode ter mais de um papel (ex: cliente que também é fornecedor)."
                    error={errors.papeis}
                  >
                    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
                      {PAPEIS.map((p) => (
                        <OiPillChip
                          key={p.id}
                          label={p.label}
                          on={form.papeis.includes(p.id)}
                          onPress={() => togglePapel(p.id)}
                        />
                      ))}
                    </View>
                  </OiField>
                </OiCard>
              </OiSection>

              <OiSection title="Tipo de pessoa">
                <OiSeg
                  value={form.tipo}
                  onChange={(v) => set("tipo", v)}
                  items={[
                    { value: "PF", label: "Pessoa Física", icon: "user" },
                    { value: "PJ", label: "Pessoa Jurídica", icon: "shield" },
                  ]}
                />
              </OiSection>

              <OiSection title="Dados principais">
                <OiCard variant="pad">
                  <OiField
                    label={form.tipo === "PJ" ? "Razão social" : "Nome completo"}
                    required
                    error={errors.nome}
                  >
                    <OiInput
                      value={form.nome}
                      onChangeText={(v) => set("nome", v)}
                      placeholder={form.tipo === "PJ" ? "Gráfica Alfa LTDA" : "João Silva"}
                    />
                  </OiField>
                  {form.tipo === "PJ" && (
                    <OiField label="Nome fantasia">
                      <OiInput
                        value={form.nomeFantasia}
                        onChangeText={(v) => set("nomeFantasia", v)}
                        placeholder="Alfa Comunicação Visual"
                      />
                    </OiField>
                  )}
                  <OiField label={form.tipo === "PJ" ? "CNPJ" : "CPF"}>
                    <OiInput
                      value={form.documento}
                      onChangeText={(v) => set("documento", v)}
                      placeholder={
                        form.tipo === "PJ" ? "00.000.000/0001-00" : "000.000.000-00"
                      }
                      keyboardType="numeric"
                      mono
                    />
                  </OiField>
                  <OiField label="Indicador IE">
                    <OiSeg
                      value={form.indicadorIe}
                      onChange={(v) => set("indicadorIe", v)}
                      items={[
                        { value: "contribuinte", label: "Contribuinte" },
                        { value: "isento", label: "Isento" },
                        { value: "nao_contribuinte", label: "Não" },
                      ]}
                    />
                  </OiField>
                  {form.indicadorIe === "contribuinte" && (
                    <OiField label="Inscrição estadual">
                      <OiInput
                        value={form.inscricaoEstadual}
                        onChangeText={(v) => set("inscricaoEstadual", v)}
                        placeholder="000.000.000.000"
                        mono
                      />
                    </OiField>
                  )}
                </OiCard>
              </OiSection>
            </>
          )}

          {step === 1 && (
            <OiSection title="Contato">
              <OiCard variant="pad">
                <OiField label="Telefone">
                  <OiInput
                    value={form.telefone}
                    onChangeText={(v) => set("telefone", v)}
                    placeholder="(11) 3000-0000"
                    keyboardType="phone-pad"
                  />
                </OiField>
                <OiField label="WhatsApp">
                  <OiInput
                    value={form.whatsapp}
                    onChangeText={(v) => set("whatsapp", v)}
                    placeholder="(11) 99999-9999"
                    keyboardType="phone-pad"
                  />
                </OiField>
                <OiField label="E-mail" error={errors.email}>
                  <OiInput
                    value={form.email}
                    onChangeText={(v) => set("email", v)}
                    placeholder="cliente@email.com"
                    keyboardType="email-address"
                    autoCapitalize="none"
                  />
                </OiField>
                <OiField
                  label="E-mail para NF-e"
                  hint="Recebe notas fiscais automaticamente"
                >
                  <OiInput
                    value={form.emailNfe}
                    onChangeText={(v) => set("emailNfe", v)}
                    placeholder="financeiro@empresa.com"
                    keyboardType="email-address"
                    autoCapitalize="none"
                  />
                </OiField>
              </OiCard>
            </OiSection>
          )}

          {step === 2 && (
            <OiSection title="Endereço">
              <OiCard variant="pad">
                <OiField
                  label="CEP"
                  hint="Preenche logradouro, bairro, cidade, UF e código IBGE"
                >
                  <View style={{ flexDirection: "row", gap: 8 }}>
                    <View style={{ flex: 1 }}>
                      <OiInput
                        value={form.cep}
                        onChangeText={(v) => set("cep", v)}
                        placeholder="00000-000"
                        keyboardType="numeric"
                        mono
                      />
                    </View>
                    <Pressable
                      onPress={buscarCep}
                      disabled={searching === "cep"}
                      style={{
                        backgroundColor: palette.accent,
                        borderRadius: radius.md,
                        paddingHorizontal: 14,
                        justifyContent: "center",
                        alignItems: "center",
                        flexDirection: "row",
                        gap: 6,
                      }}
                    >
                      {searching === "cep" ? (
                        <ActivityIndicator color="#fff" size="small" />
                      ) : (
                        <>
                          <OiIcon name="search" size={14} color="#fff" />
                          <Text
                            style={{
                              color: "#fff",
                              fontFamily: fonts.sansSemibold,
                              fontSize: 13,
                            }}
                          >
                            Buscar
                          </Text>
                        </>
                      )}
                    </Pressable>
                  </View>
                </OiField>
                <OiField label="Logradouro">
                  <OiInput
                    value={form.logradouro}
                    onChangeText={(v) => set("logradouro", v)}
                    placeholder="Rua, avenida..."
                  />
                </OiField>
                <View style={{ flexDirection: "row", gap: 10 }}>
                  <View style={{ width: 100 }}>
                    <OiField label="Número">
                      <OiInput
                        value={form.numero}
                        onChangeText={(v) => set("numero", v)}
                        placeholder="123"
                        keyboardType="numeric"
                      />
                    </OiField>
                  </View>
                  <View style={{ flex: 1 }}>
                    <OiField label="Complemento">
                      <OiInput
                        value={form.complemento}
                        onChangeText={(v) => set("complemento", v)}
                        placeholder="Sala, bloco..."
                      />
                    </OiField>
                  </View>
                </View>
                <OiField label="Bairro">
                  <OiInput
                    value={form.bairro}
                    onChangeText={(v) => set("bairro", v)}
                    placeholder="Centro"
                  />
                </OiField>
                <View style={{ flexDirection: "row", gap: 10 }}>
                  <View style={{ flex: 1 }}>
                    <OiField label="Cidade">
                      <OiInput
                        value={form.cidade}
                        onChangeText={(v) => set("cidade", v)}
                        placeholder="São Paulo"
                      />
                    </OiField>
                  </View>
                  <View style={{ width: 80 }}>
                    <OiField label="UF">
                      <OiInput
                        value={form.uf}
                        onChangeText={(v) => set("uf", v.toUpperCase())}
                        placeholder="SP"
                        maxLength={2}
                        autoCapitalize="characters"
                      />
                    </OiField>
                  </View>
                </View>
                {form.codigoMunicipioIbge ? (
                  <OiField label="Código IBGE (auto)">
                    <OiInput
                      value={form.codigoMunicipioIbge}
                      editable={false}
                      mono
                    />
                  </OiField>
                ) : null}
              </OiCard>
            </OiSection>
          )}

          {step === 3 && (
            <OiSection title="Configurações comerciais">
              <OiCard variant="pad">
                <OiField label="Classificação ABC">
                  <OiSeg
                    value={form.classificacao}
                    onChange={(v) => set("classificacao", v)}
                    items={[
                      { value: "A", label: "A" },
                      { value: "B", label: "B" },
                      { value: "C", label: "C" },
                      { value: "D", label: "D" },
                    ]}
                  />
                </OiField>
                <OiField label="Limite de crédito (R$)">
                  <OiInput
                    value={form.limiteCredito}
                    onChangeText={(v) => set("limiteCredito", v)}
                    placeholder="0,00"
                    keyboardType="numeric"
                    mono
                  />
                </OiField>
                <OiField label="Prazo padrão (dias)">
                  <OiInput
                    value={form.prazoPadraoDias}
                    onChangeText={(v) => set("prazoPadraoDias", v)}
                    placeholder="0"
                    keyboardType="numeric"
                    mono
                  />
                </OiField>
                <OiField label="Observações comerciais">
                  <OiInput
                    value={form.observacoes}
                    onChangeText={(v) => set("observacoes", v)}
                    placeholder="Notas internas, condições especiais..."
                    multiline
                    numberOfLines={3}
                    style={{ minHeight: 70, textAlignVertical: "top" }}
                  />
                </OiField>
              </OiCard>
            </OiSection>
          )}

          {step === 4 && (
            <OiSection title="Consentimento (LGPD)">
              <OiCard variant="pad">
                <OiSwitchRow
                  label="Autoriza contato via WhatsApp"
                  value={form.aceitaWhatsapp}
                  onValueChange={(v) => set("aceitaWhatsapp", v)}
                />
                <OiSwitchRow
                  label="Autoriza envio de NF-e por e-mail"
                  value={form.aceitaEmail}
                  onValueChange={(v) => set("aceitaEmail", v)}
                />
                <OiSwitchRow
                  label="Autoriza comunicações por SMS"
                  value={form.aceitaSms}
                  onValueChange={(v) => set("aceitaSms", v)}
                />
                <View
                  style={{
                    flexDirection: "row",
                    alignItems: "center",
                    gap: 6,
                    marginTop: 8,
                    paddingHorizontal: 10,
                    paddingVertical: 8,
                    backgroundColor: palette.bg2,
                    borderRadius: radius.sm,
                  }}
                >
                  <OiIcon name="shield" size={13} color={palette.textMute} />
                  <Text
                    style={{
                      flex: 1,
                      fontFamily: fonts.sans,
                      fontSize: 11.5,
                      color: palette.textDim,
                    }}
                  >
                    Data e termo de consentimento são registrados automaticamente
                    no salvamento.
                  </Text>
                </View>
              </OiCard>
            </OiSection>
          )}
        </ScrollView>
      )}

      <OiWizardBottomBar
        step={step}
        total={STEPS.length}
        onPrev={() => setStep(Math.max(0, step - 1))}
        onNext={handleNext}
        onCancel={() => router.back()}
        saving={saving}
        isEditing={editing}
      />
    </OiScreen>
  );
}

useMemo; // unused-import guard
