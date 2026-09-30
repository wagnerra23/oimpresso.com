import React, { useState } from "react";
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Text,
  View,
} from "react-native";
import { Stack, useLocalSearchParams, router as expoRouter } from "expo-router";

import { ScreenContainer } from "@/components/screen-container";
import { FormInput, ModalDialog, Select, Toast } from "@/components/erp-ui";
import { useERP } from "@/lib/erp-context";
import {
  useInventoryItem,
  useInventoryMovements,
  useRecordMovement,
} from "@/lib/erp-queries";

type TipoMov = "entrada" | "saida" | "ajuste" | "perda";

const TIPO_OPTIONS = [
  { label: "Entrada (+)", value: "entrada" },
  { label: "Saída (-)", value: "saida" },
  { label: "Ajuste (=)", value: "ajuste" },
  { label: "Perda (-)", value: "perda" },
];

const TIPO_COLORS: Record<TipoMov, { bg: string; text: string; sign: string }> = {
  entrada: { bg: "#EAF3DE", text: "#27500A", sign: "+" },
  saida: { bg: "#FCEBEB", text: "#791F1F", sign: "−" },
  perda: { bg: "#FCEBEB", text: "#791F1F", sign: "−" },
  ajuste: { bg: "#E6F1FB", text: "#0C447C", sign: "=" },
};

function formatDateTime(iso: string) {
  try {
    return new Date(iso).toLocaleString("pt-BR");
  } catch {
    return iso;
  }
}

