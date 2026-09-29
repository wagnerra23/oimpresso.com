import React, { useMemo, useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from "react-native";
import { router } from "expo-router";

import { OiHeader, OiScreen } from "@/components/oi";
import { FormInput, ModalDialog, Select, Toast } from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useCreateVehicle,
  useCustomers,
  useDeleteVehicle,
  useUpdateVehicle,
  useVehicleHistory,
  useVehicles,
} from "@/lib/erp-queries";
import { useDebouncedValue } from "@/hooks/use-debounced-value";

interface VehicleForm {
  customerId: string;
  placa: string;
  chassi: string;
  marca: string;
  modelo: string;
  ano: string;
  cor: string;
  kmAtual: string;
  observacoes: string;
}

const EMPTY_FORM: VehicleForm = {
  customerId: "",
  placa: "",
  chassi: "",
  marca: "",
  modelo: "",
  ano: "",
  cor: "",
  kmAtual: "",
  observacoes: "",
};

const STATUS_LABELS: Record<string, string> = {
  recepcao: "Recepção",
  diagnostico: "Diagnóstico",
  orcamento: "Orçamento",
  aguardando_aprovacao: "Aguard. Aprovação",
  aguardando_pecas: "Aguard. Peças",
  em_execucao: "Em Execução",
  revisao: "Revisão",
  pronto: "Pronto",
  entregue: "Entregue",
};

const brl = (n: number) =>
  new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(n);

