# PROPOSTA · Protocolo v2 — Onda D: write-path de CÓDIGO via cowork-inbox (DESIGN, não implementação)

> **Status:** DESIGN pra revisão de [W]. **Nada implementado.** [CL] mostra o desenho ANTES de qualquer merge (combinado modo 2). Se [W] aprovar, a implementação vem em PR separado.
> **Pai:** [`_PROPOSTA-protocolo-v2-colapso-W.md`](../_PROPOSTA-protocolo-v2-colapso-W.md) (#2871) · ADR Onda A [`proposals/protocolo-v2-onda-A-memoria-git-ssot.md`](protocolo-v2-onda-A-memoria-git-ssot.md) (#2874).
> **A proposta-pai chama isto de "o único trabalho técnico que destrava o colapso" (§6).**

---

## 1. O que existe hoje

`.github/scripts/cowork-inbox.py` + `cowork-inbox.yml`: um push pra `cowork-inbox/<arquivo>` com header `<!-- cowork: target: <path> -->` (ou `append-to:`) faz o bot:
1. criar branch `cowork/inbox-<sha>`, rodar o script (escreve no path), commit;
2. abrir PR e **`gh pr merge --squash --delete-branch`** (auto-merge).

**Whitelist atual:** `prototipo-ui/` · `memory/` · `docs/`. **Deny:** `..` · `.github/` · `.claude/`. Limite 1 MB.

**Lacuna:** não cobre **código** (`resources/js/**`). Hoje código só entra por [W]/[CL] colando/codando — é o que mantém viva a R1 (transporte manual).

## 2. Objetivo da Onda D

Dar ao Cowork um caminho de escrita pro git que cubra **código de tela** (`resources/js/**`), **atrás de review humano**, **NUNCA auto-merge** — eliminando o transporte manual sem abrir mão de controle.

## 3. Desenho proposto (2 modos no mesmo mecanismo)

O `cowork-inbox.py` passa a **classificar o destino** e ramificar o fluxo:

| Classe | Paths | Fluxo | Auto-merge? |
|---|---|---|---|
| **DOC** (hoje) | `prototipo-ui/` · `memory/` · `docs/` | escreve → PR → merge | **SIM** (inalterado) |
| **CÓDIGO** (novo) | `resources/js/**` (só) | escreve → PR → **PARA** (label `cowork-code-review` + aguarda review humano) | **NÃO — nunca** |

Mudanças mínimas:
- **`cowork-inbox.py`:** adicionar `CODE_PREFIXES = ("resources/js/",)`; `validate_path` aceita DOC **ou** CÓDIGO; `process_file` retorna a classe.
- **`cowork-inbox.yml`:** depois de abrir o PR, **só** roda `gh pr merge --auto --squash` se TODOS os arquivos do push forem DOC. Se houver **qualquer** arquivo CÓDIGO → **não mergeia**; aplica label `cowork-code-review`, atribui [W], e para. (Mistura DOC+código no mesmo push = tratado como código: não auto-merge.)

## 4. Por que é seguro (risk register)

| Risco | Mitigação |
|---|---|
| **Código malicioso/injeção** dropado no inbox vira PR | **Nunca auto-merge** código · **review humano obrigatório** · todos os gates required rodam no PR (Pest, PHPStan, Vite, visual-regression, **a11y-axe** [Onda C], UI Lint, lint:baseline) |
| **Escopo escapando** (escrever em `app/**`, `Modules/**`, migrations, rotas) | Whitelist de código = **só `resources/js/**`**. `app/`, `Modules/`, `database/`, `routes/` **não** entram (mudança de backend/Tier-0 segue só-[CL]/[W]) |
| **`.github/`/`.claude/` (self-modify do CI/agente)** | Já no **DENY** atual — mantido |
| **Path traversal / arquivo gigante** | `..` negado + limite 1 MB — já existem |
| **Multi-tenant Tier 0** | `resources/js/**` é front; não toca `business_id`/scope. Backend continua fora do write-path |
| **Bypass de review** (reviews ainda não são required no branch protection) | O **não-auto-merge é no próprio script** (não depende de branch protection); PR de código fica aberto até [W] mergear |

## 5. O que NÃO muda

- Fluxo DOC atual (auto-merge memory/prototipo-ui/docs) — intacto.
- Backend (`app/`, `Modules/`, migrations, rotas), `.github/`, `.claude/` — **fora** do write-path, seguem só-[CL]/[W].
- Soberania-[W] e gates required — intactos; o write-path de código **passa por todos**.

## 6. Plano de implementação (só APÓS [W] aprovar este design)

1. **PR-D1** — `cowork-inbox.py`: classe DOC/CÓDIGO + whitelist `resources/js/**` + testes (pytest: code → não-merge; doc → merge; `app/` → SKIP). 
2. **PR-D2** — `cowork-inbox.yml`: gate de auto-merge condicional (merge só se 100% DOC) + label/assignee em código.
3. **Smoke real:** dropar um arquivo `resources/js/**` trivial no inbox → confirmar que abre PR e **não** mergeia sozinho.

---

**Decisão = [W].** Isto é só o desenho — nada implementado. Aprovou? Eu abro PR-D1/D2 (e te mostro o diff do script antes do merge, modo 2). Quer mudar a whitelist (ex.: incluir/excluir algo) ou o gatilho? Diz aqui.
