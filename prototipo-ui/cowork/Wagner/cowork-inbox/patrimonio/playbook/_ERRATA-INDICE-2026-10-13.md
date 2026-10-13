---
sessao: "errata-indice"
titulo: "Patrimônio — a 16 foi entregue pela decisão (b) de [W] (#8286); a 1ª prova ainda mede o plano anterior"
executor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main 37b907474
---
# Errata do `00-INDICE.md` do Patrimônio (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24). A correção vale quando o Cowork a
fizer e o próximo retorno a trouxer. Este arquivo não é recibo: o placar lê só `_saida-<id>.md`.
O pedido já estava no fim do `_saida-16c.md` (§"O que ficou de fora"); aqui ele fica num arquivo
próprio, com o JSON pronto.

## O que o placar mostra

```
16 [em curso ] Revogacoes entram na aba de Alocacoes — …RevokeAllocatedAssetController.php (não contém "Inertia::render('Patrimonio/Alocacoes'")
17–20 [em curso ] … — depende de 16 (não feita)
```

As threads 17 a 20 aparecem travadas só por causa disto.

## Por quê

A thread 16 foi escrita para renderizar a Page de Alocações na rota `asset/revocation` (fusão de
tela, rota mantida). Em **2026-09-30** [W] escolheu a opção **(b)** (`_saida-16b.md`, §"Decisão [W]
2026-09-30"): o histórico de devoluções vai para o drawer do bem, e `/asset/revocation` redireciona
para `/asset/allocation`. A `_saida-16b` registra que essa escolha é a autorização para fundir a
rota, que o §B da ficha reservava ao [W]. O #8286 entregou isso (`_saida-16c.md`) e o smoke em
produção está no `_saida-16d.md`.

Medido em `37b907474`:

| prova do índice | estado |
|---|---|
| controller contém `Inertia::render('Patrimonio/Alocacoes'` | **reprova** — o `index()` redireciona (`RevokeAllocatedAssetController.php:62`) |
| controller não contém `asset_revocation.index` | passa (0 ocorrências; 0 chamadores em PHP) |
| `Alocacoes.tsx` existe (guarda) | passa |

O `_saida-16c` não escreveu a string no controller só para a prova passar, e esta errata mantém
isso.

## O que mudar no índice

Na thread `16`, trocar a 1ª prova:

```json
{
  "tipo": "contem",
  "path": "Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php",
  "padrao": "redirect()->action([AssetAllocationController::class, 'index'])",
  "nota": "decisão (b) [W] 2026-09-30, _saida-16b: devoluções no drawer do bem; a rota redireciona"
}
```

E atualizar a `nota_provas`, que ainda diz *"esta thread NÃO funde rota"*. Com a decisão (b), a
rota foi fundida por redirecionamento, autorizada pelo [W].

Com essa troca as 3 provas passam, a 16 sai como `feito` e as 17–20 deixam de depender dela.
