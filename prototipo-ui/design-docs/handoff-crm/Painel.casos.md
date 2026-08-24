---
id: resources-js-pages-crm-painel-casos
casos: Painel do CRM · /crm/dashboard
irmaos: Painel.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o painel é o primeiro número que o vendedor olha no dia — se ele mentir, ninguém confia no módulo
owner: wagner
last_run: "—"
---

# Casos de Uso & Aceite — Painel do CRM

> **Status:** ⬜ não verificado em todos os UC — nenhum teste cita estes ids ainda (regra G-2: UC sem teste é órfão declarado, não coberto). Nada aqui está 🧪 ou ✅.

---

## UC-CRMP-01 · O "meus acompanhamentos de hoje" conta só os meus e só hoje
- **Persona:** Larissa — abre o CRM às 8h e quer saber quantos contatos ela tem que fazer hoje.
- **Aceite:** Dado acompanhamentos de vários usuários em várias datas · Quando o painel apura "acompanhamentos de hoje" · Então conta só os atribuídos ao usuário logado com `start_datetime` no dia corrente (timezone do negócio), e um acompanhamento de ontem não entra.
- **Teste:** ⬜ a escrever.

## UC-CRMP-02 · A conversão por fonte é a razão certa, não a contagem crua
- **Persona:** Wagner — decide onde gastar em marketing pela conversão por fonte.
- **Aceite:** Dado N contatos e M clientes vindos de uma fonte · Quando o painel calcula a conversão · Então mostra `clientes / contatos` daquela fonte (0% quando não há contatos, sem divisão por zero) — a mesma razão do Blade.
- **Teste:** ⬜ a escrever.

## UC-CRMP-03 · Aniversários não disparam nada sozinhos
- **Persona:** Larissa — quer mandar parabéns, mas revisando o texto.
- **Aceite:** Dado contatos aniversariantes marcados · Quando clico em "enviar desejos" · Então abro o formulário de campanha já com os contatos selecionados, e **nenhuma** mensagem sai antes do envio explícito; sem seleção, o clique avisa e não navega.
- **Teste:** ⬜ a escrever.

## UC-CRMP-04 · O painel de outro tenant nunca soma no meu
- **Persona:** operador de qualquer negócio (Tier 0, ADR 0093).
- **Aceite:** Dado leads/acompanhamentos em outro `business_id` · Quando apuro qualquer número do painel · Então o registro estrangeiro nunca entra em nenhum dos blocos.
- **Teste:** ⬜ a escrever.

## UC-CRMP-05 · Sem permissão, o bloco não existe (não é só invisível)
- **Persona:** Eliana (financeiro) — não tem `crm.access_all_leads`.
- **Aceite:** Dado um usuário sem a permissão do bloco · Quando o painel renderiza · Então o bloco não vem no payload (não é `display:none`), e a tela segue utilizável sem buraco de layout.
- **Teste:** ⬜ a escrever.

## Backlog de casos

- **[BACKLOG]** Chamadas escondidas quando `enable_crm_call_log` está off — exige prop de flag no payload.
- **[BACKLOG]** Reapuração no header não refaz a query duas vezes em clique duplo (debounce).

## Rastreabilidade (UC → CU do SDD → US do SPEC)

| UC | CU (SDD) | US (SPEC) |
|---|---|---|
| UC-CRMP-01 … 05 | — (SDD do Crm não existe) | — |

> Colunas vazias de propósito: o módulo Crm não tem SDD nem US no SPEC — o legado é UltimatePOS e nunca passou pelo protocolo. Registro honesto, não lacuna a preencher com número inventado.
