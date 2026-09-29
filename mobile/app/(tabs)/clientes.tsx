/**
 * Clientes (CRM) — Oimpresso design.
 *
 * Search debounced + lista agrupada por inicial (A/B/C…) com avatar circular,
 * tipo PF/PJ pill e telefone. Toda a lógica preservada: create/update/delete
 * customer + WhatsApp sub-modal.
 */
import React, { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";
import { useRouter } from "expo-router";

import { FormInput, ModalDialog, Select, Toast } from "@/components/erp-ui";
import {
  OiAvatar,
  OiCard,
  OiEmpty,
  OiFab,
  OiHeader,
  OiIcon,
  OiList,
  OiListRow,
  OiScreen,
  OiSearch,
  OiSection,
  OiStatus,
} from "@/components/oi";
import { useERP } from "@/lib/erp-context";
import {
  useCreateCustomer,
  useCustomers,
  useDeleteCustomer,
  useSendWhatsappCustom,
  useUpdateCustomer,
} from "@/lib/erp-queries";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fonts, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

type Tipo = "PF" | "PJ";

interface CustomerForm {
  nome: string;
  tipo: Tipo;
  documento: string;
  telefone: string;
  email: string;
  endereco: string;
  observacoes: string;
}

const EMPTY_FORM: CustomerForm = {
  nome: "",
  tipo: "PF",
  documento: "",
  telefone: "",
  email: "",
  endereco: "",
  observacoes: "",
};

const TIPO_OPTIONS = [
  { label: "Pessoa Física", value: "PF" },
  { label: "Pessoa Jurídica", value: "PJ" },
];

export default function ClientesScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { ui, addToast } = useERP();
  const customersQuery = useCustomers();
  const createCustomer = useCreateCustomer();
  const updateCustomer = useUpdateCustomer();
  const deleteCustomer = useDeleteCustomer();

  const customers = customersQuery.data ?? [];

  const [editingId, setEditingId] = useState<string | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [form, setForm] = useState<CustomerForm>(EMPTY_FORM);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);

  const [waVisible, setWaVisible] = useState(false);
  const [waMessage, setWaMessage] = useState("");
  const sendWhatsapp = useSendWhatsappCustom();

  const setField = <K extends keyof CustomerForm>(key: K, value: CustomerForm[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  const resetForm = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setErrors({});
  };

  /**
   * v2: criação migrou para o wizard de 5 etapas em /clientes/new. O OiSheet
   * permanece somente para edição rápida (campo único).
   */
  const openCreate = () => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    router.push("/clientes/new" as any);
  };

  const openEdit = (c: (typeof customers)[number]) => {
    setEditingId(c.id);
    setForm({
      nome: c.nome,
      tipo: c.tipo,
      documento: c.documento ?? "",
      telefone: c.telefone ?? "",
      email: c.email ?? "",
      endereco: c.endereco ?? "",
      observacoes: c.observacoes ?? "",
    });
    setErrors({});
    setModalVisible(true);
  };

  const validate = () => {
    const next: Record<string, string> = {};
    if (!form.nome.trim()) next.nome = "Nome é obrigatório";
    if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
      next.email = "Email inválido";
    }
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const handleSave = async () => {
    if (!validate()) return;
    const payload = {
      nome: form.nome.trim(),
      tipo: form.tipo,
      documento: form.documento.trim() || undefined,
      telefone: form.telefone.trim() || undefined,
      email: form.email.trim() || undefined,
      endereco: form.endereco.trim() || undefined,
      observacoes: form.observacoes.trim() || undefined,
    };
    try {
      if (editingId) {
        await updateCustomer.mutateAsync({ id: editingId, ...payload });
        addToast("sucesso", `Cliente "${payload.nome}" atualizado!`);
      } else {
        await createCustomer.mutateAsync(payload);
        addToast("sucesso", `Cliente "${payload.nome}" adicionado!`);
      }
      setModalVisible(false);
      resetForm();
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao salvar cliente");
    }
  };

  const handleDelete = async (c: (typeof customers)[number]) => {
    try {
      await deleteCustomer.mutateAsync({ id: c.id });
      addToast("sucesso", `Cliente "${c.nome}" removido!`);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro ao remover cliente");
    }
  };

  const filtered = useMemo(() => {
    const term = debouncedSearch.trim().toLowerCase();
    const items = term
      ? customers.filter(
          (c) =>
            c.nome.toLowerCase().includes(term) ||
            (c.documento ?? "").toLowerCase().includes(term) ||
            (c.telefone ?? "").toLowerCase().includes(term),
        )
      : customers.slice();
    return items.sort((a, b) => a.nome.localeCompare(b.nome));
  }, [customers, debouncedSearch]);

  // Group by initial letter
  const grouped = useMemo(() => {
    const out: Record<string, typeof filtered> = {};
    for (const c of filtered) {
      const k = (c.nome[0] ?? "?").toUpperCase();
      (out[k] = out[k] ?? []).push(c);
    }
    return out;
  }, [filtered]);
  const groupKeys = useMemo(() => Object.keys(grouped).sort(), [grouped]);

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Clientes"
        eyebrow={`${customers.length} cadastrado${customers.length === 1 ? "" : "s"}`}
        actions={[{ icon: "filter", onPress: () => {} }]}
      >
        <OiSearch
          value={search}
          onChangeText={setSearch}
          placeholder="Nome, CNPJ, telefone…"
        />
      </OiHeader>

      {ui.toasts.length > 0 ? (
        <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>
      ) : null}

      <ScrollView
        contentContainerStyle={{ paddingBottom: 96 }}
        showsVerticalScrollIndicator={false}
      >
        {customersQuery.isLoading ? (
          <View style={{ paddingVertical: 48, alignItems: "center" }}>
            <ActivityIndicator color={palette.accent} />
          </View>
        ) : customersQuery.isError ? (
          <OiCard style={{ margin: 16 }}>
            <Text style={{ color: palette.danger, fontFamily: fonts.sans }}>
              {customersQuery.error?.message ?? "Erro ao carregar clientes"}
            </Text>
          </OiCard>
        ) : filtered.length === 0 ? (
          <OiEmpty
            icon="user"
            title={customers.length === 0 ? "Nenhum cliente" : "Nada encontrado"}
            subtitle={
              customers.length === 0
                ? "Cadastre o primeiro cliente para começar."
                : `Nenhum resultado para "${search}".`
            }
            action={
              customers.length === 0
                ? { label: "Novo cliente", onPress: openCreate }
                : undefined
            }
          />
        ) : (
          groupKeys.map((k) => (
            <View key={k}>
              <View
                style={{
                  paddingHorizontal: 16,
                  paddingTop: 10,
                  paddingBottom: 4,
                  backgroundColor: palette.bg2,
                }}
              >
                <Text
                  style={{
                    fontSize: 11,
                    fontFamily: fonts.sansBold,
                    letterSpacing: 1,
                    color: palette.textMute,
                  }}
                >
                  {k}
                </Text>
              </View>
              <OiList>
                {grouped[k].map((c, idx) => (
                  <OiListRow
                    key={c.id}
                    title={c.nome}
                    subtitle={`${c.tipo}${c.documento ? " · " + c.documento : ""}${c.telefone ? " · " + c.telefone : ""}`}
                    onPress={() => router.push(`/clientes/${c.id}` as never)}
                    last={idx === grouped[k].length - 1}
                    left={
                      <OiAvatar
                        label={c.nome
                          .split(" ")
                          .slice(0, 2)
                          .map((w) => w[0] ?? "")
                          .join("")
                          .toUpperCase()}
                      />
                    }
                    right={
                      <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                        <OiStatus
                          label={c.tipo}
                          variant={c.tipo === "PJ" ? "accent" : "neutral"}
                        />
                        <Pressable
                          onPress={() => handleDelete(c)}
                          hitSlop={8}
                          style={{
                            width: 32,
                            height: 32,
                            alignItems: "center",
                            justifyContent: "center",
                            borderRadius: radius.sm,
                          }}
                        >
                          <OiIcon name="trash" size={16} color={palette.textMute} />
                        </Pressable>
                      </View>
                    }
                  />
                ))}
              </OiList>
            </View>
          ))
        )}
      </ScrollView>

      <OiFab onPress={openCreate} bottom={24} />

      <ModalDialog
        visible={modalVisible}
        title={editingId ? "Editar Cliente" : "Novo Cliente"}
        onClose={() => {
          setModalVisible(false);
          resetForm();
        }}
        onConfirm={handleSave}
        confirmText={editingId ? "Salvar" : "Adicionar"}
        cancelText="Cancelar"
      >
        <FormInput
          label="Nome"
          placeholder="Ex: João da Silva"
          value={form.nome}
          onChangeText={(v) => setField("nome", v)}
          error={errors.nome}
          required
          returnKeyType="next"
        />
        <Select
          label="Tipo"
          options={TIPO_OPTIONS}
          value={form.tipo}
          onValueChange={(v) => setField("tipo", v as Tipo)}
          required
        />
        <FormInput
          label="Documento (CPF/CNPJ)"
          placeholder="Apenas números"
          value={form.documento}
          onChangeText={(v) => setField("documento", v)}
          returnKeyType="next"
        />
        <FormInput
          label="Telefone"
          placeholder="Ex: 11999999999"
          value={form.telefone}
          onChangeText={(v) => setField("telefone", v)}
          returnKeyType="next"
        />
        <FormInput
          label="Email"
          placeholder="email@exemplo.com"
          value={form.email}
          onChangeText={(v) => setField("email", v)}
          error={errors.email}
          keyboardType="email-address"
          returnKeyType="next"
        />
        <MultilineInput
          label="Endereço"
          placeholder="Rua, número, bairro, cidade…"
          value={form.endereco}
          onChange={(v) => setField("endereco", v)}
        />
        <MultilineInput
          label="Observações"
          placeholder="Notas internas"
          value={form.observacoes}
          onChange={(v) => setField("observacoes", v)}
        />
        {editingId && form.telefone.trim() ? (
          <Pressable
            onPress={() => {
              setWaMessage(`Olá ${form.nome.trim()}, `);
              setWaVisible(true);
            }}
            style={{
              backgroundColor: palette.ok,
              paddingHorizontal: 12,
              paddingVertical: 10,
              borderRadius: radius.md,
              marginTop: 8,
              alignItems: "center",
            }}
          >
            <Text
              style={{
                color: "#fff",
                fontFamily: fonts.sansSemibold,
                fontSize: 13,
              }}
            >
              Enviar WhatsApp
            </Text>
          </Pressable>
        ) : null}
      </ModalDialog>

      <ModalDialog
        visible={waVisible}
        title="Enviar WhatsApp"
        onClose={() => setWaVisible(false)}
        onConfirm={async () => {
          if (!waMessage.trim() || !editingId) return;
          try {
            const res = await sendWhatsapp.mutateAsync({
              customerId: editingId,
              mensagem: waMessage.trim(),
            });
            if (res.ok) addToast("sucesso", "WhatsApp enviado!");
            else addToast("erro", res.error ?? "Falha ao enviar");
            setWaVisible(false);
          } catch (err) {
            addToast("erro", err instanceof Error ? err.message : "Erro ao enviar");
          }
        }}
        confirmText="Enviar"
        cancelText="Cancelar"
      >
        <MultilineInput
          label={`Mensagem para ${form.telefone}`}
          placeholder="Digite a mensagem"
          value={waMessage}
          onChange={setWaMessage}
        />
      </ModalDialog>
    </OiScreen>
  );
}

function MultilineInput({
  label,
  placeholder,
  value,
  onChange,
}: {
  label: string;
  placeholder: string;
  value: string;
  onChange: (v: string) => void;
}) {
  const { palette } = useOiTheme();
  return (
    <View style={{ marginBottom: 12 }}>
      <Text
        style={{
          fontSize: 12,
          fontFamily: fonts.sansMedium,
          color: palette.text,
          marginBottom: 6,
        }}
      >
        {label}
      </Text>
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={palette.textMute}
        multiline
        numberOfLines={3}
        style={{
          borderColor: palette.border,
          borderWidth: 1,
          borderRadius: radius.md,
          paddingHorizontal: 12,
          paddingVertical: 8,
          color: palette.text,
          fontFamily: fonts.sans,
          minHeight: 70,
        }}
        textAlignVertical="top"
      />
    </View>
  );
}
