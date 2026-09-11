# AUTONOMY_LADDER.md — Degrau de autonomia POR CLASSE de mudança

> Substitui o binário "0-humano sim/não" por uma escada honesta: o degrau de cada classe é função das REDES que existem para ela. Rede nova comprovada (4 semanas sem escape) = classe pode subir 1 degrau, com OK de [W]. Escape pego por [W] = classe DESCE 1 degrau imediatamente (catraca reversa automática).

## Degraus

| Degrau | Significado |
|---|---|
| **A0** | Só [W] decide e executa. Agente nem propõe diff. |
| **A1** | Agente propõe (PR/proposta), [W] revisa e mergeia manualmente. |
| **A2** | Merge autônomo COM gate específico da classe verde + janela de veto (12h úteis) p/ [W]. |
| **A3** | Merge autônomo com CI verde. Sem janela. |

## Classes × degrau vigente

| Classe de mudança | Degrau | Rede que sustenta | Condição p/ subir |
|---|---|---|---|
| Constituição (ADR 0094 · UI-0013 · PROTOCOL · BRIEFING) | **A0** | Soberania [W] | Nunca sobe |
| Dinheiro / fiscal / NFS-e flip | **A0/A1** | Tier 0 + escalação comprovada | Nunca acima de A1 |
| Multi-tenant / scoping | **A1** | tenant-scope checks (2 bloqueios reais) | A2 quando check cobrir 100% dos controllers |
| Golden set / réguas de eval | **A1** | Imutabilidade pós-merge | Nunca acima de A1 (anti-contaminação) |
| Mudança visual (tokens, layout, CSS) | **A2** ⚠ | ratchet cor + conformance — mas visual-regression = STUB | A3 só quando US-GOV-013 entregar gate visual real. Até lá, janela de veto é obrigatória — hoje roda A3 de fato, ACIMA do degrau seguro |
| Tela nova com charter | **A2** | charter-gate + conformance + score | A3 com 4 semanas sem devolução de [W] na classe |
| Refactor com teste comportamental | **A3** | testes + CI | — |
| Refactor só estrutural (jsdom) | **A2** | estrutural ≠ correção (L-24) | A3 quando caso↔teste existir |
| Docs / memória / ADR não-constitucional | **A3** | append-only + numeração monotônica | — |

## Regra de aplicação
1. PR declara sua classe no título/label; gate mapeia classe→degrau e bloqueia merge autônomo acima do permitido.
2. Classe ambígua = assume a MAIS restritiva entre as plausíveis.
3. Tabela auditada a cada red-team mensal (EVAL-003); mudanças de degrau só por [W].
