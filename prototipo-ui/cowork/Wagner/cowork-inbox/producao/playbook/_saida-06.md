---
sessao: "_saida-06"
thread: "06 · Painel + Quadro: indicadores-filtro, fila de arte, quadro por setor/máquina, filtro, mover etapa, aviso no app"
dono: "[CL]"
data: 2026-10-21
tipo: recibo
entregue_em: "2 PRs: #9717 (Painel + Quadro) e o PR deste recibo (filtro na URL e mover à mão)"
base_lida: "wagnerra23/oimpresso.com@main 448ff8ccd9"
---
# _saida-06

## Entregue

| PR | O quê |
|----|-------|
| #9717 | Painel `/producao/painel` e Quadro `/producao/quadro`: indicadores que filtram, alertas com ação, fila de arte, carga das máquinas, "Acontecendo agora", quadro por setor ou máquina. Também entram o alocador (UC-FLX-10) e o simulador de bobina (UC-FLX-13) no cartão. |
| este | Filtro inteiro na URL com uma regra só (UC-PCV-06) e o PCP movendo a OS de coluna à mão (UC-PCV-07). |

### Por UC
- **UC-PCV-01..04, 29 e 05:** entregues no #9717. O recibo daquele PR traz o detalhe.
- **UC-PCV-06:** busca, situação, responsável e setor ficam na URL (`?q=&so=&resp=&setor=`), e o servidor devolve o filtro normalizado em `filtro`. A regra de passar saiu do Quadro para `Modules/Producao/Resources/js/Pages/Producao/_lib/filtro.ts`, que é o que a Linha do tempo (thread 07) importa. "Limpar" apaga os parâmetros. Provas: `ProducaoQuadroContratoTest` (URL → filtro, com controle negativo) e `tests/js/producao-filtro.test.ts` (Travadas por arte + Marcos dá as mesmas OS lidas de volta da URL; "Limpar" volta tudo).
- **UC-PCV-07:** no modo "Por setor", o menu ⋮ do cartão lista "Mover para" todos os setores menos o atual, e o cartão pode ser arrastado para qualquer coluna, como no protótipo (`producao-cv-page.jsx`, linhas 57 e 182). Mover grava a coluna em `pcp_quadro_colunas` (tabela nova, `business_id` + FK, chave única empresa + OS) e o evento `os_movida_quadro` ("Moveu para X (à mão)", com o nome de quem moveu). Nenhuma etapa, apontamento ou situação da OS muda. Exige `producao.etapa.mover`; OS de outra empresa → 404; setor que a empresa não tem, ou o mesmo → 422.

## Decisões (via coordenação, 2026-10-21)
- **Mover segue o protótipo (UI-0029):** só troca a coluna e registra o evento, para qualquer setor, para frente e para trás.
- **Regra que não vem do protótipo, escolha da coordenação:** a coluna manual some sozinha quando a OS tem etapa iniciada ou concluída depois do movimento (evento `etapa_iniciada`/`etapa_concluida` mais novo que o guardado em `ate_evento_id`). Sem isso, o quadro mostraria a coluna antiga depois que o operador andou.
- **Descartada (A):** "Mover para" só para a próxima etapa pendente, concluindo a de origem. Divergia do protótipo, que permite qualquer coluna.

## Divergência com o caso do Cowork (registrar no Cowork)
`Producao.casos.md` UC-PCV-07 pede que "o apontamento aberto na etapa anterior é fechado com motivo 'movida pelo PCP'". Isso **não** foi feito: o protótipo não fecha apontamento, e mover não muda a etapa atual, então o cronômetro aberto continua sendo trabalho real naquela etapa. Se o fechamento for desejado, o caso precisa dizer o que acontece com a etapa de origem. O `Index.casos.md` do Quadro registra a divergência.

## Como conferi
- `php -l` nos 8 PHP tocados, no container do CT 100 por stdin. Controle: arquivo quebrado de propósito acusou `Parse error`.
- `tsc --noEmit`: zero erro nos arquivos da Produção.
- `vitest` `tests/js/producao-filtro.test.ts`: 4 de 4 verdes. Mutante sem a checagem de responsável derrubou 2 de 4.
- **Pest não rodado local nem no CT 100** (o checkout do staging está atrás e não tem o módulo). O veredito vem da lane `verticais-pest` do PR.

## O que NÃO foi feito
- A metade "trocar para a Linha" do UC-PCV-06: a Linha é a thread 07.
- O fechamento do apontamento no UC-PCV-07 (acima).
- Medição `design-diff` do Quadro contra o alvo: sem servidor local; fica para depois do deploy.

## Placar
`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/producao/playbook/00-INDICE.md --thread 06`
