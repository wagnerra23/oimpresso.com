---
id: requisitos-asset-management-briefing
module: AssetManagement
status: producao
status_nota: "em produção; as 5 listas já são Inertia em Pages/Patrimonio, os formulários de escrita ainda migram (threads 16–20 do playbook)"
updated_at: "2026-09-30"
owner: W
related_adrs: [0394-endereco-de-ui-do-patrimonio-pages-patrimonio, 0414-patrimonio-auditoria-deep-link-e-formularios-em-drawer-react, 0104-processo-mwart-canonico-unico-caminho, 0093-multi-tenant-isolation-tier-0, 0409-zero-baseline-de-tolerancia-conformidade-absoluta]
---

# BRIEFING — `AssetManagement` (Patrimônio)

> **Função única:** resumo executivo e índice. Aponta para os donos; não recopia SCOPE, SUPERFICIE, SPEC nem os contratos de tela.
> **Contrato:** `scripts/memory-schemas/briefing.schema.json`.

## O que é

O patrimônio de uso interno da empresa: cadastro de bens (código por empresa), alocação e devolução a
colaboradores, manutenção e garantia. Transversal a todos os verticais. O código mora em
`Modules/AssetManagement`; a UI, em `resources/js/Pages/Patrimonio/` (endereço decidido pela
[ADR 0394](../../decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md) — o Patrimônio não tem módulo PHP próprio; o nome é só o da pasta de telas).

## Estado atual

- **As 5 telas de lista são Inertia/React** — Painel (`Index`), Bens, Alocações, Manutenções e Configurações —, cada uma com charter e casos ao lado. As devoluções ainda não entraram na aba de Alocações (thread 16) e os formulários de escrita ainda estão migrando para drawer React ([ADR 0414](../../decisions/0414-patrimonio-auditoria-deep-link-e-formularios-em-drawer-react.md)); as views Blade que restam são deles. Inventário exato: `SUPERFICIE.md`.
- **Bens** filtra no servidor por três recortes — Todos · **Garantia crítica** · **Em manutenção** —, com a contagem de cada um sobre o conjunto (UC-BENS-06/07). "Garantia crítica" e o KPI do Painel usam a mesma regra: a **garantia mais recente** de cada bem, vencida ou vencendo em 30 dias (UC-PAT-10).
- **Excluir um bem leva as garantias dele junto** (UC-BENS-11). Garantias órfãs de antes do conserto: `php artisan assetmanagement:garantias-orfas` lista; `--apply` apaga.
- **Auditoria** é deep-link para o `Modules/Auditoria` filtrado nos bens, não aba própria ([ADR 0414](../../decisions/0414-patrimonio-auditoria-deep-link-e-formularios-em-drawer-react.md)).
- **Contrato visual:** Painel, Bens e Manutenções estão no manifesto `tests/Browser/visreg-screens.json`; o tenant de visreg tem um bem (`VisregTenantSeeder::ensureAsset`), então a tabela de Bens com linha está coberta.
- Testes: a lane `assetmanagement-pest.yml` roda `Modules/AssetManagement/Tests/` inteiro no MySQL. Contagem viva: o sumário JUnit da lane, não este arquivo.

## Portas canônicas

- **Herança geral (componentes/layouts compartilhados):** [`../_Geral/BRIEFING.md`](../_Geral/BRIEFING.md)
- **Fronteira/ownership:** [`SCOPE.md`](SCOPE.md)
- **Superfície derivada de código:** [`SUPERFICIE.md`](SUPERFICIE.md) — regenerar com `node scripts/governance/module-surface.mjs AssetManagement --write`
- **Requisitos:** [`SPEC.md`](SPEC.md)
- **Planos por tela:** `RUNBOOK-bens.md` · `RUNBOOK-alocacoes.md` · `RUNBOOK-manutencoes.md` · `RUNBOOK-configuracoes.md` · `RUNBOOK-patrimonio-index.md`
- **Telas:** `resources/js/Pages/Patrimonio/` + `*.charter.md` / `*.casos.md` ao lado de cada `.tsx`
- **Playbook de migração (estado das threads):** `node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/00-INDICE.md`

## Decisões e riscos que exigem atenção

- **Sem escopo global de business:** `Asset`, `AssetMaintenance`, `AssetTransaction` e `AssetWarranty` constam como `grandfathered` em `governance/multi-tenant-scope-baseline.json` — isolamento por filtro manual em cada consulta. Dívida transitória da [ADR 0409](../../decisions/0409-zero-baseline-de-tolerancia-conformidade-absoluta.md): tocar uma dessas entidades acorda a dívida no mesmo PR.
- **Depreciação não é calculada:** o Painel mostra "Valor residual —" (`valorResidual = null` de propósito). A regra (linear/SAC, com qual fonte) é decisão [W] — RESÍDUO 6 do playbook, `US-ASSET-W01` no SPEC.
- **Custo de manutenção:** `asset_maintenances` não tem coluna de valor; o Painel mostra "—", não zero.
- **Retenção automática:** descartada por decisão [W] (thread 05 do playbook, bloqueada).
- **Faixa de abas do módulo não aparece no tenant de visreg** (sem assinatura do módulo): regressão nas abas passa verde no gate visual. Ponto cego declarado no docblock do `VisregTenantSeeder`.

## Próxima ação verificável

- **[W]** apagar a garantia órfã que ficou em produção: `php artisan assetmanagement:garantias-orfas --apply` (o dry-run hoje lista 1). Concluída quando o dry-run devolver `garantias órfãs: 0`.
- **Threads 16–20 do playbook** (devoluções na aba de Alocações e os 4 formulários em drawer): concluídas quando o `placar.mjs` acima mostrar as cinco como `feito`.

## Regra de manutenção

1. Mudou árvore de código: regenere `SUPERFICIE.md`; não edite a lista no BRIEFING.
2. Mudou requisito: altere `SPEC.md`/charter/casos.
3. Componente/layout compartilhado não é copiado para o módulo: aponte para o dono único.

**Atualizado:** 2026-09-30 pela sessão dos PRs #8211 · #8231 · #8237 · #8240 · #8246 · #8248 · #8252 · #8259 · #8267 · #8275 · #8293 (handoff `memory/handoffs/2026-09-30-1745-patrimonio-garantias-recortes-visreg.md`). A versão anterior (2026-06-08) ainda descrevia o frontend como Blade legado.