export default function EstoqueDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { ui, addToast } = useERP();

  const itemQuery = useInventoryItem(id);
  const movementsQuery = useInventoryMovements(id);
  const recordMutation = useRecordMovement();

  const item = itemQuery.data;
  const movements = movementsQuery.data ?? [];

  const [movVisible, setMovVisible] = useState(false);
  const [tipo, setTipo] = useState<TipoMov>("entrada");
  const [quantidade, setQuantidade] = useState("");
  const [motivo, setMotivo] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});

  const openMovement = () => {
    setTipo("entrada");
    setQuantidade("");
    setMotivo("");
    setErrors({});
    setMovVisible(true);
  };

  const handleRecord = async () => {
    const q = Number(quantidade);
    const next: Record<string, string> = {};
    if (!Number.isFinite(q) || q <= 0) next.quantidade = "Quantidade > 0";
    setErrors(next);
    if (Object.keys(next).length > 0 || !id) return;
    try {
      await recordMutation.mutateAsync({
        inventoryId: id,
        tipo,
        quantidade: q,
        motivo: motivo.trim() || undefined,
      });
      addToast("sucesso", "Movimentação registrada");
      setMovVisible(false);
    } catch (err) {
      addToast("erro", err instanceof Error ? err.message : "Erro na movimentação");
    }
  };

  return (
    <>
      <Stack.Screen
        options={{
          headerShown: true,
          title: item?.nome ?? "Item de estoque",
        }}
      />
      <ScreenContainer className="bg-background">
        <ScrollView className="flex-1" contentContainerStyle={{ paddingBottom: 20 }}>
          <View className="px-4 pt-4">
            {ui.toasts.map((t) => (
              <Toast key={t.id} tipo={t.tipo} mensagem={t.mensagem} />
            ))}
          </View>

          {itemQuery.isLoading ? (
            <View className="py-12 items-center">
              <ActivityIndicator />
            </View>
          ) : itemQuery.isError || !item ? (
            <View className="px-4 pt-4">
              <View className="bg-error/10 rounded-lg p-4">
                <Text className="text-error text-sm">
                  {itemQuery.error?.message ?? "Item não encontrado"}
                </Text>
              </View>
              <Pressable
                onPress={() => expoRouter.back()}
                className="mt-4 bg-surface border border-border px-4 py-3 rounded-lg"
              >
                <Text className="text-foreground text-center font-semibold">
                  Voltar
                </Text>
              </Pressable>
            </View>
          ) : (
            <>
              {/* Header card */}
              <View className="px-4 pt-4">
                <View className="bg-surface rounded-lg p-4 border border-border">
                  <Text className="text-xl font-bold text-foreground">
                    {item.nome}
                  </Text>
                  {item.codigo ? (
                    <Text className="text-sm text-muted mt-1">
                      Código: {item.codigo}
                    </Text>
                  ) : null}
                  <View className="flex-row items-end gap-2 mt-3">
                    <Text className="text-3xl font-bold text-primary">
                      {item.quantidade}
                    </Text>
                    <Text className="text-base text-muted mb-1">
                      {item.unidade}
                    </Text>
                  </View>
                  <Text className="text-xs text-muted mt-1">
                    mín: {item.estoqueMinimo} {item.unidade}
                  </Text>
                  {item.localizacao ? (
                    <Text className="text-xs text-muted mt-1">
                      📍 {item.localizacao}
                    </Text>
                  ) : null}
                </View>
                <Pressable
                  onPress={openMovement}
                  className="bg-primary mt-3 px-4 py-3 rounded-lg"
                >
                  <Text className="text-white font-semibold text-center">
                    + Nova movimentação
                  </Text>
                </Pressable>
              </View>

              <View className="px-4 pt-6">
                <Text className="text-base font-semibold text-foreground mb-3">
                  Histórico de movimentações
                </Text>
                {movementsQuery.isLoading ? (
                  <ActivityIndicator />
                ) : movements.length === 0 ? (
                  <View className="bg-surface rounded-lg p-6 items-center">
                    <Text className="text-muted text-sm">
                      Nenhuma movimentação registrada
                    </Text>
                  </View>
                ) : (
                  movements.map((m) => {
                    const c = TIPO_COLORS[m.tipo as TipoMov];
                    return (
                      <View
                        key={m.id}
                        className="bg-surface rounded-lg p-3 mb-2 border border-border"
                      >
                        <View className="flex-row justify-between items-center">
                          <View
                            className="px-2 py-1 rounded"
                            style={{ backgroundColor: c.bg }}
                          >
                            <Text
                              style={{ color: c.text }}
                              className="text-xs font-semibold uppercase"
                            >
                              {m.tipo}
                            </Text>
                          </View>
                          <Text
                            style={{ color: c.text }}
                            className="text-lg font-bold"
                          >
                            {c.sign}
                            {m.quantidade}
                          </Text>
                        </View>
                        <View className="flex-row justify-between mt-2">
                          <Text className="text-xs text-muted">
                            {formatDateTime(m.createdAt)}
                          </Text>
                          <Text className="text-xs text-foreground font-semibold">
                            Saldo: {m.saldoApos}
                          </Text>
                        </View>
                        {m.motivo ? (
                          <Text className="text-sm text-foreground mt-1">
                            {m.motivo}
                          </Text>
                        ) : null}
                        {m.referenciaTipo ? (
                          <Text className="text-xs text-muted mt-1">
                            Ref: {m.referenciaTipo}
                            {m.referenciaId ? ` • ${m.referenciaId.slice(0, 8)}` : ""}
                          </Text>
                        ) : null}
                      </View>
                    );
                  })
                )}
              </View>
            </>
          )}
        </ScrollView>

        <ModalDialog
          visible={movVisible}
          title="Nova movimentação"
          onClose={() => setMovVisible(false)}
          onConfirm={handleRecord}
          confirmText="Registrar"
          cancelText="Cancelar"
        >
          <Select
            label="Tipo"
            options={TIPO_OPTIONS}
            value={tipo}
            onValueChange={(v) => setTipo(v as TipoMov)}
            required
          />
          <FormInput
            label="Quantidade"
            placeholder={tipo === "ajuste" ? "Saldo final" : "Ex: 5"}
            value={quantidade}
            onChangeText={setQuantidade}
            error={errors.quantidade}
            required
            keyboardType="numeric"
            returnKeyType="next"
          />
          <FormInput
            label="Motivo"
            placeholder="opcional"
            value={motivo}
            onChangeText={setMotivo}
            returnKeyType="done"
          />
        </ModalDialog>
      </ScreenContainer>
    </>
  );
}
