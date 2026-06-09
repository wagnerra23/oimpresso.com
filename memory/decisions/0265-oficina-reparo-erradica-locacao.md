---
slug: 0265-oficina-reparo-erradica-locacao
number: 265
title: "Oficina = reparo é o único domínio; erradicar resíduo de order_type=locacao; 'Caçambas' = só nome comercial"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: errata
decided_by: [W]
decided_at: "2026-06-09"
accepted_at: "2026-06-09"
accepted_via: "Wagner autorizou no chat 2026-06-09 (zero-toque, dono do negócio/soberano do domínio): 'Pode apagar aluguel de caçamba e fundamentar para não voltar mais, eu não uso é alucinação' + 'Eu decido que sim eu quero reparo. faça a ADR.' Numeração/ratificação por [CL] sob soberania ADR 0238."
module: OficinaAuto
quarter: 2026-Q2
tags: [oficina-auto, dominio, order-type, erradicacao, anti-alucinacao, enum, migration, tier-0, errata]
supersedes: []
supersedes_partially: ["0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada"]
superseded_by: []
related: ["0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada", "0143-fsm-pipeline-live-prod-marco-2026-05-12", "0093-multi-tenant-isolation-tier-0", "0251-veiculo-na-venda-direta-oficina", "0264-governanca-executavel-trio-dominio-e2e"]
pii: false
---

# ADR 0265 — Oficina = reparo é o único domínio; erradicar o resíduo de "locação"

## Contexto

A [ADR 0194] (2026-05-26) já reclassificou o domínio da Oficina de "locação de caçamba container" → **mecânica pesada de caminhão basculante** (CNAE 4520-0/01), preservando **"Caçambas" apenas como nome comercial** da empresa Martinho. O importer Firebird já normaliza `vehicle_type: cacamba → caminhao`.

**Porém sobrou resíduo de "locação" no código** (lido ao vivo em `@main` 2026-06-09):

1. **`order_type` enum `{locacao | manutencao | mecanica}`** — `locacao` ainda existe como tipo de ordem (migrations `2026_05_12_220002` + `2026_06_02_000001`).
2. **`ServiceOrderSummaryService`** computa KPI de dashboard combinada com ramo "locação".
3. **Nomes legados** `ProducaoOficina/_components/CacambaCard.tsx` · `CacambaKanbanColumn.tsx` + comentário stale de rota citando colunas `disponivel/locada`.
4. **Menu** `topnav.php` com item rotulado `'Caçambas'`.

**Veredito de Wagner (dono do negócio, soberano do domínio):** locação de caçamba **não é processo que ele usa** — é alucinação herdada do legado WR Sistemas. A Oficina é **reparo/mecânica, ponto.** Isso **corrige um erro de enquadramento do próprio [CC]**, que na sessão anterior tratou a locação como "legado vivo intencional". A 0194 mandou *ler* "locação" como "mecânica"; Wagner agora manda **apagar**, não reinterpretar — por isso esta ADR é **errata** que `supersedes_partially` a 0194 (só na parte que manteve o resíduo `locacao`; o resto da 0194 segue válido).

## Decisões

### D-1 — Reparo é o único domínio operacional da Oficina
O ciclo de vida de uma OS é exclusivamente de serviço/reparo, apresentado como `recepcao → diagnostico → pecas → execucao → pronto` sobre a FSM canônica ServiceOrder ([ADR 0143]: `orcamento → aprovada → em_servico → concluida` + `cancelada`). **Não existe fluxo de locação.**

### D-2 — Erradicar `order_type = locacao`
- Remover `locacao` do enum `order_type` → fica `{manutencao, mecanica}`.
- **Migration de dados** (idempotente, `--dry-run` padrão como a W28): linhas legadas `order_type='locacao'` → `manutencao` (mesmo default que o importer já aplica). Em seguida `ALTER ... MODIFY` do enum sem `locacao`.
- Remover o ramo "locação" do `ServiceOrderSummaryService` (KPI dashboard) — fica manutenção + concluída_mês + atrasada. Ajustar os testes de shape (`Wave25/26SaturationTest`, etc.).

### D-3 — "Caçambas" sobrevive SÓ como nome comercial
`Martinho Caçambas` permanece como razão/nome de cliente (string de negócio). **Nenhum rótulo de UI, enum, coluna de kanban ou métrica** usa "caçamba/locação/locada/disponível" como conceito de domínio. Renomear `CacambaCard/CacambaKanbanColumn` → nomes de reparo; menu `'Caçambas'` → `'Veículos'`; limpar comentário stale de rota.

### D-4 — Fundamento anti-retorno (por que não volta)
- **`memory/proibicoes.md`** (nova linha): *"Oficina/OficinaAuto NÃO tem domínio de locação. 'Caçambas' = nome comercial do cliente Martinho, nunca tipo de ordem, coluna, KPI ou label. `order_type ∈ {manutencao, mecanica}`. Quem reintroduzir `locacao`/`locada`/`disponivel` como conceito de negócio viola esta ADR — Wagner 2026-06-09."*
- **`Modules/OficinaAuto/CHANGELOG.md`**: entrada append (não reescreve história, L-22) apontando esta ADR e que ela fecha o resíduo da 0194.
- **Gate de domínio ([ADR 0264] G-4):** o dicionário `memory/dominio/oficina-auto.md` passa a ser fonte única — `dominio:check` falha se `locacao` reaparecer num enum de migration. **A erradicação vira mecânica, não memória.**

## Escopo / não-escopo

- **Escopo:** enum + migration de dados + service KPI + rename componentes/menu + comentário de rota + lápide no CHANGELOG + linha em proibicoes + semente do dicionário de domínio.
- **Preservado (Tier 0 irrevogável):** FSM ServiceOrder ([ADR 0143]), multi-tenant global scope ([ADR 0093]), idempotência `FB_LEGACY_ID` do importer, journey E2E Martinho biz=1 (só **deixa de exercitar** o ramo locação).
- **Controle-negativo no aceite:** grep `@main` pós-merge deve dar **zero** `order_type.*locacao`, **zero** KPI "locação", **zero** `Cacamba*` em componente/menu de UI. Nome comercial "Caçambas" em dados de cliente = ok (não é regressão).

## Consequências

- **+** Domínio da Oficina vira coerente e auto-defendido: a alucinação que nenhuma spec de tela pegava agora falha um gate (`dominio:check`).
- **+** Schema simplificado (`order_type` com 2 valores reais).
- **−** Migration Tier 0 (DB de prod Martinho biz=1) — por isso idempotente + `--dry-run` padrão + preserva FK/append-only.
- Não toca a FSM nem o multi-tenant.

## Trilha do tempo

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-09 | [CC] propõe / [CL] ratifica | Errata que erradica o resíduo `locacao` deixado pela 0194 após veredito de Wagner ("apaga, eu não uso, é alucinação" + "quero reparo, faça a ADR"). Numerada 0265 sob soberania ADR 0238. Implementação (código P0) no PR de erradicação; handoff `PROMPT_PARA_CODE_OFICINA-REPARO-ERRADICA-LOCACAO.md`. |
