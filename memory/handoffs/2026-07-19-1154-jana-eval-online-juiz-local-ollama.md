---
date: "2026-07-19"
time: "1154 BRT"
slug: "jana-eval-online-juiz-local-ollama"
tldr: "PR #4536 (parado no PR, não mergeado): liga eval online da Jana com juiz LOCAL Ollama (OllamaRagasJudge, zero egress) + corrige bug de wiring (config lida em copiloto.* não jana.*). Juiz provado ao vivo no CT 100 (fiel=1.0/alucinada=0.0). Gates OFF por default — ligar em prod é decisão LGPD do [W]."
prs: [4536]
related_adrs:
  - "0318-ragas-eval-real-mata-tautologia-ct100-staging"
  - "0093-multi-tenant-isolation-tier-0"
---

# Handoff — Eval online da Jana com juiz local Ollama (US-COPI-137)

## Estado da entrega

[PR #4536](https://github.com/wagnerra23/oimpresso.com/pull/4536) **aberto, todos os checks required verdes, NÃO mergeado** (DoD do chip = para no PR). Fecha o eixo `qualidade-drift-IA-prod` 4,0 da grade de réguas: a resposta que a Jana serve ao cliente passa a poder ser medida por um juiz local no Ollama do CT 100 (zero egress, não provider pago).

**3 peças:**
1. `OllamaRagasJudge` — juiz RAGAS local (Ollama `/api/chat`), herda prompts do `RagasJudgeService`; falha → `JudgeUnavailableException` → consumidor pula sem gravar `0.0` fabricado.
2. **Fix de wiring** — Job/Listener liam `config('jana.online_eval.*')` (vazio), agora leem `copiloto.online_eval.*` (onde o bloco de fato mora). Antes, `enabled=true` no `config.php` não ligava nada.
3. `jana:ragas-real-eval --judge=local` — a extensão de comando (verificável no pipeline real).

**Provado ao vivo no CT 100** (classe PHP real contra Ollama real, `qwen2.5:3b`): `FIEL=1.000` / `ALUCINADA=0.000` — produz score e discrimina. PII redigida antes do juiz provada por teste.

## Próximos passos (quem pegar)

- **[W]** decide ligar em prod: `Modules/Jana/Config/config.php` → `online_eval.enabled = true` (+ `judge='local'` já é default) + `php artisan config:clear`. É **decisão LGPD** (amostra 5% de trace de cliente biz≠1). Estado default = nada roda.
- **Pré-req infra p/ judge=local:** o Ollama do CT 100 (`ollama-embedder`) precisa do modelo de chat — `qwen2.5:3b` **já foi puxado** nesta sessão. Se recriar o container, re-puxar.
- **Calibração vs humano** ([W]/[E], 100–300 labels) = chip #4 (separado). Sem ela o número no trace é advisory, não confiável na média (ressalva do adversário na US).
- **module-grades-gate** (advisory) ficou vermelho por regressão de 1 ponto na nota da Jana — label `module-grades-allowed-regression` aplicado; se quiser zerar de vez, bump do baseline (opcional, não-Tier-0).

## Riscos / o que NÃO foi feito

- Comando `--judge=local` NÃO foi rodado end-to-end no CT 100 (exigiria checkout do branch inteiro no staging, que está numa branch WIP de outra sessão). O juiz que ele usa está provado ao vivo.
- Arquivos de probe ficaram no `/tmp` do container CT 100 (efêmeros, some no restart) — o checkout rastreado do staging NÃO foi tocado.

## Estado MCP no momento do fechamento

Consulta 2026-07-19 ~11:54 BRT (MCP server acessível):

- **`cycles-active`:** "Nenhum cycle ATIVO em COPI." (sem cycle aberto no momento).
- **`my-work` (@wagner):** 30 tasks ativas — 10 REVIEW, 8 BLOCKED (incl. dormentes NfeBrasil Gold), 12 TODO (p0: US-RECURRINGBILLING-002/003, US-OFICINA-026, US-PROD-021, US-FISCAL-018, US-SELL-009, US-COM-008, FORJA-142). **US-COPI-137 NÃO é task MCP** — é US do `Modules/Jana/SPEC.md` (status `doing`, mantido; DoD só fecha quando [W] ligar em prod). Relacionadas no backlog: **COPI-28** (MEM-MET-4 = `/copiloto/admin/qualidade` trend 30d das 8 métricas + RAGAS + HITL) e **COPI-25** (MEM-EVAL-3).
- **`decisions-search "eval online RAGAS tráfego real Jana"`:** âncora = **ADR 0318** (RAGAS eval real, CT 100 staging). Sem ADR nova nesta entrega (é implementação de US existente sob a 0318).

## Refs

- [PR #4536](https://github.com/wagnerra23/oimpresso.com/pull/4536) · US-COPI-137 · US-COPI-138 (lane) · [ADR 0318](../decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md) · [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)
- Session log: [`memory/sessions/2026-07-19-jana-eval-online-juiz-local-ollama.md`](../sessions/2026-07-19-jana-eval-online-juiz-local-ollama.md)