export default function VeiculosScreen() {
  const { ui, addToast } = useERP();
  const vehiclesQuery = useVehicles();
  const customersQuery = useCustomers();
  const createVehicle = useCreateVehicle();
  const updateVehicle = useUpdateVehicle();
  const deleteVehicle = useDeleteVehicle();

  const vehicles = vehiclesQuery.data ?? [];
  const customers = customersQuery.data ?? [];

  const [editingId, setEditingId] = useState<string | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [form, setForm] = useState<VehicleForm>(EMPTY_FORM);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);

  const [detailId, setDetailId] = useState<string | null>(null);

  const setField = <K extends keyof VehicleForm>(key: K, value: VehicleForm[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  const resetForm = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setErrors({});
  };

  const openCreate = () => {
    resetForm();
    if (customers[0]) setField("customerId", customers[0].id);
    setModalVisible(true);
  };

  const openEdit = (v: (typeof vehicles)[number]) => {
    setEditingId(v.id);
    setForm({
      customerId: v.customerId ?? "",
      placa: v.placa,
      chassi: v.chassi ?? "",
      marca: v.marca,
      modelo: v.modelo,
      ano: v.ano != null ? String(v.ano) : "",
      cor: v.cor ?? "",
      kmAtual: String(v.kmAtual ?? 0),
      observacoes: v.observacoes ?? "",
    });
    setErrors({});
    setModalVisible(true);
  };

  const validate = () => {
    const next: Record<string, string> = {};
    if (!form.customerId) next.customerId = "Cliente é obrigatório";
    if (!form.placa.trim()) next.placa = "Placa é obrigatória";
    if (!form.marca.trim()) next.marca = "Marca é obrigatória";
    if (!form.modelo.trim()) next.modelo = "Modelo é obrigatório";
    if (form.chassi.trim() && form.chassi.trim().length > 17)
      next.chassi = "Chassi tem no máximo 17 caracteres";
    if (form.ano.trim() && !/^\d{4}$/.test(form.ano.trim()))
      next.ano = "Ano deve ter 4 dígitos";
    if (form.kmAtual.trim() && !/^\d+$/.test(form.kmAtual.trim()))
      next.kmAtual = "KM deve ser número inteiro";
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const handleSave = async () => {
    if (!validate()) return;
    const payload = {
      customerId: form.customerId,
      placa: form.placa.trim().toUpperCase(),
      chassi: form.chassi.trim() || undefined,
      marca: form.marca.trim(),
      modelo: form.modelo.trim(),
      ano: form.ano.trim() ? parseInt(form.ano, 10) : undefined,
      cor: form.cor.trim() || undefined,
      kmAtual: form.kmAtual.trim() ? parseInt(form.kmAtual, 10) : 0,
      observacoes: form.observacoes.trim() || undefined,
    };
    try {
      if (editingId) {
        await updateVehicle.mutateAsync({ id: editingId, ...payload });
        addToast("sucesso", `Veículo ${payload.placa} atualizado!`);
      } else {
        await createVehicle.mutateAsync(payload);
        addToast("sucesso", `Veículo ${payload.placa} criado!`);
      }
      setModalVisible(false);
      resetForm();
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao salvar veículo",
      );
    }
  };

  const handleDelete = async (v: (typeof vehicles)[number]) => {
    try {
      await deleteVehicle.mutateAsync({ id: v.id });
      addToast("sucesso", `Veículo ${v.placa} removido!`);
    } catch (err) {
      addToast(
        "erro",
        err instanceof Error ? err.message : "Erro ao remover veículo",
      );
    }
  };

  const filtered = useMemo(() => {
    const term = debouncedSearch.trim().toLowerCase();
    if (!term) return vehicles;
    return vehicles.filter(
      (v) =>
        v.placa.toLowerCase().includes(term) ||
        v.marca.toLowerCase().includes(term) ||
        v.modelo.toLowerCase().includes(term) ||
        (v.customer?.nome ?? "").toLowerCase().includes(term),
    );
  }, [vehicles, debouncedSearch]);

  const customerOptions = useMemo(
    () => customers.map((c) => ({ label: c.nome, value: c.id })),
    [customers],
  );

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Veículos"
        eyebrow={`${vehicles.length} veículo${vehicles.length === 1 ? "" : "s"}`}
        actions={[{ icon: "plus", onPress: openCreate }]}
      />
      <ScrollView className="flex-1" contentContainerStyle={{ paddingBottom: 20 }}>

        <View className="px-4 pt-4">
          {ui.toasts.map((toast) => (
            <Toast key={toast.id} tipo={toast.tipo} mensagem={toast.mensagem} />
          ))}
        </View>

        <View className="px-4 pt-2">
          <SearchInput
            value={search}
            onChange={setSearch}
            placeholder="Buscar placa, marca, modelo ou cliente"
          />
        </View>

        <View className="px-4 py-4">
          {vehiclesQuery.isLoading ? (
            <View className="py-12 items-center">
              <ActivityIndicator />
            </View>
          ) : vehiclesQuery.isError ? (
            <View className="bg-error/10 rounded-lg p-4">
              <Text className="text-error text-sm">
                {vehiclesQuery.error?.message ?? "Erro ao carregar veículos"}
              </Text>
            </View>
          ) : filtered.length > 0 ? (
            filtered.map((v) => (
              <View
                key={v.id}
                className="bg-surface rounded-lg p-3 mb-2 border border-border"
              >
                <View className="flex-row justify-between items-start">
                  <Pressable onPress={() => openEdit(v)} className="flex-1">
                    <Text className="text-lg font-bold text-foreground">
                      {v.placa}
                    </Text>
                    <Text className="text-sm text-foreground mt-1">
                      {v.marca} {v.modelo}
                      {v.ano ? ` (${v.ano})` : ""}
                    </Text>
                    {v.cor ? (
                      <Text className="text-xs text-muted mt-1">Cor: {v.cor}</Text>
                    ) : null}
                    <Text className="text-xs text-muted mt-1">
                      KM: {v.kmAtual?.toLocaleString("pt-BR") ?? 0}
                    </Text>
                    {v.customer ? (
                      <Text className="text-xs text-muted mt-1">
                        Cliente: {v.customer.nome}
                      </Text>
                    ) : null}
                  </Pressable>
                  <Pressable onPress={() => handleDelete(v)} className="p-2" hitSlop={8}>
                    <Text className="text-error text-lg">×</Text>
                  </Pressable>
                </View>
                <View className="flex-row gap-2 mt-3">
                  <Pressable
                    onPress={() => setDetailId(v.id)}
                    className="bg-primary/10 px-3 py-1.5 rounded"
                  >
                    <Text className="text-primary text-xs font-semibold">
                      Ver histórico
                    </Text>
                  </Pressable>
                </View>
              </View>
            ))
          ) : (
            <View className="bg-surface rounded-lg p-6 items-center">
              <Text className="text-muted text-sm">
                {vehicles.length === 0
                  ? "Nenhum veículo cadastrado"
                  : `Nada encontrado para "${search}"`}
              </Text>
            </View>
          )}
        </View>
      </ScrollView>

      <ModalDialog
        visible={modalVisible}
        title={editingId ? "Editar Veículo" : "Novo Veículo"}
        onClose={() => {
          setModalVisible(false);
          resetForm();
        }}
        onConfirm={handleSave}
        confirmText={editingId ? "Salvar Alterações" : "Adicionar"}
        cancelText="Cancelar"
      >
        {customers.length === 0 ? (
          <View className="bg-warning/10 rounded-lg p-3 mb-3">
            <Text className="text-sm text-foreground mb-2">
              Você precisa cadastrar um cliente primeiro.
            </Text>
            <Pressable
              onPress={() => {
                setModalVisible(false);
                router.push("/clientes");
              }}
              className="bg-primary px-3 py-2 rounded-lg"
            >
              <Text className="text-white text-sm font-semibold text-center">
                + Criar cliente
              </Text>
            </Pressable>
          </View>
        ) : (
          <Select
            label="Cliente"
            options={customerOptions}
            value={form.customerId}
            onValueChange={(v) => setField("customerId", v)}
            error={errors.customerId}
            required
          />
        )}
        <FormInput
          label="Placa"
          placeholder="ABC1D23 ou ABC-1234"
          value={form.placa}
          onChangeText={(v) => setField("placa", v.toUpperCase())}
          error={errors.placa}
          autoCapitalize="characters"
          required
          returnKeyType="next"
        />
        <FormInput
          label="Chassi"
          placeholder="17 caracteres (opcional)"
          value={form.chassi}
          onChangeText={(v) => setField("chassi", v.toUpperCase())}
          error={errors.chassi}
          autoCapitalize="characters"
          returnKeyType="next"
        />
        <FormInput
          label="Marca"
          placeholder="Ex: Volkswagen"
          value={form.marca}
          onChangeText={(v) => setField("marca", v)}
          error={errors.marca}
          required
          returnKeyType="next"
        />
        <FormInput
          label="Modelo"
          placeholder="Ex: Gol"
          value={form.modelo}
          onChangeText={(v) => setField("modelo", v)}
          error={errors.modelo}
          required
          returnKeyType="next"
        />
        <FormInput
          label="Ano"
          placeholder="2020"
          value={form.ano}
          onChangeText={(v) => setField("ano", v)}
          error={errors.ano}
          keyboardType="numeric"
          returnKeyType="next"
        />
        <FormInput
          label="Cor"
          placeholder="Ex: Prata"
          value={form.cor}
          onChangeText={(v) => setField("cor", v)}
          returnKeyType="next"
        />
        <FormInput
          label="KM Atual"
          placeholder="0"
          value={form.kmAtual}
          onChangeText={(v) => setField("kmAtual", v)}
          error={errors.kmAtual}
          keyboardType="numeric"
          returnKeyType="next"
        />
        <MultilineInput
          label="Observações"
          placeholder="Notas internas"
          value={form.observacoes}
          onChange={(v) => setField("observacoes", v)}
        />
      </ModalDialog>

      <VehicleHistoryModal
        visible={!!detailId}
        vehicleId={detailId}
        onClose={() => setDetailId(null)}
      />
    </OiScreen>
  );
}

