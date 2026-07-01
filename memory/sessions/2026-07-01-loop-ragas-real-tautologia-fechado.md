# Sessão 2026-07-01 — Loop IA-OS #2: matar a tautologia do RAGAS + deploy staging

## TL;DR

Wagner pediu "tem mais alguma coisa pendente? quero trabalhar" → escolheu o loop IA-OS #2 (RAGAS gate). O diagnóstico pegou que o gate **já existia mas era teatro tautológico** (`answer=context=ground_truth`, faithfulness ~1.000 falso). Fechei honesto: eval real da saída da Jana (`jana:ragas-real-eval` via `KbAnswerService`), agendado no CT 100 staging, gate PR rebatizado smoke + forçado mock (parou desperdício ~$1,20/mês), baseline honesto (faith 0.69, não 1.000), ADR 0318. 2 PRs (#3516 + #3521) merged. Deploy staging completo (reset→main + 21 migrations + build) → schedule LIVE (dom 07:00), smoke real do comando deployado passou, `staging.oimpresso.com/login` HTTP 200. Follow-up US-COPI-130 (gap de RAG real `context_recall 0.38` que o eval honesto revelou).

## Como foi

1. **Diagnóstico** (não assumir): li `jana-ragas-gate.yml` + `JanaRagasCiCommand` + os 2 Pest "reais" (`KbAnswerRelevancyTest`/`BriefDiarioFaithfulnessTest`) → os 3 fazem `answer=context=ground_truth`. Achei o pipeline real (`KbAnswerTool` → retrieval `mcp_memory_documents` + `KbAnswerAgent`).
2. **De-risco antes de codar:** confirmei no CT 100 staging que tem corpus (1153 docs) + `OPENAI_API_KEY` → eval real é viável lá (no CI GitHub sqlite efêmero não daria).
3. **Sonda em infra real** (2 perguntas): provou `answer≠ground_truth` (tautologia morta) e já pegou 1 gap (multi-tenant → 0/0).
4. **PR #3516:** `KbAnswerService` (reuse-check, SoC §5) + `jana:ragas-real-eval` + Pest determinístico (5 passed/16 assertions no staging). Fix CI: PHPStan narrow `instanceof App\User`.
5. **PR #3521:** schedule `environments(['staging'])` (anti-ghost, aprendi que o `recall-eval ['live']` é dormant), gate PR honesto, baseline real N=51, ADR 0318, tracker. Fixes CI: Infra Contract (registro CLI, zero HTTP) + regen do índice ADR (`kind` corrigido tecnica→decision).
6. **Deploy staging (A):** staging estava em `feat/perfil-meu-perfil` (não main) → reset→origin/main + `build.sh` (composer não roda na imagem, PSR-4 acha as classes) + 21 migrations aditivas + optimize:clear. Schedule LIVE + smoke real ($0.0136) + HTTP 200.

## Lições / achados

- **O item do loop era teatro, não ausência.** "Pendente" pode significar "existe mas mente" — o valor foi diagnosticar, não construir do zero. Faithfulness cravado 1.000 é assinatura de tautologia.
- **Anti-ghost:** um schedule `environments(['live'])` onde a infra (Meilisearch/OPENAI) só existe no staging é dormant por construção. Gate por onde a coisa **roda**, não por onde "deveria".
- **Real-em-CI tautológico = desperdício:** o gate rodava real ($0.04/PR) pra um 1.000 falso. Forçar mock em PR economiza + para de mentir.
- **build.sh do staging tem `composer install` que falha silenciosamente** (composer ausente na imagem `oimpresso/mcp:latest`, engolido por `| tail`). PSR-4 salva classes novas; dependência nova quebraria. Catalogar se virar problema.
- **Deploy honesto:** provei `schedule:list` + smoke do artefato deployado (não da cópia) + HTTP 200 — R1 de verdade.

## Refs

- ADR 0318 · PRs #3516 #3521 · US-COPI-130 · handoff `2026-07-01-1529-loop-ia-os-2-ragas-real-fechado-deployado.md`
- Baseline: `governance/jana-ragas-real-baseline.json` · Comando: `Modules/Jana/Console/Commands/JanaRagasRealEvalCommand.php` · Service: `Modules/Jana/Services/Kb/KbAnswerService.php`
