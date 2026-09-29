/**
 * OiChecklist — lista de tarefas com X/Y + barra de progresso + toggle.
 *
 * Bloco 9.4. Cada item: checkbox tocável (alterna feito), texto com
 * strikethrough quando feito. Header com contador "feitos/total" e barra de
 * progresso. Opcionalmente um campo "Adicionar passo" no final.
 */
import { useState } from "react";
import {
  Pressable,
  Text,
  TextInput,
  View,
} from "react-native";

import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";

import { OiIcon } from "./OiIcon";

export type ChecklistItem = {
  id: string;
  label: string;
  done: boolean;
};

export type OiChecklistProps = {
  items: ChecklistItem[];
  onToggle: (id: string) => void;
  onAdd?: (label: string) => void;
  /** Esconde o input de adicionar passo. */
  hideAdder?: boolean;
};

export function OiChecklist({
  items,
  onToggle,
  onAdd,
  hideAdder,
}: OiChecklistProps) {
  const { palette } = useOiTheme();
  const total = items.length;
  const done = items.filter((i) => i.done).length;
  const pct = total === 0 ? 0 : Math.round((done / total) * 100);

  const [draft, setDraft] = useState("");
  const submit = () => {
    const v = draft.trim();
    if (!v || !onAdd) return;
    onAdd(v);
    setDraft("");
  };

  return (
    <View style={{ gap: 10 }}>
      <View
        style={{
          flexDirection: "row",
          alignItems: "center",
          gap: 10,
        }}
      >
        <Text
          style={{
            fontFamily: fonts.sansSemibold,
            fontSize: 12,
            color: palette.textDim,
          }}
        >
          {done}/{total}
        </Text>
        <View
          style={{
            flex: 1,
            height: 6,
            borderRadius: 999,
            backgroundColor: palette.bg2,
            overflow: "hidden",
          }}
        >
          <View
            style={{
              width: `${pct}%`,
              height: "100%",
              backgroundColor: palette.ok,
            }}
          />
        </View>
        <Text
          style={{
            fontFamily: fonts.sansSemibold,
            fontSize: 11,
            color: palette.textMute,
            minWidth: 32,
            textAlign: "right",
          }}
        >
          {pct}%
        </Text>
      </View>

      <View style={{ gap: 4 }}>
        {items.map((it) => (
          <Pressable
            key={it.id}
            onPress={() => onToggle(it.id)}
            style={({ pressed }) => ({
              flexDirection: "row",
              alignItems: "center",
              gap: 10,
              paddingVertical: 8,
              paddingHorizontal: 4,
              borderRadius: radius.sm,
              backgroundColor: pressed ? palette.bg2 : "transparent",
            })}
          >
            <View
              style={{
                width: 22,
                height: 22,
                borderRadius: 6,
                borderWidth: 1.5,
                borderColor: it.done ? palette.ok : palette.border,
                backgroundColor: it.done
                  ? palette.ok
                  : hexAlpha(palette.bg2, 0.5),
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              {it.done ? <OiIcon name="check" size={14} color="#fff" /> : null}
            </View>
            <Text
              style={{
                flex: 1,
                fontFamily: fonts.sans,
                fontSize: 13.5,
                color: it.done ? palette.textMute : palette.text,
                textDecorationLine: it.done ? "line-through" : "none",
              }}
            >
              {it.label}
            </Text>
          </Pressable>
        ))}
      </View>

      {!hideAdder && onAdd ? (
        <View
          style={{
            flexDirection: "row",
            gap: 8,
            alignItems: "center",
            marginTop: 4,
          }}
        >
          <TextInput
            value={draft}
            onChangeText={setDraft}
            onSubmitEditing={submit}
            returnKeyType="done"
            placeholder="Adicionar passo…"
            placeholderTextColor={palette.textMute}
            style={{
              flex: 1,
              height: 40,
              paddingHorizontal: 12,
              borderRadius: radius.sm,
              borderWidth: 1,
              borderColor: palette.border,
              backgroundColor: palette.bg2,
              color: palette.text,
              fontFamily: fonts.sans,
              fontSize: 13,
            }}
          />
          <Pressable
            onPress={submit}
            disabled={!draft.trim()}
            style={({ pressed }) => ({
              height: 40,
              paddingHorizontal: 14,
              borderRadius: radius.sm,
              flexDirection: "row",
              alignItems: "center",
              gap: 6,
              backgroundColor: palette.accent,
              opacity: !draft.trim() ? 0.4 : pressed ? 0.85 : 1,
            })}
          >
            <OiIcon name="plus" size={14} color="#fff" />
            <Text
              style={{
                color: "#fff",
                fontFamily: fonts.sansSemibold,
                fontSize: 13,
              }}
            >
              Add
            </Text>
          </Pressable>
        </View>
      ) : null}
    </View>
  );
}

export default OiChecklist;
