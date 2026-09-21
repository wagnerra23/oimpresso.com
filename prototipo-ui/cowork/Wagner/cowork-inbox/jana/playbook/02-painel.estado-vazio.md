# ONDA 02 — Painel · **estado vazio da página**

Prefixo: `feat/jana-painel-estado-vazio` · 1 PR · 1 arquivo · ~40 linhas.

---

## 1 · Pedido — o que e por quê

A âncora tem um estado de **página inteira** quando não há histórico pra analisar: no lugar de brief + KPIs + análises + ações, um empty-state único com título, descrição e uma saída (`Ir para a Conversa`). Só o header, as abas e a nota-mob ficam.

A produção não tem esse estado. Ela tem empty-state **por bloco**: "Sem histórico" no sparkline, "Sem dados de clientes", "Sem pagamentos registrados", "Ninguém de peso parou de comprar", "Nenhuma meta cadastrada ainda". Num business novo, o resultado é uma tela cheia de caixas vazias e `R$ 0,00` repetido — cada bloco dizendo baixinho que não tem dado, nenhum dizendo por quê nem o que fazer.

É o caso do cliente recém-onboardado, que é exatamente quem mais precisa da frase.

Copy literal do alvo:

- título `A Jana ainda não tem histórico pra analisar`
- descrição `Ela precisa de pelo menos um mês de movimento pra montar o brief, os KPIs e as análises. Enquanto isso, pergunte o que quiser na aba Conversa.`
- ação `Ir para a Conversa` → `/ia/conversa`

## 2 · A11y do alvo (o que NÃO exportar)

- No alvo o empty-state cai num `<div>` com `<b>`/`<small>` quando o `EmptyState` do DS não está disponível (ramo de fallback do protótipo). **Não portar o fallback** — na produção o `EmptyState` shared sempre existe, e `<b>`+`<small>` não é hierarquia de título.
- O botão do alvo já é `<button>`/`Button` — ok.
- Bateria A1–A12 não rodada neste turno (§8).

## 3 · Alvo — o predicado, e por que só metade dele vem

O protótipo bifurca em `vazio || erro` e mostra duas copies diferentes. **Só o ramo `vazio` vira pedido.**

O ramo `erro` ("Não consegui ler os dados da empresa agora" + "Tentar de novo") **não tem fonte no `main`**: o `IndexController` não emite sinal de falha — `buildSellKpis`/`buildInsightsAggregates` resolvem ou estouram, e um estouro vira página de erro do Inertia, não este card. Exportar o card de erro seria pedir uma UI pra um estado que o servidor não sabe produzir. Fica no §7.

Predicado do vazio, derivado do payload que o controller JÁ manda (nenhum campo novo):

```
semHistorico = sellKpis.total === 0
            && insightsAggregates.totalAReceber === 0
            && insightsAggregates.topClientes.length === 0
            && insightsAggregates.methodsAgg.length === 0
```

`coworkAggregates` **fica de fora do predicado** de propósito: é `Inertia::defer`, chega depois, e `undefined` ali significa "ainda não chegou" — não "não tem dado". Misturar os dois faria a tela piscar o empty-state durante o carregamento normal. O `carregandoCockpit` que já existe continua mandando no skeleton, e o empty-state só é avaliado quando ele é `false`.

Geometria: não se aplica — o estado substitui o corpo.

## 4 · Comportamento + invariantes

| elemento | estados | gatilho | efeito | persistência | reversível | prova |
|---|---|---|---|---|---|---|
| corpo do Painel | dados · carregando · vazio | payload | vazio ⇒ só o empty-state | não persiste | sim (chega venda) | predicado do §3 |
| header + abas + nota-mob | sempre | — | nunca somem | — | — | ficam fora do ramo |
| METAS | independente | `metas.length` | **mantém** o `painel-metas-vazio` próprio | — | — | copy pinada, não se mexe |

**Invariante que decide o desenho:** metas e vendas são eixos **separados**. Um business pode ter meta cadastrada e zero venda, e vice-versa. O empty-state de página cobre o eixo VENDAS; a seção METAS segue com o `painel-metas-vazio` dela. **Não fundir os dois** — fundir apagaria copy pinada em contrato.

Precedência: `carregandoCockpit` (skeleton) → `semHistorico` (empty-state) → conteúdo.

## 5 · Não inventar (reusar, não recriar)

- **Componentes:** `Components/shared/EmptyState` (variante `first` é a semântica certa: primeiro uso, não erro nem filtro) · `Components/ui/button` · `Link`.
- **Dados:** `sellKpis` + `insightsAggregates`, que já chegam **sem defer** (o controller declara defer só em `coworkAggregates`). Nenhuma query, nenhum campo, nenhuma flag de servidor.
- **Rota:** `/ia/conversa` (`jana.chat.index`) — de pé.
- **Tokens:** os do `EmptyState`.

## 6 · Instrução de execução

