---
sessao: "02"
titulo: Paginação `.fx-pager` no cockpit Fiscal
dono: "[CL]"
base: 11eff17f13db
prefixo: resources/js/Pages/Fiscal/Cockpit.tsx · Modules/Fiscal/Http/Controllers/CockpitController.php (SÓ se o corte for server-side)
nao_toca: _components/** · _lib/** · Nfe.tsx · Nfse.tsx · Dfe.tsx · Eventos.tsx · Config.tsx · Sped.tsx · os 7 contratos
depende: — (vaga 1). É a ÚNICA das 10 ondas de 03/09 que sobreviveu à releitura e não tem trava [W].
---
# 02 · Paginação `.fx-pager`

## A · Identidade — ancoragem dupla
- **alvo (protótipo medido, dark, T1 estável 1099/1099/1099):** `.fx-page` tem **10 filhos nesta ordem** — `.fx-h · .ds-tabbar · .fx-ribbon · .fx-alerts · .fx-writeoff · .fx-toolbar · .fx-chips · .fx-table.fx-d-comfort · **.fx-pager** · .fx-toasts`. O `.fx-pager` é o **9º**, entre a tabela e os toasts. Fonte: `prototipo-ui/cowork/fiscal-page.jsx`.
- **âncora (código):** `resources/js/Pages/Fiscal/Cockpit.tsx` (34.457 B, sha `a75f4bd1a961`). Busca dirigida hoje por `Pagination` em `Pages/Fiscal/` → **0 hit**: a tela **não tem paginação**, nem do DS nem local.
- **componente obrigatório:** `Pagination` do DS (`prev/next + números + elipse + "N–M de T"`, props `page`/`pageCount`/`onChange` + `total`/`pageSize`). **Não escrever pager próprio** (C1 · §5 do pacote).

## B · Recorte de leitura
```
ÂNCORA (congelada 2026-09-08)
  arquivo   resources/js/Pages/Fiscal/Cockpit.tsx    34.457 B  sha a75f4bd1a961
  recorte   o JSX da tabela e o que vem DEPOIS dela — localizar por `.fx-table` e ler
            até o fim do return (a faixa contém :605, o onKeyDown da linha)
  ler       + o §"lista"/"paginação" de Cockpit.charter.md (18.053 B — SÓ a seção)
  NÃO ler   Cockpit.casos.md (43.511 B) · Config.tsx (42.005 B) · os outros 6 .tsx
            (ORÁCULO: abrir só para dirimir dúvida pontual)
  frescor   sha mudou ⇒ REMEDIR antes de escrever
```

## C · A pergunta que decide o tamanho do PR (medir antes de aplicar)
**O corte é do servidor ou do cliente?** Ler o que o `CockpitController` manda para a prop da lista:
- **já vem paginado** (`LengthAwarePaginator`, com `total`/`per_page`/`current_page`) ⇒ **1 arquivo**, só UI: ligar o `Pagination` do DS aos campos que já chegam.
- **vem coleção inteira** ⇒ **2 arquivos**, e o corte nasce no controller (`paginate()`), com `only:[...]` no partial reload — como `Pages/Repair/Index.tsx` já faz.
- **Não paginar no cliente uma coleção inteira** para "resolver rápido": em multi-tenant fiscal isso carrega a base do negócio inteiro numa tela de balcão.

## D · Comportamento (EARS)
| elemento | QUANDO → O SISTEMA DEVE | persiste | reversível | prova |
|---|---|---|---|---|
| `Pagination` | página escolhida → recarregar só a lista (partial reload) | querystring | sim (voltar) | 1ª linha troca; `total` estável |
| tamanho de página | alterado → refiltrar do 1º item, nunca manter offset órfão | querystring | sim | contador coerente |
| lista vazia após filtro | → `EmptyState` com "Limpar busca e filtros", **sem pager fantasma** | — | sim | pager não renderiza com `pageCount<2` |
| linha | **teclado continua funcionando** (Enter/Space) depois de trocar de página | — | — | `UC-FCKP-11` verde |

**Invariantes:** ordenação e filtro são de servidor · `business_id` em toda consulta (Tier 0, ADR 0093) · número sem fonte ⇒ `—` (C7) · zero CSS novo: o `.fx-pager` já existe no `fiscal-cockpit.css`.

## Execução
```
ARQUIVOS A EDITAR : resources/js/Pages/Fiscal/Cockpit.tsx
                    Modules/Fiscal/Http/Controllers/CockpitController.php  (SÓ no caso 2 do bloco C)
REUSAR            : Pagination do DS · EmptyState do shared · o padrão de partial reload
                    (only:[...]) de Pages/Repair/Index.tsx · botao-fiscal.ts / chip-filtro.ts
CRIAR             : nada — nenhum componente, nenhum CSS, nenhum token
NÃO TOCAR         : o onKeyDown da linha (:605) · DensidadeToggle · RibbonSpark · AlertasFiscais
                    · SavedViewsChips · NotaDrawerV2 · os 16 _components e 11 _lib
PASSO A PASSO     : 1) gh pr list --state open × Cockpit.tsx
                    2) LER o recorte + a prop da lista no controller → decidir o caso do bloco C
                    3) montar o Pagination na posição 9 (entre .fx-table e .fx-toasts) — a ORDEM
                       é o alvo, não só a presença
                    4) ESTENDER Cockpit.casos.md com o UC da paginação citando o teste; nunca recriar
                    5) contar flex/grid do arquivo antes e depois (ratchet por arquivo)
                    6) placar no PR · _saida-02.md
PARAR SE          : (a) a lista chegar inteira E o controller estiver fora do seu prefixo autorizado
                        → PARE em 1 arquivo e reporte: paginação de cliente aqui é regressão de Tier 0
                    (b) o contrato fiscal-cockpit.contract.json declarar a seção do pager com copy
                        literal diferente da sua → o contrato manda
                    (c) o teste do UC-FCKP-11 ficar vermelho → o teclado é lei (charter :226), o
                        pager cede
```

## Checklist de saída (`_saida-02.md`)
1. sha conferido · 2. caso do bloco C identificado **com a linha do controller citada** · 3. `Pagination` do DS (não pager próprio) · 4. posição 9 verificada no render · 5. `casos.md` estendido com UC citado por teste · 6. `UC-FCKP-11` verde · 7. contagem flex/grid igual · 8. placar no PR.

## Prova (o que o PLACAR confere)
`Cockpit.tsx` contém `Pagination` · **guarda:** contém `onKeyDown` e `Cockpit.casos.md` continua existindo · `_saida-02.md` presente. Não verificável daqui: T7 · screenshot prod dark 1280.
