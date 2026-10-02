/**
 * ScreenContainer — ADAPTADOR (Onda 3). Antes: SafeAreaView + classes Tailwind.
 * Agora delega ao OiScreen (fundo --bg do DS + safe area). Usado ainda por dashboard,
 * empresas, estoque/[id] e dev/theme-lab. Telas novas: OiScreen direto.
 */
import type { ReactNode } from "react";
import type { Edge } from "react-native-safe-area-context";

import { OiScreen } from "@/components/oi";

export function ScreenContainer({ children, edges = ["top", "bottom"] }: { children: ReactNode; edges?: Edge[]; className?: string; containerClassName?: string }) {
  return <OiScreen edges={edges}>{children}</OiScreen>;
}

export default ScreenContainer;
