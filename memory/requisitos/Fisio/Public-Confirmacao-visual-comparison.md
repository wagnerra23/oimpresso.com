---
id: requisitos-fisio-public-confirmacao-visual-comparison
title: "Comparação design × produção — Fisio/Public/Confirmacao"
module: Fisio
tela: Fisio/Public/Confirmacao
owner: W
status: rascunho
last_validated: "2026-11-23"
---

# Comparação design × produção — `Fisio/Public/Confirmacao`

> **Âncora:** `prototipo-ui/cowork/Wagner/prototipos/fisio/PacienteConfirmarLink.dc.html`, declarada em `related_prototype` do charter. O protótipo foi desenhado em 2026-11-23, depois da tela (US-FISIO-017): a tela nasceu sem protótipo próprio e isso foi corrigido no mesmo PR.
> Medido em **2026-11-23**, viewport 390 × 844, tema claro nos dois lados.
> Snapshots: [`governance/design/targets/medidas/Fisio--Public--Confirmacao/`](../../../governance/design/targets/medidas/Fisio--Public--Confirmacao/).

## Como a produção foi renderizada

Não há app local. A tela foi renderizada com o componente real (`Confirmacao.tsx`), o CSS de entrada do app (`resources/css/inertia.css`), as fontes `@fontsource` que o `app.tsx` importa e o `cockpit.css` que a própria página importa; só o `@inertiajs/react` foi substituído por um stub (`Head`, `router`, `usePage` com `auth.user = null`, como o middleware compartilha para visitante). O harness não foi versionado. Medir de novo no app de verdade, com um link assinado, quando houver atendimento em produção.

## Veredito: **sem `DIVERGE(bug)` na sonda** (`design-diff --compare --check`, 0 divergências)

Canário: com título 24 px e primário em matiz 220 no lado do design, a mesma comparação acusa 2 `DIVERGE(bug)` (D4 e D6). A sonda discrimina.

## Itens

| Item | Produção | Protótipo | Veredito |
|---|---|---|---|
| Título (h1), tamanho e peso — D4 | 18px · 600 | 18px · 600 | igual |
| Botão primário, fundo — D6 | `oklch(0.55 0.15 295)` | `oklch(0.55 0.15 295)` | igual |
| Altura dos 3 botões | 44px | 44px | igual (alvo de toque) |
| Texto dos botões, tamanho e peso | 14px · 500 | 14px · 500 | igual (protótipo alinhado ao `Button` do DS) |
| Raio dos botões | 6px | 6px | igual |
| Raio do cartão da sessão | 12px | 12px | igual |
| Fonte | IBM Plex Sans | IBM Plex Sans | igual depois do invólucro `.cockpit` (antes a produção saía em fonte do sistema) |
| Texto de cada estado (pergunta, confirmado, desmarcado, fora do prazo) | — | — | igual, lido do DOM nos dois lados |
| Cor do texto e do fundo da página | `oklch(0.137 0.036 258.5)` sobre branco | `oklch(0.22 0.01 80)` sobre `oklch(0.95 0.006 90)` | **diverge, a classificar — fundação**: a produção usa o tema Inertia gerado do DS (`_generated-inertia-theme.css`), o protótipo usa a paleta do espelho do DS; vale para todas as telas, não se resolve nesta |
| Saúde dos tokens na raiz | `--accent`, `--border` e outros não resolvem em `:root` | idem | não medido: os tokens moram em `.cockpit`, não na raiz; igual dos dois lados |

## Corrigido nesta medição

- **Fonte do sistema na produção.** Os tokens do DS (fonte, neutros) só existem sob `.cockpit`, e o `cockpit.css` só entra pelo AppShellV2. A página agora importa o CSS e se envolve num `.cockpit` com `display: contents`, o idioma do `PortalContatoLayout` (CRM) e do `TemaDoCockpit` (Fluxos).
- **Informação repetida depois de responder.** "Sessão confirmada." aparecia junto com "Confirmado! Até…"; a linha de situação agora só aparece para quem volta ao link depois (estado "Voltou depois" no protótipo).

## Fica aberto

- O portal do paciente (`Fisio/Public/Portal`) também renderiza fora do `.cockpit` (o `Portal.tsx` não importa o `cockpit.css`), então deve sair na fonte do sistema como esta saía. Não foi medido aqui: precisa de medição própria antes de receber o mesmo invólucro.
