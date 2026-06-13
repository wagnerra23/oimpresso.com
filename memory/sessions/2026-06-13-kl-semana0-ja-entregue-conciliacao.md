---
date: "2026-06-13"
topic: "Frente KL (Semana 0 SDD) — sessão paralela: re-derivação anti-stale revela que as 3 entregas JÁ estão 100% em origin/main; conciliação 1:1 + verificação adversarial G5; nada criado (evita regressão Tier 0 de duplicação)"
authors: [C]
related_adrs:
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
prs: []
---

# Frente KL Semana-0 — já entregue em origin/main (conciliação + auditoria G5)

> Sessão paralela disparada pra frente KL (conhecimento) da Semana 0 do plano
> [2026-06-12-plano-reestruturacao-sdd-ondas-paralelas](2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md).
> Briefing pedia 3 entregas em 5 arquivos novos. A regra anti-stale do próprio briefing
> ("re-derive de origin/main, conte ghosts e colisões reais") foi aplicada e **revelou que
> as 3 entregas já foram mergeadas** por sessões KL anteriores — com mais rigor que o plano
> estimava. **Nada foi criado**: os 5 arquivos seriam duplicatas / colisão de ADR (regressão
> Tier 0). Esta sessão entrega a conciliação e a verificação adversarial.

## TL;DR

| Briefing pediu (5 arquivos novos) | Já existe em origin/main | Veredito |
|---|---|---|
| `scripts/governance/ghost-ratchet.mjs` | catraca em 2 camadas: `knowledge-drift.mjs --check` (qualitativa, ghost novo barrado) + `sdd-scorecard.mjs --ratchet` (quantitativa, `ghost_count` armado ↓) | ❌ não criar — duplicaria a catraca quantitativa |
| `governance/ghost-baseline.json` | `governance/knowledge-ghosts-baseline/<Mod>.json` (39 mód · 27 nomes) + `governance/sdd-scorecard-baseline.json` (`ghost_count armed:true`) | ❌ não criar — fragmentaria o baseline canônico |
| `scripts/codemod-identity.mjs` | `scripts/governance/ghost-fix.mjs` (dry-run; `--write` fase 2) | ❌ não criar — duplicata pura |
| `governance/renames-table.json` | `governance/ghost-rename-map.json` (4 renames Classe A c/ evidência + 23 excluded) | ❌ não criar — duplicata pura |
| `memory/decisions/0274-alias-map-colisoes-adr.md` | `memory/decisions/0274-referencia-adr-por-slug-alias-map-13-colisoes.md` (aceito [W]) + `governance/adr-alias-map.json` | ❌ não criar — **criaria a 14ª colisão no nº 0274** (auto-viola o próprio ADR + append-only) |

## Conciliação 1:1 (com commits e evidência re-derivada)

### Entrega 1 — catraca anti-ghost, baseline por módulo, ghost_count só desce
- **Qualitativa (ghost novo barrado, por módulo):** `knowledge-drift.mjs --check` / `--write-baseline`
  contra `governance/knowledge-ghosts-baseline/<Mod>.json`. Baseline **só diminui** (interseção no
  rewrite; ghost novo recusado). Freeze 2026-06-12: **39 módulos citantes · 27 nomes**. Commit `d9503f35`.
- **Quantitativa (contagem agregada só desce):** `sdd-scorecard.mjs --ratchet` lê
  `governance/sdd-scorecard-baseline.json` onde `ghost_count` está `direction:down, armed:true,
  valid_measurements:3` → **exit 1 se subir**. Commit `4804dde2`.
- **Gates (advisory):** `.github/workflows/knowledge-ghost-gate.yml` + `sdd-scorecard.yml`.
- **Selftest (quem vigia os vigias):** `gate-selftest.mjs` cobre `knowledge-drift --check` E
  `sdd-scorecard --ratchet ARMADO` com fixtures boa/ruim — prova que **mordem**.
- Re-derivado agora: `ghost_count = 27`, `--ratchet exit=0` (==baseline, sem regressão). ✔

### Entrega 2 — codemod + tabela de renames
- `scripts/governance/ghost-fix.mjs` + `governance/ghost-rename-map.json` — commit `a58172f1` (KL-A1).
- **Dry-run default** (0 writes); `--write` gated em Wagner aprovar o map (fase 2 = Semanas 1-2).
- Tabela curada: **4 renames Classe A com evidência dura** (Copiloto→Jana, PontoWr2→Ponto,
  MemCofre→SRS, DocVault→SRS·transitivo) + **23 excluded** (Classe B lápide / C reescrita / AMBIGUO fila).
