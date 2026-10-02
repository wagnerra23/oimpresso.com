---
casos: Importação de vendas · /import-sales
irmaos: Index.charter.md (lei) · Index.tsx · Preview.casos.md
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a importação grava venda finalizada e baixa estoque — o valor, o estoque e em que negócio isso grava não mudam num refactor visual nem por ir para a fila.
owner: wagner
last_run: "2026-10-02"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Importação de vendas

> **Fonte:** texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Importacao.casos.md`
> (5 casos; prefixo próprio `UC-IMPV-*` porque `UC-IMP-*` já é da tela Impostos do Financeiro e o
> casos-gate casa id no corpus global — §5 2026-09-04) + decisões D2/D3 de [W] (2026-10-02) +
> `ImportSalesController` / `ImportSalesService` / `ImportarVendasJob` reais.
>
> **Teste:** `tests/Feature/Sells/ImportSalesContratoTest.php` — tenant 98 × 2º business semeado,
> `DatabaseTransactions`, headers do browser (`X-Inertia` + `X-Requested-With`).
>
> ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)` — `.github/workflows/sells-pest.yml`.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-IMPV-01 · Planilha pequena importa na hora, com o valor e a baixa certos `[T0]` `[must]`
- **Persona:** Larissa — importa as vendas do dia de outro sistema.
- **Aceite:** Dado um lote de 3 linhas (fatura A: 2 × 50,00 e 1 × 30,00; fatura B: 3 × 50,00), produto com 10 em estoque e limite de 200 linhas · Quando importo · Então nascem 2 vendas finalizadas no mesmo lote, A = 130,00 e B = 150,00, e o estoque fica em 4. Nada vai para a fila.
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-01 · abaixo do limite importa na hora`.
- **Regressão que defende:** a extração do cálculo para o serviço mudando total ou baixa (REGRA MESTRE).
- **Status: 🧪**

## UC-IMPV-02 · Planilha grande vai para a fila `[must]`
- **Persona:** Larissa — importa um mês inteiro de vendas.
- **Aceite:** Dado uma planilha acima do limite · Quando envio · Então o job vai para a fila `sales-import` com o meu negócio, o meu usuário e o local escolhido, o request não grava venda nem baixa estoque, e a tela passa a mostrar "na fila".
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-02 · acima do limite vai para a fila`.
- **Regressão que defende:** job sem `business_id` (fila não tem sessão) e planilha grande derrubando o request (achado A2).
- **Status: 🧪**

## UC-IMPV-03 · A fila grava o mesmo que o request `[T0]` `[must]`
- **Persona:** Larissa.
- **Aceite:** Dado o mesmo lote do UC-IMPV-01 · Quando o worker processa o job · Então A = 130,00, B = 150,00, estoque 4, o estado fica "concluído" com 2 vendas, o arquivo temporário some e o usuário do processo volta ao que era.
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-03 · a fila grava o mesmo valor e a mesma baixa que o request`.
- **Regressão que defende:** dois caminhos de importação com contas diferentes; sessão vazando entre jobs de negócios diferentes no mesmo worker.
- **Status: 🧪**

## UC-IMPV-04 · SKU de outro negócio não entra `[T0]` `[must]`
- **Persona:** Larissa — a planilha tem um SKU que existe noutra empresa do sistema.
- **Aceite:** Dado a linha 3 com SKU que só existe noutro negócio · Quando importo · Então recebo "produto não encontrado na linha 3" e nada do lote é gravado.
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-04 · SKU que só existe noutro negócio não é importado`.
- **Regressão que defende:** o legado buscava SKU no banco inteiro e importava o produto alheio (ADR 0093). Cobre também o UC-IMP-04 do texto revisado (erro cita a linha).
- **Status: 🧪**

## UC-IMPV-05 · Local de outro negócio é recusado `[T0]` `[must]`
- **Persona:** requisição forjada com `location_id` de outra empresa.
- **Aceite:** Dado um local de outro negócio · Quando envio a importação · Então ela é recusada e nenhum estoque é baixado.
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-05 · local de outro negócio é recusado antes de baixar estoque`.
- **Regressão que defende:** baixa de estoque em local alheio (ADR 0093).
- **Status: 🧪**

## UC-IMPV-07 · A tela abre com o que precisa `[must]`
- **Persona:** usuário com `sell.create`.
- **Aceite:** Dado esse usuário e uma importação na fila · Quando abre `/import-sales` pelo browser · Então recebe a Page `ImportSales/Index` com os campos importáveis, o limite de linhas e o estado "na fila".
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-07 · a tela abre pelo browser`.
- **Regressão que defende:** Page sem dado (o skeleton eterno do §5 2026-09-08) ou andamento da fila invisível.
- **Status: 🧪**

## UC-IMPV-08 · Só os meus lotes `[T0]` `[must]`
- **Persona:** Larissa.
- **Aceite:** Dado um lote no meu negócio e outro noutro negócio · Quando a lista de importações carrega · Então vejo só o meu.
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-08 · a lista de lotes mostra só os lotes do próprio negócio`.
- **Regressão que defende:** vazamento de faturas entre empresas na lista deferred (ADR 0093).
- **Status: 🧪**

## UC-IMPV-10 · Sem permissão, sem tela `[must]`
- **Persona:** usuário sem `sell.create`.
- **Aceite:** Dado esse usuário · Quando abre `/import-sales` · Então recebe 403, igual ao Blade.
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-10 · sem sell.create a tela devolve 403`.
- **Regressão que defende:** trava removida na migração.
- **Status: 🧪**

## UC-IMPV-11 · Sem telefone nem e-mail, nada é gravado `[must]`
- **Persona:** Larissa — esqueceu de mapear a coluna de telefone.
- **Aceite:** Dado telefone e e-mail sem coluna · Quando importo pela tela · Então o erro diz a linha 2 e nada é gravado. (UC-IMP-01 do texto revisado.)
- **Teste:** `ImportSalesContratoTest` — `UC-IMPV-11 · planilha sem telefone nem e-mail mapeado é recusada citando a linha`.
- **Regressão que defende:** mensagem de erro sumindo na tela React (o Blade lia `notification`, a tela lê `status`).
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Reverter lote cancela em vez de apagar (D3 de [W], UC-IMP-05 do texto revisado). Parado: não há caminho de cancelar venda sem efeito externo — ver `_saida-05.md`. Hoje a tela confirma que o lote será **apagado**.
- **[BACKLOG]** Andamento "importando N de M" atualizando sozinho — o polling da tela não tem teste de navegador.

## Trilha do tempo
- 2026-10-02 · [CL] trio criado na thread 05 do playbook `venda-menu`. Refs: ADR 0104 · ADR 0264 G-1/G-2 · ADR 0358.
