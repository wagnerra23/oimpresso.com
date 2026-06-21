---
slug: 0285-handoff-publisher-cowork-to-repo
number: 285
title: "Publisher Cowork→repo — fechar o 1º hop do loop zero-paste reusando a cowork-inbox (sem [W] commitar à mão)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-17"
module: governance
related: [0283-handoff-loop-zero-paste, 0282-protocolo-v2-colapso-ratificacao, 0241-loop-design-cowork-code-autonomo-zero-humano, 0269-deploy-automatico-build-no-runner, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0093-multi-tenant-isolation-tier-0]
supersedes: []
---

# ADR 0285 — Publisher Cowork→repo (reusa a `cowork-inbox` · fecha o 1º hop sem [W] no transporte)

> **Status:** aceito por [W] em 2026-06-17 (delegou a escolha do mecanismo: _"escolha a melhor e faça"_).
> Follow-up do **PR-6 (#2921)** / **[ADR 0283](0283-handoff-loop-zero-paste.md)** (Fase 0, "médio→norte" da §Migração).
> Proposta + discovery: `memory/decisions/proposals/handoff-publisher-cowork-to-repo.md`.

## Contexto

Pós-PR-6 o **transporte** do handoff já é mecânico: um `.md` commitado em `prototipo-ui/handoffs/*.md`
no push pra `main` → a Action `handoff-sign-submit.yml` assina (HMAC) e chama o tool MCP `handoff-submit`
→ `pending`. **Mas o "primeiro hop" (artefato do Cowork → o `.md` no repo) ainda exigia [W] commitar
à mão**, porque "o Cowork é read-only no GitHub e não expõe endpoint estável de pendentes" (cabeçalho da
própria Action). O chip de follow-up pedia um **publisher Cowork→repo**.

**Discovery (verificado no repo):** o relay já existe. `cowork-inbox/README.md` diz textual: _"Cowork tem
write GitHub mas não pode aplicar patch no repo: ele cria o arquivo aqui via `github_import_files`, Action
faz o resto."_ A Action `cowork-inbox.yml` pousa qualquer arquivo dropado em `cowork-inbox/**` no destino
(whitelist em `cowork-inbox.py:30` já libera `prototipo-ui/` como tier `auto`) e auto-mergeia doc-only.
Faltava **fiação** — não um relay novo.

**O furo real (verificado):** o auto-merge da `cowork-inbox.yml` é feito com `GITHUB_TOKEN`, e **eventos do
`GITHUB_TOKEN` não disparam outros workflows** (regra anti-recursão do GitHub). Logo o commit que pousa o
handoff em `main` **não** dispara o `on: push` do `handoff-sign-submit.yml` — o handoff nunca viraria
`pending`. (`handoff-sign-submit.yml` não tem `workflow_run`; não há PAT/App de bot no repo — o `grokwr2`
ainda é bootstrap planejado.)

## Decisão

**Reusar a `cowork-inbox` como publisher** (a "Opção B" do chip — relay autenticado — já materializada),
fechando a fiação **inline**:

1. **Convenção:** o Cowork dropa `cowork-inbox/handoff-<slug>.md` com `<!-- cowork: target:
   prototipo-ui/handoffs/<slug>.md -->` + o frontmatter canônico (`handoff_id`/`tela`/`files`/`created_by`/
   `audited_against`) que o assinador e o `handoff-submit` esperam.
2. **Detecção:** `cowork-inbox.py` emite `handoffs=<...>` (`$GITHUB_OUTPUT`) com os `.md` pousados em
   `prototipo-ui/handoffs/`.
3. **Transporte inline:** a `cowork-inbox.yml` ganha um passo que **assina+submete** esses handoffs ali
   mesmo (mesmo job, working tree quente) — **não** espera um `on: push` que o `GITHUB_TOKEN` não dispara.
4. **DRY:** o sign+POST vira `bin/submit-handoff.sh` (fonte única), reusado pela `cowork-inbox.yml` **e**
   pela `handoff-sign-submit.yml` (que continua servindo o commit MANUAL de [W]). Self-test
   (controle-negativo, sem rede) roda no job `selftest`.

### Mecanismo: por que inline, e não as outras opções
- **(a) PAT/App de bot no merge** (faria o push de auto-merge disparar downstream) — **adiada como NORTE**:
  exige [W] provisionar um secret de bot (`grokwr2`, bootstrap Tier-0 humano). Quando existir, também
  destrava o zero-humano de `AUTOMACAO-LOOP-AUTONOMO.md §3`. Não bloqueia este PR.
- **(b) `workflow_run` chaining** — **descartada**: o `cowork-inbox.yml` _completa antes_ do auto-merge
  acontecer (auto-merge espera os checks), então o `.md` ainda não está no `main` quando o evento
  dispararia → o signer não acharia o arquivo. Timing fura.
- **(c) inline (escolhida):** sem secret novo, sem timing, fonte única do transporte, testável.

## Consequências
- **Positiva:** [W] sai do **transporte** do artefato (não clona/commita/pusha/cola/computa HMAC); o
  Cowork dropa 1 arquivo e o handoff vira `pending` sozinho. DRY (um `submit-handoff.sh`). 2 caminhos de
  transporte (commit manual de [W] **e** publisher Cowork) convergem na mesma fila idempotente.
- **Negativa/limite honesto:** enquanto o Cowork for **sessão interativa**, "zero-toque REAL" não existe —
  há sempre **um** ato em-Cowork (importar/publicar o arquivo). O publisher mata o transporte manual, não
  o ato de publicar. É o análogo do "1-clique de merge é o ótimo real" do 0283.
- **Custo:** a `cowork-inbox.yml` passa a precisar de PHP (setup-php). Trivial.

## Guardrails (invariantes do ADR 0283 PRESERVADOS)
- **Sem auto-merge de CÓDIGO.** Só auto-mergeia o `.md` inerte (popula `pending`); o `.tsx` segue
  **1-clique de [W]**. `cowork-inbox.py` já manda `resources/js/**` pra tier **review** (nunca auto-merge).
- **Segredo nunca no Cowork.** Quem assina é a Action (`HANDOFF_SECRET` no CI/servidor). Sem os secrets →
  **skip-as-pass** (advisory).
- **Append-only + idempotente** no `handoff-submit` (`source_hash` no-op; revisão → nova `version`).
- **Não toca prod.** `deploy.yml` ignora `prototipo-ui/**`/`**.md`/`cowork-inbox/**` (E5 do 0283).
- **Multi-tenant Tier 0:** infra de governança; zero dado de negócio trafega.

## O que este ADR NÃO faz (continua BLOQUEADO)
- **Não** reabre o auto-merge de **Camada-4 render-only** do `.tsx` — segue bloqueado até os **5 controles
  de conteúdo** do ADR 0283 existirem e passarem fixture no `gate-selftest`. O publisher é **upstream e
  ortogonal**: entrega o handoff na fila; o merge do código é decisão de [W].
- **Não** promove gate a required nem trata `multi-tenant-gate` como rede pra `.tsx` (E2 = teatro, 0283).

## Responsabilidades
| Papel | Faz |
|---|---|
| **[W]** | (1×) configura `HANDOFF_SECRET`/`HANDOFF_SUBMIT_TOKEN`; (norte) provisiona bot p/ opção (a); clica o merge do código |
| **[CC]/Cowork** | dropa `cowork-inbox/handoff-<slug>.md` repo-nativo+auditado (R1); nunca assina; nunca `.om-*` cru |
| **[CL]** | mantém `submit-handoff.sh`/workflows; liga gates; numera ADR sob OK de [W] |

## Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-06-17 | [CC] | discovery + proposta: relay já existe (`cowork-inbox`); furo real = `GITHUB_TOKEN` não dispara downstream |
| 2026-06-17 | [W] | **aceito** — delegou o mecanismo ("escolha a melhor e faça"); [CL] implementa inline + `submit-handoff.sh` (PR-7) |