- Re-derivado agora: 107 ocorrências Classe A mapeáveis em 45 arquivos, **0 escritas**. ✔
- ⚠ O "8 renames" do plano §1 era **estimativa**; a re-derivação rigorosa achou 4 com prova
  (ADR+commit). Isso é a regra anti-stale funcionando — não inventar 8 quando só 4 têm evidência.

### Entrega 3 — ADR 0274 alias map das 13 colisões
- `memory/decisions/0274-referencia-adr-por-slug-alias-map-13-colisoes.md` (`status: aceito`, [W],
  2026-06-12) + `governance/adr-alias-map.json` (machine-readable) — commit `4bb2ec43` (KL-B1, #2590).
- 13 colisões (11 duplos + 2 triplos = 28 arquivos), confere com `adr-index-generate.mjs`.
- O briefing pediu `status: proposed`; o ADR já **passou de draft pra aceito** numa sessão anterior. ✔

## Verificação adversarial G5 (refutador leve — tentei provar que está ERRADO)

1. **A catraca morde de verdade?** SIM — `gate-selftest.mjs` tem fixture ruim que faz
   `sdd-scorecard --ratchet` (modo armado) retornar exit 1. Não é catraca de teatro.
2. **O codemod pode vazar write acidental?** NÃO — `--write` é flag explícita, default é relatório;
   `ghost-rename-map.json` só toca nomes com evidência dura; `excluded` nunca é tocado.
3. **As 13 colisões batem com a fonte?** SIM — idênticas ao detector `adr-index-generate.mjs`.
4. **Algum dos 23 excluded deveria ser rename Classe A?** NÃO — cada um tem justificativa de
   classe (nunca-construído / namespace legacy / ambíguo) coerente com o git history.

### 2 ruídos finos achados — ambos BENIGNOS (nenhuma correção necessária)
- **Ghost `Index` não-catalogado no map** (`ghost-fix.mjs` reporta `{"Index":1}`): **falso-positivo
  de regex**. A citação é `Pages/Modules/Index` (tela Inertia) em `_TRIAGEM-IDENTIDADE-2026-06.md`,
  e o próprio doc já anota *"nome 'Modules' é isca de ghost-name"*. Não é rename faltando.
- **Drift 0273/0274** na mensagem do commit `4bb2ec43` ("ADR 0273" mas arquivo é `0274`):
  **benigno**. `0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md` (frente SA) existe e é
  legítimo; o número 0274 do arquivo está correto (frontmatter `number: 274`), sem colisão. É um
  exemplo *vivo* do problema que o ADR 0274 resolve — e o sistema funcionou (nasceu 0274 certo).
  Mensagem de commit é imutável e inofensiva. Nada a fazer.

## O que NÃO fiz e por quê (anti-regressão Tier 0)
Criar os 5 arquivos do briefing violaria `memory/proibicoes.md`: *"NUNCA criar arquivo sem checar
duplicação"*, *"§5 — achar o canon dono do tema e ESTENDER, nunca abrir paralelo"*, *"ADRs são
append-only, nunca editar accepted"*. Não toquei `knowledge-drift.mjs` (infra-lane, só leitura),
nem Kernel.php, nem workflows, nem áreas SA/FV/GT/Charters.

## Recomendação
1. **Não disparar outra sessão KL Semana-0** — está fechada. Este doc é o rastro pra evitar a 4ª.
2. **Semanas 1-2 (frente KL, o que de fato sobra):** E1 tabela de identidade (Wagner 15min) → E2
   aplicar renames (`ghost-fix.mjs --write` pós-aprovação do map) → E2b re-seed Meilisearch →
   Classe B lápides "(planejado — não existe)" → Classe C reescrita revisada. Tudo **fora** do escopo
   desta sessão (o briefing proibiu aplicar renames / re-seed).
3. **Opcional (custo ~zero, da frente KL-A1):** listar `Index` em `excluded` do `ghost-rename-map.json`
   pra zerar o ruído de "ghost não-catalogado" no relatório do codemod. Não-bloqueante.

## Estado / evidência no fechamento
- Branch `claude/adoring-keller-07isrj`, parte de `origin/main @ a957eb98`.
- Comandos de prova (read-only, sem CT 100/prod): `knowledge-drift.mjs --json` (27 nomes / 39
  citantes) · `sdd-scorecard.mjs --ratchet` (exit 0) · `ghost-fix.mjs` (0 writes) · `ls .github/workflows`.
- Refs: SDD Semana-0 KL.