```
ONDA 02 — Painel sem histórico mostra um estado de página, não 6 caixas vazias
  ARQUIVOS A EDITAR   : resources/js/Pages/Jana/_components/JanaCockpit.tsx
  REUSAR              : EmptyState shared (variant="first") · Button · Link ·
                        `carregandoCockpit`, que já existe no arquivo
  CRIAR               : nada
  NÃO TOCAR           : a seção METAS / `aposKpis` (eixo separado, copy pinada) ·
                        JanaAreaHeader · nota-mob · os empty-states POR BLOCO (eles
                        continuam cobrindo o caso "tem venda, não tem cliente top") ·
                        IndexController · SellsCockpitAggregator
  PASSO A PASSO       : 1) derivar `semHistorico` conforme o predicado do §3, junto dos
                           outros derivados no topo do componente
                        2) o ramo entra DEPOIS do skeleton: se `carregandoCockpit`,
                           nada muda; se `semHistorico`, o componente retorna só o
                           EmptyState + `{aposKpis}` (METAS continuam!) e nada mais
                        3) copy literal do §1; `action` = Button dentro de
                           `<Link href="/ia/conversa">`
                        4) contar flex/grid antes e depois (`layout:check`, ratchet por
                           arquivo)
  DADO                : nenhum campo novo
  PARAR SE            : o `PainelContratoTest`/`jana-painel.contract.json` pinarem
                        seções do corpo como sempre-presentes (NÃO LI os dois — ler no
                        turno); aí o contrato ganha o estado no MESMO PR
                        · ou se [W] preferir um estado de servidor (flag no payload) em
                        vez do predicado derivado — é uma decisão legítima e muda o PR
  NÃO FAZER           : não incluir `coworkAggregates` no predicado (é deferida) ·
                        não fundir com `painel-metas-vazio` · não escrever o ramo de
                        ERRO (§7) · não esconder o header nem as abas
```

## 7 · O que a ancoragem NÃO resolve

- **Estado de ERRO da página.** O alvo tem card + "Tentar de novo" (com estado `tentando`). O `main` não produz o sinal: nenhum `try/catch` no `IndexController`, nenhum campo de falha no payload. Fechar exige decidir (a) o que é falha recuperável do aggregator, (b) como ela chega à Page, (c) o que "tentar de novo" recarrega. **PR de fundação + decisão [W]** — não está pedido aqui.
- **Skeleton de página inteira** (`JmPainelSkeleton` no alvo, parcial na produção). A produção já skeletona brief, KPI e sparkline; trocar por um skeleton de página é ganho duvidoso e mexe no que funciona. Não pedido.
- **Onboarding.** O que o business faz pra sair do vazio (importar histórico? esperar?) é produto, não tela.

## 8 · Não medido, declarado

- **Zero medição de DOM vivo** da produção e **zero render medido** do protótipo neste turno — tudo é leitura de código do `main` (`e57b78bf54e7`) e do build do Cowork.
- **`PainelContratoTest.php` e `jana-painel.contract.json` não lidos** — daí o `PARAR SE`.
- **`SellsCockpitAggregator` não lido**: afirmo o SHAPE dos agregados pelo que o `JanaCockpitProps` declara e o cockpit consome, **não** pelo que as queries devolvem num business zerado. Quem executar confirma que `topClientes`/`methodsAgg` vêm `[]` (e não `null`) nesse caso — o predicado depende disso.
- **Business zerado não observado** em prod. O cenário do §1 é inferido do código, não visto.
- **Bateria a11y A1–A12** não rodada; **contraste AA** não calculado.

## 9 · DoD

1. Screenshot autenticado 1280px, dark e light, num business **sem vendas** e num **com vendas** (4 imagens).
2. Vazio: um único empty-state com a copy literal do §1 + botão pra `/ia/conversa`; header, abas e nota-mob de pé.
3. Vazio **com** meta cadastrada: a seção METAS renderiza normalmente ao lado do empty-state.
4. Vazio **sem** meta: `painel-metas-vazio` continua aparecendo com a copy pinada intacta.
5. Durante o defer de `coworkAggregates`: skeleton, **nunca** o empty-state (o flicker é o defeito clássico desta onda — provar com throttle).
6. Com vendas: tela byte-idêntica à de hoje.
7. `layout:check` na mesma contagem; `PainelContratoTest` verde.
8. `Index.casos.md` com ≥1 UC (business sem histórico), citado por teste, no mesmo PR.
9. `github.md` com a linha do ciclo + `bundle regenerado (<data> · N arquivos)`.

## 10 · Recibo

- **Nada foi commitado** — GitHub read-only deste lado.
- **Build do Cowork não alterado nesta onda**: o alvo já tem o estado; a produção é que está atrás. O ramo de ERRO do alvo **fica** no protótipo (é design válido), só não desce como pedido enquanto não houver sinal de servidor.
