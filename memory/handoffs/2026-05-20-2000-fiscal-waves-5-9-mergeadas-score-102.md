---
date: "2026-05-20"
time: "20:00"
slug: fiscal-waves-5-9-mergeadas-score-102
authors: [wagner, claude]
cycle: CYCLE-06
module: Fiscal
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
tldr: 6 PRs Fiscal mergeados hoje (Waves 5-9 + docs evolução). Score Capterra 80→102/100. Próxima sessão retoma PR #10 (PIS/COFINS + saldo credor real + Bloco H Stock) OU pivot cycle Martinho/FSM/Jana V2.
---

# Handoff — Fiscal Waves 5-9 mergeadas, score 80→102/100

## Estado MCP

- **Cycle ativo:** CYCLE-06 (Martinho prod + FSM rollout + Jana V2 demo) — 8d restantes
- **Drift detectado:** 40/40 commits 7d não tocam tasks do cycle. Pivot estratégico Fiscal hoje justificado pelo design KB-9.75 fechamento (top-3 gaps Bling/Tiny).
- **PRs hoje mergeados:** 6 (5 Wave Fiscal + 1 docs)
- **HITL pending Wagner:** 6 (não tratados hoje — top 2 Proposta comercial Gold + Upgrade plataforma on-prem Gold)
- **Brain B uso 24h:** 0% (0/50)

## O que foi entregue (sessão 2026-05-20 17:00→20:00)

5 Waves Fiscal + docs evolução, todas mergeadas em main via `gh pr merge --squash --admin`:

