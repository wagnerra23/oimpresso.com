# RUNBOOK: Design-Sync PULL (design vivo → git tokens)

> **Quando usar:** você (Wagner) desenha/ajusta tokens no **Claude Design** (claude.ai/design, projeto "Office Impresso — Design System") e quer que aquilo chegue no git **sem transcrição manual** (que erra).
>
> **Direção:** design = **superfície de autoria** (onde você desenha); **git = SSOT** (o que deploya e o CI valida). O design→git é PULL determinístico. Emenda a [ADR 0315](../../memory/decisions/0315-design-sync-claude-design-vs-cowork-charter.md) (que antes só permitia git→design). Proposta: [`2026-07-08-ds-direcao-design-git-e-sidebar-dark-fixa.md`](../../memory/decisions/proposals/2026-07-08-ds-direcao-design-git-e-sidebar-dark-fixa.md).

---

## ⚠️ A lição que motiva este runbook (não pule)

O espelho **NÃO está uniformemente à frente do git.** Em 2026-07-08 o diff provou: das 28 divergências, **19 eram o design VELHO** (tokens shadcn azul hue 258 que o próprio README do DS diz que foram *"superseded"*). Um `design→git` cego teria **regredido** o git. O design só estava à frente onde o Wagner **editou de verdade** (o canvas do cockpit). **Por isso o passo do diff + triagem é obrigatório — nunca faça dump.**

---

## Passos

### 1. Puxar o design vivo (agente)
```
DesignSync get_file  project=019dd02f-d2d0-7ba6-a57f-24b3ddd073ac  path=colors_and_type.css
```
Salvar o conteúdo num arquivo de staging (fora do repo), ex.: `<scratchpad>/design-colors_and_type.css`. (Opt-in `/design-sync` exigido — hooks `block-(skill-)design-sync-without-optin`.)

### 2. Rodar o diff determinístico (o motor do protocolo)
```bash
node scripts/design-sync/ds-token-diff.mjs <staging>/design-colors_and_type.css resources/css/tokens
```
Reporta, por escopo (`light` / `dark` / `cockpit-light` / `cockpit-dark`), cada **divergência de valor** + tokens só-de-um-lado. Read-only (não escreve nada).

### 3. Triagem (decisão humana — Fundações Tier 0)
Para cada divergência, classificar:
- **Design intencionalmente à frente** (Wagner editou) → **adota** design→git.
- **Design stale** (valor legado / README diz "superseded") → **mantém git** + marca pra re-espelhar (passo 7).
Divergências que mexem no **look de toda tela** (canvas, sidebar, primary) → Wagner decide **por imagem**, não por oklch.

### 4. Gravar no git
Editar `resources/css/tokens/semantic.tokens.json` só nas divergências **adotadas** (o `$value` = light; `$extensions.com.oimpresso.dark` = dark).

### 5. Build
```bash
npm run tokens:build   # regenera _generated-*-{light,dark}.css a partir do JSON
```

### 6. PR + gate de screenshot
PR **draft**. Fundações = **Wagner aprova o SCREENSHOT buildado** (não tabela, não oklch) antes de merge. `deploy.yml` roda em push no `main` e **não** ignora `resources/css/**` → **merge = deploy**. Logo o merge/deploy é sempre decisão do Wagner + smoke real na tela logada (R1/R2/R10).

### 7. Pós-merge — re-espelhar o stale (git→design)
Para as divergências classificadas "design stale", refrescar o design a partir do git (fecha o loop, para de divergir):
```
DesignSync finalize_plan  writes=[colors_and_type.css]  → planId
DesignSync write_files    planId  files=[{path:colors_and_type.css, localPath:<git _generated concatenado>}]
```
Assim o espelho volta a bater com o git. (É a direção legítima "read-mostly a partir do git aprovado" da 0315.)

---

## Sentinela de drift (P2, opcional)
O mesmo `ds-token-diff.mjs` rodado periodicamente (cron/CI advisory) **avisa** quando design e git separarem — sem bloquear. Promover a gate required só depois de estável (política [ADR 0314](../../memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md): required = só Tier-0).

## Anti-padrões
- ❌ `design→git` em massa sem rodar o diff + triagem (regride git com token stale).
- ❌ Portar token de **valor/canvas/sidebar/primary** sem gate de screenshot Wagner.
- ❌ Tratar o espelho como fonte concorrente do git (git é SSOT — [ADR 0239](../../memory/decisions/0239-governanca-design-system-git-ssot-regressao-ia.md)).
- ❌ Editar `_generated-*.css` na mão (é output; edite o `.tokens.json` e rode o build).

**Criado:** 2026-07-08 — protocolo de pull design→git + motor `ds-token-diff.mjs`. Origem: Wagner "gostaria que pegasse direto do design".
