---
id: cowork-inbox-fiscal-cockpit-casos
casos: Cockpit fiscal · ondas F1 · /fiscal · /fiscal/nfe · /fiscal/nfse
irmaos: Cockpit.charter.md (lei do delta) · resources/js/Pages/Fiscal/Cockpit.charter.md (lei da tela, no main)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
owner: wagner
autor: "[CC]"
last_run: "nunca — os testes nascem nesta entrega"
---

# Casos de Uso & Aceite — Cockpit fiscal (ondas F1)

> Personas: **Eliana [E]** (contadora, resolve rejeição e fecha o mês) · **Larissa [L]** (balcão, emite e cancela no mesmo turno).
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, veredito pendente da lane · ⬜ não verificado · ❌ nasce vermelho de propósito.

## Rastreabilidade

| UC | O que defende | Prio | Teste | Status |
|---|---|---|---|---|
| UC-FOF1-01 | cancelar exige justificativa de 15 caracteres | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FOF1-02 | cancelar publica evento 110111 na timeline | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FOF1-03 | CC-e numera a sequência sozinha | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |
| UC-FOF1-04 | retransmitir preserva a numeração | `[must]` | herda `AcoesControllerTest` (vivo) | 🧪 |
| UC-FOF1-05 | inutilizar faixa não serve pra cancelar nota | `[must]` | `FiscalOndasF1Test` | ⬜ |
| UC-FOF1-06 | contador da visão salva bate com o cabeçalho | `[should]` | — (comportamento de UI) | ⬜ |
| UC-FOF1-07 | atalho não rouba digitação | `[must]` | — (E2E ausente) | ⬜ |
| UC-FOF1-08 | procedência nomeia superfície de demonstração | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |

---

## UC-FOF1-01 — Cancelar sem justificativa é recusado antes de sair da tela `[must]`

**Dado** uma NF-e com a janela legal aberta
**Quando** [E] pede cancelamento e escreve menos de 15 caracteres
**Então** o botão de confirmar fica desabilitado, o contador mostra quanto falta e nenhuma requisição sai.

- **Regressão que defende:** justificativa vazia vira evento sem defesa documental; o Fisco recebe cancelamento que o business não sabe explicar.
- **Aceite:** com 15+ caracteres, confirmar habilita; a justificativa aparece na auditoria da nota **com o texto digitado**.

## UC-FOF1-02 — Cancelamento aceito aparece nos eventos, não só na linha `[must]`

**Dado** uma nota cancelada com sucesso
**Quando** [E] abre `/fiscal/eventos`
**Então** existe um evento *Cancelamento* com cstat 101, o documento nomeado, o autor e a justificativa.

- **Por que importa:** a timeline é a trilha de auditoria (LGPD Art. 37). Ação que muda a linha e não deixa evento é buraco de auditoria.
- **Aceite:** a linha perde a pílula de prazo de cancelamento e o status vira *Cancelada*.

## UC-FOF1-03 — A sequência da CC-e é do sistema, não do operador `[must]`

**Dado** uma nota autorizada que já teve uma carta de correção
**Quando** [E] envia a segunda
**Então** ela sai como #2, sem o operador digitar número.

- **Regressão que defende:** sequência repetida é rejeitada pela SEFAZ; sequência escolhida à mão erra sob concorrência.

## UC-FOF1-04 — Retransmitir não gasta número novo `[must]`

**Dado** uma nota rejeitada
**Quando** [E] retransmite depois de corrigir o cadastro
**Então** a nota é autorizada **com o mesmo número e série** e a auditoria registra a retransmissão.

- **Âncora:** contrato de preservação de numeração (CONFAZ Art. 14) já provado no vivo em `AcoesControllerTest`.

## UC-FOF1-05 — Inutilizar faixa não é atalho para cancelar `[must]`

**Dado** uma faixa numérica que nunca virou nota
**Quando** [E] inutiliza informando série, intervalo e justificativa
**Então** sai evento cstat 102 e **nenhuma** nota autorizada é afetada.

- **Aceite:** a tela diz, no próprio modal, que inutilização não cancela nota autorizada.

## UC-FOF1-06 — O contador do filtro nunca contradiz o cabeçalho `[should]`

**Dado** a tela de NF-e/NFC-e (que exclui NFS-e)
**Quando** [E] lê "8 notas" no cabeçalho
**Então** o chip "Todas" também mostra 8 — os contadores derivam do conjunto exibido.

- **Regressão que defende:** no vivo os contadores são fixos no código e divergem do filtro; o defeito foi visto neste F1 e corrigido.

## UC-FOF1-07 — Atalho de lista não atropela campo de texto `[must]`

**Dado** o cursor dentro da busca, de um `textarea` de justificativa, ou um drawer aberto
**Quando** [E] digita "j", "k" ou "n"
**Então** as letras entram no campo e o cursor da lista não se move.

## UC-FOF1-08 — A contadora sabe o que é demonstração `[must]`

**Dado** que a lista de notas, os eventos do cabeçalho, a situação da SEFAZ, o pacote da contabilidade, o write-off e o histórico de DF-e são servidos por dado fixo no código
**Quando** [E] liga *Procedência*
**Então** cada superfície mostra o selo correspondente (`demonstração` · `leitura real` · `derivado da lista` · `atrás de trava`) e a explicação do que falta pra virar consulta real.

- **Âncora:** `CU-FISC-16` — o backlog do vivo pede exatamente isso e marca como decisão [W].
- **Aceite:** nenhum número de demonstração aparece sem selo quando o modo está ligado.

---

## Backlog de casos (viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜] Filtro e paginação server-side** — o F1 filtra em memória; o vivo declara a pendência (`NotasUnifiedService` + cursor).
- **[BACKLOG · ⬜] Densidade persistida por usuário no servidor** — hoje é armazenamento local do navegador.
- **[BACKLOG · ⬜ · decisão [W]] Ações em lote da barra de seleção** (XMLs em ZIP, DANFEs em PDF, reenvio por e-mail) — hoje só confirmam com toast.
- **[BACKLOG · ⬜] E2E de teclado** — a tela não aparece em `tests/Browser`; UC-FOF1-07 não tem como ser provado sem lane de navegador.
