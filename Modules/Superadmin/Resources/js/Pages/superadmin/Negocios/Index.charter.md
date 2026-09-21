---
id: modules-superadmin-pages-superadmin-negocios-index-charter
page: /superadmin/business
component: Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/superadmin-page.jsx
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
- Clicar na linha abre o **drawer** (SA-O2b) sem sair da lista: filtro, busca e scroll ficam
  onde estavam, e `esc` fecha. Antes do drawer existir, a linha era inerte de propósito —
  melhor inerte do que abrir vazio.
- Sem emoji. Sentence case. Plural PT-BR correto (1 negócio / 2 negócios).

---

## Contrato visual

Travado por [`governance/design/contracts/superadmin-negocios.contract.json`](../../../../../../../governance/design/contracts/superadmin-negocios.contract.json)
(ADR 0286), verificado no CI por `contrato-de-tela.mjs` — âncora `data-contract` + **copy literal** +
ordem. Fonte da copy: o §3 do F1 [CC] (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`).

| Seção | Copy travada |
|---|---|
| `superadmin.negocios.busca-filtros` | Assinatura · Status do negócio · Última venda |
| `superadmin.negocios.tabela` | Negócio · Dono · Pacote · Assinatura · Status · Cadastro |
| `superadmin.negocios.paginacao` | — (estrutura, sem copy fixa) |
| `superadmin.negocios.drawer` | Assinatura · Uso contra o limite do pacote · Dono e contato · Histórico de assinaturas |

⚠️ **4 das 7 seções do F1**, e ~~duas divergências DECLARADAS~~ **quatro DÍVIDAS A FECHAR**,
registradas em vez de escondidas:

> ⛔ **"DECLARADAS" revogado por [W] em 2026-09-18** (*"eu revogo tudo, de todos. a regra mudou,
> agora é o Protótipo quem manda, e a paridade deve ser o objetivo"*). No eixo FORMA não há
> divergência aceita — há **dívida a fechar**, e a razão de cada item abaixo diz por que ela ainda
> não fechou, nunca que pode ficar ([UI-0029](../../../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md),
> ratificada em 2026-08-31, §41: *"não se abre exceção per-tela"*).
>
> ⚠️ **O texto dizia "duas" e lista QUATRO** — e as quatro não são do mesmo eixo. **BulkBar** e
> **FormDrawer** são **capacidade ausente** (a SA-O3 mediu que `edit`/`update` são andaime
> quebrado: não há comportamento a contratar) — dívida cuja pré-condição é backend, não paridade
> visual. **Tabela** (5 colunas + seleção × 6 sem seleção) e **paginação** (6 × 20) são **FORMA
> pura**, e essas duas o protótipo decide: ou a produção converge, ou o protótipo muda no Cowork
> e desce. A pergunta aberta sobre o `6` ser intenção ou tamanho do mock **segue sendo decisão
> [W]** — mas ela agora é *"qual é o número certo"*, não *"posso divergir"*.

- **BulkBar + seleção múltipla** ficaram de fora — produção não tem, e dependem das ações da SA-O3.
- **FormDrawer novo/editar** ficou de fora — o pré-flight da SA-O3 ([#6011](https://github.com/wagnerra23/oimpresso.com/pull/6011))
  **mediu** que `edit`/`update` são andaime quebrado (view inexistente + método de corpo vazio):
  não há comportamento a contratar.
- **Tabela:** o F1 pede 5 colunas + seleção; produção tem **6 sem seleção**.
- **Paginação:** o F1 pede **6/página**; produção usa **20**. Com 126 negócios reais, 6 vira 21
  páginas — não sei se o 6 é intenção ou tamanho do mock. **Decisão [W] em aberto.**

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

- ~~Drawer de detalhe PT-02~~ e ~~uso contra o teto~~ — **entregues na SA-O2b** (2026-08-19).
- **O valor recorrente NAO entra no drawer** enquanto não houver vínculo no dado: a cobrança
  vive em `rb_subscriptions` → `contacts` (biz=1), sem FK para `business`; casar por nome
  acerta 4 de 109. [W] confirmou que "o resto ainda vai ter que arrumar" — quando o vínculo
  existir, a seção Assinatura ganha o valor e a nota sai.
- Seleção múltipla + BulkBar e ordenação por coluna seguem em aberto.
- Decisão do F1 sobre a grade: o protótipo usa `os-table` do shell, o DS tem `DataTablePro`.
  Esta tela usa tabela simples com tokens do DS — não fecha a porta para nenhum dos dois, e a
  escolha segue sendo de [W].
