---
date: "2026-08-08"
topic: "Fatia D da Jana (Memória/LGPD) — o método que funcionou e os 3 erros de medição da mesma família"
---

# Fatia D da Jana — o que o método rendeu, e onde eu escorreguei

> Estado, PRs e achados estão no [handoff 2026-08-08 19:36](../handoffs/2026-08-08-1936-jana-memoria-fatia-d-lgpd-motivo.md).
> Este log guarda o que é **reaproveitável**: como as coisas foram descobertas, não o que foram.

## O pré-flight pagou, e pagou cedo

O pedido era "7 divergências protótipo × produção". Três passos de pré-flight mudaram o trabalho:

1. **Abrir o SPEC antes de planejar** (lição LC-19, escrita **na véspera**) — achou a `US-COPI-148`, dona da fusão, e me impediu de escrever plano paralelo. As 4 ondas dela já tinham fechado; a fatia D é **adjacente**, não onda dela.
2. **Ler o charter** — e ali estava o achado que valeu a sessão: o Goal do `motivo` e o Anti-hook do `activitylog` existiam desde **2026-05-16** e o código não os cumpria. O item 1 deixou de ser "o protótipo diverge" e virou "**a lei do charter valia zero**".
3. **Consultar o DesignSync** (`list_files` → `get_file`) em vez de varrer só o git — é a lápide de 2026-08-07, de **um dia antes**, sobre declarar ausência de fonte visual sem consultar o 2º dono do inventário.

## Duas perguntas que evitaram teatro

**"Este teste vai rodar?"** — antes de escrever o Pest, medi qual lane o executaria. `MemoriaControllerTest` **não está em lane nenhuma**, e o Controller mora em `Modules/KB`, **fora** do `Modules/Jana/**` que dispara a lane. Sem ligar os 3 pontos (filtro do PR + `push: paths` + lista de execução), o teste seria gate mudo com cara de cobertura. Isso é a lápide de 2026-08-02 aplicada **antes** de errar, não depois.

**"Este UC vai ser citado?"** — antes de criar o `casos.md`, li como o `casos-gate` liga UC↔teste. O **G-2** aceita citação em qualquer lugar; o **G-7** exige o id no **título** do teste. Nomeei os testes `UC-MEM-01 · …` e os dois passaram. Criar `casos.md` sem isso teria **quebrado um gate required**.

## A tradução que não foi cópia

O protótipo lista categorias `preferência/operação/financeiro/cliente/sazonalidade/equipe` — taxonomia do **mock do Martinho**. A produção tem outra (`meta/preferencia/restricao/contexto/acao_pendente`). Copiar a lista literal teria produzido chips que não casam com fato nenhum. Derivei do dado real. É a lápide §5 2026-07-16 ("importar solução sem checar se a premissa vale aqui") aplicada a um caso pequeno.

## Os 3 erros — todos LC-08, todos o mesmo formato

O que os une não é o comando; é que **o número falso era plausível**:

| # | o que fiz | o que medi de fato |
|---|---|---|
| 1 | `node … \| tail -8; echo exit=$?` | o exit do **`tail`** — reportei `exit=0` com a saída dizendo `❌ 1 falha(s)` |
| 2 | `… && grep -c "X" && node gate; echo exit=$?` | `grep -c`→0 ⇒ exit 1 ⇒ **o `&&` abortou**; o gate **nunca rodou** e o "exit" era do grep |
| 3 | `grep -c "<nome-de-teste>" SUPERFICIE.md` | o arquivo **agrega por pasta**, não lista teste — li um `0` que não significava nada |

**Regra que fica:** quando um comando compõe (`|`, `&&`), `$?` **não é** do que você acha. Capture direto (`cmd >/dev/null 2>&1; RC=$?`). E antes de ler um "0", pergunte se o arquivo **pode** responder aquilo.

## Como provei flakiness sem poder rodar Pest local

A lane `kb-pest` ficou vermelha e eu precisava saber se era minha. Três medições, em ordem de custo:

1. **Meu diff toca o caminho do `/sops`?** Não — nenhum arquivo de KB, rota, permissão ou middleware.
2. **A mesma branch já rodou verde?** Sim, **3×**; e o diff entre o último verde e o vermelho só tinha arquivos de **outras sessões** vindos via main.
3. **O teste que falha é o mesmo?** **Não** — 07/08 caiu `V2c /sops`, 08/08 caiu `KbNodeBodyReaderTest > L2`, mesma forma (1 failed · 14 skipped · 105 passed).

O (3) é o que fecha: mesma lane, mesmo PR, **teste diferente a cada corrida** = instabilidade, não quebra. E para o `IT5 benchmark STALE` bastou extrair `prototipo-ui` do `origin/main` **puro** e ver falhar idêntico — prova de que era do repo, não do PR.

## Uma hipótese minha que a medição derrubou

Atribuí o vermelho do KB aos commits `fix(permissoes)` recém-entrados no main. **Falso**: são de ~14:00 e minha branch rodou verde às 19:39, cinco horas depois, já com eles dentro. Registro porque era plausível e estava errada — e porque a tentação é só apagar a hipótese ruim.

## Onde eu quase deixei passar teatro

Declarei o `charter related_us` vermelho como "esperado, linkar seria teatro". Estava errado: confundi *"que US implementa esta mudança"* com o que o campo mede — *"User Stories que esta tela **atende**"*, per o próprio schema. A `US-COPI-148` atende. Teatro seria inventar id inexistente (o que eu **evitei** ao remover os fantasmas `US-COPI-MEM-*`); linkar uma US real não é.

Simétrico: no `Layout primitives` o guard oferecia `--write-baseline` para "regressão legítima". Recusei — não havia refator consciente a absorver, era dívida nascida no PR com o primitivo já existindo. **A saída fácil do gate nem sempre é a saída certa dele.**

## O pedágio da fila (custo real, pra calibrar expectativa)

`Preflight` exige `origin/main` **ancestral** do HEAD. O main anda **~1 commit/11 min** e a fila do CI chegou a **335 runs**. Reconciliei **5×** e o check reprovou entre uma e outra — é corrida que se perde por construção enquanto o PR espera. Todos os conflitos foram nos **2 arquivos gerados**, sempre resolvidos **regenerando**, nunca escolhendo lado.

Se isso repetir com frequência, a pergunta pro [W] não é técnica: é **mergear mais cedo** vs. **esperar verde simultâneo em 118 checks**, que pode nunca acontecer com a fila nesse estado.
