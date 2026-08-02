---
date: "2026-08-02"
hour: "11:45 BRT"
duration: "2h"
topic: "B6 (subtração dos 21 fósseis da raiz de memory/requisitos/) executado e mergeado com refutação adversarial GT-G5 (Fable 0/64); B7-censo dos 14 sem SPEC concluído — falso-alarme, nenhum gap real de SPEC a preencher."
authors: [F, C]
prs: [5165]
related_adrs: [0357, 0264, 0273]
outcomes:
  - "B6 MERGEADO (#5165): 21 specs-planas-fósseis da raiz viraram lápide-no-lugar (mesmo path + mesmo id → doc-id-index intacto; corpo redireciona pro dono vivo). Officeimpresso1.md preservada (ref histórica ADR 0017). 9 índices/templates mantidos."
  - "Medido antes de tocar: a raiz de requisitos/ NÃO cai sob anchor-lint nem memory-schema-gate (ambos só globam */SPEC.md) → sem o big-bang que a §5 2026-07-12/27 proíbe. Único gate em jogo = deadlink (verde 1081/1081)."
  - "GT-G5: PR de lote (>10 arquivos em requisitos/) → refutação adversarial Opus 4.8 (gerador) → Fable 5 (sessão fresca, tier superior). Veredito aprovado 0/64, PII 0. Entry no sdd-verification-ledger.json + parecer no comment do PR. 2 observações de prosa do refutador aplicadas antes do merge."
  - "Consertado no caminho: plans-index --check estava vermelho por drift PRÉ-EXISTENTE (DEPRECATION-PLANs de ADS/Admin/Brief/TeamMcp de outras sessões nunca regenerados) — regen incluído no PR pra destravar o Governance Gate."
  - "B7-censo dos 14 dirs sem SPEC.md: FALSO-ALARME. 12 são tombstones/lápides já marcados (9 FUNDIDO/REPARTIDO: Atendimento→Whatsapp, Chat/Copiloto→Jana, Modules→Admin, Orcamento→Sells, Site→Cms, Purchase→Compras, StockAdjustment/StockTransfer→Estoque; + 3 ⚰️ LÁPIDE: BI, Grow, Tarefas). 1 é core UltimatePOS (User, status active). 1 é VozDoCliente — cujo requisito JÁ vive como US-INFRA-002 em Infra/SPEC.md (módulo mergeado no #4917). Conclusão: nenhum gap de SPEC a preencher; forçar SPEC em qualquer um seria erro (revive módulo morto / colide id / inventa)."
  - "B7-cobertura (~136 telas charter-sem-casos): grandfathered pelo casos-gate forward-only (sem pressão de gate). NÃO mass-executável — cada casos.md exige UC real derivado do contrato + teste que o cite (G-2), rodado no CT 100. É ratchet incremental tela-a-tela, não batch autônomo (evita o teste tautológico da §5 + LC-13 verde-por-não-execução)."
---

# B6 subtração de fósseis + B7-censo — sessão 2026-08-02

Continuação do programa **Opção B (B0–B8)** da [proposal 2026-08-01](../decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md). [F] desbloqueou B6/B7 ("🔓 [F] — B6 / B7 quando priorizar" + "escolha pode fazer tudo").

## B6 — executado e mergeado (#5165)

Censo dos 31 `.md` soltos na raiz de `memory/requisitos/`: `31 = 9 índices/templates (mantidos) + 1 preservado (Officeimpresso1) + 21 fósseis subtraídos`. Cada fóssil (spec plana gerada em 2026-04-22, `status: ausente_branch_atual`, premissa hoje falsa) virou **lápide-no-lugar**: mesmo path, mesmo `id` de frontmatter (doc-id-index intacto, 0 colisões), corpo redireciona pro dono vivo. `INDEX.md` relinkado.

**Por que foi seguro (medido em origin/main, não presumido):** a raiz não cai sob `anchor-lint` nem `memory-schema-gate` (L810-814 do anchor-lint / matriz do schema só globam `memory/requisitos/*/SPEC.md`) → nenhum gate diff-aware "acordado" na dívida de legado. Inbounds quase-zero; e como a lápide fica no path, os inbounds de arquivos gated (ex. `Inventory/SPEC.md` → `IProduction.md`) seguem resolvendo sem tocar nenhum SPEC.

**GT-G5:** lote >10 arquivos → refutação adversarial obrigatória. Refutador Fable 5 (fresco, tier > Opus gerador) verificou 100% dos 21 redirects contra `origin/main` + PII scan: **aprovado 0/64, PII 0**. Entry no ledger + parecer no comment. Aplicadas 2 observações de prosa (contradição "já tem casa viva"×"sem sucessor" nas 3 lápides mortas; overpromessa "SPEC/charter moram lá" em dirs finos).

**Drift de terceiro consertado:** `plans-index --check` vermelho por DEPRECATION-PLANs não-regenerados (ADS/Admin/Brief/TeamMcp) — regen incluído.

## B7-censo — concluído (falso-alarme, sem mudança de código)

Os "14 sem SPEC" não são 14 gaps. Classificação com evidência:

| Bucket | Dirs | Estado |
|---|---|---|
| Tombstone→sucessor (marcado ⚰️ FUNDIDO/REPARTIDO) | Atendimento, Chat, Copiloto, Modules, Orcamento, Purchase, Site, StockAdjustment, StockTransfer | não recebe SPEC (revive morto) |
| ⚰️ LÁPIDE "planejado, não existe" | BI, Grow, Tarefas | idem |
| Core UltimatePOS | User (`status: active`) | SPEC de produto não se aplica |
| Módulo com requisito já rastreado | VozDoCliente (`Modules/` existe, `em-construcao`) | US-INFRA-002 já em `Infra/SPEC.md` (#4917 mergeado) — criar SPEC próprio duplicaria/colidiria |

Todos os 12 tombstones/lápides já carregam marca clara → nada a implementar. **Conclusão: nenhum gap de SPEC.**

## O que fica aberto (por design, não por esquecimento)

- **B7-cobertura** (~136 telas charter-sem-casos): grandfathered, incremental via ratchet tela-a-tela — precisa CT 100 + derivação de contrato por tela. Não é batch autônomo.
- **B0** (ADR 0365 flip) e **B3** (RAG in-place) seguem [W] (soberania), como a proposal define.
- **VozDoCliente**: se um dia quiser SPEC próprio, é decisão de ONDE o requisito mora (mover US-INFRA-002 vs re-ancorar), não preenchimento — decisão de governança, não deste automático.