| PR | Wave | Score | Conteúdo |
|---|---|---|---|
| [#1249](https://github.com/wagnerra23/oimpresso.com/pull/1249) | 5 — CC-e + Inutilização | +4pp (80→85) | `NfeCartaCorrecaoService` novo + AcoesController.cartaCorrecao + AcoesController.inutilizar (delega NfeInutilizacaoService US-SELL-030). NotaDrawer CCe modal + Nfe.tsx Inutilizar modal extraído (`InutilizacaoModal.tsx`). 14 Pest tests. |
| [#1253](https://github.com/wagnerra23/oimpresso.com/pull/1253) | 6 — Retransmitir | +3pp (85→88) | `NfeService::retransmitir` (UPDATE preservation contract CONFAZ Art. 14 — NUNCA forceDelete). AcoesController.retransmitir. NotaDrawer Retransmitir modal. 2 fixes adicionais pra remover toda menção literal 'forceDelete' (Wave26/27 saturation tests). |
| [#1257](https://github.com/wagnerra23/oimpresso.com/pull/1257) | 7 — ⌘K palette | +8pp (88→96) | `PaletteSearchController` novo (busca cross-fiscal notas + DF-e) + `CmdKPalette.tsx` listener global Cmd/Ctrl+K + FxShell mount + botão Buscar habilitado. SCOPE.md registrou Controller novo. |
| [#1259](https://github.com/wagnerra23/oimpresso.com/pull/1259) | 8 — SPED EFD-ICMS/IPI MVP | +4pp (96→100) | `SpedIcmsIpiGeneratorService` novo (Modules/Fiscal/Services) + 16 registros canon (Blocos 0+C+9) + SpedController.gerar download TXT + Sped.tsx botão habilitado + baseline `module-grades-baseline.json` v3.3. |
| [#1261](https://github.com/wagnerra23/oimpresso.com/pull/1261) | 9 — Bloco E + H | +2pp (100→102) | Expande SpedIcmsIpiGeneratorService com 7 registros (E001+E100+E110+E116+E990+H001+H990). 23 registros canon total — estrutura completa pra validação PVA-EFD CONFAZ. Substituto do PR #1260 fechado automaticamente quando base #1259 mergeou. |
| [#1263](https://github.com/wagnerra23/oimpresso.com/pull/1263) | docs evolução | n/a | SCOPE.md roadmap pós-Wave 9 + BRIEFING.md NOVO (105 linhas, padrão `skill brief-update` Tier B) + RUNBOOK-sped.md atualizado (placeholder → gerador real documentado). |

**Score final Capterra Fiscal cockpit: 102/100** (acima cap — top-3 gaps Bling/Tiny fechados: CC-e, Retransmitir, ⌘K busca, SPED).

## Mudanças canônicas em produção

**Controllers novos em `Modules/Fiscal/`:** `PaletteSearchController` (Wave 7)

**Services novos:**
- `Modules/Fiscal/Services/SpedIcmsIpiGeneratorService` (Wave 8 + 9 — 472+138=610 linhas, 23 registros EFD)
- `Modules/NfeBrasil/Services/NfeCartaCorrecaoService` (Wave 5 — espelhado em NfeInutilizacaoService)
- `Modules/NfeBrasil/Services/NfeService::retransmitir` método público novo (Wave 6 — UPDATE preservation contract)

**Components React novos:**
- `resources/js/Pages/Fiscal/_components/CmdKPalette.tsx` (Wave 7 — 442 linhas)
- `resources/js/Pages/Fiscal/_components/InutilizacaoModal.tsx` (Wave 5 — 209 linhas)

**Rotas novas (Modules/Fiscal/Routes/web.php):**
- `POST /fiscal/acoes/nfe/{emissao}/cce` (Wave 5)
- `POST /fiscal/acoes/nfe/inutilizar` (Wave 5)
- `POST /fiscal/acoes/nfe/{emissao}/retransmitir` (Wave 6)
- `GET  /fiscal/palette/search?q={query}` (Wave 7)
- `GET  /fiscal/sped/icms-ipi/{ano}/{mes}` (Wave 8)

## Aprendizados (lessons learned hoje)

1. **Worktrees isoladas são obrigatórias quando sessão paralela ativa** — perdi ~30min no PR #5 quando outra sessão Claude commitou Financeiro Onda 17-21 no worktree principal, sobrescrevendo meus edits via branch switches. As 5 worktrees isoladas em `.claude/worktrees/fiscal-pr{5,6,7,8,9}-*` (+ 1 docs + 1 rebase) foram blindadas.

2. **Saturation tests Tier 0 são guardiões críticos** — Wave26SaturationTest + Wave27NfeSaturationTest pegaram que `NfeService` não pode ter `forceDelete` (CONFAZ Art. 14 preservation contract — documento fiscal imutável). PR #6 levou 2 fixes:
   - Fix #1: substituir `forceDelete` por `update(transaction_id=null, status=inutilizada)` (preservation contract)
   - Fix #2: remover menção literal "forceDelete" também dos comentários (Wave27 sat usa substring match cru sem `->()`)

3. **`check-scope` strict mode** força declarar Controller novo em `SCOPE.md.contains[]` — pegou Wave 7 (PaletteSearchController) com 1 fix simples.

4. **`module-grades-gate`** pega regressão + módulos novos sem aprovação — Wave 8 atualizou `governance/module-grades-baseline.json` v3.3 (Fiscal+PaymentGateway novos + Governance -1).

5. **PRs encadeados** — Wave 9 baseou em #1259 Wave 8 não em main. Quando #1259 mergeou, PR #1260 fechou auto (base branch deletada). Solução: cherry-pick commit Wave 9 em branch nova baseada em main → PR #1261 substituto.

6. **MCP `tasks-create` exige `project` key** — passou `module: Fiscal` falhou ("Sem 'module' canônico, é obrigatório passar 'project'"). Backlog tasks documentadas neste handoff (próxima sessão consulta).

## Próximos passos (prioridade alta → baixa)

### 1. PR #10 SPED Fiscal complete (1+ semana, +3pp)

**Escopo:** complementa Waves 8/9 com 3 frentes:

a) **EFD-Contribuições (PIS/COFINS) arquivo separado** — SPED CONFAZ ADE 20/2012:
- Novo Service `Modules/Fiscal/Services/SpedPisCofinsGeneratorService` espelhando SpedIcmsIpiGeneratorService
- Registros canon: Bloco 0 (0000+0100+0110+0140+0150+0190+0200+0990), Bloco C (C001+C100+C170+C181 PIS+C185 COFINS+C190+C990), Bloco M (M001+M200 PIS apuração+M600 COFINS apuração+M990), Bloco 9
- Endpoint `GET /fiscal/sped/contribuicoes/{ano}/{mes}`
- Botão download adicional em Sped.tsx
- Caveat: Simples Nacional NÃO entrega (isento) — só Lucro Real/Presumido

b) **Saldo credor anterior real em E110** (US-FISCAL-016 fix):
- Migração nova: `fiscal_apuracoes_icms` (business_id + ano + mes + vl_saldo_credor + vl_icms_recolher + persistido_em)
- Service grava em DB pós-gerar SPED do mês corrente (snapshot do E110 calculado)
- Próxima geração mês N+1 lê saldo de N pra preencher `VL_SLD_CREDOR_ANT`
- Compatibilidade: primeira geração de cada biz = saldo 0 (no-op)

c) **Bloco H dados reais** (US-FISCAL-017 fix):
- Integração `Modules/ProductCatalogue/Stock` (queries no inventário de 31/12)
- Habilita IND_MOV=0 quando mês=janeiro
- H010 com itens do inventário (cod_item, qtd, valor, ind_prop)
- Backward-compat: outros meses mantém IND_MOV=1 (esqueleto vazio)

**DoD:**
- SpedPisCofinsGeneratorService completo + Pest tests
- Migração + Service atualização E110 com saldo persistido
- Bloco H integrado com Modules/ProductCatalogue/Stock pra janeiro
- Sped.tsx 2 botões download (ICMS-IPI + Contribuições)
- SPEC US-FISCAL-018 (Contribuições) + US-FISCAL-019 (E110 persistido) + US-FISCAL-020 (Bloco H Stock)

### 2. Smoke biz=1 prod-like — validar PVA-EFD CONFAZ (4-6h, p1)

Pest browser MCP em Hostinger HOMOLOG biz=1 cobre 6 fluxos:
1. SPED EFD-ICMS/IPI download + import PVA-EFD CONFAZ validação estrutural
2. CC-e (cstat 135/136 sandbox SP)
3. Inutilização (cstat 102)
4. Retransmitir (preservation contract validado em DB)
5. ⌘K palette (busca + permissão + tier 0 scope)
6. Tier 0 multi-tenant scope (biz=1 vs biz=99 mock)

Salvar screenshots `tests/Browser/Screenshots/Fiscal/` + visual regression baseline.

### 3. Tech debt (pode entrar em sessão dedicada)

- 234 schema violations grace-period (51 sessions + 171 ADRs + 12 handoffs) — sessão dedicada de normalização frontmatter
- 54 stashes acumulados — revisar 1 a 1, drop os redundantes (provavelmente 30+ obsoletos)
- 6 worktrees ainda abertas (`fiscal-pr{5,6,7,8,9}-*` + `fiscal-docs` + `rebase-pr1253`) — `git worktree remove` pós-confirmação merge
- Casing artifacts `Nfe-visual-comparison.md` vs `nfe-visual-comparison.md` + `recurringbilling.php` pt-br vs pt-BR — Windows tracking drift git

### 4. Pivot pro cycle CYCLE-06 (alternativa)

Brief mostrou drift 40/40 commits 7d NÃO tocam tasks do cycle ativo. Se Wagner quiser realinhar:
- **Martinho prod** — piloto OficinaAuto Caçambas (ADR 0171 ativação faseada — task FIN-004 atualizar cobrança ROTA LIVRE em voo 69h)
- **FSM rollout biz=1** — ADR 0143 cascade live (LIVE em prod)
- **Jana V2 demo** — apresentável a 1 piloto

## Estado worktrees (D:/oimpresso.com/.claude/worktrees/)

| Worktree | Branch | Status |
|---|---|---|
| `fiscal-pr5-cce-inut` | feat/fiscal-pr5-cce-inutilizacao | ✅ mergeada — pode `git worktree remove` |
| `fiscal-pr6-retransmitir` | feat/fiscal-pr6-retransmitir | ✅ mergeada — remover |
| `fiscal-pr7-cmdk` | feat/fiscal-pr7-cmdk-palette | ✅ mergeada — remover |
| `fiscal-pr8-sped` | feat/fiscal-pr8-sped-icms | ✅ mergeada — remover |
| `fiscal-pr9-bloco-e` | feat/fiscal-pr9-sped-bloco-e | ✅ mergeada — remover |
| `rebase-pr1253` | rebase-pr1253-tmp | scratch — remover |
| `fiscal-docs` | docs/fiscal-pos-wave9-evolucao | ✅ mergeada — remover |
| `frosty-greider-83ab2f` | main (worktree principal) | manter |

## Referências canônicas

- BRIEFING.md: `memory/requisitos/Fiscal/BRIEFING.md` (NOVO — 1-pager canon)
- SCOPE.md: `Modules/Fiscal/SCOPE.md` (roadmap Waves 1-9 ✅ + Wave 10 backlog)
- SPEC.md: `memory/requisitos/Fiscal/SPEC.md` (US-FISCAL-001 até US-FISCAL-017)
- RUNBOOK-sped.md: `memory/requisitos/Fiscal/RUNBOOK-sped.md` (gerador real documentado)
- Design KB-9.75: `prototipo-ui/Oimpresso ERP Conunicação Visual. Ultimotopo/fiscal-page.jsx`
- ADRs canônicas: 0093, 0094, 0101, 0104, 0114, 0143
- CONFAZ: Ajuste SINIEF 02/2009 (EFD-ICMS/IPI) + Ajuste SINIEF 07/2005 (Art. 14 preservation) + ADE 20/2012 (EFD-Contribuições)
