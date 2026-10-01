---
id: modules-superadmin-pages-superadmin-usuario360-show-casos
casos: Superadmin · Usuário 360 · raio-X de um usuário · /superadmin/usuarios/{id}/360
irmaos: Show.charter.md (lei) · Show.tsx (tela) · Index.casos.md (a busca que chega aqui)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela de investigação de acesso — trancar um funcionário revoga os tokens dele, e destrancar NÃO pode devolvê-los. Sem casos, um "conserto" de conveniência no unlock restaura credencial de quem foi trancado por desvio, e ninguém percebe porque a tela continua bonita.
owner: wagner
last_run: "2026-09-30"
last_run_ci: "_pendente_ — o trio nasce na thread Superadmin/01. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪, nunca ✅."
---

# Casos de Uso & Aceite — Superadmin · Usuário 360 · raio-X (`/superadmin/usuarios/{id}/360`)

> **Âncora:** [Show.charter.md](Show.charter.md) (Mission, Goals, Non-Goals, Anti-hooks) +
> [SPEC US-SUPER-010](../../../../../../../memory/requisitos/Superadmin/SPEC.md) ("cross-tenant
> explícito") + a exceção Superadmin da
> [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
> Os UCs derivam do **charter e do SPEC**, nunca do `Show.tsx` nem do controller.
>
> Teste: `Modules/Superadmin/Tests/Feature/Usuario360ContratoTest.php` (lane `verticais-pest`, MySQL).

---

## UC-SAUX-01 · O raio-X responde Inertia com os blocos 360 · `must`

**Dado** que sou superadmin autenticado
**Quando** abro `/superadmin/usuarios/{id}/360` de um usuário existente
**Então** recebo Inertia com o componente `superadmin/Usuario360/Show` e os blocos que o charter
consolida — identidade (`user`), `roles`, `permissions`, `scopes_ads`, `tokens_mcp`,
`quotas_copiloto`, `sessions_ativas`, `auditoria`, `lockouts` — mais `tabelas_ausentes`, o sinal
da degradação graciosa (Goal *"nunca quebra"*).

Status: 🧪

---

## UC-SAUX-02 · Admin de negócio é barrado ENQUANTO o superadmin passa · `must` `[T0]`

**Dado** um admin de negócio
**Quando** acessa o raio-X de um usuário
**Então** é barrado — **e** o superadmin, no mesmo cenário, recebe 200.

Status: 🧪

---

## UC-SAUX-03 · O superadmin abre o raio-X de usuário de OUTRO business · `must` `[T0]`

**Dado** um usuário de um `business_id` diferente do do superadmin logado
**Quando** o superadmin abre o raio-X dele
**Então** a tela responde 200 e `user.business_id` é o business **do usuário investigado**, não o
da sessão.

> Cross-tenant é o propósito da tela (SPEC: *"cross-tenant explícito"*; charter: *"não vaza dados
> cross-tenant sem o superadmin pedir"* — aqui ele pediu). Este caso impede o "conserto" por
> escopo de tenant.

Status: 🧪

---

## UC-SAUX-04 · Trancar sem motivo não tranca · `must`

**Dado** um usuário ativo
**Quando** o superadmin pede para trancá-lo **sem motivo**
**Então** recebe erro no campo `reason`, o usuário **continua ativo** e **nenhum** registro de
trancamento é criado.

> Charter, Goals: *"Trancar (com motivo + snapshot)"*; UX: *"Lock … com motivo obrigatório"*.
> O caso olha o **efeito**, não o status HTTP — recusa e sucesso devolvem os dois um `302`.

Status: 🧪

---

## UC-SAUX-05 · Destrancar reativa o usuário e NÃO devolve os tokens MCP · `must` `[T0]`

**Dado** um usuário com um token MCP ativo
**Quando** o superadmin o tranca com motivo
**Então** o usuário fica inativo, o trancamento fica registrado e o token é revogado.

**E quando** o superadmin o destranca
**Então** o usuário volta a ativo, o trancamento é encerrado — e o token **continua revogado**.

> Charter, Goals: *"Destrancar (reativa status, NÃO restaura tokens)"*; Non-Goals: *"Não restaura
> tokens MCP no unlock (segurança — Wagner gera novo manual)"*.

Status: 🧪

---

## Ainda sem teste (prosa honesta — viram UC quando ganharem teste que os cite)

- [BACKLOG] **Log de acesso do superadmin ao raio-X** — é aceite da US-SUPER-010 (*"logs de acesso em `audit_log` (LGPD Art. 7º)"*) e **não está implementado** (o SPEC marca `_parcial_` por isso). Um UC aqui nasceria vermelho; a implementação é decisão de escopo, não desta thread.
- [BACKLOG] Unlock confirmado por AlertDialog DS, nunca `window.confirm` (UX targets) — front, pede E2E.
- [BACKLOG] Navegação por Tabs entre os blocos e risco por Badge semântico (Goals) — idem.
- [BACKLOG] Sem polling / auto-refresh (Anti-hooks) — idem.

## Testes mínimos

- DQE: 1 superadmin no tenant fictício 98 · 1 usuário-alvo em outro business, ativo, com 1 token MCP ativo.
- Borda: trancar sem motivo; ciclo trancar → destrancar.
- Permissão: admin de negócio barrado; superadmin 200.
