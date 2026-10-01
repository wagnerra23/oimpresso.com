# Onda 1 — Base (tokens + fontes + status da OS)

Pacote gerado no projeto de design. **Proposta** — aplicar via PR no `wagnerra23/oimpresso.com`, revisão Wagner + Luiz.

## Arquivos

| Arquivo | Ação |
| --- | --- |
| `mobile/lib/oi-theme.ts` | **Substituir.** Neutros, accent, ok/warn/danger e fontes do DS. Brand, action, info, origin, radius e shadows intocados. Novo: `touch`. |
| `mobile/lib/os-status.ts` | **Criar.** Mapa único das 9 etapas da OS ↔ StatusBadge do DS. |
| `mobile/app/_layout.tsx.patch` | **Aplicar.** Troca Inter/JetBrains Mono por IBM Plex no `useFonts`. |

## Passos

1. Confirmar no `mobile/package.json` que `@expo-google-fonts/ibm-plex-sans` e `@expo-google-fonts/ibm-plex-mono` estão instalados (senão `npx expo install` os dois).
2. Copiar `oi-theme.ts` e `os-status.ts`; aplicar o patch do `_layout.tsx`.
3. `grep -rn "Inter_\|JetBrainsMono_" mobile/` — não deve sobrar nada fora do patch.
4. Rodar o app nos dois temas; conferir Início, Tarefas e Financeiro (já em Oi*, mudam só de cor/fonte).

## Fora desta onda

- Trocar `STATUS_COLORS` de `oss.tsx` por `os-status.ts` — Onda 2 (Oficina). O arquivo já fica pronto.
- Hero com degradê em `(tabs)/index.tsx` — Onda 2/3.

## Valores

Convertidos de OKLCH (`.cockpit`) com a fórmula OKLab → sRGB. Ver seção 01 de "Mobile DS Proposta".
