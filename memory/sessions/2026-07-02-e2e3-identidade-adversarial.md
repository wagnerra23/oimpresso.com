---
date: "2026-07-02"
hour: "19:20 BRT"
topic: "E2/E3 identidade — painel adversarial reverteu o veredito de completude e completou as fusões FUNDIR (US órfãs viram HISTORICAL, lápides que mentiam reconciliadas, _processo un-tombstone)"
authors: [C]
prs: [3653, 3656]
related_adrs: [0130-handoff-append-only-mcp-first, 0316-esquecimento-real-adr-morta-tombstone-git-auditoria]
---

# E2/E3 identidade — adversário reverteu meu veredito

## TL;DR
Fui executar a trilha FUNDIR/MATAR de identidade já autorizada. Validando de `origin/main` fresco (base local −4616), concluí que **já estava feito** e que só faltava bookkeeping. Wagner pediu um **adversário pra ir mais fundo**. Spawnei 3 céticos paralelos — eles **refutaram** o veredito com evidência dura: as fusões estavam **incompletas** (SPECs com US órfãs) e 3 lápides **mentiam**. Virou correção real: PR #3653 (mergeado) + follow-up #3656 (mergeado).

## Linha do tempo
1. **Reconhecimento** de `origin/main` fresco: todos os 12 FUNDIR + 4 MATAR já tinham lápide-BRIEFING (KL-E2 #2750/#2751/#2757/#3565). Conclusão precipitada: "só bookkeeping" → abri #3653 marcando a triagem.
2. **Wagner: "quero um adversário pra ir mais fundo e fazer o correto."**
3. **3 adversários paralelos** (Agent general-purpose), cada um de `origin/main` fresco:
   - **adv1 (US/SPEC):** veredito "FUNDIR 100% completo" = **FALSO**. 5 SPECs com US órfãs sem HISTORICAL nem ponteiro: TaskRegistry (15+8 US), PontoWr2 (12), MemoriaAutonoma (5), FinanceiroAvancado (33), LaravelAI (5 parcial).
   - **adv2 (lápide/refs):** `_processo` "fundido em Mwart" era **FALSO e perigoso** — hub VIVO citado como Fonte de US aprovadas em 8 SPECs; `MWART-CHECKLIST` nunca moveu. BI/Grow "arquiva em _Ideias/" nunca aconteceu. INDEX.md lista ~10 pastas mortas como vivas. 10/10 receptores não reconhecem a absorção.
   - **adv3 (meu PR):** honesto no geral, mas com 2 spins — citei **#3559 (só CODEOWNERS)** como PR de fusão + vesti "12/13 pularam o git mv planejado" como "✅ executado coerente com MemCofre" (álibi — MemCofre é rename in-place, não fusão cross-módulo).
4. **Correção (#3653):** 5 SPECs → `status: historical` + banner-ponteiro (caminho sancionado step 2, sem re-escrever 65 US); `_processo` un-tombstone (BRIEFING honesto, sem mover arquivos — 8 SPECs citam o caminho); TaskRegistry com ponteiro **nuançado** (SPEC-UI-FASE7 segue Fonte viva de ProjectMgmt US-TR-309); BI/Grow + FinanceiroAvancado lápides reconciliadas; INDEX.md ⚰️; append reescrito honesto.
5. **Verificação:** `knowledge-drift --check` = 0 ghosts novos; `ghost-fix.test.mjs` verde; `validate-memory-schema.sh spec` = 0 erros; sem tokens `Modules/<ghost>` novos.
6. **Wagner: "merge"** → #3653 squash-merged (CI 65 verdes). Follow-up receptores mudos virou chip → rodou em sessão separada → **#3656 mergeado**.

## Lições
- **Veredito de completude por cabeçalho de lápide é raso.** "BRIEFING tombstoneado" ≠ "fusão completa". O adversário tier-superior pegou o que o auto-eval otimista não viu — mesmo padrão empírico do LOTE C (Fable-5 > Opus tier-igual).
- **Lápide que descreve o estado-alvo ("moveu/arquivou/vira") como fato consumado = drift.** Escrever a promessa antes de rodar a operação e nunca voltar pra fechar o loop.
- **Chamada do Wagner por adversário estava certa.** É o pattern canônico do projeto (verificação adversarial antes de cravar "feito"). Anotar como reflexo: quando eu concluir "já está tudo pronto" rápido, esse é o gatilho pra um cético independente.

## Artefatos
- [`_TRIAGEM-IDENTIDADE-2026-06.md`](../requisitos/_TRIAGEM-IDENTIDADE-2026-06.md) §"conferência ADVERSARIAL"
- Handoff: [`2026-07-02-1920-e2e3-identidade-adversarial-fusao.md`](../handoffs/2026-07-02-1920-e2e3-identidade-adversarial-fusao.md)
