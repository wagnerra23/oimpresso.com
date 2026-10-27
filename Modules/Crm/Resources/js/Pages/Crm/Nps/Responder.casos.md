---
id: modules-crm-pages-crm-nps-responder-casos
casos: Crm · Resposta do NPS · /nps/{token}
irmaos: Responder.charter.md (lei) · Responder.tsx (tela)
tecnica: Caso de uso = narrativa do cliente final + critério de aceite verificável (Dado/Quando/Então)
por_que: a página é pública. Errar o escopo mostra dado de uma empresa a quem tem o link; errar a gravação deixa a nota ser trocada e distorce o NPS.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Crm · Resposta do NPS (`/nps/{token}`)

> **Âncora:** SPEC Crm US-CRM-097 (critérios 4-5 e 7) e o [charter](Responder.charter.md). Os UCs não derivam do `.tsx`.

---

## UC-NPSR-01 · Link desconhecido ou NPS desligado não abre nada · `must` [T0]

**Dado** a flag `CRM_NPS_ENABLED` desligada, ou um token que não existe · **Quando** o visitante abre
`/nps/{token}` ou envia a nota · **Então** recebe 404 e nada é gravado.

- **Teste:** `Modules/Crm/Tests/Feature/CrmNpsRespostaContratoTest.php` — `UC-NPSR-01 · flag desligada ou token desconhecido dão 404 e não gravam`.

Status: 🧪

---

## UC-NPSR-02 · Abrir o link mostra só o nome da empresa · `must` [T0]

**Dado** uma pesquisa enviada · **Quando** o cliente abre o link · **Então** a tela `Crm/Nps/Responder` recebe
o nome da empresa e `nota = null`, e nenhum dado do cliente, da venda ou da OS.

- **Teste:** mesmo arquivo — `UC-NPSR-02 · abrir o link entrega só o nome da empresa e a nota ainda vazia`.

Status: 🧪

---

## UC-NPSR-03 · Enviar a nota grava uma vez · `must`

**Dado** a página aberta · **Quando** o cliente envia 9 com comentário · **Então** a nota fica gravada e a
página passa a mostrar 9; um segundo envio (0) não troca a nota; nota 11 é recusada.

- **Teste:** mesmo arquivo — `UC-NPSR-03 · enviar grava a primeira nota, recusa fora de 0-10 e não troca depois`.

Status: 🧪

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** No celular (360px) os 11 botões cabem em duas linhas sem rolagem lateral (E2E de viewport).

## Trilha do tempo
- 2026-10-27 · [C] trio nascido com a tela (US-CRM-097).
