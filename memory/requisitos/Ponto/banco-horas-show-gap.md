---
id: requisitos-ponto-banco-horas-show-gap
tela: Ponto/BancoHoras/Show (/ponto/banco-horas/{colaborador})
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/BancoHoras/Show.tsx
gerado_em: 2026-09-28
---

# GAP-SPEC — Ponto/BancoHoras/Show

> **Origem:** thread `21-gap-banco-horas.md` do playbook do Ponto. Decisão citada só existe em
> `ATA-DECISOES-2026-09-14.md`: **D-PONTO-DETALHE** (o extrato é rota própria — `BancoHoras/Show` está na
> lista das 9 páginas).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`, ramo `if (sel)` do símbolo
> `BancoHoras` (`:352-407`).
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/BancoHoras/Show.tsx` @ `e4289e688` (244 linhas) e
> `BancoHorasController.php`. Toda linha abaixo saiu de `grep -n`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho do colaborador | **Diverge em conteúdo.** Vivo: `h1` com o nome, matrícula e "Ledger append-only", `Voltar` para a lista (`Show.tsx:98-112`). Protótipo: `Voltar aos saldos` + nome + matrícula · cargo · escala (`ponto-telas.jsx:368-371`). | Protótipo à frente em cargo e escala; exige o dado no payload (não medido). Forma, sem id na ata. |
| KPIs do extrato | **Protótipo à frente.** Vivo: um `Card` só com o saldo atual (`Show.tsx:114-125`). Protótipo: Saldo atual, Lançamentos, Teto do acordo, Prazo de compensação (`ponto-telas.jsx:374-379`). Os dois últimos respondem à pendência "regra de expiração de crédito exibida ao usuário" do charter do Index. | Emenda de charter proposta (dona: thread 27). Não é pedido de código antes da emenda. O Non-Goal "não expira crédito na tela" (`Index.charter.md:60`) continua valendo: mostrar o prazo não é expirar. |
| Histórico de movimentos | **Diverge em colunas.** Vivo: Data ref., Tipo, Minutos, Observação, Registrado (humanizado) (`Show.tsx:192-198`). Protótipo: Data, Referência, Origem, Minutos, Observação (`ponto-telas.jsx:380-393`). | Forma, sem id na ata: fica para a passada de FORMA. A thread 17 grava `data-contract="bancohoras-historico-de-movimentos"`. |
| Paginação do histórico | **Ausente nos dois lados, e o vivo perde linha.** O servidor pagina 50 (`BancoHorasController.php:107`) e a interface declara `last_page` (`Show.tsx:47`), mas o `.tsx` não renderiza navegação (`grep -n "links"` e `grep -n "last_page"` só acham a interface). Quem tem mais de 50 movimentos vê só os 50 primeiros. Protótipo: renderiza a lista inteira (`ponto-telas.jsx:383`). Goal do charter: 50/pág (`Show.charter.md:32`). | **Gap real no vivo:** navegação de página com partial reload `only: ['movimentos']`. O protótipo também corrige (ganha o `Pager`). |
| Ajuste manual | **Paridade.** Vivo: Minutos (±) e Observação com mínimo 5 (`Show.tsx:139-164`, regra em `:81`). Protótipo: Minutos + Observação 500 com mínimo 5 (`ponto-telas.jsx:395-402`, regra em `:360`). Charter: observação obrigatória, mínimo 5 (`Show.charter.md:31`). | Nada. A soma local de saldo do protótipo (`ponto-telas.jsx:362`) é artefato de mock: não portar — viola o Non-Goal "não recalcula o saldo" (`Show.charter.md:39`). |
| Aviso append-only | **Paridade.** Vivo: `Alert` "Append-only" (`Show.tsx:171-178`). Protótipo: `Nota` de append-only (`ponto-telas.jsx:400`) e `Legal` com a Portaria (`:404`). | Nada. A citação da Portaria MTP 671/2021 falta no vivo (`grep -n "Portaria"` = 0) — passada de FORMA. |
