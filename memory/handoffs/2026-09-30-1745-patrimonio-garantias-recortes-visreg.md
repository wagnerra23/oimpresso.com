---
date: "2026-09-30"
time: "17:45 BRT"
slug: patrimonio-garantias-recortes-visreg
tldr: "Patrimônio: thread 12 (Garantia crítica) fechada, recorte Em manutenção, garantia mais recente, tabelas de Bens/Manutenções sem coluna 0px, vazio visível no DataTable, Bens no contrato visual com um bem no seed, plural e Sem categoria no Painel, exclusão de bem leva as garantias. Pendente [W]: --apply da garantia órfã em produção."
prs: [8211, 8231, 8237, 8240, 8241, 8246, 8248, 8249, 8252, 8259, 8267, 8275, 8293]
decided_by: [W]
next_steps:
  - "[W] rodar em produção: php artisan assetmanagement:garantias-orfas --apply (hoje lista 1: id=1 asset_id=1)"
---

# Patrimônio — garantias, recortes e contrato visual (2026-09-30)

## Estado MCP no momento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas para @wr23.
- Handoffs irmãos do dia: `2026-09-30-1045-governance-thread-07-painel-ptbr.md`, `2026-09-30-1414-patrimonio-thread-20-settings-remocao.md`.

## O que aconteceu
Começou em `/onda patrimonio --thread 12` e seguiu pedido a pedido do [W], tudo em `Modules/AssetManagement` e `Pages/Patrimonio`:
- **Recortes de Bens no servidor**: "Garantia crítica" (#8211, D-GARANTIAS) e "Em manutenção" (#8240, lista fechada `new`/`in_progress` do `contarAbertas`). Contagem do conjunto, `business_id` explícito.
- **Garantia mais recente** (#8231, [W]): um dono só (`ultimaGarantiaPorBem`) para o recorte, o KPI e os 4 baldes do Painel. Achado de valor: o `SUM` dos baldes contava o bem uma vez por garantia. Impacto em produção medido nulo (0 bens) antes do merge.
- **Chip do Painel** abre `?recorte=garantia` (#8237) + charter do Painel reconciliado (#8241).
- **Layout**: coluna "Bem" com 0px sob `table-layout: fixed` (as fixas somavam mais que o `minTableWidth`) em Bens (#8246) e Manutenções (#8248); mensagem de lista vazia na área visível da rolagem no `DataTable` compartilhado (#8252, 11 telas). Todos medidos no DOM de produção.
- **Contrato visual**: Bens entra no manifesto (#8259); o tenant de visreg ganha um bem e Bens/Painel são recapturados (#8267). Olhar a captura revelou plural errado e "100% EM NENHUM", consertados em #8275 (a 1ª recaptura mostrou "EM SEM CATEGORIA" e foi corrigida antes do PR).
- **Garantia órfã em produção**: causa era `AssetService::remover()` não apagar garantias; consertada + comando `assetmanagement:garantias-orfas` (dry-run por padrão) em #8293. A linha em si **não foi apagada** — exclusão definitiva é do [W].
- **UC-BENS-10** vermelho no `main`: já consertado por outra sessão (#8271); só confirmado verde no CI.

## Artefatos gerados
- Código: `AssetController` (recortes, `ultimaGarantiaPorBem`, `SEM_CATEGORIA`), `AssetService::remover`, `GarantiasOrfasCommand` + registro no provider, `Bens.tsx`, `Manutencoes.tsx`, `Index.tsx`, `DataTable.tsx`, `VisregTenantSeeder::ensureAsset`.
- Contratos: UC-BENS-06/07/11 (`Bens.casos.md`), UC-PAT-10/11 (`Index.casos.md`), charters de Bens e Painel.
- Testes: `BensContratoTest`, `PainelGarantiaContratoTest`, `GarantiasOrfasContratoTest`, vitest `patrimonio-bens-colunas`, `patrimonio-manutencoes-colunas`, `datatable-vazio-visivel`, `patrimonio-painel-forma`.
- Evidência visual: `.snap` de `Patrimonio/Bens` (novo) e `Patrimonio/Index` (recapturado 2×).
- Ledger: LC-23 rec 09-30 (#8249), passado pelo `ciclo-adversary` (REJECT na 1ª redação).

## Persistência
Git: 13 PRs mergeados (lista no frontmatter). `_saida-12.md` no Cowork (subido por outra sessão, sha idêntico conferido). BRIEFING do AssetManagement não atualizado nesta sessão.

## Próximos passos pra retomar
`php artisan assetmanagement:garantias-orfas --apply` em produção ([W]). Nada mais pendente desta frente.

## Lições catalogadas
- **LC-23 reincidiu** no bite-test do #8246: o `git show main > <path>` sobre o conserto não-commitado apagou o conserto. Defesa aplicada no resto da sessão: commitar antes de mutar e abortar se o path estiver sujo.
- Filtro `screens` do modo update casa pelo **`source`** do manifesto (`Patrimonio`, não `Patrimonio/Index`) — um run perdido por isso.
- Olhar a imagem capturada antes do commit pegou dois defeitos de copy e um erro meu no selo; o `snap-diff.mjs` separou conteúdo de rasterização em todos os casos.
- Três ações de "consertar" terminaram em "já tem dono" (baseline Ponto em #8236/#8238, UC-BENS-10 em #8271): conferir PR aberto e `main` antes de abrir o 2º conserto.

## Pointers detalhados
Corpo dos PRs acima (cada um traz medição, bite-test e limites). Playbook: `prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/_saida-12.md`.
