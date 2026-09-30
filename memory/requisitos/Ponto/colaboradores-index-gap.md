---
id: requisitos-ponto-colaboradores-index-gap
tela: Ponto/Colaboradores/Index (/ponto/colaboradores)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Colaboradores/Index.tsx
gerado_em: 2026-09-29
charter: resources/js/Pages/Ponto/Colaboradores/Index.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/23-gap-colaboradores.md
---

# GAP-SPEC — Ponto/Colaboradores/Index

> **Fonte do contrato:** charter `Colaboradores/Index.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `Colaboradores` (`:590-687` em 2026-09-29; era `:564-659` no build importado no #8067). Os ids `D-*` são da
> [ATA-DECISOES-2026-09-14](../../../prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/ATA-DECISOES-2026-09-14.md).
> **Lado vivo medido** em `Colaboradores/Index.tsx` e `ColaboradorController.php` nesta base
> (`origin/main` e4289e688), e **re-medido em 2026-09-29** (branch `claude/reancora-maps-8194`, sobre o
> #8194). As linhas citadas na thread 23 (`:492-634`) eram de um build anterior e **não** valem mais —
> as daqui foram relidas.
> Na base e4289e688 o `.tsx` vivo tinha **0** `data-contract`; em 2026-09-29 tem **1**
> (`data-contract="colaboradores-colaboradores"`, `Index.tsx:123`) — o resto segue linha-only.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Barra de busca e filtros | **Parcial** (re-medido 2026-09-29). Vivo: `PageFilters` com a busca (`Index.tsx:96-108`) e um select **Situação** só com Todos · **Sem PIS cadastrado** (`:109-120`), debounce 350ms e partial reload `only: ['colaboradores','search','situacao']` em `:60-75`; o filtro "Sem PIS" roda no servidor (`ColaboradorController.php:48-52`, UC-COLIDX-04). Protótipo (`ponto-telas.jsx:630-646`): busca + Limpar + select **Escala** + select **Situação** (Ativos · Só quem controla ponto · **Sem PIS cadastrado** · Desligados · Todos) + contador "N de M" (`:645`). Faltam no vivo: o select Escala, as situações Ativos · Só quem controla ponto · Desligados, e o contador. | **Incorporar os 2 filtros** — `D-COLAB-COLUNAS` = INCORPORA tudo; o "Sem PIS cadastrado" é, pela ata, o item de maior valor do lote e **já entrou** (2026-09-29: `ColaboradorController.php:48-52` + `Index.tsx:109-120`); faltam o select Escala e as outras 3 situações. Exige filtro no `ColaboradorController@index` (`:15-76`), não só na tela. A busca em memória do protótipo é artefato de mock: **não portar** (o vivo já tem debounce + servidor). |
| Lista de colaboradores | **Vivo com 7 colunas × protótipo com 9.** Vivo `Index.tsx:146-152`: Matrícula · Nome(+e-mail) · CPF/PIS · Escala · Ponto · BH · ação. Protótipo `:648`: + **Último ponto** + **Saldo BH** (cor por sinal, `:667`) e cargo na sub-linha do nome (`:661`). A linha de desligado com estado próprio (`tr.folga`, `:659`) não existe no vivo (`:157` só tem `hover`). | **Incorporar** as 2 colunas e o estado de desligado — `D-COLAB-COLUNAS`. São dado agregado (última marcação do mês, saldo do banco de horas): mudança de payload no controller, não só de tela. |
| CPF e PIS redigidos | **Paridade.** Vivo redige nos 3 últimos dígitos com `redigirDigitos` (`Index.tsx:168-176`, comentário `:163-167` citando `D-COLAB-CPF`); PIS ausente vira **"PIS não cadastrado"** (`:175`). Protótipo idem (`mascara`, `:600` e `:664`). | Nada — `D-COLAB-CPF` já está aplicado nos dois lados. O "PARAR SE" da thread 23 se cumpriu: o defeito era só do protótipo e já foi corrigido nele. |
| Estados vazios | **Paridade** (re-medido 2026-09-29). Vivo distingue "sem cadastro" × "busca sem resultado" × "filtro sem resultado" (`Index.tsx:126-140`, frase com o termo buscado em `:132`, a do filtro em `:133`). Protótipo idem (`:649-654`), com a 3ª frase para o caso filtrado (`:653`). | Nada — a 3ª frase do protótipo só fazia sentido quando os filtros da 1ª parte existissem; em 2026-09-29 ela já entrou no vivo junto com o filtro "Sem PIS" (`Index.tsx:133`). |
| Paginação | **Paridade.** Vivo: 25/pág em `ColaboradorController.php:54` (`paginate(25)`), links com partial reload em `Index.tsx:204-225`. Protótipo: `usePagina(lista.length, 25)` (`:612`), `Pager` em `:680`. | Nada — paridade (25/pág é o número do charter). |
| Nota de vinculação com o HRM | **Diverge em forma** (re-medido 2026-09-29). O subtítulo "Nome/email vêm do HRM (UltimatePOS core)." que o vivo tinha no header não existe mais — o cabeçalho é `<PontoAreaHeader>` (`Index.tsx:94`); o HRM só aparece na descrição do estado vazio (`:134`). Protótipo: `Nota` própria no rodapé (`:682-684`). | Protótipo à frente na forma: no vivo a nota persistente sumiu e o HRM só aparece no estado vazio (`Index.tsx:134`). Restaurar a nota no vivo, seguindo o protótipo (forma é do protótipo, UI-0029). |

## Decisões desta tela (ata)

- `D-COLAB-CPF` — **aplicado** nos dois lados.
- `D-COLAB-COLUNAS` — **INCORPORA tudo** (2 filtros + 2 colunas + estado de desligado). Pedido
  para o vivo; a emenda de charter é da thread 27, não desta. (2026-09-29: do lote, só a situação
  "Sem PIS cadastrado" entrou — UC-COLIDX-04.)
- Nenhuma decisão [W] nova nasceu desta medição.
