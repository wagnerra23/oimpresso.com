const { themeColors } = require("./theme.config");
const plugin = require("tailwindcss/plugin");

const tailwindColors = Object.fromEntries(
  Object.entries(themeColors).map(([name, swatch]) => [
    name,
    {
      DEFAULT: `var(--color-${name})`,
      light: swatch.light,
      dark: swatch.dark,
    },
  ]),
);

/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: "class",
  // Scan all component and app files for Tailwind classes
  content: ["./app/**/*.{js,ts,tsx}", "./components/**/*.{js,ts,tsx}", "./lib/**/*.{js,ts,tsx}", "./hooks/**/*.{js,ts,tsx}"],

  presets: [require("nativewind/preset")],
  theme: {
    extend: {
      colors: {
        ...tailwindColors,
        // Oimpresso static palette (dark-first reference values). Components
        // that need theme-aware colors should pull from useOiTheme().palette
        // and apply via style prop; these utility classes are convenient for
        // throwaway or always-dark surfaces.
        "oi-bg": "#0c0d0e",
        "oi-bg2": "#08090a",
        "oi-surface": "#131415",
        "oi-surface2": "#191b1c",
        "oi-border": "#222426",
        "oi-border2": "#1b1d1f",
        "oi-text": "#ecebe7",
        "oi-text-dim": "#9f9e9b",
        "oi-text-mute": "#73716e",
        "oi-accent": "#2d869f",
        "oi-accent2": "#499fb8",
        "oi-accent-soft": "#13323c",
        "oi-danger": "#cf4040",
        "oi-warn": "#d08600",
        "oi-ok": "#479c4d",
      },
      fontFamily: {
        sans: ["IBMPlexSans_400Regular"],
        "sans-medium": ["IBMPlexSans_500Medium"],
        "sans-semibold": ["IBMPlexSans_600SemiBold"],
        "sans-bold": ["IBMPlexSans_700Bold"],
        mono: ["IBMPlexMono_400Regular"],
        "mono-medium": ["IBMPlexMono_500Medium"],
        "mono-semibold": ["IBMPlexMono_600SemiBold"],
      },
      borderRadius: {
        "oi-sm": "7px",
        "oi-md": "10px",
        "oi-lg": "14px",
      },
    },
  },
  plugins: [
    plugin(({ addVariant }) => {
      addVariant("light", ':root:not([data-theme="dark"]) &');
      addVariant("dark", ':root[data-theme="dark"] &');
    }),
  ],
};
