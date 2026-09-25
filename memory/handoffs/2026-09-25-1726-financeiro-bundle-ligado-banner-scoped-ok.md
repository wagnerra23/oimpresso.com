---
date: "2026-09-25"
time: "17:26 BRT"
slug: financeiro-bundle-ligado-banner-scoped-ok
tldr: "Banner SCOPED-OK descartava a 1ª regra de 5 CSS do port; e um comentário do inertia.css engolia o @import do bundle do Financeiro inteiro desde maio. Corrigido em 7 PRs, medido em 16 telas × 2 temas, bundle ligado (#7980)."
prs: [7972, 7973, 7974, 7975, 7977, 7978, 7980]
decided_by: [W]
next_steps:
  - "Replicar nas outras 11 telas do Financeiro o smoke pós-deploy feito em Impostos e Conciliação, se quiser recibo por tela"
  - "Errata no _saida-07b: a causa que valia era inertia.css:76 (`**/*.tsx`), não só a :92"
---

# Financeiro — bundle Cowork ligado; banner SCOPED-OK consertado

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work` (@wr23): sem tasks ativas.
- Todos os PRs da sessão mergeados por [W]. Deploy do #7980 (`de3a020d0`) concluído com sucesso. **Smoke em prod feito** (1440px): bundle no CSS servido (`inertia-CKBhpCGc.css`), tokens escuros inertes, raiz com `--text .94`; Impostos com 0 textos <3:1 nos dois temas; Conciliação com o "Importar OFX" já estilizado (30px, roxo, texto `--accent-fg`).
- ⚠️ **Errata:** o "cabeçalho espremido" que cheguei a reportar ao [W] era artefato do painel do navegador com 800px de largura. Em 1440px o cabeçalho sai normal — não é defeito de prod.

## O que aconteceu
1. **Banner quebrado** (`/* SCOPED-OK */ - escopo …`): o texto após o `*/` virava prefixo do seletor e o navegador
   descartava a 1ª regra de cada arquivo escopado. Medi cada arquivo antes de mexer (parse `replaceSync` +
   reinserção em prod na posição da cascata, `getComputedStyle` 2 leituras, temas claro/escuro):
   - #7972 `fin-cowork`/`fin-curadoria` — 0 elementos mudam.
   - #7973 `fin-output` — a pílula `.fin-xlink` ganha forma (linha com referência cresce 0,875px).
   - #7974/#7975 bundle Financeiro e `sells-cowork` — os blocos de tokens CLAROS regrediam o escuro
     (Sells: 7.361 propriedades), então ficaram inertes por seletor `[data-tokens-cowork="ligado"]`.
     #7975 também conserta o gerador do Sells (banner + idempotência por substring, bite-test em sandbox).
2. **Retorno ao Cowork**: recibo `_saida-07b` (#7977) + envio via DesignSync de 4 recibos (inclui
   `_saida-14/15/16` do Sidebar, pendentes) registrado no #7978.
3. **Descoberta**: o bundle do Financeiro (1.238 regras) **nunca entrou no build** — comentário do
   `inertia.css` fechava cedo (L76 `**/*.tsx` e L92 `/* SCOPED-OK */`) e engolia o `@import`. [W] mandou
   ligar. #7980: comentário corrigido + bloco de tokens ESCURO do bundle inerte (par do claro) +
   `.os-btn.primary` com `var(--accent-fg)`. Medido em 16 telas: 0 textos novos <3:1; 11 telas mudam só o
   fundo da raiz, com valor idêntico ao herdado.

## Artefatos gerados
- `resources/css/{fin-cowork,fin-curadoria,fin-output,cowork-canon-financeiro-bundle,sells-cowork,inertia}.css`
- `scripts/scope-sells-cowork-css.py` · `config/stylelint-baseline.json` (sai o `CssSyntaxError` do inertia.css)
- `prototipo-ui/cowork/Wagner/cowork-inbox/financeiro/playbook/_saida-07b.md` · `scripts/design-sync/state/enviados-cowork.json`

## Persistência
Git (7 PRs mergeados) · Cowork (4 recibos enviados, registrados) · MCP via webhook deste handoff.

## Próximos passos pra retomar
Nada bloqueante. Se quiser recibo por tela, repetir o smoke nas outras 11 telas do Financeiro (ver frontmatter `next_steps`).

## Lições catalogadas
- **LC-26 (2×)**: par de barra invertida colapsou em heredoc Python. Contornado com `chr(92)`/Edit.
- **LC-08**: declarei a causa como "linha 92" no recibo #7977; a que valia era a L76. O compilador (não a leitura) mostrou.
- **LC-08**: concluí "cabeçalho espremido, defeito anterior" medindo com viewport de 800px; em 1440px não existe. Medir no tamanho real antes de chamar de defeito.
- **LC-23 near-miss**: comando com fallback `git checkout origin/main -- scripts/…` sobre arquivo editado — barrado pelo hook do `rm` no mesmo comando.

## Pointers detalhados
Medições completas nos corpos dos PRs #7973, #7974, #7975 e #7980.
