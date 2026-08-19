---
id: modules-superadmin-pages-superadmin-negocios-index-charter
page: /superadmin/business
component: Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx
related_prototype: prototipo-ui/cowork/superadmin-page.jsx
owner: wagner
status: draft
last_validated: "2026-08-19"
related_us: [US-SUPER-001]
parent_module: Superadmin
related_adrs: [104, 93]
tier: B
charter_version: 1
---

# Page Charter — /superadmin/business

> **Status:** criado em 2026-08-19 na onda SA-O2 (DataTables → Inertia). Nasce `draft`: o
> `charter-live-signal` exige **sinal de prod**, e a tela ainda não foi ao ar. Vai a `live` no
> PR pós-deploy, com a evidência do smoke.
>
> Os **Non-Goals** e **Anti-hooks** vêm do F1 do Cowork
> (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`), transportados e não inferidos — [W] ratifica.
>
> Backend: `Modules\Superadmin\Http\Controllers\BusinessController@index`, rota
> `Route::resource('/business', …)`. Acesso em 2 camadas (username em
> `config('constants.administrator_usernames')` + permissão Spatie) — ver
> [RUNBOOK-negocios](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-negocios.md).

---

## Mission

Responde **uma** pergunta: *"quem é este cliente e o que ele tem contratado?"*. É a porta de
entrada para atender um chamado — achar o negócio, ver o que ele assinou e em que estado está.
Não é BI e não administra o dado operacional do cliente.

Persona única: [W], escritório, 1440px. Admin de negócio toma 403.

---

## Goals — Features (faz)

O que a tela entrega **hoje**:

- Busca por nome, dono, e-mail e **número do negócio**, com `/` focando o campo e debounce de
  300 ms — sem submit.
- 4 filtros combináveis: pacote · assinatura (vigente/vencida/sem) · status do negócio · última
  venda. Trocar um **preserva** os outros, e todos vivem na query string (sobrevivem a refresh).
- Lista paginada **no servidor**, 20 por página, com total dito em texto.
- Vocabulário PT-BR fechado: negócio, assinatura, pacote. O enum do banco **nunca** aparece.
- Vazio que distingue *"nenhum negócio cadastrado"* de *"nenhum resultado para estes filtros"*,
  citando o termo buscado.

## Non-Goals — Features (NÃO faz)

> Do F1 §Non-goals. Cada item vira Pest GUARD quando [W] ratificar.

- **Sem exclusão em lote de negócios** — só desativação, e ela é da SA-O3.
- **Não edita dado operacional do cliente** (produto, OS, venda) — para isso existe "entrar
  como este negócio", que é decisão D1 em aberto.
- **Não é BI**: nada de série temporal ou agregação aqui; isso é a visão geral.
- **Não faz cobrança** — gateway é `Modules/PaymentGateway`.

---

## UX targets

- Achar um negócio por número tem que ser o caminho mais curto: `/` → digitar → ver.
- Filtro nunca zera outro filtro. Refresh mantém o que estava filtrado.
- Nenhuma linha clicável **enquanto não houver para onde ir** — melhor inerte do que abrir um
  drawer vazio (o drawer é a SA-O2b).
- Sem emoji. Sentence case. Plural PT-BR correto (1 negócio / 2 negócios).

---

## Automation hooks (faz)

- Busca e filtros recarregam só `negocios` + `filtros` (partial reload), sem full page load.
- Props caras entram por `Inertia::defer` com skeleton.

## Anti-hooks (NÃO faz automaticamente)

> Do F1 + medições de 2026-08-19. Cada item vira Pest GUARD quando [W] ratificar.

- **Não aplica escopo de tenant.** O cross-tenant é intencional (ADR 0093 §exceções): esta tela
  existe para ver todos os negócios. Escopar quebraria o produto.
- **Não muda estado de nada.** É leitura. Ativar/desativar/excluir é SA-O3; status de assinatura
  passa pelo `SubscriptionLifecycleService`.
- **Não traz a lista inteira pro browser.** A paginação é server-side; paginar no cliente é a
  dívida que o DataTables tinha e que esta onda pagou.

---

## Pendências antes de `status: live`

1. [W] ratifica Non-Goals e Anti-hooks (transportados do F1).
2. **Sinal de prod**: deploy + smoke real de `/superadmin/business`.

Em aberto (SA-O2b / SA-O3):

- Drawer de detalhe PT-02, seleção múltipla + BulkBar, uso contra o teto do pacote
  (`Progress`), ordenação por coluna.
- Decisão do F1 sobre a grade: o protótipo usa `os-table` do shell, o DS tem `DataTablePro`.
  Esta tela usa tabela simples com tokens do DS — não fecha a porta para nenhum dos dois, e a
  escolha segue sendo de [W].
