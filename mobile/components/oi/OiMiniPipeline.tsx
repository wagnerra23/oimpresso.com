/**
 * OiMiniPipeline — N segmentos colorindo etapas do pipeline.
 *
 * Bloco 9.2. Segmento verde = concluído, accent = atual, neutro = pendente.
 * Espelha o `mini-pipeline` dos cards de Produção (4 segmentos) e Oficina
 * (6 segmentos).
 */
import { View, type ViewStyle } from "react-native";

import { useOiTheme } from "@/lib/oi-theme-context";

export type OiMiniPipelineProps = {
  /** Total de etapas (largura é dividida igualmente). */
  total: number;
  /** Índice (0-based) da etapa atual. */
  currentIndex: number;
  /** Marca a etapa atual como pausada/aguardando. */
  paused?: boolean;
  height?: number;
  style?: ViewStyle;
};

export function OiMiniPipeline({
  total,
  currentIndex,
  paused = false,
  height = 5,
  style,
}: OiMiniPipelineProps) {
  const { palette } = useOiTheme();
  const segs = Array.from({ length: Math.max(1, total) }, (_, i) => i);
  const cur = Math.max(0, Math.min(total - 1, currentIndex));
  return (
    <View
      style={[
        {
          flexDirection: "row",
          gap: 2,
          height,
          width: "100%",
        },
        style,
      ]}
    >
      {segs.map((i) => {
        let color = palette.bg2;
        if (i < cur) color = palette.ok;
        else if (i === cur) color = paused ? palette.warn : palette.accent;
        return (
          <View
            key={i}
            style={{
              flex: 1,
              borderRadius: 999,
              backgroundColor: color,
            }}
          />
        );
      })}
    </View>
  );
}

export default OiMiniPipeline;
