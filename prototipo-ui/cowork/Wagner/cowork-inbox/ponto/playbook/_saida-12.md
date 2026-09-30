---
sessao: "12"
titulo: "Relatórios legais — saída da thread (AFD entregue; AEJ adiado por [W])"
autor: "[CL]"
criado: 2026-09-30
base: 5606344ca
thread: 12-relatorios-legais-bloqueada.md
veredito: "PARCIAL — 1 de 2 PRs. AFD do REP-P por colaborador + AFDT fora do catálogo no #8224. AEJ adiado por decisão [W] 2026-09-30 — a razão registrada (NSR original do REP-C) está ERRADA, ver §Errata. O placar vai ler esta thread como 'feito' porque a única prova do índice é a existência do RelatorioLegalContratoTest — isso é falso para o AEJ; ver §Para o Cowork."
---

# _saída 12 · Relatórios legais

## ⚠️ Errata (2026-09-30, mesma sessão) — medida contra os leiautes oficiais do MTE

Li os dois PDFs oficiais (gov.br, "Leiaute do Arquivo Fonte de Dados - AFD" e "Leiaute do Arquivo
Eletrônico de Jornada - AEJ", baixados neste turno) **depois** de ter escrito esta saída. Dois erros meus:

1. **A lacuna 5 abaixo é falsa.** O registro 05 (Marcações) do AEJ **não tem NSR**: identifica a marcação
   por `idtVinculoAej` + `dataHoraMarc` + `idRepAej`. O NSR original do REP-C **não** é pré-requisito do
   AEJ. O PR #8232 (grava `nsr_origem`) nasceu dessa premissa — auto-merge desarmado, decisão [W].
2. **O AFD do #8224 tinha 3 desvios da norma**, corrigidos no mesmo PR: linha da assinatura com 100
   posições (espaços à direita); nome `AFD` + INPI + CNPJ/CPF do empregador + `REP_P` (item 10.3);
   CNPJ/CPF do empregador (campo A) alinhado à esquerda com espaços (item 7), não zeros à esquerda.

**O que de fato trava o AEJ** (leiaute AEJ versão `002`): registro 04 (horário contratual) e 07
(DSR · falta · banco de horas) são dado de **tratamento**, não de `ponto_marcacoes` — e a ADR 0413 lista
como violação gerar AEJ lendo `ponto_apuracao_dia`; `fonteMarc "I"` (manual) e `tpMarc "D"`
(desconsiderada) exigem `motivo`, que marcação manual não guarda e anulação antiga (antes de 29/09) não
tem; registro 08 pede a identidade do PTRP (nome, versão, CNPJ, razão, e-mail). Decisões [W].

## Lei de referência

Portaria MTP 671/2021 — o leiaute do **Arquivo Fonte de Dados (AFD)**, publicado pelo MTE em gov.br
junto da Portaria (versão `004`, as mesmas posições que o `AfdLeiaute671ContratoTest` já ancora), e o
AEJ que substituiu AFDT + ACJEF. Ordem e escopo: **ADR 0413 W7** (AFD → AEJ, o AFDT sai da exportação,
a importação de AFDT legado fica).

## PARAR SE disparou antes do código — 5 lacunas medidas, 4 decisões [W] no mesmo dia

A thread diz *"formato exigir campo que ponto_marcacoes não tem → parar"*. Medido no `main`:

| # | lacuna | decisão [W] 2026-09-30 |
|---|---|---|
| 1 | cabeçalho: nº INPI do REP-P (190-206) — `grep -i inpi` fora do parser = 0 | config `ponto_afd.rep_p_inpi`, **sem default, falha fechada** |
| 2 | cabeçalho: CNPJ do desenvolvedor (255-268) — não existe | idem, `ponto_afd.desenvolvedor_cnpj` |
| 3 | NSR/hash do REP-P são **por colaborador** (decisão de 29/09) → AFD da empresa teria NSR repetido | **1 AFD por colaborador** |
| 4 | `.p7s` ICP-Brasil — sem certificado, ADR 0413 D2 deixou ICP fora | gera **sem .p7s, declarado** no catálogo |
| 5 | REP-C importado: `MarcacaoService::registrar` troca o NSR do arquivo pelo contador interno → AFD de REP-C e AEJ citariam NSR errado | **AEJ adiado** |

## O que saiu

| PR | conteúdo |
|---|---|
| [#8224](https://github.com/wagnerra23/oimpresso.com/pull/8224) | `ReportService::afd()` (tipo 1 · tipo 7 · trailer · linha da assinatura) · chave `afd` sai do 501 · `afdt` sai do catálogo · `config/ponto_afd.php` · `RelatorioLegalContratoTest` UC-RELIDX-06..09 · lane `ponto-pest` |

## Placar

**entregue 1 de 2** · ausente **AEJ** por decisão [W] — depende de gravar o NSR original do arquivo nas
marcações importadas (só para frente; marcação é append-only).

## Prova

- CT 100 (`oimpresso-staging`, worktree isolado, MySQL): **23 passed · 113 assertions** nos 3 arquivos
  (legal + catálogo + parser 671).
- Mutação: hash sem encadear → UC-06/07 caem · `gerarAfd` sem escopo de tenant → UC-09 cai. Restaurado → verde.
- Lane `ponto-pest` do #8224: ver o PR (sem veredito quando esta saída foi escrita).

## Achados — nenhum consertado de passagem

1. **Anulações e legado fora do AFD.** A anulação entra na sequência do REP-P ([W] 29/09), mas o leiaute do
   REP-P não tem registro de anulação — o NSR do arquivo mostra o buraco. O legado com NSR de `microtime`
   também fica fora (não cabe em 9 posições).
2. **Premissa do hash** (a mesma do parser): `sha256(pos. 001-073 + hash anterior)`, `""` antes do 1º.
3. **Timezone do DH de gravação**: `created_at` é `CURRENT_TIMESTAMP` do MySQL, lido pelo Eloquent no fuso
   do app. Se o servidor MySQL não estiver em America/Sao_Paulo, o DH de gravação sai deslocado — não medido.
4. **UC-RELIDX-04 fica frágil quando o AFD estiver configurado**: `relPorColaborador()` pega o 1º relatório
   `disponivel && requer_colaborador`, que passa a ser o AFD (download, não redirect). Hoje verde porque
   nenhum ambiente de teste tem a config.

## Para o Cowork (o Code não edita o índice)

- A thread 12 tem **uma** prova (`RelatorioLegalContratoTest.php` existe) e o índice diz 2 PRs. Com esta
  saída o placar vai marcá-la **feito**, e o AEJ some da conta. Pedido: dividir em **12a AFD** (esta prova)
  e **12b AEJ** (prova nova, ex.: `ReportService` sem `Gerador AEJ ainda não implementado`), com
  `depende` do PR que grava o NSR original.
