---
id: resources-js-pages-repair-status-index-casos
casos: Status de OS · /repair/status
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + criterio de aceite verificavel (Dado/Quando/Entao)
por_que: comportamento e duravel — "a tela e read-only e a cor vem do banco" vale em qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-11"
---

> ℹ️ **`last_run` 2026-09-09 → 2026-09-11 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` no [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) foi **1 linha(s) de COMENTÁRIO** —
> o path do protótipo (`prototipo-ui/cowork/…` → `prototipo-ui/cowork/Wagner/…`, topologia por dono da ADR 0397).
> Zero JSX estrutural, zero handler, zero prop, zero copy alterada (verificado: `git diff origin/main...HEAD -- resources/js/Pages/Repair/Status/Index.tsx`
> só tem linhas iniciadas por `//`, `*` ou `{/*`). **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run — mesmo tratamento do #6913.

# Casos de Uso & Aceite — Status de OS

> Derivados do [Index.charter.md](Index.charter.md) e do `RepairStatusController` — não do `.tsx`
> ([§5 2026-06-05](../../../../../memory/proibicoes.md)).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> Defendidos por [`RepairStatusContratoTest`](../../../../../Modules/Repair/Tests/Feature/RepairStatusContratoTest.php).
> Recibo do run: CT 100, 2026-09-05 — **6 passed, 17 assertions, 0 skipped**.
>
> ⚠️ **Estes casos cumprem uma promessa que estava vazia.** O charter declarava, sob *"Métricas
> vivas (Pest GUARD — completar em F1.5)"*, três testes — `RepairStatusCharterTest::
> it_does_not_mutate_state`, `::it_does_not_emit_emails` e `::it_isolates_by_business_id`. Nenhum
> existia: em 2026-09-05, `git grep RepairStatusCharterTest` devolvia **um** hit, o próprio charter.
> Os três anti-hooks viram UC-RSTIDX-03, 04 e 05, e a seção do charter passou a apontar os nomes reais.

---

## UC-RSTIDX-01 · Ver o fluxo da oficina na ordem em que ele acontece
- **Persona:** [W] configurando as etapas por onde uma OS passa.
- **Aceite:** Dado status cadastrados fora de ordem · Quando abro `/repair/status` · Então a lista
  chega ordenada por `sort_order` crescente, não pela ordem de cadastro.
- **Regressão que defende:** perder o `orderBy` mostra o fluxo embaralhado — "Entrega" antes de
  "Recebido" —, e como toda linha parece correta isoladamente, ninguém percebe que a sequência mente.
- **Teste:** `RepairStatusContratoTest` — *"UC-RSTIDX-01: a lista chega na ordem de exibição configurada, não na ordem de cadastro"*.
- **Status: 🧪**

## UC-RSTIDX-02 · A cor que cadastrei é a cor que aparece
- **Persona:** operador reconhece a etapa pela cor antes de ler o nome.
- **Aceite:** Dado um status com cor `#A1B2C3` · Quando abro a tela · Então o valor chega
  **idêntico**, sem normalização de caixa nem de formato.
- **Por que:** esta cor é **dado do tenant**, não decisão de design — a tela a aplica direto no
  swatch. Trocá-la por um token do Design System apagaria a identidade que o cliente cadastrou;
  normalizá-la ("#a1b2c3") quebraria comparação com o que ele digitou.
- **Teste:** `RepairStatusContratoTest` — *"UC-RSTIDX-02: a cor de cada status chega à tela exatamente como está no banco"*.
- **Status: 🧪**

## UC-RSTIDX-03 · Status de outra empresa não aparece (Tier 0)
- **Persona:** qualquer operador — o isolamento não depende de quem olha.
- **Aceite:** Dado um status meu e um de outra empresa · Quando abro a tela · Então vejo o meu e
  **não** vejo o alheio.
- **Regressão que defende:** perder o escopo por `business_id`
  ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0 irrevogável).
  Cumpre o anti-hook *"não acessa status de outro `business_id`"* do charter.
- **Controle positivo embutido:** o aceite exige que o **meu** status apareça — lista vazia reprova.
- **Teste:** `RepairStatusContratoTest` — *"UC-RSTIDX-03: status de outro tenant não aparece na lista (Tier 0 · ADR 0093)"*.
- **Status: 🧪**

## UC-RSTIDX-04 · Abrir a tela não muda nada
- **Persona:** ninguém — este caso existe porque a falha seria **invisível**.
- **Aceite:** Dado o catálogo de status como está · Quando abro a tela · Então a tabela
  `repair_statuses` fica **byte a byte igual** ao que era.
- **Regressão que defende:** um `updated_at` tocado por acidente (observer, `touch`, save defensivo)
  contamina o audit trail do Spatie ActivityLog, que existe para registrar mudança **de configuração**.
  Cumpre o anti-hook *"não escreve no banco / read-only puro"* do charter.
- **Teste:** `RepairStatusContratoTest` — *"UC-RSTIDX-04: abrir a tela não escreve nada no catálogo (read-only puro)"*.
- **Status: 🧪**

