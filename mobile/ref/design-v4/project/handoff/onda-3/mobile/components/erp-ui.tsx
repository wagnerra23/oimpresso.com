/**
 * erp-ui — ADAPTADOR DE DESCONTINUAÇÃO (Onda 3).
 *
 * Mesma API pública de antes (Toast, Badge, MetricCard, FormInput, Select, Button,
 * ModalDialog, KanbanCard, Table), agora desenhada com os tokens Oi/DS. Assim as ~15 telas
 * que ainda importam daqui mudam de cara sem tocar nelas. Telas novas: importar de
 * "@/components/oi". Quando o grep por "erp-ui" zerar, apagar este arquivo.
 */
import React from "react";
import { Pressable, ScrollView, Text, View } from "react-native";

import { OiBtn, OiCard, OiIcon, OiKpi, OiSheet, OiStatus, type OiStatusVariant } from "@/components/oi";
import { OiFormFooter, OiFormInput, OiSelectField } from "@/components/oi/OiForm";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

/** @deprecated use useNotify() (toast global). Mantido como faixa inline com tokens. */
export function Toast({ tipo, mensagem }: { tipo: "sucesso" | "erro" | "info"; mensagem: string }) {
  const { palette } = useOiTheme();
  const c = tipo === "sucesso" ? palette.ok : tipo === "erro" ? palette.danger : palette.info;
  const icon = tipo === "sucesso" ? "check-circle" : tipo === "erro" ? "alert" : "bell";
  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 10, paddingHorizontal: 12, paddingVertical: 10, borderRadius: radius.md, marginBottom: 8, backgroundColor: hexAlpha(c, 0.06), borderWidth: 1, borderColor: hexAlpha(c, 0.22) }}>
      <OiIcon name={icon} size={18} color={c} />
      <Text style={{ flex: 1, color: palette.text, fontFamily: fonts.sansMedium, fontSize: 13 }}>{mensagem}</Text>
    </View>
  );
}

export function Badge({ count, label }: { count: number; label: string }) {
  const { palette } = useOiTheme();
  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
      <Text style={{ fontFamily: fonts.sansMedium, fontSize: 13.5, color: palette.text }}>{label}</Text>
      <View style={{ backgroundColor: palette.accentSoft, borderRadius: 999, paddingHorizontal: 7, paddingVertical: 1 }}>
        <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 11, color: palette.accent }}>{count}</Text>
      </View>
    </View>
  );
}

export function MetricCard({ label, value }: { label: string; value: string | number; icon?: string }) {
  return <View style={{ flex: 1 }}><OiKpi label={label} value={String(value)} /></View>;
}

interface FormInputProps {
  label: string;
  placeholder: string;
  value: string;
  onChangeText: (text: string) => void;
  error?: string;
  required?: boolean;
  keyboardType?: "default" | "numeric" | "email-address";
  returnKeyType?: "done" | "next" | "search" | "go" | "send" | "default";
  onSubmitEditing?: () => void;
  secureTextEntry?: boolean;
  autoCapitalize?: "none" | "sentences" | "words" | "characters";
}

export function FormInput({ keyboardType = "default", returnKeyType = "done", secureTextEntry = false, autoCapitalize, ...rest }: FormInputProps) {
  return (
    <OiFormInput
      {...rest}
      keyboardType={keyboardType}
      returnKeyType={returnKeyType}
      secureTextEntry={secureTextEntry}
      mono={keyboardType === "numeric"}
      autoCapitalize={autoCapitalize ?? (keyboardType === "email-address" || secureTextEntry ? "none" : undefined)}
      autoCorrect={!secureTextEntry && keyboardType !== "email-address"}
    />
  );
}

interface SelectOption { label: string; value: string }
interface SelectProps {
  label: string;
  options: SelectOption[];
  value: string;
  onValueChange: (value: string) => void;
  error?: string;
  required?: boolean;
}

export function Select(props: SelectProps) {
  // inline: telas antigas abrem Select dentro do ModalDialog (agora um sheet) — sheet sobre sheet trava no iOS.
  return <OiSelectField {...props} placeholder="Selecione…" inline />;
}

interface ButtonProps {
  title: string;
  onPress: () => void;
  variant?: "primary" | "secondary" | "danger";
  disabled?: boolean;
}

export function Button({ title, onPress, variant = "primary", disabled = false }: ButtonProps) {
  return <OiBtn label={title} onPress={onPress} disabled={disabled} variant={variant === "secondary" ? "default" : variant} block />;
}

