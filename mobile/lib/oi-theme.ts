/**
 * Oimpresso Mobile design tokens — RN port (v3 · skin canônico do handoff 3).
 *
 * Source of truth: ref/handoff-3/app/oimpresso-tokens.css (969 linhas).
 * O CSS usa OKLCH (que o RN não parseia) — valores light convertidos para hex
 * via scripts/oklch (OKLCH→OKLab→sRGB); valores dark já vêm em hex no CSS.
 *
 * v3 (2026-06-19): SKIN do protótipo — accent ROXO (não mais magenta), magenta
 * reservado à marca/logo; fontes Inter + JetBrains Mono; 6 origens (+ OFI);
 * tokens de ação (verde), accentText e info.
 */

export type OiThemeName = "light" | "dark";
export type OiDensity = "compact" | "normal" | "comfy";

export type OiOriginKey = "OS" | "CRM" | "FIN" | "PNT" | "MFG" | "OFI";

export type OiBrand = {
  magenta: string;
  purple: string;
  deep: string;
  cyan: string;
  yellow: string;
};

export type OiPalette = {
  bg: string;
  bg2: string;
  surface: string;
  surface2: string;
  border: string;
  border2: string;
  text: string;
  textDim: string;
  textMute: string;
  /** Ação primária (roxo). */
  accent: string;
  accent2: string;
  accentSoft: string;
  accentFg: string;
  /** Texto/realce em accent — escuro no claro, claro no escuro (contraste AA). */
  accentText: string;
  /** Ação/consulta = verde. */
  action: string;
  action2: string;
  actionSoft: string;
  actionFg: string;
  info: string;
  danger: string;
  warn: string;
  ok: string;
  brand: OiBrand;
  origin: Record<OiOriginKey, { bg: string; fg: string }>;
};

/**
 * Marca institucional (logo CMYK). Magenta é a cor da MARCA — não da ação
 * primária (essa é o accent roxo). Mantida em ambas as paletas.
 */
export const brand: OiBrand = {
  magenta: "#c12682",
  purple: "#5b2987",
  deep: "#391153",
  cyan: "#34dde5",
  yellow: "#fce765",
};

export const lightPalette: OiPalette = {
  bg: "#f7f6f9",
  bg2: "#f1eff4",
  surface: "#fdfcfe",
  surface2: "#f4f3f6",
  border: "#e0dee4",
  border2: "#eae8ee",
  text: "#2d2933",
  textDim: "#67646e",
  textMute: "#908d96",
  accent: "#663e9e",
  accent2: "#7a53b4",
  accentSoft: "#f2eaff",
  accentFg: "#ffffff",
  accentText: "#663e9e",
  action: "#2c894e",
  action2: "#12773d",
  actionSoft: "#d3f5db",
  actionFg: "#ffffff",
  info: "#2ea7ff",
  danger: "#c45b56",
  warn: "#c49548",
  ok: "#549864",
  brand,
  origin: {
    OS: { bg: "#fbe8ce", fg: "#754f27" },
    CRM: { bg: "#d6f0ff", fg: "#2c5e7a" },
    FIN: { bg: "#d9f3dd", fg: "#315e3c" },
    PNT: { bg: "#efe6ff", fg: "#5e4e7c" },
    MFG: { bg: "#fee5dc", fg: "#7c4937" },
    OFI: { bg: "#d5f0fe", fg: "#28577c" },
  },
};

export const darkPalette: OiPalette = {
  bg: "#15131b",
  bg2: "#1a1722",
  surface: "#1e1a27",
  surface2: "#2a2633",
  border: "#2a2633",
  border2: "#232030",
  text: "#f2f2f2",
  textDim: "#b9b7c4",
  textMute: "#7e7b8c",
  accent: "#9a2bcb",
  accent2: "#c85bff",
  accentSoft: "#2c1740",
  accentFg: "#ffffff",
  accentText: "#c85bff",
  action: "#22c55e",
  action2: "#34d36a",
  actionSoft: "#143324",
  actionFg: "#08110b",
  info: "#2ea7ff",
  danger: "#ff4d4f",
  warn: "#ffb300",
  ok: "#22c55e",
  brand: {
    magenta: "#c85bff",
    purple: "#9a2bcb",
    deep: "#7a0b7e",
    cyan: "#34dde5",
    yellow: "#f5e05d",
  },
  origin: {
    OS: { bg: "#4b341d", fg: "#e4c4a1" },
    CRM: { bg: "#1e3d4f", fg: "#a9d1ea" },
    FIN: { bg: "#26402c", fg: "#b0d6b6" },
    PNT: { bg: "#3d334f", fg: "#d0c2ed" },
    MFG: { bg: "#4c3229", fg: "#eebeae" },
    OFI: { bg: "#203d50", fg: "#a1d1f4" },
  },
};

export const radius = { sm: 7, md: 10, lg: 14, pill: 999 } as const;

export const rowHeight: Record<OiDensity, number> = {
  compact: 46,
  normal: 56,
  comfy: 62,
};

export const shadows = {
  pop: {
    shadowColor: "#000",
    shadowOpacity: 0.18,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 8 },
    elevation: 6,
  },
  soft: {
    shadowColor: "#000",
    shadowOpacity: 0.05,
    shadowRadius: 2,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
} as const;

/** Avatar gradient pairs (`oi-av-1` … `oi-av-6`). */
export const avatarGradients: readonly [string, string][] = [
  ["#c4685a", "#a6344f"],
  ["#0096b8", "#2a5fb7"],
  ["#549957", "#007d5e"],
  ["#ac7d1b", "#a34100"],
  ["#9274c3", "#435ab8"],
  ["#009d89", "#007a85"],
];

/**
 * Fontes — Inter (texto) + JetBrains Mono (placas/IDs/valores), conforme
 * `oimpresso-tokens.css` (--font-sans / --font-mono). Carregadas em
 * app/_layout.tsx via @expo-google-fonts.
 */
export const fonts = {
  sans: "Inter_400Regular",
  sansMedium: "Inter_500Medium",
  sansSemibold: "Inter_600SemiBold",
  sansBold: "Inter_700Bold",
  sansExtrabold: "Inter_800ExtraBold",
  mono: "JetBrainsMono_400Regular",
  monoMedium: "JetBrainsMono_500Medium",
  monoSemibold: "JetBrainsMono_600SemiBold",
} as const;

export function getPalette(theme: OiThemeName): OiPalette {
  return theme === "dark" ? darkPalette : lightPalette;
}

/**
 * Convert a hex color + alpha (0..1) to an `rgba()` string. Useful for the
 * `color-mix` patterns from the CSS (status pill backgrounds, etc).
 */
export function hexAlpha(hex: string, alpha: number): string {
  const h = hex.replace("#", "");
  const r = parseInt(h.slice(0, 2), 16);
  const g = parseInt(h.slice(2, 4), 16);
  const b = parseInt(h.slice(4, 6), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}
