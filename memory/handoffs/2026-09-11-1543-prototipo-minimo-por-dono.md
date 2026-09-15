# Protótipo mínimo por dono — 2026-09-11 15:43 BRT

## Resultado

Após autorização integral de [W], `prototipo-ui/` ficou fisicamente limitado a duas áreas:

```text
prototipo-ui/
├── cowork/
│   ├── Wagner/
│   │   ├── handoffs/
│   │   ├── legado/
│   │   └── fontes de build ativas
│   └── Felipe/
│       ├── handoffs/
│       ├── legado/
│       └── fontes de Venda/Produto
└── design-system/
```

Não há arquivo solto na raiz, terceiro dono, `_arquivo/`, `design-docs/`, snapshot paralelo do
DS ou cache `_ds/`. A varredura SHA-256 do disco encontrou zero conteúdo duplicado.

## Processo aplicado

1. Classificação por papel e procedência: Wagner e Felipe deixaram de competir no mesmo path.
2. Consolidação física: fontes de tela ficaram sob o dono; referências históricas ainda
   apontadas por charter foram agrupadas em `legado/`; payloads não aplicados foram agrupados em
   `handoffs/payloads/`.
3. Separação operacional: máquinas foram movidas para `scripts/design/`; sincronizadores ficaram
   em `scripts/design-sync/`; contratos, alvos e mapas foram movidos para `governance/design/`;
   fixtures/evals foram movidos para `tests/Design/`; documentação foi movida para
   `memory/reference/prototipo-ui/`.
4. DS direto: o shell Wagner passou a ler `prototipo-ui/design-system/`; o snapshot duplicado foi
   excluído e `--preview-ds` foi aposentado para não recriar `_ds/`.
5. Caminhos e âncoras: scripts, workflows, charters, contratos e mapas passaram a usar endereços
   completos com o dono. O alias concorrente de `suporte-page.jsx` foi removido em favor do
   charter de `Suporte/Visao`.
6. Catraca: `cowork-ssot-guard` passou a verificar topologia e SHA de todos os arquivos físicos,
   inclusive ignorados.

## Provas

- `node scripts/governance/cowork-ssot-guard.mjs` — estrutura válida e fonte única.
- varredura `Get-FileHash` recursiva em `prototipo-ui/` — zero grupos duplicados.
- `node scripts/design/protocolo.config.mjs --selftest` — painel e destinos válidos.
- `node scripts/design/detectar-telas.test.mjs` — aliases e charters sem conflito.
- `node scripts/design-sync/bundle-transaction.test.mjs` — transporte e lifecycle verdes.
- `node scripts/design-sync/aplicar-payload.test.mjs` — aplicação atômica e DS direto verdes.
- `node scripts/governance/cowork-mirror-freshness.test.mjs` — export, frescor e aposentadoria do
  cache verdes.
- `node scripts/contrato-de-tela.test.mjs` — contratos no novo endereço verdes.
- `node scripts/governance/deadlink-gate.mjs --check` — nenhum link vivo piorou.

## Decisão

A [ADR 0397](../decisions/0397-prototipo-minimo-por-dono-e-ds-direto.md) registra a topologia,
supersede a ADR 0396 e impede reintrodução de máquinas, documentação ou DS paralelo dentro do
protótipo.

## Estado MCP no momento do fechamento

Snapshot do `brief-fetch` (Brief #632, gerado ~2h antes) na sessão que assumiu o PR pra fechar o CI (Claude, 2026-09-11 ~16h BRT):

- Cycle ativo: nenhum · HITL pendente [W]: 5 (top: `agent-corpus-counterfactual`, 1 handoff de design Cowork→Code)
- Em voo: 11 tasks (Forja Triage/Inbox ×5 · Infra Zod schemas · Documentacao Blade→Inertia · Produto G-06 e V0 · Repair ×2) — nenhuma toca `prototipo-ui/`
- Últimas 24h: 31 commits · 0 ADRs no brief (a 0397 entra por este PR) · 0 incidentes
- Flags: 🟠 683 US sem atribuição · 🟡 SDD composta 55,7 · 🟢 visual regression / PRs em review

A sessão do Codex que produziu o lote não usou tool MCP; a validação foi pelos scripts versionados (lista em "Provas").

## Continuidade

PR de entrega: [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224). O fechamento desta
sessão inclui commit, CI e merge do PR.
