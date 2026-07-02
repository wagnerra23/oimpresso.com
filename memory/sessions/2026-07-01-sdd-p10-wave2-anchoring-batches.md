---
date: "2026-07-01"
topic: "P10 wave 2 — 8 lotes de anchoring (Pcp/PG/Fiscal/Compras/Sells/Crm/NfeBrasil/RecurringBilling): 147 US, 5 lotes reprovados r1 pelo refutador Fable e re-aprovados, coverage 42,6%→59,8%"
authors: [C]
type: execucao-campanha-sdd
metodo: "8 geradores Opus 4.8 paralelos (status-truth embutido na passada) → 8 refutadores Fable 5 sessão fresca (amostra 100%, G5) → consolidação serial com auto-merge (cadeia de rebases-união em ledger+baseline)"
gatilho: "Wagner — 'sim' à wave 2 + 'Merge' na cadeia"
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0302-doneness-fonte-unica-anchor
---

# P10 wave 2 — anchoring em 8 lotes com refutador tier superior (2026-07-01, noite)

## Resultado

| Lote | US | ancorado/parcial/pendente | Refutação | PR |
|---|---:|---|---|---|
| Pcp | 21 | 0/0/21 (módulo dormente ADR 0152) | APROVADO r1 0% | #3571 |
| PaymentGateway | 6 | 0/2/4 | APROVADO r1 0% (+1 prosa aplicada) | #3572 |
| Fiscal | 19 | 14/1/4 | APROVADO r1 0% | #3573 |
| Compras | 8 | 6/1*/2 | r1 REPROVADO 8,3% (done sem teste-429) → r2 0% | #3574 |
| Sells-completion | 17 | 10/5/2 | APROVADO r1 0% (2 advisories) | #3575 |
| Crm | 22 | 0/2/20 | r1 REPROVADO 4,5% (justificativa negava linker existente) → r2 0% | #3576 |
| NfeBrasil | 18 | 2/1/15 | r1 REPROVADO 4,8% (pendência FANTASMA vs decisão Wagner) → r2 0% | #3577 |
| RecurringBilling | 36 | 12/15/9 | r1 **REPROVADO 30,9%** (overclaim sistemático + 2 claims falsos) → r2 0% | #3580 |

_*US-COM-008 rebaixada na correção._

- **147 US `sem_campo` → 0** nos 8 módulos; **coverage global 42,6% → 59,8%** (dia inteiro: 16,1% → 59,8%; sem_campo 717 → 344). dead=0, zombie=0 em tudo.
- **Taxa de ambiguidade: 0%** (nenhuma US ficou sem campo por ambiguidade) — §103 nunca disparou.
- Ledger: +9 entries (26 total) — reprovados REGISTRADOS (§6). Baseline entry-gate: 343→437 chaves, cada crescimento com trailers `BASELINE-GROW`+`BASELINE-ABSORB`.
- Cadeia de merges: serial com `gh pr merge --auto` + rebase-união por PR (ledger entries pr-específicas + união de baseline) — 8 merges sem nenhum vermelho de required.

## O refutador tier superior pegou (5 lotes reprovados na r1)

1. **RecurringBilling 30,9%** — overclaim sistemático: 8 full que eram `_parcial_` + `C6Driver::cancelar()` é stub `BadMethodCallException` vendido como entregue + "UI de cancelamento na Cobrança" quando o botão foi REMOVIDO de propósito (B6) + permissões inexistentes citadas. Claim-mãe (módulo vivo, ao contrário do audit 2026-05-10) CONFIRMADO.
2. **NfeBrasil 4,8%** — pendência FANTASMA: "falta tela Configuracao/Certificado.tsx" que foi removida de propósito (unificação Wagner 2026-05-27) — anchor induziria recriação contra decisão explícita.
3. **Compras 8,3%** — flip done com "âncora de teste que não morde" (source-grep de throttle sem `assertStatus(429)` no repo).
4. **Crm 4,5%** — justificativa negava o `ConversationContactLinker` existente (discovery stale 2026-05-12) — risco de 2º writer.
5. **Financeiro/OficinaAuto** (wave 1, mesma sessão) — zombies de sub-componente + DoD semântico.

Padrão das 2 waves: **7 de 12 lotes reprovados na rodada 1** (taxa de reprovação 58%) — a refutação por tier superior NÃO é teatro; é onde a qualidade do backfill é fabricada.

## Lições novas (além das da wave 1)

- **Overclaim de done-ness é o modo de falha dominante do gerador** (não path inventado — dead=0 sempre; o gerador erra pra CIMA no estado). A régua "acceptance codável incompleto = `_parcial_`" precisa estar no prompt do gerador com exemplos.
- Pegadinhas de parser catalogadas: `PLACEHOLDER_RE` casa "todo" como substring ("mé**todo**"); backtick-com-`/` em PROSA vira path morto (rotas/dirs citados em notas → sem crase).
- `git rebase` falha silencioso com unstaged changes de agentes irmãos — consolidar o último lote antes de re-iniciar a cadeia.
- Meu gate-fixer flipava status de US LEGADAS grandfathered (bug: lia conflicts RAW sem filtro de baseline) — detectado e revertido no PG; wave 2 fez status-truth no próprio gerador.

## Estado do P10 pós-wave-2

Restam ~344 `sem_campo`: Infra (45), Governance (35), Marketplaces (26), ComunicacaoVisual (18), TaskRegistry* (16), Autopecas (15), Comissao (15), NFSe (15), Mwart (13), Vestuario (12), Connector (12), Cms (10), Essentials (10), Ponto (10), Superadmin (10), + cauda (*gated trilha E — não ancorar). Receita comprovada e barata; wave 3 pode rodar quando Wagner quiser.