interface ModalDialogProps {
  visible: boolean;
  title: string;
  children: React.ReactNode;
  onClose: () => void;
  onConfirm?: () => void;
  confirmText?: string;
  cancelText?: string;
  showConfirmation?: boolean;
}

/** Agora é o OiSheet (mesmo scrim, alça, título com fechar) + rodapé padrão. */
export function ModalDialog({ visible, title, children, onClose, onConfirm, confirmText = "Salvar", cancelText = "Cancelar", showConfirmation = true }: ModalDialogProps) {
  return (
    <OiSheet visible={visible} onClose={onClose} title={title}>
      {children}
      {showConfirmation && onConfirm ? (
        <OiFormFooter onCancel={onClose} onConfirm={onConfirm} confirmLabel={confirmText} cancelLabel={cancelText} />
      ) : null}
    </OiSheet>
  );
}

const KANBAN_TONE: Record<string, OiStatusVariant> = {
  novo: "info", aprovado: "accent", execucao: "warn", entregue: "ok",
  fila: "info", andamento: "warn", revisao: "accent", concluido: "ok",
};

interface KanbanCardProps {
  id: string;
  title: string;
  subtitle: string;
  status: string;
  onPress?: () => void;
  onDelete?: () => void;
  originInfo?: string;
}

export function KanbanCard({ title, subtitle, status, onPress, onDelete, originInfo }: KanbanCardProps) {
  const { palette } = useOiTheme();
  return (
    <Pressable onPress={onPress} style={{ marginBottom: 8 }}>
      <OiCard variant="tight">
        <View style={{ flexDirection: "row", alignItems: "flex-start", gap: 8 }}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text numberOfLines={1} style={{ fontFamily: fonts.sansSemibold, fontSize: 14, color: palette.text }}>{title}</Text>
            <Text numberOfLines={1} style={{ fontFamily: fonts.sans, fontSize: 12.5, color: palette.textDim, marginTop: 2 }}>{subtitle}</Text>
          </View>
          {onDelete ? (
            <Pressable onPress={onDelete} hitSlop={10} style={{ width: 32, height: 32, alignItems: "center", justifyContent: "center" }}>
              <OiIcon name="trash" size={18} color={palette.danger} />
            </Pressable>
          ) : null}
        </View>
        <View style={{ flexDirection: "row", alignItems: "center", justifyContent: "space-between" }}>
          <OiStatus label={status} variant={KANBAN_TONE[status] ?? "neutral"} />
          {originInfo ? <Text style={{ fontFamily: fonts.mono, fontSize: 11, color: palette.textMute }}>{originInfo}</Text> : null}
        </View>
      </OiCard>
    </Pressable>
  );
}

interface TableColumn { key: string; label: string; width?: number; sortable?: boolean }
interface TableProps {
  columns: TableColumn[];
  data: any[];
  onSort?: (key: string) => void;
  renderCell?: (key: string, value: any, row: any) => React.ReactNode;
}

export function Table({ columns, data, onSort, renderCell }: TableProps) {
  const { palette } = useOiTheme();
  return (
    <ScrollView horizontal>
      <View style={{ borderWidth: 1, borderColor: palette.border, borderRadius: radius.md, overflow: "hidden", backgroundColor: palette.surface }}>
        <View style={{ flexDirection: "row", borderBottomWidth: 1, borderBottomColor: palette.border }}>
          {columns.map((col) => (
            <Pressable key={col.key} onPress={() => col.sortable && onSort?.(col.key)} style={{ width: col.width || 100, paddingHorizontal: 12, paddingVertical: 8 }}>
              <Text style={{ fontFamily: fonts.sansBold, fontSize: 10.5, letterSpacing: 0.8, textTransform: "uppercase", color: palette.textMute }}>{col.label}</Text>
            </Pressable>
          ))}
        </View>
        {data.map((row, idx) => (
          <View key={idx} style={{ flexDirection: "row", minHeight: 44, borderBottomWidth: idx === data.length - 1 ? 0 : 1, borderBottomColor: palette.border2 }}>
            {columns.map((col) => (
              <View key={col.key} style={{ width: col.width || 100, paddingHorizontal: 12, justifyContent: "center" }}>
                <Text style={{ fontFamily: fonts.sans, fontSize: 13.5, color: palette.text }}>{renderCell ? renderCell(col.key, row[col.key], row) : row[col.key]}</Text>
              </View>
            ))}
          </View>
        ))}
      </View>
    </ScrollView>
  );
}
