import { Stack, useRouter } from "expo-router";
import { useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  Text,
  View,
} from "react-native";

import {
  Button,
  FormInput,
  ModalDialog,
  Select,
} from "@/components/erp-ui";
import { ScreenContainer } from "@/components/screen-container";
import { useAuthContext } from "@/lib/auth-context";
import {
  useCompanies,
  useCreateCompany,
  useDeleteCompany,
  useUpdateCompany,
} from "@/lib/erp-queries";

type VerticalValue = "cv" | "mecanica" | "outro";

const VERTICAL_OPTIONS = [
  { label: "Comunicação Visual", value: "cv" },
  { label: "Mecânica", value: "mecanica" },
  { label: "Outro", value: "outro" },
];

/**
 * F3-08 — gerenciamento de empresas.
 * Lista todas as empresas do usuário, permite criar, renomear, mudar vertical
 * e desativar (soft delete). Apenas o dono pode editar/excluir.
 */
export default function EmpresasScreen() {
  const router = useRouter();
  const { user, currentCompany, switchCompany } = useAuthContext();
  const companiesQuery = useCompanies();
  const createMut = useCreateCompany();
  const updateMut = useUpdateCompany();
  const deleteMut = useDeleteCompany();

  const [showCreate, setShowCreate] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [formNome, setFormNome] = useState("");
  const [formVertical, setFormVertical] = useState<VerticalValue>("outro");

  function openCreate() {
    setEditingId(null);
    setFormNome("");
    setFormVertical("outro");
    setShowCreate(true);
  }

  function openEdit(c: { id: string; nome: string; vertical: VerticalValue }) {
    setEditingId(c.id);
    setFormNome(c.nome);
    setFormVertical(c.vertical);
    setShowCreate(true);
  }

  async function handleSave() {
    const nome = formNome.trim();
    if (!nome) {
      Alert.alert("Nome obrigatório", "Informe o nome da empresa.");
      return;
    }
    try {
      if (editingId) {
        await updateMut.mutateAsync({
          id: editingId,
          nome,
          vertical: formVertical,
        });
      } else {
        const created = await createMut.mutateAsync({ nome, vertical: formVertical });
        // Switch to the newly created company so the user lands in it.
        await switchCompany(created.id);
      }
      setShowCreate(false);
    } catch (e) {
      Alert.alert("Erro", e instanceof Error ? e.message : "Falha ao salvar");
    }
  }

  async function handleDelete(id: string, nome: string) {
    Alert.alert(
      "Desativar empresa",
      `Deseja desativar "${nome}"? Os dados permanecem, mas a empresa some da lista até reativação manual.`,
      [
        { text: "Cancelar", style: "cancel" },
        {
          text: "Desativar",
          style: "destructive",
          onPress: async () => {
            try {
              await deleteMut.mutateAsync({ id });
            } catch (e) {
              Alert.alert(
                "Erro",
                e instanceof Error ? e.message : "Falha ao desativar",
              );
            }
          },
        },
      ],
    );
  }

  const list = companiesQuery.data ?? [];

  return (
    <>
      <Stack.Screen options={{ headerShown: true, title: "Empresas" }} />
      <ScreenContainer className="p-4">
        <ScrollView contentContainerStyle={{ flexGrow: 1 }}>
          <View className="gap-4">
            <View className="flex-row items-center justify-between">
              <Text className="text-2xl font-bold text-foreground">
                Minhas empresas
              </Text>
              <Button title="+ Nova" onPress={openCreate} />
            </View>

            <Pressable
              onPress={() => router.back()}
              className="self-start py-1"
            >
              <Text className="text-sm text-primary">← Voltar</Text>
            </Pressable>

            {companiesQuery.isLoading ? (
              <ActivityIndicator />
            ) : list.length === 0 ? (
              <Text className="text-sm text-muted">
                Você ainda não tem nenhuma empresa. Toque em + Nova.
              </Text>
            ) : (
              list.map((c) => {
                const isOwner = c.ownerUserId === user?.id;
                const isActive = c.id === currentCompany?.id;
                return (
                  <View
                    key={c.id}
                    className={`p-4 rounded-xl border ${
                      isActive ? "border-primary" : "border-border"
                    } bg-surface gap-2`}
                  >
                    <View className="flex-row items-center justify-between">
                      <View className="flex-1 pr-3">
                        <Text className="text-lg font-semibold text-foreground">
                          {c.nome}
                          {isActive ? "  (ativa)" : ""}
                        </Text>
                        <Text className="text-xs text-muted">
                          Vertical: {c.vertical} · Papel: {c.role}
                          {c.ativa ? "" : " · inativa"}
                        </Text>
                      </View>
                    </View>
                    <View className="flex-row gap-2 flex-wrap">
                      {!isActive ? (
                        <Button
                          title="Usar esta"
                          variant="secondary"
                          onPress={() => switchCompany(c.id)}
                        />
                      ) : null}
                      {isOwner ? (
                        <>
                          <Button
                            title="Editar"
                            variant="secondary"
                            onPress={() =>
                              openEdit({
                                id: c.id,
                                nome: c.nome,
                                vertical: c.vertical,
                              })
                            }
                          />
                          <Button
                            title="Desativar"
                            variant="danger"
                            onPress={() => handleDelete(c.id, c.nome)}
                          />
                        </>
                      ) : null}
                    </View>
                  </View>
                );
              })
            )}
          </View>
        </ScrollView>

        <ModalDialog
          visible={showCreate}
          title={editingId ? "Editar empresa" : "Nova empresa"}
          confirmText="Salvar"
          onConfirm={handleSave}
          onClose={() => setShowCreate(false)}
        >
          <View className="gap-3">
            <FormInput
              label="Nome"
              value={formNome}
              onChangeText={setFormNome}
              placeholder="Ex.: Oficina Central"
              required
            />
            <Select
              label="Vertical"
              value={formVertical}
              onValueChange={(v) => setFormVertical(v as VerticalValue)}
              options={VERTICAL_OPTIONS}
            />
          </View>
        </ModalDialog>
      </ScreenContainer>
    </>
  );
}
