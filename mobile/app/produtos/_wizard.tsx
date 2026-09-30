/**
 * Produto wizard (v2).
 *
 * 5 etapas: Dados · Preços · Estoque · Fiscal · Ficha técnica.
 * Suporta criação (sem `id`) e edição (`id` carregado via tRPC.getById).
 *
 * Replicação fiel de ref/design-v2/screens-novo-produto.jsx.
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
  OiScreen,
  OiSection,
  OiSeg,
  OiStatus,
  OiWizardBottomBar,
  OiWizardSteps,
  type WizardStep,
} from "@/components/oi";
import { useERP } from "@/lib/erp-context";
import {
  useCreateProduto,
  useCustomers,
  useUpdateProduto,
} from "@/lib/erp-queries";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { trpc } from "@/lib/trpc";

const STEPS: ReadonlyArray<WizardStep> = [
  { id: "dados", label: "Dados" },
  { id: "precos", label: "Preços" },
  { id: "estoque", label: "Estoque" },
  { id: "fiscal", label: "Fiscal" },
  { id: "ficha", label: "Ficha técnica" },
];

const CATEGORIAS = [
  "Impressos",
  "Sinalização",
  "Adesivos",
  "Etiquetas",
  "Insumos",
  "Serviços",
];
const UNIDADES = ["UN", "MIL", "CT", "RL", "FL", "M2", "KG"];

type TipoItem = "produto" | "servico" | "insumo";

interface WizardForm {
  tipoItem: TipoItem;
  nome: string;
  sku: string;
  gtin: string;
  categoria: string;
  unidade: string;
  precoStr: string;
  precoCustoStr: string;
  precoPromoStr: string;
  controlaEstoque: boolean;
  estoqueAtualStr: string;
  estoqueMinimoStr: string;
  localizacao: string;
  fornecedorId: string;
  ncm: string;
  cfop: string;
  cest: string;
  origem: string;
  imagemPrincipal: string;
  gramatura: string;
  acabamento: string;
  descricao: string;
}

const EMPTY_FORM: WizardForm = {
  tipoItem: "produto",
  nome: "",
  sku: "",
  gtin: "",
  categoria: "",
  unidade: "UN",
  precoStr: "",
  precoCustoStr: "",
  precoPromoStr: "",
  controlaEstoque: true,
  estoqueAtualStr: "",
  estoqueMinimoStr: "",
  localizacao: "",
  fornecedorId: "",
  ncm: "",
  cfop: "5101",
  cest: "",
  origem: "0",
  imagemPrincipal: "",
  gramatura: "",
  acabamento: "",
  descricao: "",
};

function parseMoney(s: string): number {
  const cleaned = s.replace(/[^\d,.-]/g, "").replace(/\./g, "").replace(",", ".");
  return parseFloat(cleaned) || 0;
}

export interface ProdutoWizardProps {
  produtoId?: string;
  initialStep?: number;
}

export default function ProdutoWizard({ produtoId, initialStep = 0 }: ProdutoWizardProps) {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { addToast } = useERP();
  const editing = !!produtoId;

  const existing = trpc.produtos.getById.useQuery(
    { id: produtoId ?? "" },
    { enabled: !!produtoId },
  );
  const customers = useCustomers();
  const createProduto = useCreateProduto();
  const updateProduto = useUpdateProduto();

  const [step, setStep] = useState(initialStep);
  const [maxReached, setMaxReached] = useState(editing ? STEPS.length - 1 : 0);
  const [form, setForm] = useState<WizardForm>(EMPTY_FORM);
  const [done, setDone] = useState(false);
  const [savedName, setSavedName] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    if (!editing || !existing.data) return;
    const p = existing.data;
    setForm({
      tipoItem: (p.tipoItem ?? "produto") as TipoItem,
      nome: p.nome ?? "",
      sku: p.sku ?? "",
      gtin: p.gtin ?? "",
      categoria: p.categoria ?? "",
      unidade: p.unidade ?? "UN",
      precoStr: p.preco != null ? p.preco.toFixed(2).replace(".", ",") : "",
      precoCustoStr:
        p.precoCusto != null ? p.precoCusto.toFixed(2).replace(".", ",") : "",
      precoPromoStr:
        p.precoPromo != null ? p.precoPromo.toFixed(2).replace(".", ",") : "",
      controlaEstoque: p.controlaEstoque ?? true,
      estoqueAtualStr: p.estoqueAtual != null ? String(p.estoqueAtual) : "",
      estoqueMinimoStr: p.estoqueMinimo != null ? String(p.estoqueMinimo) : "",
      localizacao: p.localizacao ?? "",
      fornecedorId: p.fornecedorId ?? "",
      ncm: p.ncm ?? "",
      cfop: p.cfop ?? "5101",
      cest: p.cest ?? "",
      origem: p.origem ?? "0",
      imagemPrincipal: p.imagemPrincipal ?? "",
      gramatura: p.gramatura ?? "",
      acabamento: p.acabamento ?? "",
      descricao: p.descricao ?? "",
    });
  }, [editing, existing.data]);

  const set = <K extends keyof WizardForm>(key: K, value: WizardForm[K]) =>
    setForm((s) => ({ ...s, [key]: value }));

  const go = (i: number) => {
    setStep(i);
    setMaxReached((m) => Math.max(m, i));
  };

  // Calculated margin
  const preco = parseMoney(form.precoStr);
  const custo = parseMoney(form.precoCustoStr);
  const margem = preco > 0 && custo > 0 ? ((preco - custo) / preco) * 100 : null;

  const fornecedores = useMemo(
    () =>
      (customers.data ?? []).filter((c) =>
        (c.papeis as string[] | null)?.includes("fornecedor"),
      ),
    [customers.data],
  );

  const gerarSku = () => {
    const base = (form.nome || "PROD")
      .toUpperCase()
      .replace(/[^A-Z0-9 ]/g, "")
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map((w) => w.slice(0, 3))
      .join("-");
    const suffix = Math.floor(Math.random() * 90 + 10);
    set("sku", `${base}-${suffix}`);
  };

  const validateStep = (): boolean => {
    const next: Record<string, string> = {};
    if (step === 0) {
      if (!form.nome.trim()) next.nome = "Nome é obrigatório";
      if (!form.categoria) next.categoria = "Selecione uma categoria";
    }
    if (step === 1) {
      if (preco <= 0) next.preco = "Preço deve ser maior que 0";
    }
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const buildPayload = () => ({
    nome: form.nome.trim(),
    categoria: form.categoria,
    preco,
    tipoItem: form.tipoItem,
    sku: form.sku.trim() || null,
    gtin: form.gtin.trim() || null,
    unidade: form.unidade || null,
    precoCusto: custo > 0 ? custo : null,
    precoPromo: parseMoney(form.precoPromoStr) || null,
    margemLucroPercent: margem,
    controlaEstoque: form.controlaEstoque,
    estoqueAtual: parseFloat(form.estoqueAtualStr.replace(",", ".")) || 0,
    estoqueMinimo: parseFloat(form.estoqueMinimoStr.replace(",", ".")) || 0,
    localizacao: form.localizacao.trim() || null,
    fornecedorId: form.fornecedorId || null,
    ncm: form.ncm.trim() || null,
    cfop: form.cfop.trim() || null,
    cest: form.cest.trim() || null,
    origem: form.origem || null,
    imagemPrincipal: form.imagemPrincipal.trim() || null,
    gramatura: form.gramatura.trim() || null,
    acabamento: form.acabamento.trim() || null,
    descricao: form.descricao.trim() || null,
  });

  const saving = createProduto.isPending || updateProduto.isPending;

  const handleNext = async () => {
    if (!validateStep()) return;
    if (step < STEPS.length - 1) {
      go(step + 1);
      return;
    }
    try {
      const payload = buildPayload();
      if (editing && produtoId) {
        await updateProduto.mutateAsync({ id: produtoId, ...payload });
        addToast("sucesso", "Produto atualizado!");
      } else {
        await createProduto.mutateAsync(payload);
        addToast("sucesso", "Produto criado!");
      }
      setSavedName(form.nome.trim() || "Sem nome");
      setDone(true);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao salvar");
    }
  };

  if (done) {
    return (
      <OiScreen edges={["top", "bottom"]}>
        <OiDetailHeader
          title={editing ? "Produto atualizado" : "Produto criado"}
          onBack={() => router.back()}
        />
        <ScrollView contentContainerStyle={{ paddingBottom: 24 }}>
          <OiDoneCard
            title={editing ? "Alterações salvas!" : "Produto salvo!"}
            subtitle={savedName}
          >
            <View
              style={{ flexDirection: "row", marginTop: 10, gap: 6, flexWrap: "wrap", justifyContent: "center" }}
            >
              <OiStatus
                variant="accent"
                label={
                  preco > 0
                    ? `R$ ${preco.toFixed(2).replace(".", ",")}`
                    : "—"
                }
              />
              {form.sku ? <OiStatus variant="neutral" label={form.sku} /> : null}
            </View>
          </OiDoneCard>
          <OiSection>
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
          </OiSection>
        </ScrollView>
      </OiScreen>
    );
  }

  return (
    <OiScreen edges={["top", "bottom"]}>
      <OiDetailHeader
        title={editing ? "Editar produto" : "Novo produto"}
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
              <OiSection title="Tipo">
                <OiSeg
                  value={form.tipoItem}
                  onChange={(v) => set("tipoItem", v)}
                  items={[
                    { value: "produto", label: "Produto", icon: "box" },
                    { value: "servico", label: "Serviço", icon: "zap" },
                    { value: "insumo", label: "Insumo", icon: "layers" },
                  ]}
                />
              </OiSection>
              <OiSection title="Identificação">
                <OiCard variant="pad">
                  <OiField label="Nome do produto" required error={errors.nome}>
                    <OiInput
                      value={form.nome}
                      onChangeText={(v) => set("nome", v)}
                      placeholder="Ex: Cartão de visita 9x5 4/4"
                    />
                  </OiField>
                  <OiField label="SKU / Código interno">
                    <View style={{ flexDirection: "row", gap: 8 }}>
                      <View style={{ flex: 1 }}>
                        <OiInput
                          value={form.sku}
                          onChangeText={(v) => set("sku", v)}
                          placeholder="CV-9X5-44"
                          mono
                          autoCapitalize="characters"
                        />
                      </View>
                      <Pressable
                        onPress={gerarSku}
                        style={{
                          borderColor: palette.border,
                          borderWidth: 1,
                          borderRadius: radius.md,
                          paddingHorizontal: 12,
                          justifyContent: "center",
                          alignItems: "center",
                          flexDirection: "row",
                          gap: 6,
                        }}
                      >
                        <OiIcon name="zap" size={14} color={palette.text} />
                        <Text
                          style={{
                            fontFamily: fonts.sansSemibold,
                            fontSize: 12.5,
                            color: palette.text,
                          }}
                        >
                          Gerar
                        </Text>
                      </Pressable>
                    </View>
                  </OiField>
                  <OiField
                    label="Código de barras (GTIN/EAN)"
                    hint="Câmera para bipar — em breve"
                  >
                    <OiInput
                      value={form.gtin}
                      onChangeText={(v) => set("gtin", v)}
                      placeholder="789..."
                      keyboardType="numeric"
                      mono
                    />
                  </OiField>
                  <OiField
                    label="Categoria"
                    required
                    error={errors.categoria}
                  >
                    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
                      {CATEGORIAS.map((c) => {
                        const on = c === form.categoria;
                        return (
                          <Pressable
                            key={c}
                            onPress={() => set("categoria", c)}
                            style={{
                              paddingHorizontal: 12,
                              paddingVertical: 7,
                              borderRadius: radius.pill,
                              borderColor: on ? palette.accent : palette.border,
                              borderWidth: 1,
                              backgroundColor: on
                                ? hexAlpha(palette.accent, 0.12)
                                : palette.bg2,
                            }}
                          >
                            <Text
                              style={{
                                fontFamily: fonts.sansSemibold,
                                fontSize: 12.5,
                                color: on ? palette.accent : palette.textDim,
                              }}
                            >
                              {c}
                            </Text>
                          </Pressable>
                        );
                      })}
                    </View>
                  </OiField>
                  <OiField label="Unidade">
                    <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
                      {UNIDADES.map((u) => {
                        const on = u === form.unidade;
                        return (
                          <Pressable
                            key={u}
                            onPress={() => set("unidade", u)}
                            style={{
                              paddingHorizontal: 12,
                              paddingVertical: 7,
                              borderRadius: radius.sm,
                              borderColor: on ? palette.accent : palette.border,
                              borderWidth: 1,
                              backgroundColor: on
                                ? hexAlpha(palette.accent, 0.12)
                                : palette.surface,
                            }}
                          >
                            <Text
                              style={{
                                fontFamily: fonts.monoSemibold,
                                fontSize: 12,
                                color: on ? palette.accent : palette.textDim,
                              }}
                            >
                              {u}
                            </Text>
                          </Pressable>
                        );
                      })}
                    </View>
                  </OiField>
                </OiCard>
              </OiSection>
            </>
          )}

          {step === 1 && (
            <OiSection title="Preços">
              <OiCard variant="pad">
                <OiField label="Preço de venda (R$)" required error={errors.preco}>
                  <OiInput
                    value={form.precoStr}
                    onChangeText={(v) => set("precoStr", v)}
                    placeholder="0,00"
                    keyboardType="numeric"
                    mono
                  />
                </OiField>
                <OiField label="Custo (R$)">
                  <OiInput
                    value={form.precoCustoStr}
                    onChangeText={(v) => set("precoCustoStr", v)}
                    placeholder="0,00"
                    keyboardType="numeric"
                    mono
                  />
                </OiField>
                {margem !== null && (
                  <View
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      gap: 6,
                      backgroundColor: hexAlpha(
                        margem < 20 ? palette.danger : palette.ok,
                        0.12,
                      ),
                      borderRadius: radius.sm,
                      padding: 8,
                    }}
                  >
                    <OiIcon
                      name="chart"
                      size={13}
                      color={margem < 20 ? palette.danger : palette.ok}
                    />
                    <Text
                      style={{
                        fontFamily: fonts.sansSemibold,
                        fontSize: 12,
                        color: margem < 20 ? palette.danger : palette.ok,
                      }}
                    >
                      Margem de {margem.toFixed(1)}% · lucro R${" "}
                      {(preco - custo).toFixed(2).replace(".", ",")} por {form.unidade}
                    </Text>
                  </View>
                )}
                <OiField
                  label="Preço promocional"
                  hint="Opcional — usado em campanhas"
                >
                  <OiInput
                    value={form.precoPromoStr}
                    onChangeText={(v) => set("precoPromoStr", v)}
                    placeholder="0,00"
                    keyboardType="numeric"
                    mono
                  />
                </OiField>
              </OiCard>
            </OiSection>
          )}

          {step === 2 && (
            <OiSection title="Estoque">
              <OiCard variant="pad">
                <OiCheckRow
                  on={form.controlaEstoque}
                  onToggle={() => set("controlaEstoque", !form.controlaEstoque)}
                >
                  Controlar estoque deste item
                </OiCheckRow>
                {form.controlaEstoque ? (
                  <>
                    <View style={{ flexDirection: "row", gap: 10, marginTop: 10 }}>
                      <View style={{ flex: 1 }}>
                        <OiField label="Estoque atual">
                          <OiInput
                            value={form.estoqueAtualStr}
                            onChangeText={(v) => set("estoqueAtualStr", v)}
                            placeholder="0"
                            keyboardType="numeric"
                            mono
                          />
                        </OiField>
                      </View>
                      <View style={{ flex: 1 }}>
                        <OiField label="Estoque mínimo">
                          <OiInput
                            value={form.estoqueMinimoStr}
                            onChangeText={(v) => set("estoqueMinimoStr", v)}
                            placeholder="0"
                            keyboardType="numeric"
                            mono
                          />
                        </OiField>
                      </View>
                    </View>
                    <OiField label="Localização">
                      <OiInput
                        value={form.localizacao}
                        onChangeText={(v) => set("localizacao", v)}
                        placeholder="Prateleira, gaveta..."
                      />
                    </OiField>
                    <OiField label="Fornecedor padrão">
                      <View style={{ gap: 6 }}>
                        {fornecedores.length === 0 ? (
                          <Text
                            style={{
                              fontSize: 12,
                              fontFamily: fonts.sans,
                              color: palette.textMute,
                            }}
                          >
                            Nenhum fornecedor cadastrado (use o cadastro de Pessoa
                            com papel “Fornecedor”).
                          </Text>
                        ) : (
                          fornecedores.map((c) => {
                            const on = c.id === form.fornecedorId;
                            return (
                              <Pressable
                                key={c.id}
                                onPress={() =>
                                  set("fornecedorId", on ? "" : c.id)
                                }
                                style={{
                                  paddingHorizontal: 10,
                                  paddingVertical: 9,
                                  borderRadius: radius.md,
                                  borderColor: on ? palette.accent : palette.border,
                                  borderWidth: 1,
                                  backgroundColor: on
                                    ? hexAlpha(palette.accent, 0.10)
                                    : palette.surface,
                                  flexDirection: "row",
                                  alignItems: "center",
                                  gap: 8,
                                }}
                              >
                                <OiIcon
                                  name={on ? "check" : "user"}
                                  size={14}
                                  color={on ? palette.accent : palette.textDim}
                                />
                                <Text
                                  style={{
                                    fontFamily: fonts.sansMedium,
                                    fontSize: 13,
                                    color: on ? palette.accent : palette.text,
                                  }}
                                >
                                  {c.nome}
                                </Text>
                              </Pressable>
                            );
                          })
                        )}
                      </View>
                    </OiField>
                  </>
                ) : (
                  <View
                    style={{
                      backgroundColor: palette.bg2,
                      borderRadius: radius.sm,
                      padding: 10,
                      marginTop: 10,
                      flexDirection: "row",
                      gap: 6,
                      alignItems: "center",
                    }}
                  >
                    <OiIcon name="zap" size={13} color={palette.textMute} />
                    <Text
                      style={{
                        fontFamily: fonts.sans,
                        fontSize: 12,
                        color: palette.textDim,
                        flex: 1,
                      }}
                    >
                      Item sem controle de estoque (serviço ou sob demanda).
                    </Text>
                  </View>
                )}
              </OiCard>
            </OiSection>
          )}

          {step === 3 && (
            <OiSection title="Dados fiscais">
              <OiCard variant="pad">
                <View style={{ flexDirection: "row", gap: 10 }}>
                  <View style={{ flex: 1 }}>
                    <OiField label="NCM">
                      <OiInput
                        value={form.ncm}
                        onChangeText={(v) => set("ncm", v)}
                        placeholder="0000.00.00"
                        keyboardType="numeric"
                        mono
                      />
                    </OiField>
                  </View>
                  <View style={{ width: 110 }}>
                    <OiField label="CFOP">
                      <OiInput
                        value={form.cfop}
                        onChangeText={(v) => set("cfop", v)}
                        placeholder="5101"
                        keyboardType="numeric"
                        mono
                      />
                    </OiField>
                  </View>
                </View>
                <OiField label="Origem">
                  <OiSeg
                    value={form.origem}
                    onChange={(v) => set("origem", v)}
                    items={[
                      { value: "0", label: "0 — Nacional" },
                      { value: "1", label: "1 — Import. dir." },
                      { value: "2", label: "2 — Merc. interno" },
                    ]}
                  />
                </OiField>
                <OiField
                  label="CEST"
                  hint="Preenchido conforme o NCM, quando aplicável"
                >
                  <OiInput
                    value={form.cest}
                    onChangeText={(v) => set("cest", v)}
                    placeholder="00.000.00"
                    keyboardType="numeric"
                    mono
                  />
                </OiField>
              </OiCard>
            </OiSection>
          )}

          {step === 4 && (
            <OiSection title="Ficha técnica">
              <OiCard variant="pad">
                <OiField label="Foto do produto">
                  <Pressable
                    onPress={() =>
                      addToast("info", "Upload de imagem em breve")
                    }
                    style={{
                      height: 96,
                      borderColor: palette.border,
                      borderWidth: 1,
                      borderStyle: "dashed",
                      borderRadius: radius.md,
                      alignItems: "center",
                      justifyContent: "center",
                      gap: 6,
                      backgroundColor: palette.bg,
                    }}
                  >
                    <OiIcon name="image" size={24} color={palette.textMute} />
                    <Text
                      style={{
                        fontFamily: fonts.sansMedium,
                        fontSize: 11,
                        color: palette.textMute,
                      }}
                    >
                      Adicionar foto
                    </Text>
                  </Pressable>
                </OiField>
                {form.tipoItem !== "servico" && (
                  <View style={{ flexDirection: "row", gap: 10 }}>
                    <View style={{ flex: 1 }}>
                      <OiField label="Gramatura / material">
                        <OiInput
                          value={form.gramatura}
                          onChangeText={(v) => set("gramatura", v)}
                          placeholder="250g couchê"
                        />
                      </OiField>
                    </View>
                    <View style={{ flex: 1 }}>
                      <OiField label="Acabamento">
                        <OiInput
                          value={form.acabamento}
                          onChangeText={(v) => set("acabamento", v)}
                          placeholder="Laminação fosca"
                        />
                      </OiField>
                    </View>
                  </View>
                )}
                <OiField label="Descrição / observações">
                  <OiInput
                    value={form.descricao}
                    onChangeText={(v) => set("descricao", v)}
                    placeholder="Detalhes que ajudam vendas e produção..."
                    multiline
                    numberOfLines={3}
                    style={{ minHeight: 80, textAlignVertical: "top" }}
                  />
                </OiField>
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
