/**
 * OiTimeline — timeline vertical conectada (pontos + linha).
 *
 * Bloco 9.3. Cada etapa concluída/atual mostra carimbo de hora + operador +
 * nota. Etapa atual marcada "Em andamento" (ou "Pausado" se `paused`).
 *
 * Espelha a timeline em `ref/design/screens-oficina-os.jsx`.
 */
import { Text, View } from "react-native";

import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { formatTimestamp, type TimelineEvent } from "@/lib/format";

import { OiIcon } from "./OiIcon";

export type OiTimelineProps = {
  events: readonly TimelineEvent[];
};

export function OiTimeline({ events }: OiTimelineProps) {
  const { palette } = useOiTheme();
  return (
    <View style={{ paddingLeft: 4 }}>
      {events.map((ev, i) => {
        const isLast = i === events.length - 1;
        const dotColor = ev.done
          ? palette.ok
          : ev.current
            ? ev.paused
              ? palette.warn
              : palette.accent
            : palette.bg2;
        const lineColor = ev.done ? palette.ok : palette.border;
        return (
          <View key={ev.stage + i} style={{ flexDirection: "row", gap: 12 }}>
            {/* Coluna do ponto + linha */}
            <View style={{ alignItems: "center", width: 18 }}>
              <View
                style={{
                  width: 16,
                  height: 16,
                  borderRadius: 999,
                  backgroundColor: dotColor,
                  alignItems: "center",
                  justifyContent: "center",
                  borderWidth: ev.current && !ev.done ? 2 : 0,
                  borderColor: palette.surface,
                }}
              >
                {ev.done ? (
                  <OiIcon name="check" size={10} color="#fff" />
                ) : null}
              </View>
              {!isLast ? (
                <View
                  style={{
                    width: 2,
                    flex: 1,
                    backgroundColor: lineColor,
                    marginTop: 2,
                    minHeight: 24,
                  }}
                />
              ) : null}
            </View>

            {/* Conteúdo */}
            <View style={{ flex: 1, paddingBottom: isLast ? 0 : 18 }}>
              <View
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 8,
                  flexWrap: "wrap",
                }}
              >
                <Text
                  style={{
                    fontFamily: fonts.sansSemibold,
                    fontSize: 13.5,
                    color: ev.done || ev.current ? palette.text : palette.textMute,
                  }}
                >
                  {ev.stage}
                </Text>
                {ev.current ? (
                  <Text
                    style={{
                      fontFamily: fonts.sansSemibold,
                      fontSize: 10.5,
                      color: ev.paused ? palette.warn : palette.accent,
                      textTransform: "uppercase",
                      letterSpacing: 0.6,
                    }}
                  >
                    {ev.paused ? "Pausado" : "Em andamento"}
                  </Text>
                ) : null}
              </View>
              {ev.done || ev.current ? (
                <Text
                  style={{
                    fontFamily: fonts.sans,
                    fontSize: 11.5,
                    color: palette.textMute,
                    marginTop: 2,
                  }}
                  numberOfLines={2}
                >
                  {[
                    ev.done && ev.at ? formatTimestamp(ev.at) : null,
                    ev.operator,
                    ev.note,
                  ]
                    .filter(Boolean)
                    .join(" · ")}
                </Text>
              ) : null}
            </View>
          </View>
        );
      })}
    </View>
  );
}

export default OiTimeline;
