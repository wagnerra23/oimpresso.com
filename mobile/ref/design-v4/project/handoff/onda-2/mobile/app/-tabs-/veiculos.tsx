import React, { useMemo, useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, View } from "react-native";
import { router } from "expo-router";

import { OiBtn, OiCard, OiEmpty, OiHeader, OiIcon, OiMoney, OiPlaca, OiScreen, OiSearch, OiSheet, OiStatus } from "@/components/oi";
import { OiFormFooter, OiFormInput, OiSelectField } from "@/components/oi/OiForm";
import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useNotify } from "@/lib/notify";
import { OS_STATUS, type ServiceOrderStatus } from "@/lib/os-status";
import { useCreateVehicle, useCustomers, useDeleteVehicle, useUpdateVehicle, useVehicleHistory, useVehicles } from "@/lib/erp-queries";
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

const EMPTY_FORM: VehicleForm = { customerId: "", placa: "", chassi: "", marca: "", modelo: "", ano: "", cor: "", kmAtual: "", observacoes: "" };

export default function VeiculosScreen() {
  const { palette } = useOiTheme();
  const notify = useNotify();
  const vehiclesQuery = useVehicles();
  const customersQuery = useCustomers();
  const createVehicle = useCreateVehicle();
  const updateVehicle = useUpdateVehicle();
  const deleteVehicle = useDeleteVehicle();

  const vehicles = vehiclesQuery.data ?? [];
  const customers = customersQuery.data ?? [];

  const [editingId, setEditingId] = useState<string | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [form, setForm] = useState<VehicleForm>(EMPTY_FORM);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);
  const [openId, setOpenId] = useState<string | null>(null);

  const setField = <K extends keyof VehicleForm>(key: K, value: VehicleForm[K]) => setForm((prev) => ({ ...prev, [key]: value }));
  const resetForm = () => { setEditingId(null); setForm(EMPTY_FORM); setErrors({}); };

  const openCreate = () => {
    resetForm();
    if (customers[0]) setField("customerId", customers[0].id);
    setFormOpen(true);
  };

  const openEdit = (v: (typeof vehicles)[number]) => {
    setEditingId(v.id);
    setForm({
      customerId: v.customerId ?? "", placa: v.placa, chassi: v.chassi ?? "", marca: v.marca, modelo: v.modelo,
      ano: v.ano != null ? String(v.ano) : "", cor: v.cor ?? "", kmAtual: String(v.kmAtual ?? 0), observacoes: v.observacoes ?? "",
    });
    setErrors({});
    setFormOpen(true);
  };

  const validate = () => {
    const next: Record<string, string> = {};
    if (!form.customerId) next.customerId = "Cliente é obrigatório";
    if (!form.placa.trim()) next.placa = "Placa é obrigatória";
    if (!form.marca.trim()) next.marca = "Marca é obrigatória";
    if (!form.modelo.trim()) next.modelo = "Modelo é obrigatório";
    if (form.chassi.trim() && form.chassi.trim().length > 17) next.chassi = "Chassi tem no máximo 17 caracteres";
    if (form.ano.trim() && !/^\d{4}$/.test(form.ano.trim())) next.ano = "Ano deve ter 4 dígitos";
    if (form.kmAtual.trim() && !/^\d+$/.test(form.kmAtual.trim())) next.kmAtual = "KM deve ser número inteiro";
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
        notify("sucesso", `Veículo ${payload.placa} atualizado`);
      } else {
        await createVehicle.mutateAsync(payload);
        notify("sucesso", `Veículo ${payload.placa} cadastrado`);
      }
      setFormOpen(false);
      resetForm();
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro ao salvar veículo");
    }
  };

  const handleDelete = async () => {
    if (!editingId) return;
    try {
      await deleteVehicle.mutateAsync({ id: editingId });
      notify("sucesso", `Veículo ${form.placa} removido`);
      setFormOpen(false);
      resetForm();
    } catch (err) {
      notify("erro", err instanceof Error ? err.message : "Erro ao remover veículo");
    }
  };

  const filtered = useMemo(() => {
    const term = debouncedSearch.trim().toLowerCase();
    if (!term) return vehicles;
    return vehicles.filter((v) =>
      v.placa.toLowerCase().includes(term) || v.marca.toLowerCase().includes(term) ||
      v.modelo.toLowerCase().includes(term) || (v.customer?.nome ?? "").toLowerCase().includes(term));
  }, [vehicles, debouncedSearch]);

  const customerOptions = useMemo(() => customers.map((c) => ({ label: c.nome, value: c.id })), [customers]);

  return (
    <OiScreen edges={["top"]}>
      <OiHeader
        title="Veículos"
        eyebrow={`${vehicles.length} veículo${vehicles.length === 1 ? "" : "s"}`}
        actions={[{ icon: "plus", onPress: openCreate }]}
      >
        <OiSearch value={search} onChangeText={setSearch} placeholder="Placa, marca, modelo ou cliente" />
      </OiHeader>
      <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: 16, gap: 10, paddingBottom: 24 }}>
        {vehiclesQuery.isLoading ? (
          <View style={{ paddingVertical: 48, alignItems: "center" }}><ActivityIndicator color={palette.accent} /></View>
        ) : vehiclesQuery.isError ? (
          <OiEmpty icon="alert" title="Não foi possível carregar os veículos" subtitle={vehiclesQuery.error?.message} action={{ label: "Tentar de novo", onPress: () => vehiclesQuery.refetch() }} />
        ) : filtered.length === 0 ? (
          vehicles.length === 0
            ? <OiEmpty icon="truck" title="Nenhum veículo cadastrado" subtitle="Cadastre o veículo na recepção para abrir a OS." action={{ label: "Cadastrar veículo", onPress: openCreate }} />
            : <OiEmpty icon="search" title={`Nada encontrado para "${search}"`} subtitle="Tente a placa sem traço ou o nome do cliente." />
        ) : (
          filtered.map((v) => {
            const open = openId === v.id;
            return (
              <OiCard key={v.id} variant="tight">
                <Pressable onPress={() => setOpenId(open ? null : v.id)} style={{ flexDirection: "row", alignItems: "center", gap: 12, minHeight: 44 }}>
                  <OiPlaca text={v.placa} />
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text numberOfLines={1} style={{ fontFamily: fonts.sansSemibold, fontSize: 14.5, color: palette.text }}>{v.marca} {v.modelo}{v.ano ? ` · ${v.ano}` : ""}</Text>
                    <Text numberOfLines={1} style={{ fontFamily: fonts.sans, fontSize: 12.5, color: palette.textDim }}>
                      {v.customer?.nome ?? "Sem cliente"} · <Text style={{ fontFamily: fonts.mono }}>{(v.kmAtual ?? 0).toLocaleString("pt-BR")} km</Text>
                    </Text>
                  </View>
                  <OiIcon name={open ? "chev-u" : "chev-d"} size={20} color={palette.textMute} />
                </Pressable>
                {open ? (
                  <>
                    <VehicleHistory vehicleId={v.id} />
                    <View style={{ flexDirection: "row", gap: 8 }}>
                      <OiBtn size="sm" variant="primary" leftIcon="wrench" label="Nova OS" onPress={() => router.push("/oss/new" as never)} />
                      <OiBtn size="sm" leftIcon="edit" label="Editar" onPress={() => openEdit(v)} />
                    </View>
                  </>
                ) : null}
              </OiCard>
            );
          })
        )}
      </ScrollView>

      <OiSheet visible={formOpen} onClose={() => { setFormOpen(false); resetForm(); }} title={editingId ? `Editar ${form.placa}` : "Novo veículo"}>
        {customers.length === 0 ? (
          <OiCard variant="tight" style={{ marginBottom: 12 }}>
            <Text style={{ fontFamily: fonts.sans, fontSize: 13.5, color: palette.text }}>Você precisa cadastrar um cliente primeiro.</Text>
            <OiBtn variant="primary" size="sm" label="Cadastrar cliente" onPress={() => { setFormOpen(false); router.push("/clientes"); }} />
          </OiCard>
        ) : (
          <OiSelectField inline label="Cliente" required options={customerOptions} value={form.customerId} onValueChange={(v) => setField("customerId", v)} error={errors.customerId} />
        )}
        <View style={{ flexDirection: "row", gap: 8 }}>
          <View style={{ flex: 1 }}><OiFormInput label="Placa" required mono placeholder="ABC1D23" value={form.placa} onChangeText={(v) => setField("placa", v.toUpperCase())} error={errors.placa} autoCapitalize="characters" /></View>
          <View style={{ flex: 1 }}><OiFormInput label="Ano" mono placeholder="2020" value={form.ano} onChangeText={(v) => setField("ano", v)} error={errors.ano} keyboardType="numeric" /></View>
        </View>
        <View style={{ flexDirection: "row", gap: 8 }}>
          <View style={{ flex: 1 }}><OiFormInput label="Marca" required placeholder="Volkswagen" value={form.marca} onChangeText={(v) => setField("marca", v)} error={errors.marca} /></View>
          <View style={{ flex: 1 }}><OiFormInput label="Modelo" required placeholder="Gol" value={form.modelo} onChangeText={(v) => setField("modelo", v)} error={errors.modelo} /></View>
        </View>
        <OiFormInput label="Chassi" mono placeholder="17 caracteres (opcional)" value={form.chassi} onChangeText={(v) => setField("chassi", v.toUpperCase())} error={errors.chassi} autoCapitalize="characters" />
        <View style={{ flexDirection: "row", gap: 8 }}>
          <View style={{ flex: 1 }}><OiFormInput label="Cor" placeholder="Prata" value={form.cor} onChangeText={(v) => setField("cor", v)} /></View>
          <View style={{ flex: 1 }}><OiFormInput label="KM atual" mono placeholder="0" value={form.kmAtual} onChangeText={(v) => setField("kmAtual", v)} error={errors.kmAtual} keyboardType="numeric" /></View>
        </View>
        <OiFormInput label="Observações" multiline placeholder="Notas internas" value={form.observacoes} onChangeText={(v) => setField("observacoes", v)} />
        {editingId ? <OiBtn variant="ghost" leftIcon="trash" label="Remover veículo" onPress={handleDelete} style={{ alignSelf: "flex-start" }} /> : null}
        <OiFormFooter onCancel={() => { setFormOpen(false); resetForm(); }} onConfirm={handleSave} confirmLabel={editingId ? "Salvar" : "Cadastrar"} loading={createVehicle.isPending || updateVehicle.isPending} />
      </OiSheet>
    </OiScreen>
  );
}

