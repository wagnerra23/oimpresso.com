---
sessao: "02"
titulo: Coluna "Margem" no drawer do protótipo — número sem fonte
dono: "[CC]"
base: 9101f86af501
prefixo: prototipo-ui/cowork/compras-page.jsx · prototipo-ui/cowork/oimpresso.com.html (bump ?v=)
nao_toca: resources/js/Pages/Compras/** · Modules/Compras/** · compras-grade-matrix.jsx · qualquer arquivo do main
depende: — (vaga 1). Esta thread NÃO vira PR no main: é correção do ALVO.
---
# 02 · Margem sem fonte

## A · O achado
O drawer de detalhe do meu protótipo mostra `table.items-tbl` com 6 colunas: Produto · Qtd · Custo unit. · Total · Venda · **Margem**.

Busca dirigida hoje, `main@9101f86af501`: **`margem|margin|lucro` em `Modules/Compras/` = 0 ocorrências.** O `ComprasService` não entrega margem, e o cockpit é de **leitura** — não há caminho de dado pra essa coluna.

Margem = (venda − custo) / venda é **conta derivada**. Calcular na UI é a mesma classe de defeito que a projeção de meta da Jana (`atual*1.3`): a tela passa a afirmar um número que o servidor não afirma. Lei do protocolo: **exportar coluna sem fonte é exportar dívida com selo de autoridade.**

## B · Ancoragem — o que ler antes de decidir
- `resources/js/Pages/Compras/components/Drawer.tsx` (19.739 B) — **não lido**. É ele que decide: se o drawer de produção já mostra margem, a fonte existe em outro lugar (props do `Index.tsx`, não no service) e a coluna fica **com a fonte declarada**.
- `resources/js/Pages/Compras/Index.tsx` (28.813 B) — que props o controller manda pro drawer.
- `Modules/Compras/**` — `ComprasService` + `ListarComprasRequest` (a busca de hoje diz que margem não está lá).

## C · As duas saídas (escolher com a leitura, não com preferência)
1. **A produção entrega** (o `Drawer.tsx` mostra margem com dado do servidor) → a coluna **fica** no protótipo, e o `_saida-02.md` registra o caminho exato do campo. Nada muda no build.
2. **A produção não entrega** (esperado, pela busca) → a coluna **sai** do drawer do protótipo, ou fica com `—` e um selo "fora desta onda". **Não** manter número calculado no front.

## Execução
```
ARQUIVOS A EDITAR : prototipo-ui/cowork/compras-page.jsx  (só o cabeçalho e as células do items-tbl)
                    prototipo-ui/cowork/oimpresso.com.html (bump ?v= do compras-page.jsx)
REUSAR            : o próprio items-tbl (5 colunas restantes ficam intactas, mesma ordem)
CRIAR             : nada
NÃO TOCAR         : layout, tokens, largura das outras colunas, a grade tam×cor, o cockpit do main
PASSO A PASSO     : 1) LER Drawer.tsx + Index.tsx no main (a decisão é a leitura, não o gosto)
                    2) saída 1 ⇒ registrar a fonte no _saida-02.md e parar
                       saída 2 ⇒ remover a coluna (th + td) OU trocar o valor por "—" com selo
                    3) medir o render: contagem de th do items-tbl antes/depois, dark, duas leituras
                       iguais de querySelectorAll('*').length
                    4) _saida-02.md com a decisão, a evidência e o número de colunas final
PARAR SE          : o Drawer.tsx mostrar margem mas por CÁLCULO no front (venda-custo/venda em .tsx):
                    então o defeito é da produção também, e vira RESÍDUO pra [W] — não se copia
                    cálculo de UI de um lado pro outro
```

## Prova
- `_saida-02.md` com: qual saída (1 ou 2), o trecho lido do `Drawer.tsx`/`Index.tsx` que a justifica, e a contagem de colunas medida no render.
- Nenhum arquivo do `main` tocado. Nenhum PR.
