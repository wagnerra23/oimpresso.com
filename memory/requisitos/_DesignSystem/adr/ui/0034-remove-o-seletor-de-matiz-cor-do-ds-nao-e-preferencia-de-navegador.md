---
id: requisitos-design-system-adr-ui-0034-remove-o-seletor-de-matiz-cor-do-ds-nao-e-preferencia-de-navegador
---

# ADR UI-0034 · Remove o seletor de matiz: cor do DS não é preferência de navegador

- **Status**: proposto
- **Data**: 2026-09-08
- **Ratificação**: merge deste PR por [W].
- **Decisão de**: [M] 2026-09-08, textual: *"vamos remover o seletor de matiz, para isso não ocorrer
  mais (…) respeita a predominância do ds"* e *"as cores devem sempre ser fiéis ao protótipo"*.
- **Decisores**: Wagner (merge), [M] (decisão de escopo), Claude Code (medição + execução)
- **Categoria**: ui · fundações · tokens
- **Refs**: [UI-0029](0029-prototipo-soberano-sobre-adr-ui.md) (protótipo soberano na FORMA) ·
  [UI-0031](0031-fundacao-dark-adota-o-accent-do-prototipo.md) (accent escuro) ·
  [UI-0033](0033-foreground-de-success-e-warning-vira-cor-de-contraste.md) (mesma classe: token sem par de tema)

## Contexto — a cor do DS estava terceirizada pro `localStorage`

O `TweaksPanel` oferecia um slider **"Tom do accent"** (0–360°). O valor ia pro `localStorage`
(`oimpresso.cockpit.tweaks.accentHue`) e o `AppShellV2` reescrevia, **por style inline**,
`--accent` · `--accent-2` · `--accent-soft` · `--bubble-me` a partir dele.

Style inline vence **qualquer** seletor — inclusive `.cockpit[data-theme="dark"]`, que é o **mesmo
elemento**. Logo a preferência de UM navegador mandava na cor do Design System.

**Medido em produção** (2026-09-08, tema escuro): o badge do atalho IA saía
`oklch(0.70 0.15 220)` — **ciano** — enquanto o checkbox ao lado, que lê `--color-primary`,
saía `oklch(0.7 0.15 295)` — **roxo**. Dois elementos, duas cores, mesma tela.

E o azul **não existe na paleta do DS** — a ADR 0401 já dizia isso, e o mesmo erro já tinha sido
diagnosticado e registrado em `Produto/Unificado/Index.charter.md` (2026-08-24), quando um agente
mediu o azul em produção e concluiu que era "a cor da empresa".

## O agravante: o default ERA azul, e não há migração

Até **2026-06-08** o default do slider era **220 (azul)**; depois virou 295 (roxo). Nada migra nem
limpa o valor antigo. Todo navegador que abriu o sistema antes dessa data **tem 220 gravado e vê
azul até hoje** — mais de 3 meses. Duas pessoas na mesma tela veem cores diferentes, e nenhuma
das duas está vendo o DS.

## Decisão

O slider sai. Cor de acento passa a vir **só** do CSS gerado do DTCG
(`_generated-cockpit-light/dark.css`, hue 295), que já tem par de tema nos três tokens.

Removidos: o controle no `TweaksPanel`, o state e a persistência no `AppShellV2`, a reescrita
inline dos 4 tokens, e a constante `LS.TW_HUE` (órfã).

**Densidade continua inline** — é layout do usuário, não token de cor. A distinção é a regra:
o usuário ajusta *quanta informação cabe na tela*; **não** ajusta a identidade visual.

## A premissa que NÃO se sustentou — e a peça a mais que ela exigiu

A decisão veio com a premissa *"não causa nenhum prejuízo em nenhuma outra tela"*. **Medida, ela
falhou** — e o próprio código avisava, num comentário que dizia *"NÃO conserte o `--bubble-me`"*.

`--bubble-me` (bolha do chat) é **alias de `var(--accent)`** com `dark_absent`. Sem o inline
segurando-o em 0.55, ele viraria 0.70 no escuro, e o texto branco **fixo**
(`--bubble-me-fg: #ffffff`, sem par de tema) cairia:

| cenário | contraste | AA |
|---|---|---|
| hoje (inline segura 0.55) | **5,17:1** | ✅ |
| remoção ingênua (vira `var(--accent)` = 0.70) | **2,81:1** | ❌ |

Consumidores: 4 arquivos, incluindo as bolhas do Whatsapp (`ConversationThread.tsx`).

**Conserto, e ele é o próprio protótipo:** `prototipo-ui/cowork/styles.css:28` declara
`--bubble-me: oklch(0.55 0.15 295)` **literal**, e **não** redeclara no bloco escuro. Ou seja, o
protótipo já dizia que a bolha é 0.55 nos dois temas — quem virou alias foi o DTCG. O token ganhou
par de tema escuro explícito (0.55), e o valor deixa de depender do inline.

É a **mesma classe** da UI-0033: token com `dark_absent` que precisava de par próprio.

## O que esta ADR NÃO faz

- **Não** mexe em `--color-primary` (nunca foi reescrito por ninguém).
- **Não** apaga a chave já gravada nos navegadores — ela fica órfã e inofensiva, porque nada mais a lê.
- **Não** toca densidade nem vibe.

## Consequências

- Quem tinha azul **passa a ver roxo** na próxima visita. É o efeito pretendido.
- Ninguém pode mais mudar a matiz pela UI — que é o ponto: cor é token do DS, não preferência.
- Baselines visuais que fotografaram telas com acento azul vão acusar diferença. É o ganho.

## Prova

- `npm run tokens:equivalence` → **exit 0** · 312 provados · **0 divergências**
- `npm run tokens:build` → **1 linha exata** no `_generated-cockpit-dark.css`
- `npm run tokens:version:write` → v1.3.0 → **v1.4.0** (`+1`)
- `tsc --noEmit` → **314 erros = MESMO baseline**; os erros nesses arquivos são pré-existentes
  (as chaves duplicadas de `shared.ts` estão no `origin/main`, conferido linha a linha)
- `eslint` → nenhum erro novo
