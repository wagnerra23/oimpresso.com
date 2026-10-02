/**
 * OiForm — campos de formulário no padrão do DS (rótulo uppercase + ajuda + erro inline).
 * Substitui FormInput / Select / multiline de erp-ui.tsx. Alvo de toque 44.
 */
import { useState } from "react";
import { Pressable, Text, TextInput, View, type TextInputProps } from "react-native";

import { fonts, radius, touch } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon } from "./OiIcon";
import { OiSheet } from "./OiSheet";

function Label({ text, required }: { text: string; required?: boolean }) {
  const { palette } = useOiTheme();
  return (
    <Text style={{ fontSize: 11, fontFamily: fonts.sansSemibold, letterSpacing: 0.6, textTransform: "uppercase", color: palette.textDim, marginBottom: 5 }}>
      {text}{required ? <Text style={{ color: palette.danger }}> *</Text> : null}
    </Text>
  );
}

function Erro({ text }: { text?: string }) {
  const { palette } = useOiTheme();
  if (!text) return null;
  return <Text style={{ marginTop: 4, fontSize: 12, fontFamily: fonts.sansMedium, color: palette.danger }}>{text}</Text>;
}

export type OiFormInputProps = TextInputProps & {
  label: string;
  error?: string;
  required?: boolean;
  mono?: boolean;
  multiline?: boolean;
};

export function OiFormInput({ label, error, required, mono, multiline, style, ...rest }: OiFormInputProps) {
  const { palette } = useOiTheme();
  const [focus, setFocus] = useState(false);
  const border = error ? palette.danger : focus ? palette.accent : palette.border;
  return (
    <View style={{ marginBottom: 12 }}>
      <Label text={label} required={required} />
      <TextInput
        placeholderTextColor={palette.textMute}
        {...rest}
        onFocus={(e) => { setFocus(true); rest.onFocus?.(e); }}
        onBlur={(e) => { setFocus(false); rest.onBlur?.(e); }}
        multiline={multiline}
        textAlignVertical={multiline ? "top" : "center"}
        style={[
          {
            minHeight: multiline ? 88 : touch.default,
            borderWidth: 1,
            borderColor: border,
            borderRadius: radius.md,
            backgroundColor: palette.bg,
            paddingHorizontal: 12,
            paddingVertical: multiline ? 10 : 0,
            color: palette.text,
            fontSize: 14,
            fontFamily: mono ? fonts.mono : fonts.sans,
          },
          style,
        ]}
      />
      <Erro text={error} />
    </View>
  );
}

export type OiSelectOption = { label: string; value: string; hint?: string };

export type OiSelectFieldProps = {
  label: string;
  options: OiSelectOption[];
  value: string;
  onValueChange: (v: string) => void;
  placeholder?: string;
  error?: string;
  required?: boolean;
  /** Abre a lista logo abaixo do campo em vez de um sheet — use DENTRO de OiSheet/ModalDialog (sheet sobre sheet trava no iOS). */
  inline?: boolean;
};

export function OiSelectField({ label, options, value, onValueChange, placeholder = "Selecionar…", error, required, inline }: OiSelectFieldProps) {
  const { palette } = useOiTheme();
  const [open, setOpen] = useState(false);
  const sel = options.find((o) => o.value === value);
  return (
    <View style={{ marginBottom: 12 }}>
      <Label text={label} required={required} />
      <Pressable
        onPress={() => setOpen(true)}
        style={{ minHeight: touch.default, borderWidth: 1, borderColor: error ? palette.danger : palette.border, borderRadius: radius.md, backgroundColor: palette.bg, paddingHorizontal: 12, flexDirection: "row", alignItems: "center", gap: 8 }}
      >
        <Text numberOfLines={1} style={{ flex: 1, fontSize: 14, fontFamily: fonts.sans, color: sel ? palette.text : palette.textMute }}>{sel ? sel.label : placeholder}</Text>
        <OiIcon name={inline && open ? "chev-u" : "chev-d"} size={20} color={palette.textMute} />
      </Pressable>
      <Erro text={error} />
      {inline ? (open ? (
        <View style={{ marginTop: 6, borderWidth: 1, borderColor: palette.border, borderRadius: radius.md, backgroundColor: palette.surface, overflow: "hidden" }}>
          {options.map((o, i) => {
            const on = o.value === value;
            return (
              <Pressable key={o.value} onPress={() => { onValueChange(o.value); setOpen(false); }}
                style={{ minHeight: touch.default, paddingHorizontal: 12, flexDirection: "row", alignItems: "center", gap: 8, borderBottomWidth: i === options.length - 1 ? 0 : 1, borderBottomColor: palette.border2, backgroundColor: on ? palette.accentSoft : "transparent" }}>
                <Text style={{ flex: 1, fontSize: 14, fontFamily: on ? fonts.sansSemibold : fonts.sans, color: on ? palette.accent : palette.text }}>{o.label}</Text>
                {on ? <OiIcon name="check" size={18} color={palette.accent} /> : null}
              </Pressable>
            );
          })}
        </View>
      ) : null) : (
      <OiSheet visible={open} onClose={() => setOpen(false)} title={label}>
        {options.length === 0 ? (
          <Text style={{ paddingVertical: 16, color: palette.textDim, fontFamily: fonts.sans, fontSize: 13 }}>Nenhuma opção disponível.</Text>
        ) : options.map((o) => {
          const on = o.value === value;
          return (
            <Pressable key={o.value} onPress={() => { onValueChange(o.value); setOpen(false); }}
              style={{ minHeight: touch.default + 4, flexDirection: "row", alignItems: "center", gap: 10, borderBottomWidth: 1, borderBottomColor: palette.border2 }}>
              <View style={{ flex: 1 }}>
                <Text style={{ fontSize: 14, fontFamily: on ? fonts.sansSemibold : fonts.sans, color: on ? palette.accent : palette.text }}>{o.label}</Text>
                {o.hint ? <Text style={{ fontSize: 12, fontFamily: fonts.sans, color: palette.textMute }}>{o.hint}</Text> : null}
              </View>
              {on ? <OiIcon name="check" size={20} color={palette.accent} /> : null}
            </Pressable>
          );
        })}
      </OiSheet>
      )}
    </View>
  );
}

/** Rodapé fixo de formulário em sheet/tela: Cancelar + ação primária. */
export function OiFormFooter({ onCancel, onConfirm, confirmLabel, cancelLabel = "Cancelar", loading }: { onCancel: () => void; onConfirm: () => void; confirmLabel: string; cancelLabel?: string; loading?: boolean }) {
  const { palette } = useOiTheme();
  return (
    <View style={{ flexDirection: "row", gap: 8, paddingTop: 12, borderTopWidth: 1, borderTopColor: palette.border2, marginTop: 4 }}>
      <Pressable onPress={onCancel} style={{ flex: 1, height: touch.lg, borderRadius: radius.md, borderWidth: 1, borderColor: palette.border, alignItems: "center", justifyContent: "center" }}>
        <Text style={{ fontSize: 14, fontFamily: fonts.sansSemibold, color: palette.text }}>{cancelLabel}</Text>
      </Pressable>
      <Pressable onPress={loading ? undefined : onConfirm} style={{ flex: 1, height: touch.lg, borderRadius: radius.md, backgroundColor: palette.accent, opacity: loading ? 0.6 : 1, alignItems: "center", justifyContent: "center" }}>
        <Text style={{ fontSize: 14, fontFamily: fonts.sansSemibold, color: palette.accentFg }}>{loading ? "Salvando…" : confirmLabel}</Text>
      </Pressable>
    </View>
  );
}
