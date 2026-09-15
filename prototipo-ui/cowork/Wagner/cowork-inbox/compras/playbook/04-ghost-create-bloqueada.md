---
sessao: "04"
titulo: Ghost /compras/create — conflito de canon (SCOPE × charter)
dono: "[W]"
base: 9101f86af501
prefixo: — (nenhum arquivo até [W] responder D-GHOST)
nao_toca: resources/js/Pages/Purchase/Create.tsx · resources/js/Pages/Purchase/Create.charter.md
depende: D-GHOST
---
# 04 · Ghost `/compras/create` — BLOQUEADA

## O conflito, literal
- `memory/requisitos/Compras/SCOPE.md` lista **"Wave 3 (TODO): rota `/compras/create`"** e o **sidebar v3 já declara o ghost**.
- `resources/js/Pages/Purchase/Create.charter.md` declara Non-Goal: **"NÃO nasce `Pages/Compras/Create.tsx` — a grade vive aqui (convergência C1)"**.
- `Modules/Compras/Routes/web.php` não tem a rota (2 rotas, ambas GET de leitura).

Ghost apontando pra rota que o canon proíbe é **link morto** (se ninguém criar) ou **tela proibida** (se alguém criar). Os dois estados são defeito; qual deles corrigir é decisão de [W].

## As 2 saídas, com custo medido
| saída | o que muda | arquivos |
|---|---|---|
| (a) **remover o ghost** | tirar a entrada do sidebar (`DataController`, Fase 4 ADR 0180) — o canon vigente (C1) passa a valer sozinho | **1 editado** |
| (b) **criar `/compras/create`** | Page + charter + casos + rota + controller + contrato — **e emendar o charter do `Purchase/Create`**, porque contraria o Non-Goal C1 | **5 novos + 1 editado** |

Recomendação técnica (não é decisão): (a). A grade tam×cor **já está plugada** em `Purchase/Create.tsx` (`:26` importa `GradeMatrixInput`, `:459` usa) e o módulo é cockpit de leitura por SCOPE — criar um segundo caminho de escrita duplica o que já funciona.

## O que NÃO fazer enquanto isso
- Não criar `Pages/Compras/Create.tsx`: hoje é **Non-Goal declarado**, e um PR que o crie contraria charter vigente.
- Não apagar o ghost por conta própria: mexer no sidebar sem decisão é mudar navegação de produção.
- Não inventar alias de permissão `compras.create` — o código usa `purchase.*`; `compras.*` é futuro (o charter do cockpit chama isso de C1 explicitamente).

## Prova
Respondida a D-GHOST, esta thread se reescreve com prefixo e provas: saída (a) = `nao_contem` do ghost no `DataController`; saída (b) = o pacote de 6 com `Inertia::render` no controller (D4) + emenda datada no charter.
