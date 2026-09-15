---
id: cowork-inbox-fiscal-sped-casos
casos: SPED e livros · ondas F1 · /fiscal/sped
irmaos: Sped.charter.md (lei do delta) · resources/js/Pages/Fiscal/Sped.casos.md (aceite da tela, no main)
owner: wagner
autor: "[CC]"
last_run: "nunca"
---

# Casos de Uso & Aceite — SPED (ondas F1)

> Persona: **Eliana [E]** (contadora) fecha a competência e entrega a EFD-ICMS/IPI.

## Rastreabilidade

| UC | O que defende | Prio | Teste | Status |
|---|---|---|---|---|
| UC-FSF1-01 | o bloqueio diz o motivo | `[must]` | — (UI) | ⬜ |
| UC-FSF1-02 | trava é fail-secure e o bypass é nomeado | `[must]` | herda `SimplesOnlyGateTest` (vivo) | 🧪 |
| UC-FSF1-03 | mês aberto não gera arquivo | `[must]` | `FiscalOndasF1Test` | ⬜ |
| UC-FSF1-04 | prévia é declarada amostra | `[must]` | — (UI) | ⬜ |
| UC-FSF1-05 | a tela admite que ninguém validou o TXT | `[must]` | `FiscalOndasF1Test` | ❌ nasce vermelho |

---

## UC-FSF1-01 — Botão desabilitado explica o que falta `[must]`

**Dado** a competência escolhida
**Quando** [E] não pode gerar
**Então** a barra lista os quatro pré-requisitos e marca qual falhou (ano, competência futura, mês em aberto, trava ativa) — nunca um botão cinza sem motivo.

## UC-FSF1-02 — A trava começa ligada `[must]`

**Dado** o fallback de tributação com hardcodes
**Quando** a tela abre
**Então** `sped_simples_only_lock` está ativa e o download bloqueado; liberar é ação explícita de superadmin, e reativar é um clique.

- **Regressão que defende:** o TXT vai pro Fisco. CFOP/CST errado em venda interestadual expõe multa (achado do audit sênior).

## UC-FSF1-03 — Competência aberta não vira arquivo `[must]`

**Dado** o mês corrente, ainda recebendo notas
**Quando** [E] tenta gerar
**Então** a ação é recusada e a tela mostra a data em que a competência fecha.

## UC-FSF1-04 — Prévia não é o arquivo `[must]`

**Dado** a prévia aberta
**Quando** [E] lê as linhas `|REG|…`
**Então** a tela declara que são amostra dos registros canônicos com linhas encurtadas para leitura.

## UC-FSF1-05 — A tela não finge validação que não houve `[must]`

**Dado** que os testes dos blocos são *source-grep* e não existe golden file
**Quando** [E] abre o cartão de validação
**Então** lê "smoke no PVA-EFD: nunca executado" e "golden file: não existe".

- **Âncora:** backlog de `Sped.casos.md` no `main` (5 casos source-grep + smoke PVA ausente).
- **Por que importa:** contadora que assume arquivo validado entrega sem conferir; o preço do engano é multa.

---

## Backlog de casos

- **[BACKLOG · ⬜] Golden file + smoke no PVA-EFD** — o único caso que transforma os 5 source-grep em prova.
- **[BACKLOG · ⬜] Prévia gerada do arquivo real** (hoje é amostra fixa no F1).
- **[BACKLOG · ⬜] Bloco H com inventário real** — exige integração de estoque (declaração de 31/12).
