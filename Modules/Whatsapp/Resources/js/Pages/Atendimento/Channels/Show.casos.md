---
id: modules-whatsapp-resources-js-pages-atendimento-channels-show-casos
casos: Detalhe do canal — Config, Usuários e Histórico · /atendimento/canais/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: é onde se decide quem atende cada canal (ACL canal=fila, US-WA-068/069) — um acesso errado aqui vira atendente lendo a fila de outro departamento.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Detalhe do canal

> Fonte: `Show.charter.md` (Goals · Non-Goals · Anti-hooks) · SPEC `US-WA-068` · ADR 0093.
> **Não derivados do `.tsx`** (lápide §5 2026-06-05).
>
> **Teste:** `tests/Feature/Whatsapp/AtendimentoChannelsContratoTest.php` — lane sqlite.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) ·
> ⬜ não verificado · ❌ quebrou.

---

## UC-CNLD-01 · Abro o detalhe de um canal meu
- **Persona:** gestor diagnosticando um canal.
- **Aceite:** Dado um canal do meu business · Quando abro o detalhe · Então vejo a configuração dele já na abertura, e as abas Usuários e Histórico carregam sob demanda.
- **Teste:** `UC-CNLD-01 · abre o detalhe do canal do meu business com a configuração`.
- **Regressão que defende:** charter Goals (aba Config) + Automation hooks (`users`/`availableUsers`/`audit` via `Inertia::defer`).
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031, `PHP / Pest (Unit)`, 2026-10-01); ✅ quando `casos:results` regravar o manifesto.

---

## UC-CNLD-02 · Canal de outra empresa não abre
- **Persona:** gestor do business 98 com um id de canal do business 99.
- **Aceite:** Dado um canal de outro business · Quando abro `/atendimento/canais/{id}` · Então recebo 404, antes de qualquer dado ser montado.
- **Teste:** `UC-CNLD-02 · canal de outro business devolve 404 antes de renderizar`.
- **Regressão que defende:** charter Non-Goal *"Não expõe canais de outro `business_id`"* · Tier 0.
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031); ✅ com o manifesto regravado.

---

## UC-CNLD-03 · A aba Usuários mostra só quem atende hoje
- **Persona:** gestor conferindo quem tem acesso ao canal.
- **Aceite:** Dado um usuário com acesso ativo e outro com acesso revogado · Quando abro a aba Usuários · Então só o ativo aparece, com o nome.
- **Teste:** `UC-CNLD-03 · aba Usuários mostra só quem tem acesso ativo`.
- **Regressão que defende:** charter Goal *"lista quem tem acesso ativo ao canal"* · US-WA-068.
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031); ✅ com o manifesto regravado.

---

## UC-CNLD-04 · Revogar não apaga o histórico
- **Persona:** gestor tirando um atendente do canal.
- **Aceite:** Dado um acesso ativo · Quando revogo · Então o registro continua existindo, e o Histórico o mostra como revogado, com a data.
- **Teste:** `UC-CNLD-04 · revogar preserva o registro e o Histórico mostra o acesso como revogado`.
- **Regressão que defende:** charter Goal *"tabela append-style de grants/revokes"* · SPEC US-WA-068 *"Remove user: soft remove"*.
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031); ✅ com o manifesto regravado.

---

## UC-CNLD-05 · Conceder de novo não duplica o acesso
- **Persona:** gestor clicando duas vezes em conceder.
- **Aceite:** Dado um usuário com acesso ativo · Quando concedo acesso a ele de novo · Então nada muda: continua um único acesso ativo.
- **Teste:** `UC-CNLD-05 · conceder acesso a quem já tem acesso ativo não cria duplicata`.
- **Regressão que defende:** charter Anti-hook *"Não concede/revoga acesso automaticamente — sempre ação manual"* + integridade do ACL canal=fila (US-WA-069).
- **Status: 🧪** — passou na lane sqlite do PR #8327 (run 36801547031); ✅ com o manifesto regravado.

---

## Backlog (prosa, sem UC — não há teste que os cite)

- [BACKLOG] Re-parear (`connect` + poll de `status` a cada 3s) depende do daemon no CT 100 — sem teste de contrato nesta onda.
- [BACKLOG] Lista de usuários disponíveis pra conceder (`availableUsers`) filtra por permissão Spatie (`whatsapp.access`/`whatsapp.send`); o schema sintético da lane sqlite não tem as tabelas de permissão.
- [BACKLOG] SPEC US-WA-068 pede *"AuditLog write em grant/revoke"*; o controller grava só `Log::info`. Não verificado se isso atende o aceite — decisão [W].
