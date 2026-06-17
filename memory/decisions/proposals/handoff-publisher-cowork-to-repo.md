---
title: "Publisher Cowork→repo — fechar o 1º hop do loop zero-paste sem [W] commitar à mão (PR-7)"
status: decided
date: "2026-06-17"
proposal_id: handoff-publisher-cowork-to-repo
proposed_by: claude-code
decided_by: wagner
decided_at: "2026-06-17"
parent_adr: "0283-handoff-loop-zero-paste"
resulting_adr: "0285-handoff-publisher-cowork-to-repo"
related_adrs:
  - 0283-handoff-loop-zero-paste
  - 0282-protocolo-v2-colapso-ratificacao
  - 0241-loop-design-cowork-code-autonomo-zero-humano
  - 0269-deploy-automatico-build-no-runner
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
origem: "Follow-up do PR-6 (#2921). [W]: 'caramba escolha a melhor e faça termine conclua' → mecanismo delegado a [CC]."
---

> ✅ **DECIDED 2026-06-17 — aceito por [W]** (delegou o mecanismo: _"escolha a melhor e faça"_).
> Formalizado em **[ADR 0285](../0285-handoff-publisher-cowork-to-repo.md)** (fonte única da decisão).
> Este doc fica como registro do **discovery** + alternativas avaliadas.

# Publisher Cowork→repo — discovery + design (PR-7, follow-up do PR-6)

## 0. O gap (o "primeiro hop")
Pós-PR-6: `.md` em `prototipo-ui/handoffs/*.md` no push → `handoff-sign-submit.yml` assina + POST
`handoff-submit` → `pending`. **O que faltava:** alguém pôr o artefato do Cowork em
`prototipo-ui/handoffs/<slug>.md` e commitar. Hoje = [W] à mão.

## 1. Discovery — o que JÁ EXISTE (o achado central)
**O publisher é WIRING de coisa que já existe, não greenfield.** A premissa "Cowork é read-only no
GitHub" é parcialmente falsa:

| Peça | Estado | Evidência |
|---|---|---|
| Cowork **cria arquivo** (não dá push/patch) | ✅ | `cowork-inbox/README.md` — _"cria via `github_import_files`, Action faz o resto"_ |
| **Relay** Cowork→repo (a "Opção B" do chip) | ✅ **já existe** | `.github/workflows/cowork-inbox.yml` |
| Pousar em `prototipo-ui/` (tier auto-merge) | ✅ | `.github/scripts/cowork-inbox.py:27-30` |
| Assinatura só no servidor (Cowork não vê segredo) | ✅ | `bin/sign-handoff.php` + `HandoffSubmitTool` |
| `handoff-submit` só cria `pending` (sem auto-merge) | ✅ | `Modules/TeamMcp/Mcp/Tools/HandoffSubmitTool.php` |
| Pousar `.md` não dispara deploy (E5 do 0283) | ✅ | `deploy.yml` `paths-ignore`: `prototipo-ui/**`, `**.md`, `cowork-inbox/**` |

## 2. O gap REAL de engenharia
Encadear `cowork-inbox → handoff-sign-submit` **quebra**: o auto-merge da `cowork-inbox.yml` usa
`GITHUB_TOKEN`, e **eventos do `GITHUB_TOKEN` não disparam outros workflows** → o `on: push` do signer
não acende. Verificado: signer só tem `push`/`pull_request`/`workflow_dispatch` (sem `workflow_run`); não
há PAT/App de bot (o `grokwr2` é bootstrap planejado); `prototipo-ui/handoffs/` nunca existiu (loop nunca
rodou ponta-a-ponta).

## 3. As 3 opções do chip → realidade
- **A — push via API** ("se/quando o Cowork ganhar git write"): parcial — já dá, sem push (Cowork *cria*).
- **B — relay autenticado**: ✅ **escolhida** — o relay já é o `cowork-inbox`.
- **C — polling de manifesto estável**: ❌ — Cowork não expõe endpoint estável (URLs efêmeras ~1h); pull
  inferior ao push quando A/B funcionam.

## 4. Decisão (mecanismo) — ver ADR 0285
**Reusar `cowork-inbox` como publisher**, fechando a fiação **inline** (a `cowork-inbox.yml` assina+submete
o handoff que acabou de pousar, no mesmo job). Alternativas de fiação avaliadas:
- **(a) PAT/App de bot** → adiada como **norte** (precisa [W] provisionar secret; destrava `grokwr2`).
- **(b) `workflow_run`** → descartada (timing: o `cowork-inbox.yml` completa **antes** do auto-merge; o
  `.md` ainda não está no `main`).
- **(c) inline** → **escolhida** (sem secret novo, sem timing, DRY via `bin/submit-handoff.sh`, testável).

## 5. Entregue no PR-7
1. `bin/submit-handoff.sh` — fonte única do sign+POST + self-test (controle-negativo, sem rede).
2. `.github/scripts/cowork-inbox.py` — emite `handoffs=` dos `.md` pousados em `prototipo-ui/handoffs/`.
3. `.github/workflows/cowork-inbox.yml` — setup-php + passo inline de sign+submit.
4. `.github/workflows/handoff-sign-submit.yml` — refatorado pra reusar `submit-handoff.sh` (DRY) + self-test.
5. `cowork-inbox/README.md` — seção "Publisher de handoff (ADR 0285)" com a convenção.
6. `memory/decisions/0285-handoff-publisher-cowork-to-repo.md`.

## 6. Guardrails 0283 preservados / o que fica BLOQUEADO
Sem auto-merge de código (só o `.md` inerte); segredo server-side; append-only; não toca prod. **Auto-merge
Camada-4 render-only do `.tsx` continua bloqueado** até os 5 controles de conteúdo do 0283. O publisher é
upstream e ortogonal.

## 7. Residual honesto
Enquanto o Cowork for sessão interativa, há sempre **um** ato de publicar em-Cowork. O publisher mata o
**transporte manual**, não o clique de publicar. Norte (zero-toque real) = opção (a) + Cowork com gatilho
autônomo.

## 8. Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-06-17 | [CC] | discovery + design; achado: relay já existe; furo = `GITHUB_TOKEN` não dispara downstream |
| 2026-06-17 | [W] | **decided** — "escolha a melhor e faça"; → ADR 0285 + PR-7 |
