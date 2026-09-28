---
id: requisitos-ponto-colaboradores-index-gap
tela: Ponto/Colaboradores/Index (/ponto/colaboradores)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Colaboradores/Index.tsx
gerado_em: 2026-09-28
charter: resources/js/Pages/Ponto/Colaboradores/Index.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/23-gap-colaboradores.md
---

# GAP-SPEC — Ponto/Colaboradores/Index

> **Fonte do contrato:** charter `Colaboradores/Index.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `Colaboradores` (`:564-659`, build importado no #8067). Os ids `D-*` são da
> [ATA-DECISOES-2026-09-14](../../../prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/ATA-DECISOES-2026-09-14.md).
> **Lado vivo medido** em `Colaboradores/Index.tsx` e `ColaboradorController.php` nesta base
> (`origin/main` e4289e688). As linhas citadas na thread 23 (`:492-634`) eram de um build
> anterior e **não** valem mais — as daqui foram relidas.
> O `.tsx` vivo tem **0** `data-contract` (`grep -n data-contract` = 0): toda âncora abaixo é
> linha-only até a thread 17.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Barra de busca e filtros | **Parcial.** Vivo tem só a busca: `Index.tsx:86-99` (`PageFilters` + `Input`), com debounce 350ms e partial reload `only: ['colaboradores','search']` em `:52-64`. Protótipo (`ponto-telas.jsx:602-618`): busca + Limpar + select **Escala** + select **Situação** (Ativos · Só quem controla ponto · **Sem PIS cadastrado** · Desligados · Todos) + contador "N de M". Nenhum dos 2 selects existe no vivo. | **Incorporar os 2 filtros** — `D-COLAB-COLUNAS` = INCORPORA tudo; o "Sem PIS cadastrado" é, pela ata, o item de maior valor do lote. Exige filtro no `ColaboradorController@index` (`:15-60`), não só na tela. A busca em memória do protótipo é artefato de mock: **não portar** (o vivo já tem debounce + servidor). |
| Lista de colaboradores | **Vivo com 7 colunas × protótipo com 9.** Vivo `Index.tsx:121-129`: Matrícula · Nome(+e-mail) · CPF/PIS · Escala · Ponto · BH · ação. Protótipo `:620`: + **Último ponto** + **Saldo BH** (cor por sinal, `:639`) e cargo na sub-linha do nome (`:633`). A linha de desligado com estado próprio (`tr.folga`, `:631`) não existe no vivo (`:133` só tem `hover`). | **Incorporar** as 2 colunas e o estado de desligado — `D-COLAB-COLUNAS`. São dado agregado (última marcação do mês, saldo do banco de horas): mudança de payload no controller, não só de tela. |
| CPF e PIS redigidos | **Paridade.** Vivo redige nos 3 últimos dígitos com `redigirDigitos` (`Index.tsx:144-153`, comentário `:139-143` citando `D-COLAB-CPF`); PIS ausente vira **"PIS não cadastrado"** (`:151`). Protótipo idem (`mascara`, `:573` e `:636`). | Nada — `D-COLAB-CPF` já está aplicado nos dois lados. O "PARAR SE" da thread 23 se cumpriu: o defeito era só do protótipo e já foi corrigido nele. |
| Estados vazios | **Paridade.** Vivo distingue "sem cadastro" × "busca sem resultado" (`Index.tsx:103-116`, frase com o termo buscado em `:109`). Protótipo idem (`:621-626`), com uma 3ª frase para o caso filtrado. | Nada — a 3ª frase do protótipo só faz sentido quando os filtros da 1ª parte existirem; entra junto com eles. |
| Paginação | **Paridade.** Vivo: 25/pág em `ColaboradorController.php:41` (`paginate(25)`), links com partial reload em `Index.tsx:180-201`. Protótipo: `usePagina(lista.length, 25)` (`:585`), `Pager` em `:652`. | Nada — paridade (25/pág é o número do charter). |
| Nota de vinculação com o HRM | **Paridade de conteúdo, forma diferente.** Vivo: subtítulo do header "Nome/email vêm do HRM (UltimatePOS core)." (`Index.tsx:79`). Protótipo: `Nota` própria no rodapé (`:654-656`). | Nada — o Non-Goal "não cadastra colaborador" está materializado nos dois. Forma é assunto das threads de FORMA, não deste gap. |

## Decisões desta tela (ata)

- `D-COLAB-CPF` — **aplicado** nos dois lados.
- `D-COLAB-COLUNAS` — **INCORPORA tudo** (2 filtros + 2 colunas + estado de desligado). Pedido
  para o vivo; a emenda de charter é da thread 27, não desta.
- Nenhuma decisão [W] nova nasceu desta medição.
