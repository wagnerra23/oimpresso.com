import { useRouter } from "expo-router";
import { useState } from "react";
import { ActivityIndicator, Pressable, Text, View } from "react-native";

import { ModalDialog } from "@/components/erp-ui";
import { useAuthContext } from "@/lib/auth-context";
import { useCompanies } from "@/lib/erp-queries";

/**
 * F3-08: header pill showing the active company name + a chevron.
 * Tap opens a bottom sheet listing all companies the user belongs to
 * with a "Gerenciar empresas" link to the /empresas Stack screen.
 */
export function CompanySwitcher() {
  const router = useRouter();
  const { currentCompany, switchCompany } = useAuthContext();
  const companiesQuery = useCompanies();
  const [open, setOpen] = useState(false);
  const [switching, setSwitching] = useState<string | null>(null);

  async function handlePick(id: string) {
    if (id === currentCompany?.id) {
      setOpen(false);
      return;
    }
    try {
      setSwitching(id);
      await switchCompany(id);
      setOpen(false);
    } finally {
      setSwitching(null);
    }
  }

  const label = currentCompany?.nome ?? "Sem empresa";

  return (
    <>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`Empresa ativa: ${label}. Toque para trocar.`}
        onPress={() => setOpen(true)}
        className="flex-row items-center gap-2 px-3 py-2 rounded-full bg-surface border border-border self-start active:opacity-70"
      >
        <Text className="text-sm font-medium text-foreground" numberOfLines={1}>
          {label}
        </Text>
        <Text className="text-xs text-muted">▼</Text>
      </Pressable>

      <ModalDialog
        visible={open}
        onClose={() => setOpen(false)}
        title="Trocar empresa"
        showConfirmation={false}
      >
        <View className="gap-2">
          {companiesQuery.isLoading ? (
            <ActivityIndicator />
          ) : companiesQuery.data && companiesQuery.data.length > 0 ? (
            companiesQuery.data.map((c) => {
              const active = c.id === currentCompany?.id;
              const isSwitching = switching === c.id;
              return (
                <Pressable
                  key={c.id}
                  onPress={() => handlePick(c.id)}
                  disabled={isSwitching}
                  className={`flex-row items-center justify-between p-3 rounded-lg border ${
                    active
                      ? "border-primary bg-primary/10"
                      : "border-border bg-surface"
                  } active:opacity-70`}
                >
                  <View className="flex-1 pr-3">
                    <Text className="text-base font-medium text-foreground" numberOfLines={1}>
                      {c.nome}
                    </Text>
                    <Text className="text-xs text-muted">
                      {c.vertical === "cv"
                        ? "Comunicação Visual"
                        : c.vertical === "mecanica"
                        ? "Mecânica"
                        : "Outro"}
                      {c.role ? ` · ${c.role}` : ""}
                    </Text>
                  </View>
                  {isSwitching ? (
                    <ActivityIndicator size="small" />
                  ) : active ? (
                    <Text className="text-primary text-lg">✓</Text>
                  ) : null}
                </Pressable>
              );
            })
          ) : (
            <Text className="text-sm text-muted">Nenhuma empresa encontrada.</Text>
          )}

          <Pressable
            onPress={() => {
              setOpen(false);
              router.push("/empresas");
            }}
            className="mt-2 p-3 rounded-lg border border-dashed border-border active:opacity-70"
          >
            <Text className="text-center text-sm font-medium text-primary">
              + Gerenciar empresas
            </Text>
          </Pressable>
        </View>
      </ModalDialog>
    </>
  );
}
