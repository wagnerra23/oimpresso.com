---
id: reference-tecnico-frontend-tokens
name: Técnico — Front-end e tokens
description: Inertia + React + Tailwind sobre tokens DTCG compilados por Style Dictionary — a cadeia de onde a cor vem, e as proibições de UI que o ds:report cobra.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: tecnico
nav_order: 30
lente: [construir]
---

# Técnico — Front-end e tokens

> **Cor, tipo, raio e espaçamento não são literais no componente.** Vêm de tokens DTCG
> compilados; o design system é fonte-única no git ([ADR 0239](../decisions/0239-governanca-design-system-git-ssot-regressao-ia.md)).

## A cadeia: de onde a cor vem

```
resources/css/tokens/*.tokens.json   (DTCG, autoria)
        ↓  style-dictionary.config.mjs   (npm run tokens:build)
    CSS custom properties               (--bg, --surface, --text, --accent, …)
        ↓  <html class="cockpit">
            a tela
```

O build de token roda **antes** do Vite: `predev:inertia` e `prebuild:inertia` chamam
`tokens:build` (ver [`package.json`](../../package.json)). O CSS de token é **gerado** — editar o
arquivo de saída é trabalho que o próximo build apaga.

## Canon visual

- Primary **roxo** `oklch(0.55 0.15 295)` ([ADR 0190](../decisions/0190-primary-button-roxo-universal-295.md) ·
  errata de tokens [ADR 0300](../decisions/0300-errata-0239-nome-real-fonte-design-system.md)). O azul do shadcn é legado
  superseded.
- Neutros quentes; superfícies quase planas: 1px de borda, sem glassmorphism, sem gradiente.
- **IBM Plex Sans / Mono self-hosted** — sem CDN: renderiza offline e imprime sem FOUT.
- Escuro tem par de token pra tudo; o accent do modo escuro **não** é o mesmo do claro (`0.55` é
  fundo de botão, não texto sobre fundo escuro).

## Proibições de UI

| Proibido | Em vez disso |
|---|---|
| modal full-screen pra detalhe | **drawer lateral** (padrão PT-02) |
| inglês em UI cliente-facing | português, sentence case |
| emoji no app operacional | ícone Lucide |
| paleta inventada / cor crua no componente | token |
| arquivo novo pra variação de tela | tweak no mesmo componente — ver [Contrato de tela](TECNICO-CONTRATO-DE-TELA.md) |

Quem cobra: [`ds-report.mjs`](../../scripts/ds-report.mjs) (`npm run ds:report`), mais os
baselines de lint e de tamanho de CSS descritos em [Qualidade & CI](TECNICO-QUALIDADE-CI.md).

## Densidade

Tela operacional é **densa por padrão**: tabela apertada, item de sidebar curto, número tabular
(`tnum`) pra coluna de valor não dançar. A generosidade de espaço é do site de marketing, não do
balcão — quem usa o sistema passa o dia nele.