function VehicleHistoryModal({
  visible,
  vehicleId,
  onClose,
}: {
  visible: boolean;
  vehicleId: string | null;
  onClose: () => void;
}) {
  const historyQuery = useVehicleHistory(vehicleId ?? undefined);
  const history = historyQuery.data ?? [];

  return (
    <ModalDialog
      visible={visible}
      title="Histórico de OSs"
      onClose={onClose}
      showConfirmation={false}
    >
      {historyQuery.isLoading ? (
        <ActivityIndicator />
      ) : history.length === 0 ? (
        <Text className="text-muted text-sm py-4 text-center">
          Nenhuma OS registrada para este veículo
        </Text>
      ) : (
        history.map((h) => (
          <Pressable
            key={h.id}
            onPress={() => {
              onClose();
              router.push(`/oss/${h.id}` as any);
            }}
            className="bg-surface rounded-lg p-3 mb-2 border border-border"
          >
            <View className="flex-row justify-between items-start">
              <View className="flex-1">
                <Text className="font-bold text-foreground">OS #{h.numero}</Text>
                <Text className="text-xs text-muted mt-1">
                  {new Date(h.dataEntrada).toLocaleDateString("pt-BR")}
                </Text>
                <Text className="text-xs text-muted mt-1">
                  Status: {STATUS_LABELS[h.status] ?? h.status}
                </Text>
              </View>
              <Text className="text-sm font-bold text-foreground">
                {brl(h.valorTotal)}
              </Text>
            </View>
          </Pressable>
        ))
      )}
    </ModalDialog>
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
  return (
    <View className="mb-4">
      <Text className="text-sm font-medium text-foreground mb-2">{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        multiline
        numberOfLines={3}
        className="border border-border rounded-lg px-3 py-2 text-foreground"
        textAlignVertical="top"
      />
    </View>
  );
}

function SearchInput({
  value,
  onChange,
  placeholder,
}: {
  value: string;
  onChange: (v: string) => void;
  placeholder: string;
}) {
  return (
    <View className="flex-row items-center bg-surface border border-border rounded-lg px-3">
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        className="flex-1 py-2 text-foreground"
        autoCorrect={false}
        returnKeyType="search"
      />
      {value.length > 0 && (
        <Pressable onPress={() => onChange("")} hitSlop={8} className="px-2">
          <Text className="text-muted">×</Text>
        </Pressable>
      )}
    </View>
  );
}
