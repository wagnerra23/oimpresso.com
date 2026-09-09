---
sessao: "03"
titulo: Aferição read-only — SeloProcedencia está plugado? + ficha das 4 telas nunca lidas
dono: "[CC]"
base: 11eff17f13db
prefixo: nenhum (só `_saida-03.md`) — read-only, não abre PR
nao_toca: tudo. Medir e aplicar são passos separados (C12).
depende: — (vaga 1). Destrava a decisão D-PROC e as fichas das ondas 6–9.
---
# 03 · Aferição

Duas perguntas que **nenhum pedido pode responder sem leitura**, e que hoje travam três threads.

## A · `SeloProcedencia` está plugado?
**Medido em 2026-09-08:** `_components/SeloProcedencia.tsx` (3.530 B) e `_lib/procedencia.ts` (3.570 B) **existem**; a busca por `import SeloProcedencia` em `resources/js/Pages/Fiscal/` **não retornou hit**. Três leituras possíveis, e só a leitura decide:

| hipótese | como confirmar | consequência |
|---|---|---|
| importado com sintaxe que a busca não casou (`import {`, re-export, import dinâmico) | `grep -rn "SeloProcedencia\|procedencia" resources/js/Pages/Fiscal/` | a onda 10 está **feita** e D-PROC fecha sozinha |
| importado fora de `Pages/Fiscal/` (drawer compartilhado, `_components/_shared/`) | mesma busca no repo inteiro | idem, com o caminho declarado |
| **órfão** — peça construída e nunca ligada | nenhum hit fora da própria definição | vira RESÍDUO: peça morta no repo é dívida, e D-PROC continua de pé |

**Não** ligar a peça nesta thread. Aferir é passo separado de aplicar (C12); se estiver órfã, o plugue vira thread própria **depois** de [W] responder D-PROC — que é justamente a pergunta de *onde* a procedência aparece (6 superfícies).

## B · Ficha das 4 telas que eu nunca li
O pacote de 03/09 abria as ondas 6–9 com `LER NO TURNO` — ou seja, **emitiu pedido sem medir**. Aqui isso se corrige: cada tela ganha uma **ficha do §13.2** antes de virar thread.

| tela | `.tsx` | charter | casos | onda de 03/09 | trava |
|---|---:|---:|---:|---|---|
| `Dfe.tsx` | 33.318 B | 2.008 B | 23.172 B | 6 · manifestação em lote | D-DFE |
| `Eventos.tsx` | **8.508 B** | 4.489 B | 17.514 B | 7 · export CSV + chips | D-DFE (parte CSV) |
| `Config.tsx` | **42.005 B** | 4.512 B | 19.679 B | 8 · abas + gate de ambiente | D-CONFIG |
| `Sped.tsx` | 33.033 B | 13.226 B | 30.514 B | 9 · competência + prévia do TXT | — |

Para **cada** uma, produzir no `_saida-03.md`:
```
FICHA Fiscal.<tela>.<seção>
  alvo_nos          (do MAPA no protótipo servido)         teto ~400
  leitura_bytes     soma dos RECORTES, não dos arquivos    teto 40.000
  escrita_linhas    estimativa declarada                   teto 300
  prefixo_arquivos                                         teto 8
  simbolos                                                 teto 3
  decisoes_abertas                                         teto 0
  RECORTE           arquivo :: símbolo :: faixa :: sha  +  o "NÃO ler"
  VEREDITO          CABE | DIVIDE | RECUSA | BLOQUEADA
```
`Config.tsx` (42 KB) e `Dfe.tsx`/`Sped.tsx` (33 KB) **quase certamente dão DIVIDE** — nesse caso, fatiar `seção → símbolo` e remedir cada filha (§13.3), sem escrever thread nenhuma ainda.

## C · O que NÃO fazer
- Não abrir PR. Não editar `.tsx`, `.md` do repo, nem o build do Cowork.
- Não transformar ficha em pedido no mesmo turno: **medir e aplicar são passos separados** (C12) — e foi misturar os dois que produziu as 5 ondas mortas de 03/09.
- Não estimar bytes de cabeça: os tamanhos acima vieram da árvore `11eff17f13db`; as faixas de símbolo vêm de busca dirigida.
- Não abrir as 4 telas na mesma sessão se a soma dos recortes passar de 40 KB — **uma sessão por tela** é mais barato que uma sessão que estoura.

## Checklist de saída (`_saida-03.md`)
1. veredito do `SeloProcedencia` **com caminho e linha** (ou "nenhum hit no repo") · 2. quatro fichas completas · 3. para cada `DIVIDE`, as filhas já fatiadas e remedidas · 4. lista das threads que **podem** ser emitidas depois, com o dono e a decisão que cada uma espera · 5. sha usado.

## Prova
Read-only: a prova é o `_saida-03.md`. Nenhum arquivo do `main` muda; nenhum PR abre. Se o `SeloProcedencia` estiver plugado, esta thread **fecha a decisão D-PROC** sem custo de código — e isso é o resultado mais valioso que ela pode ter.