/** Histórico inline (antes era modal): últimas OS do veículo, toque abre a OS. */
function VehicleHistory({ vehicleId }: { vehicleId: string }) {
  const { palette } = useOiTheme();
  const historyQuery = useVehicleHistory(vehicleId);
  const history = historyQuery.data ?? [];
  if (historyQuery.isLoading) return <ActivityIndicator color={palette.accent} style={{ marginVertical: 8 }} />;
  if (history.length === 0) return <Text style={{ fontFamily: fonts.sans, fontSize: 12.5, color: palette.textMute, paddingVertical: 6 }}>Nenhuma OS registrada para este veículo.</Text>;
  return (
    <View style={{ borderTopWidth: 1, borderTopColor: palette.border2 }}>
      {history.map((h) => {
        const st = OS_STATUS[h.status as ServiceOrderStatus];
        return (
          <Pressable key={h.id} onPress={() => router.push(`/oss/${h.id}` as any)}
            style={{ minHeight: 48, flexDirection: "row", alignItems: "center", gap: 10, borderBottomWidth: 1, borderBottomColor: palette.border2 }}>
            <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 12.5, color: palette.text }}>#{h.numero}</Text>
            <Text style={{ flex: 1, fontFamily: fonts.mono, fontSize: 12, color: palette.textDim }}>{new Date(h.dataEntrada).toLocaleDateString("pt-BR")}</Text>
            {st ? <OiStatus label={st.label} variant={st.tone} /> : null}
            <OiMoney value={h.valorTotal} size={12.5} />
          </Pressable>
        );
      })}
    </View>
  );
}
