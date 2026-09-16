# Pedido pro [CL] — 44 notas de uso do DS v6 + de-para dos apelidos

Resposta do [CD] aos dois pedidos do [C] (2026-08-31): §B do primeiro documento e §A do segundo são **o mesmo item**, e é este. Autoral, não é espelho de nada.

## O que tem nesta pasta

| arquivo | o que é |
| --- | --- |
| `ds-notas-uso.json` | a fonte: 44 componentes × {quando, pareia, nao, apelidos} + `lacunas` |
| `ds-notas-gerar.mjs` | gera os 44 `*.prompt.md` do JSON e, com `--bundle`, injeta as notas como campo por componente no manifesto |

Duas coisas caem daqui: a **prosa** (o `.prompt.md` que humano lê) e o **dado** (`usage`/`whenToUse`/`pairsWith`/`whenNotToUse`/`moduleAliases` no manifesto, que máquina lê). Mesma fonte, uma execução.

## Aplicar

```bash
node handoff-ds-notas/ds-notas-gerar.mjs --dry                          # confere as 44 linhas
node handoff-ds-notas/ds-notas-gerar.mjs --out design-system/components # escreve os *.prompt.md
node handoff-ds-notas/ds-notas-gerar.mjs --bundle <caminho do _ds_manifest.json>
node scripts/governance/component-registry-check.mjs --roles            # o dono dos números
node prototipo-ui/ds-guard.mjs --all
```

O `--out` está com o default do path citado no pedido (`design-system/components/<Nome>/<Nome>.prompt.md`); se no `main` o diretório do DS for outro, é só passar `--out`. Não conferi esse path no `main` neste turno — **não verifiquei**.

## Decisões que tomei e você pode reverter em uma linha

1. **PT-BR.** O `PageHeader.prompt.md` que existe está em inglês; reescrevi em português pra não ficarem duas línguas no mesmo diretório. Conteúdo é o mesmo. Se [W] preferir EN, o JSON é a fonte — traduzir é um passe.
2. **Uma linha corrida por arquivo**, não seções. É o formato do exemplo, e é o que caiba num campo do manifesto sem virar markdown dentro de JSON.
3. **`apelidos` é campo de primeira classe**, não comentário. É ele que faz a nota substituir o de-para: quem procurar `Vazio` acha `EmptyState`. É também o insumo do detector/lint que você quer escrever — `moduleAliases` já sai no manifesto pronto pra isso.
4. **Os 3 sem consumo não saem do bundle.** `AppSidebar`, `KpiFilterCard` e `Logo` têm consumidor óbvio e não medido — a sidebar do shell (hoje desenhada à mão), a faixa de KPI clicável do Clientes, e a marca no topo da sidebar. A nota de cada um diz "hoje consumo ZERO" e aponta o consumidor. Não é carga morta: é retrofit que ninguém pediu ainda. Decisão final é do [W].
5. **`lacunas` no fim do JSON.** Os símbolos de módulo que **não** têm contraparte nos 44 — `Card` é o mais grave (5 namespaces reinventam superfície de card e o bundle não entrega nenhuma). Não escrevi nota pra eles: não existem. É pedido de kit, e é seu e do [W], não meu.

## Os candidatos a extensão do kit (do §B2 do segundo pedido)

Registrei cada um na nota do componente correspondente, na frase de "não usar", pra que a próxima tela leia o limite junto com a regra: `KpiCard` sem `icon`/`contextual` · `BulkBar` sem seleção que atravessa página · `StatusBadge` que não aceita status de domínio cru · `FsmStepper` que não deriva do domínio · `PeriodBar` que não é controlador de filtro. **Não decidi nenhum** — a pergunta "o kit absorve ou a tela compõe?" é do [W].

## Limite honesto

Os `apelidos` vêm dos objetos de export que eu li linha a linha no espelho Cowork (`window.HrmUI`, `PontoUI`, `AcessosDS`, `ModuloPadrao`, `CatchupUI`, `CBUI`, `PBUI`, `EstForms`, `HrmForms`). Onde inferi pelo nome sem abrir o componente, a entrada leva `?` (`HrmUI.KV?`, `PontoUI.Voltar?`, `HrmUI.Busca?`, `ModuloPadrao.Resumo?`). Nenhuma linha prova equivalência funcional — prova papel aparente, igual à medição do [C].

Achado colateral, não pedido: existe `modulo-padrao.jsxv=mp1` no espelho — dupe `?v=` que o `cowork-ssot-guard` deveria barrar. Não toquei.

Eu não escrevo no git. Este é o pedido; aplicar é do [CL].