## UC-RSTIDX-05 · Abrir a tela não avisa cliente nenhum
- **Persona:** o cliente da oficina, que não deve receber "sua OS mudou" porque alguém abriu uma tela
  de configuração.
- **Aceite:** Dado status com `sms_template`, `email_subject` e `email_body` preenchidos · Quando
  abro a tela · Então **nenhum** e-mail e **nenhuma** notificação são disparados.
- **Regressão que defende:** esta é a tela onde se **configuram** os avisos de mudança de etapa;
  confundir listar com disparar mandaria mensagem a cliente real. Cumpre o anti-hook *"não dispara
  nada ao abrir"* do charter.
- **Teste:** `RepairStatusContratoTest` — *"UC-RSTIDX-05: abrir a tela não dispara e-mail nem notificação"*.
- **Status: 🧪**

## UC-RSTIDX-06 · Sem a permissão certa, a tela não abre
- **Persona:** operador de balcão que não deve reconfigurar o fluxo da oficina.
- **Aceite:** Dado um usuário sem `superadmin` · Quando acesso `/repair/status` · Então **403**.
- **Contraste medido (2026-09-05), e ele é contrato, não acaso:** as duas telas deste mesmo hub de
  configuração têm portas de entrada **diferentes**. O catálogo de modelos exige `superadmin` **ou**
  a assinatura do `repair_module` — e o tenant de teste **tem** a assinatura, então um usuário comum
  entra. Já o Status exige `superadmin` **ou** (assinatura **e** `repair_status.access`); como a
  permission `repair_status.access` não existe na base, o segundo ramo nunca fecha e só superadmin
  passa. Quem for uniformizar os dois gates precisa decidir **qual** dos dois é o certo — não
  presumir que já são iguais.
- **Teste:** `RepairStatusContratoTest` — *"UC-RSTIDX-06: sem a permissão de status a tela responde 403, mesmo com o módulo assinado"*.
- **Status: 🧪**

---

## UC-RSTIDX-07 · Vejo qual aviso o cliente recebe, sem abrir o formulário
- **Persona:** quem configura o fluxo e precisa conferir o texto que sai pro cliente.
- **Aceite:** Dado um status com modelo de SMS gravado · Quando abro `/repair/status` · Então
  vejo o texto na linha; e um status **sem** modelo mostra "sem modelo de SMS", não um campo vazio.
- **Por que existe:** a coluna `sms_template` existe desde 2020 (`2020_08_22_104640`) e nunca chegou
  à tela — o operador só descobria o texto entrando no formulário de edição, um por um. O protótipo
  (`repair-page.jsx` região `Status`) mostra na linha.
- **Atenção ao caso vazio:** o teste cobre com **e sem** template de propósito. Sem os dois casos o
  assert não distingue "veio null" de "a coluna não veio no payload".
- **Teste:** `RepairStatusContratoTest` — *"UC-RSTIDX-07: o modelo de SMS de cada status chega à tela como está no banco"*.
- **Status: 🧪**

---

## UC-RSTIDX-08 · Sei quantas folhas cada status carrega antes de mexer nele
- **Persona:** quem vai excluir ou renomear um status e não sabe o estrago.
- **Aceite:** Dado um status usado por 2 folhas do meu negócio e outro sem nenhuma · Quando abro
  `/repair/status` · Então vejo `2 folha(s)` e `0 folha(s)`, e a contagem **ignora** as folhas de
  outra empresa.
- **Por que é Tier 0, e não cosmético:** `status_id` é FK em `repair_job_sheets` sem
  `ON DELETE SET NULL`. A tela avisa que apagar status usado deixa folha órfã — se a agregada
  esquecesse o `business_id`, esse aviso **mentiria** sobre quantas folhas ficariam órfãs, e a
  decisão de apagar seria tomada em cima de um número da oficina do vizinho.
- **Teste:** `RepairStatusContratoTest` — *"UC-RSTIDX-08: a contagem de folhas por status conta só as do próprio tenant (Tier 0 · ADR 0093)"*, com uma folha do vizinho pendurada num status do vizinho.
- **Status: 🧪**

---

## Rastreabilidade

| UC | Defendido por |
|---|---|
| 01, 02, 03, 04, 05, 06, 07, 08 | `Modules/Repair/Tests/Feature/RepairStatusContratoTest.php` |

Os testes rodam no CT 100, nunca local ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)),
no tenant fictício 98 ([ADR 0358](../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

## O que este contrato NÃO cobre

A tela linka para `/repair/status/create` e `/repair/status/{id}/edit`, que **não** são telas Inertia
— `create()` devolve Blade e `edit()` só responde a requisição ajax. Cobrir o destino desses botões
exige tocar `Status/Index.tsx`, e **não existe `RUNBOOK-status.md`**: a F1 do processo MWART teria de
vir antes ([ADR 0104](../../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)).
Fica declarado como lacuna conhecida, em vez de virar um UC sem teste que o defenda.
