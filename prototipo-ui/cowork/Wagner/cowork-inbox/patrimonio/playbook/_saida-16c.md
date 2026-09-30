---
sessao: "16c"
titulo: Saída da thread 16 (3ª passada) — executada; a prova literal do índice pede errata
dono: "[CL]"
medido_em: 2026-09-30
base_medida: 9cd5b8223 (branch claude/patrimonio-16-fusao, sobre main ffc377fee)
complementa: "_saida-16.md (parou no dado) · _saida-16b.md (decisão [W] e nova ordem)"
---

# 16c · Executada — a fusão é de TELA, a rota fica

## Por que agora dava
As duas dependências que a `_saida-16b` nomeou mergearam em 2026-09-30:
- **histórico por evento** (1 alocação : N devoluções) no drawer do bem — [#8261](https://github.com/wagnerra23/oimpresso.com/pull/8261);
- **excluir devolução** no drawer de devolução — thread 18, [#8262](https://github.com/wagnerra23/oimpresso.com/pull/8262).

## O que mudou
- `RevokeAllocatedAssetController::index` deixa de devolver a lista Blade `Resources/views/asset_revocation/index.blade.php` e devolve a **mesma Page de Alocações**, pelo render único do dono (`AssetAllocationController::renderAlocacoes`), com `situacao = devolvidas` por default (`?situacao=` explícito vence).
- **A rota não funde.** `GET /asset/revocation` segue existindo — ghost "Devoluções" do sub-nav e links antigos continuam chegando. Isso segue a `nota_provas` da própria thread no índice ("a fusão é de TELA"), e dispensa o redirect que a `_saida-16b` tinha anotado.
- O ramo `ajax()` do DataTables saiu inteiro: o cliente Inertia manda `X-Requested-With` em toda visita.
- `Alocacoes.tsx`: o sub-nav marca **Devoluções** quando se entra por `/asset/revocation`.
- Charter de Alocações: o Non-Goal que dizia "Devoluções é aba própria / a rota manda" foi reescrito com a data. Casos: **UC-ALOC-09**.

## Recibos
- **Pest no CT 100** (worktree temporário do branch, `vendor` copiado, stub do manifest do Vite igual ao da lane; controle positivo: o `RevokeAllocatedAssetController` carregado veio do worktree): suíte inteira do módulo **136 passed (655 assertions)**.
- **Mordida:** com o controller do `main` no mesmo worktree, `UC-ALOC-09` **falha** (a resposta não é página Inertia). Com a mudança, passa.
- `tests/js` inteiro 492/492.

## ⚠️ Errata pedida ao índice (do Cowork — não editado aqui)
A thread 16 tem duas provas. Uma passa, a outra **não passa, e não vai passar sem enganar**:

| prova | resultado |
|---|---|
| `nao_contem RevokeAllocatedAssetController.php "asset_revocation.index"` | ✅ |
| `contem RevokeAllocatedAssetController.php "Inertia::render('Patrimonio/Alocacoes'"` | ❌ |

A segunda exige o **texto** da chamada neste arquivo. A implementação chama o render **único** do dono, `renderAlocacoes()`, que antes deste PR já servia quatro caminhos (index, create e edit de alocação, create de devolução) e agora serve cinco — medido com `git grep -n "renderAlocacoes("`. Duas cópias do render seriam duas listas de props para divergir. Satisfazer a prova exigiria duplicar esse render ou escrever o texto num comentário: os dois são presence-gate burlado (classe LC-11).

**Proposta de troca** (prova de comportamento no lugar de prova de texto): `contem RevokeAllocatedAssetController.php "renderAlocacoes("` **ou**, melhor, a prova de recibo apontar o teste `UC-ALOC-09` do `AlocacoesFormContratoTest.php`.

Enquanto o índice não mudar, o placar mostra a 16 **em curso**, e 17, 18, 19 e 20 também, porque dependem dela. O trabalho das cinco está no `main`.

## Pendente
- Smoke em produção de `/asset/revocation` depois do deploy (vai no PR de envio do `_saida-16c`).
- O Blade `Resources/views/asset_revocation/index.blade.php` segue no disco sem chamador — retirar arquivo é onda de limpeza, como a thread previa.
