<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "Relatórios · gap").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 25 · gap.md de Relatórios — onda 7 (1 tela, 1 símbolo)

> **Entrega:** proposta de `memory/requisitos/Ponto/relatorios-index-gap.md`.
> **Lido no turno:** `Relatorios/Index.charter.md` (2.779 B) · protótipo `ponto-telas.jsx :768-852` (símbolo `Relatorios`, inteiro). **NÃO lido:** `Index.tsx` (9.238 B) ⇒ lado vivo **TODO**.

## Regiões — 5

| # | região | protótipo | `data-contract` | status |
|---|---|---|---|---|
| 1 | Nota de topo — AFD/AFDT/AEJ saem em texto (**Portaria MTP 671/2021 Anexo I**), espelho e gerenciais em PDF/CSV | `:790-792` | *falta* | ✅ lei citada literalmente, como a norma exige |
| 2 | **Wizard "Gerar: <relatório>"** — Competência (select de meses) · Colaborador (só quem controla ponto) · Formato (read-only "TXT posicional") · flag "Incluir marcações anuladas" · aviso de 501 quando indisponível | `:794-816` | ✅ `relatorios-gerar` | ⚠️ **divergência de fluxo** (abaixo) |
| 3 | **Grade de cards** — ícone + título + descrição + `Gerar` (primary se disponível) + pill "em implementação" | `:818-832` | *falta* (`.pt-relgrid` não é Card) | ⚠️ **sem agrupamento por categoria** |
| 4 | **Pedidos desta sessão** — 5 colunas (Relatório, Competência, Escopo, Formato, Estado `GERADO`/`NAO_IMPLEMENTADO`) | `:834-848` | ✅ `relatorios-pedidos-desta-sessao` | ⚠️ **extra meu** — não está no charter |
| 5 | Rodapé legal — só o Espelho está implementado em `ReportService` | `:850` | — | ✅ bate com o charter, palavra por palavra |

## Divergências

1. **Fluxo: wizard × filtros globais.** O charter declara **filtros globais no topo** (período `<input type="month">` + colaborador) e o `Gerar` **em cada card** montando a URL `/ponto/relatorios/{chave}?periodo=…&colaborador=…`. Eu faço o inverso: clicar em `Gerar` **abre um wizard** com os filtros daquele relatório. **Dois modelos de interação diferentes para a mesma tela** — e o meu tem um passo a mais. Decisão `D-REL-FLUXO`.
2. **Período: select de meses × `<input type="month">`.** O charter é explícito no tipo de campo. O meu select vem de `D.MESES` (extenso, "agosto de 2026"), que é **mais legível** e **menos padrão**. O DS tem `PeriodBar`/`DatePicker` — nenhum dos dois é `type="month"`. Entra em `D-REL-FLUXO`.
3. **Agrupamento por categoria ausente.** O charter pede *"cards agrupados por categoria (default 'Geral')"*; minha grade é plana. 🟠 região a nascer — barato, e o dado (`D.RELATORIOS`) precisa ganhar o campo `categoria`.
4. **Formato: eu travo em TXT no wizard** (`readOnly`, "TXT posicional") mas o estado `f.formato` alterna pdf/txt por `chave` (`:829`) — ou seja, **o campo mostra sempre TXT mesmo quando o pedido vai como PDF**. **Defeito meu, real**: o campo mente para os relatórios gerenciais. Não corrigi no build porque a correção certa depende do `D-REL-FLUXO` (se os filtros subirem pro topo, esse campo deixa de existir).
5. **"Pedidos desta sessão" é invenção minha** — e é a única memória que a tela tem de um pedido que saiu 501. O charter não a pede; sem ela, clicar em "Gerar" num relatório "Em breve" **não deixa rastro nenhum**. Sobe como proposta (`D-REL-FILA`).
6. **Flag "Incluir marcações anuladas"** com a justificativa legal na própria label (*"exigido no AFD — a anulação também é registro"*): **extra meu**, e é regra de conformidade, não preferência. O charter não menciona. Sobe junto.

## O que o charter fecha e eu obedeço

- **Non-Goal "não gera na própria tela"**: meu `gerar()` **não gera** — registra na fila e avisa. ✓
- **Non-Goal "não permite gerar 'Em breve'"**: ⚠️ **eu permito clicar** e registro como `NAO_IMPLEMENTADO`. O charter manda **botão desabilitado**. Duas leituras: a minha preserva a intenção do usuário (o pedido não some), a do charter evita falsa expectativa. **Não mudei** — é `D-REL-FILA` na prática, porque desabilitar o botão mata a fila.
- **Anti-hook "não dispara ao trocar filtro"**: ✓ só no clique.

```json
[
  {
    "id": "D-REL-FLUXO",
    "pergunta": "Filtros globais no topo + Gerar por card (charter) ou wizard por relatório (protótipo)?",
    "medido": "charter: periodo <input type=month> + colaborador no topo, Gerar monta URL. Protótipo: Gerar abre Card de wizard com competência (select extenso), colaborador, formato read-only e flag de anuladas.",
    "consequencia": "arrasta o tipo do campo de período, o campo Formato (que hoje mente) e o agrupamento por categoria.",
    "dono": "[W]"
  },
  {
    "id": "D-REL-FILA",
    "pergunta": "Relatório 'Em breve' aceita clique e registra pedido, ou o botão fica desabilitado?",
    "medido": "charter: 'não permite gerar Em breve (botão desabilitado)'. Protótipo: aceita, avisa que não há geração em ReportService e registra em 'Pedidos desta sessão'.",
    "nota": "desabilitar mata a fila; a fila é a única memória de demanda que a tela produz. Hoje só o Espelho gera — os outros 7 são 501 no vivo.",
    "dono": "[W]"
  }
]
```

**PARAR SE** — o `Index.tsx` vivo já agrupar por categoria ⇒ região 3 é catch-up meu · `D-REL-FLUXO` aberta ⇒ **nenhuma thread de layout** (o fluxo decide a estrutura inteira da tela).
