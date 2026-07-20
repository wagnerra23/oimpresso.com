# Sessão 2026-07-20 — Enforcement de protocolo via hooks (conversa meta)

**Contexto:** conversa curta e meta (não trabalho de módulo). Começou com uma pergunta factual
("quais os arquivos do produto") e virou discussão sobre por que Claude pulou regras Tier A e como
impedir isso mecanicamente.

**Branch:** `test/financeiro-ci-baseline-lane` (STALE — ~+3291 / −5516 vs `origin/main`).
Nada implementado; só discussão + este registro. Hook novo, se houver, entra por PR pra `main`.

---

## Pontos discutidos

1. **Falha de abertura.** Claude respondeu "quais os arquivos do produto" com `Glob` no filesystem,
   pulando dois Tier A: `brief-fetch` (o `SessionStart` até já tinha carregado o brief) e `mcp-first`
   (usar `memoria-search`/tools MCP antes de ler disco).
2. **Como garantir que não pule.** Distinção central: **instrução** (skill/CLAUDE.md) só empurra e falha;
   **hook `PreToolUse`** é executado pelo harness e bloqueia de verdade. O repo já usa o padrão
   (`block-automem.ps1`, `block-test-fora-ct100.ps1`, `block-mwart-violation.ps1`) e já existe um
   `mcp-first-warning.ps1` — hoje o enforcement de mcp-first é **soft-warning**, não bloqueio.
3. **Crítica adversarial da proposta de hook** (a pedido — "Adversário"):
   - Trata o sintoma (tocar o disco), não a causa (escolher a ferramenta errada por julgamento).
   - Furo de escape trivial via `Bash: ls`/`Get-Content` se o matcher não cobrir `Bash|PowerShell`.
   - Estado de sessão é o ponto fraco: hooks são stateless; exigiria arquivo de marca, e o
     `SessionStart` já dispara `brief-fetch` (então "MCP já foi tocado" é sempre verdade no seg. 0).
   - Falso positivo garante o próprio fracasso: leitura legítima de `memory/` vira atrito → nasce um
     escape valve → passa a ser usado por reflexo → volta à estaca zero.
   - "Mais parede ≠ mais obediência": a pilha de hooks já é a prova de que enforcement-por-camada
     falha; o ganho real do hook é estreito (impede 1 padrão específico), não "a solução".
4. **Persistência de hooks ("não se perderem"):**
   - Onde mora decide tudo: `.claude/settings.json` **versionado** (vai pro time, sobrevive reclone)
     vs `.claude/settings.local.json` **gitignored** (só na máquina, some) vs `~/.claude/` (fora do repo).
   - Config + script no **mesmo commit** (senão hook falha silencioso).
   - Não nascer em **branch morto** (este branch é o exemplo vivo).
   - **Estado runtime** (`.claude/run/*`) *deve* se perder; **config** não. Não confundir.
   - Buraco atual: falta um **guardião de integridade no CI** que valide `settings.json` ↔ disco
     (todo hook referenciado existe; todo script existente está referenciado). Sem isso, remover/
     renomear entrada num merge some sem CI vermelho.

## Acordados

- Enforcement confiável = **hook** (harness), não instrução.
- Lugar de hook do time = **`settings.json` versionado**; `settings.local.json` é gitignored e some.
- Hook novo entra por **PR pra `main`**, não em branch stale.
- **Nada foi implementado** nesta sessão (sem criar hook / sem mexer em `settings.json`).

## Desacordados / em aberto

- **Comportamento** (bloquear hard vs avisar soft) e **escopo** (`memory/**` vs `+Modules/**`) do hook
  proposto: pergunta feita, **dispensada** por Felipe → indefinido.
- **Guardião de integridade CI** (settings ↔ disco): proposto, **não verificado** se já existe nem
  aprovado pra criar.
- Interpretação de "Adversário" foi **assumida** (auto-crítica da proposta), não confirmada.

---

_Registro append-only. Autor: Claude (Opus 4.8) + Felipe [F]. Sem PII. Sem mudança de código._
