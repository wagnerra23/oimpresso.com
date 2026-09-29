import type { ReactNode } from "react";
import {
  Pressable,
  ScrollView,
  Switch,
  Text,
  TextInput,
  type TextInputProps,
  View,
} from "react-native";

import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon, type OiIconName } from "./OiIcon";

// ─── Step chips header ──────────────────────────────────────────────────────

export type WizardStep = { id: string; label: string };

export function OiWizardSteps({
  steps,
  current,
  maxReached,
  onGo,
}: {
  steps: ReadonlyArray<WizardStep>;
  current: number;
  maxReached: number;
  onGo: (i: number) => void;
}) {
  const { palette } = useOiTheme();
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={{
        paddingHorizontal: 12,
        paddingVertical: 10,
        gap: 6,
      }}
      style={{
        backgroundColor: palette.bg,
        borderBottomColor: palette.border,
        borderBottomWidth: 1,
      }}
    >
      {steps.map((s, i) => {
        const active = i === current;
        const done = i < current || (i <= maxReached && !active);
        const bg = active
          ? palette.accent
          : done
            ? hexAlpha(palette.accent, 0.12)
            : palette.surface;
        const fg = active
          ? "#fff"
          : done
            ? palette.accent
            : palette.textDim;
        const border = active
          ? palette.accent
          : done
            ? hexAlpha(palette.accent, 0.4)
            : palette.border;
        return (
          <Pressable
            key={s.id}
            onPress={() => onGo(i)}
            style={{
              flexDirection: "row",
              alignItems: "center",
              gap: 6,
              backgroundColor: bg,
              borderColor: border,
              borderWidth: 1,
              borderRadius: radius.pill,
              paddingHorizontal: 10,
              paddingVertical: 6,
            }}
          >
            <View
              style={{
                width: 18,
                height: 18,
                borderRadius: 999,
                backgroundColor: active
                  ? "#ffffff33"
                  : done
                    ? palette.accent
                    : palette.bg2,
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              {done && !active ? (
                <OiIcon name="check" size={11} color="#fff" />
              ) : (
                <Text
                  style={{
                    color: active ? "#fff" : palette.textMute,
                    fontFamily: fonts.sansBold,
                    fontSize: 10,
                  }}
                >
                  {i + 1}
                </Text>
              )}
            </View>
            <Text
              style={{
                color: fg,
                fontFamily: fonts.sansSemibold,
                fontSize: 12.5,
              }}
            >
              {s.label}
            </Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

// ─── Bottom bar (Voltar / Avançar / Salvar) ─────────────────────────────────

export function OiWizardBottomBar({
  step,
  total,
  onPrev,
  onNext,
  onCancel,
  saving,
  isEditing,
}: {
  step: number;
  total: number;
  onPrev: () => void;
  onNext: () => void;
  onCancel: () => void;
  saving?: boolean;
  isEditing?: boolean;
}) {
  const { palette } = useOiTheme();
  const isLast = step === total - 1;
  return (
    <View
      style={{
        flexDirection: "row",
        gap: 8,
        padding: 12,
        backgroundColor: palette.surface,
        borderTopColor: palette.border,
        borderTopWidth: 1,
      }}
    >
      {step > 0 ? (
        <Pressable
          onPress={onPrev}
          style={{
            borderColor: palette.border,
            borderWidth: 1,
            borderRadius: radius.md,
            paddingHorizontal: 16,
            paddingVertical: 11,
            backgroundColor: palette.surface,
            flexDirection: "row",
            alignItems: "center",
            gap: 6,
          }}
        >
          <OiIcon name="chev-l" size={16} color={palette.text} />
          <Text
            style={{
              fontFamily: fonts.sansSemibold,
              fontSize: 13.5,
              color: palette.text,
            }}
          >
            Voltar
          </Text>
        </Pressable>
      ) : (
        <Pressable
          onPress={onCancel}
          style={{
            borderRadius: radius.md,
            paddingHorizontal: 16,
            paddingVertical: 11,
            backgroundColor: "transparent",
          }}
        >
          <Text
            style={{
              fontFamily: fonts.sansSemibold,
              fontSize: 13.5,
              color: palette.textDim,
            }}
          >
            Cancelar
          </Text>
        </Pressable>
      )}
      <Pressable
        disabled={saving}
        onPress={onNext}
        style={{
          flex: 1,
          backgroundColor: palette.accent,
          borderColor: palette.accent,
          borderWidth: 1,
          borderRadius: radius.md,
          paddingVertical: 11,
          flexDirection: "row",
          alignItems: "center",
          justifyContent: "center",
          gap: 6,
          opacity: saving ? 0.6 : 1,
        }}
      >
        <Text
          style={{
            fontFamily: fonts.sansSemibold,
            fontSize: 14,
            color: "#fff",
          }}
        >
          {isLast
            ? isEditing
              ? "Salvar alterações"
              : "Salvar"
            : "Avançar"}
        </Text>
        {!isLast ? <OiIcon name="chev-r" size={16} color="#fff" /> : null}
      </Pressable>
    </View>
  );
}

// ─── Form Field ─────────────────────────────────────────────────────────────

export function OiField({
  label,
  required,
  hint,
  error,
  children,
}: {
  label?: string;
  required?: boolean;
  hint?: string;
  error?: string;
  children: ReactNode;
}) {
  const { palette } = useOiTheme();
  return (
    <View style={{ marginBottom: 10 }}>
      {label ? (
        <Text
          style={{
            fontFamily: fonts.sansMedium,
            fontSize: 12,
            color: palette.textDim,
            marginBottom: 4,
          }}
        >
          {label}
          {required ? (
            <Text style={{ color: palette.danger }}>{" *"}</Text>
          ) : null}
        </Text>
      ) : null}
      {children}
      {hint ? (
        <View
          style={{
            flexDirection: "row",
            alignItems: "center",
            gap: 4,
            marginTop: 4,
            backgroundColor: palette.bg2,
            borderRadius: radius.sm,
            paddingHorizontal: 8,
            paddingVertical: 4,
          }}
        >
          <OiIcon name="zap" size={11} color={palette.textMute} />
          <Text
            style={{
              fontSize: 11,
              fontFamily: fonts.sans,
              color: palette.textDim,
              flex: 1,
            }}
          >
            {hint}
          </Text>
        </View>
      ) : null}
      {error ? (
        <Text
          style={{
            color: palette.danger,
            fontFamily: fonts.sansMedium,
            fontSize: 11,
            marginTop: 4,
          }}
        >
          {error}
        </Text>
      ) : null}
    </View>
  );
}

// ─── Text Input ─────────────────────────────────────────────────────────────

export function OiInput({
  mono,
  style,
  ...rest
}: TextInputProps & { mono?: boolean }) {
  const { palette } = useOiTheme();
  return (
    <TextInput
      placeholderTextColor={palette.textMute}
      {...rest}
      style={[
        {
          backgroundColor: palette.bg,
          borderColor: palette.border,
          borderWidth: 1,
          borderRadius: radius.md,
          paddingHorizontal: 12,
          paddingVertical: 11,
          color: palette.text,
          fontFamily: mono ? fonts.mono : fonts.sans,
          fontSize: 14,
        },
        style,
      ]}
    />
  );
}

// ─── Segmented control ──────────────────────────────────────────────────────

export type OiSegItem<T extends string> = {
  value: T;
  label: string;
  icon?: OiIconName;
};

export function OiSeg<T extends string>({
  value,
  onChange,
  items,
}: {
  value: T;
  onChange: (v: T) => void;
  items: ReadonlyArray<OiSegItem<T>>;
}) {
  const { palette } = useOiTheme();
  return (
    <View
      style={{
        flexDirection: "row",
        gap: 4,
        backgroundColor: palette.bg2,
        borderRadius: radius.md,
        padding: 3,
      }}
    >
      {items.map((it) => {
        const on = it.value === value;
        return (
          <Pressable
            key={it.value}
            onPress={() => onChange(it.value)}
            style={{
              flex: 1,
              flexDirection: "row",
              alignItems: "center",
              justifyContent: "center",
              gap: 6,
              backgroundColor: on ? palette.surface : "transparent",
              borderColor: on ? palette.border : "transparent",
              borderWidth: 1,
              borderRadius: radius.sm,
              paddingVertical: 8,
              paddingHorizontal: 10,
            }}
          >
            {it.icon ? (
              <OiIcon
                name={it.icon}
                size={14}
                color={on ? palette.text : palette.textDim}
              />
            ) : null}
            <Text
              style={{
                fontFamily: fonts.sansSemibold,
                fontSize: 12.5,
                color: on ? palette.text : palette.textDim,
              }}
            >
              {it.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

// ─── Check row ──────────────────────────────────────────────────────────────

export function OiCheckRow({
  on,
  onToggle,
  children,
}: {
  on: boolean;
  onToggle: () => void;
  children: ReactNode;
}) {
  const { palette } = useOiTheme();
  return (
    <Pressable
      onPress={onToggle}
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 10,
        paddingVertical: 8,
        paddingHorizontal: 10,
        borderRadius: radius.md,
        borderColor: on ? palette.accent : palette.border,
        borderWidth: 1,
        backgroundColor: on ? hexAlpha(palette.accent, 0.08) : palette.surface,
      }}
    >
      <View
        style={{
          width: 22,
          height: 22,
          borderRadius: 6,
          backgroundColor: on ? palette.accent : palette.surface,
          borderColor: on ? palette.accent : palette.border,
          borderWidth: 1,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        {on ? <OiIcon name="check" size={14} color="#fff" /> : null}
      </View>
      <View style={{ flex: 1 }}>
        {typeof children === "string" ? (
          <Text
            style={{
              fontFamily: fonts.sansMedium,
              fontSize: 13,
              color: palette.text,
            }}
          >
            {children}
          </Text>
        ) : (
          children
        )}
      </View>
    </Pressable>
  );
}

// ─── Switch row (LGPD style toggle) ─────────────────────────────────────────

export function OiSwitchRow({
  label,
  value,
  onValueChange,
}: {
  label: string;
  value: boolean;
  onValueChange: (v: boolean) => void;
}) {
  const { palette } = useOiTheme();
  return (
    <View
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 10,
        paddingVertical: 6,
      }}
    >
      <Text
        style={{
          flex: 1,
          fontFamily: fonts.sansMedium,
          fontSize: 13,
          color: palette.text,
        }}
      >
        {label}
      </Text>
      <Switch
        value={value}
        onValueChange={onValueChange}
        trackColor={{ true: palette.accent, false: palette.border }}
        thumbColor="#fff"
      />
    </View>
  );
}

// ─── Pill chip (multi-select toggle) ────────────────────────────────────────

export function OiPillChip({
  label,
  on,
  onPress,
  color,
}: {
  label: string;
  on: boolean;
  onPress: () => void;
  color?: string;
}) {
  const { palette } = useOiTheme();
  const c = color ?? palette.accent;
  return (
    <Pressable
      onPress={onPress}
      style={{
        flexDirection: "row",
        alignItems: "center",
        gap: 6,
        paddingHorizontal: 12,
        paddingVertical: 7,
        borderRadius: radius.pill,
        borderColor: on ? c : palette.border,
        borderWidth: 1,
        backgroundColor: on ? hexAlpha(c, 0.14) : palette.bg2,
      }}
    >
      <OiIcon
        name={on ? "check" : "plus"}
        size={13}
        color={on ? c : palette.textDim}
      />
      <Text
        style={{
          fontFamily: fonts.sansSemibold,
          fontSize: 12.5,
          color: on ? c : palette.textDim,
        }}
      >
        {label}
      </Text>
    </Pressable>
  );
}

// ─── Done card (success state) ──────────────────────────────────────────────

export function OiDoneCard({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children?: ReactNode;
}) {
  const { palette } = useOiTheme();
  return (
    <View style={{ paddingHorizontal: 16, paddingTop: 28, alignItems: "center" }}>
      <View
        style={{
          width: 76,
          height: 76,
          borderRadius: 20,
          backgroundColor: hexAlpha(palette.ok, 0.18),
          alignItems: "center",
          justifyContent: "center",
          marginBottom: 14,
        }}
      >
        <OiIcon name="check-circle" size={42} color={palette.ok} />
      </View>
      <Text
        style={{
          fontFamily: fonts.sansSemibold,
          fontSize: 20,
          color: palette.text,
          textAlign: "center",
        }}
      >
        {title}
      </Text>
      {subtitle ? (
        <Text
          style={{
            fontFamily: fonts.sans,
            fontSize: 13,
            color: palette.textDim,
            marginTop: 4,
            textAlign: "center",
          }}
        >
          {subtitle}
        </Text>
      ) : null}
      {children}
    </View>
  );
}
