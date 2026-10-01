---
id: modules-whatsapp-pages-atendimento-metricas-index-casos
casos: Dashboard de métricas do Atendimento · /atendimento/metricas
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do líder de atendimento + critério de aceite verificável (Dado/Quando/Então)
por_que: custo e volume de um business só podem sair do snapshot daquele business — ler cru ou de outro tenant é o defeito caro.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Métricas do Atendimento

> US-WA-041 (acelera US-WA-021) · charter `Index.charter.md` (draft). UCs derivados do charter
> (Goals, Anti-hooks, UX targets), não do `.tsx`. Teste: `tests/Feature/Whatsapp/AtendimentoAdminContratoTest.php`
> (DB-less, lane sqlite).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa no CI (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-AMET-01 · Custo e volume são só do próprio negócio
- **Persona:** líder de atendimento do business 98.
- **Aceite:** Dado a sessão do business 98 · Quando a tela monta totais, série e breakdown por canal · Então **toda** consulta carrega `business_id = 98`.
- **Teste:** `UC-AMET-01 · totais, série e breakdown filtram o business da sessão`.
- **Regressão que defende:** Anti-hook do charter "mostrar custo de biz alheio" (Tier 0, ADR 0093).
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## UC-AMET-02 · O dashboard lê o snapshot, nunca as mensagens cruas
- **Persona:** líder abre a tela num business com milhões de mensagens.
- **Aceite:** Dado a abertura da tela · Quando as props adiadas resolvem · Então as consultas só tocam `whatsapp_conversation_metricas` e `channels` — nenhuma toca a tabela de mensagens.
- **Teste:** `UC-AMET-02 · lê só o snapshot agregado e os rótulos de canal`.
- **Regressão que defende:** Anti-hook "query real-time agregando `whatsapp_messages` cru — usar snapshot" e o alvo "TTFB via snapshot pré-agregado". Também cobre o Non-Goal LGPD "não mostra conteúdo de mensagens".
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## UC-AMET-03 · Trocar o período é barato e só aceita 7, 30 ou 90 dias
- **Persona:** líder alterna o período.
- **Aceite:** Dado `?range=7` · Então o período é 7 · Dado `?range=45` · Então cai em 30 · E `aggregated`/`breakdown` são props adiadas (o render inicial não consulta).
- **Teste:** `UC-AMET-03 · período whitelisted e props pesadas adiadas`.
- **Regressão que defende:** Goal "filtro período sem reload (partial reload)" e alvo "switch de período ≤ 200ms".
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## Backlog (prosa honesta — sem UC até ganhar teste que o cite)
- [BACKLOG] Período "custom" — o charter lista, o backend só aceita 7/30/90. Divergência registrada no `_saida-04` do playbook; decisão [W].
- [BACKLOG] Tabela top-10 conversas mais longas/caras e botão "ver" → Inbox filtrada — o charter lista, sem teste.
- [BACKLOG] Empty state "Aguardando dados" e CSS de impressão — comportamento de tela.
