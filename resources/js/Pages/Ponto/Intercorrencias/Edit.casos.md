---
id: resources-js-pages-ponto-intercorrencias-edit-casos
casos: Editar rascunho de intercorrência · /ponto/intercorrencias/{id}/edit
irmaos: Edit.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F4 + §5.4 item 1 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: era a última tela Blade viva do módulo — e a que decide o que ainda pode ser reescrito.
owner: wagner
last_run: "2026-08-28"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Editar rascunho de intercorrência

> **Âncora:** `CU-PONTO-05` (§6.2, *"a intercorrência nasce RASCUNHO (…) e só rascunho é
> editável"*) e o fluxo **F4** (§5.3) do
> [SDD](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md).
> Fonte de paridade: `Modules/Ponto/Resources/views/intercorrencias/_form.blade.php` +
> `IntercorrenciaRequest` (a validação que o submit encontra).
>
> 📌 **Por que esta tela ganhou trio agora.** Ela nasceu React em 2026-08-28, migrada da
> última Blade viva do módulo (SDD §5.4 item 1: 21 renders = 20 Inertia + **1** Blade).
> Tela nova nasce com o trio — é a regra forward-only; os `casos.md` que faltam nas telas
> **antigas** do módulo seguem fora de escopo por decisão registrada (atacar antes os UC
> órfãos).
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: fica vermelha
> visível, não bloqueia merge (SDD §8.1).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-INTEDT-01 | Só rascunho é editável | must | `CU-PONTO-05` + F4 | `IntercorrenciaEditContratoTest` | 🧪 sem veredito |
| UC-INTEDT-02 | O form abre com os valores atuais do rascunho | must | paridade `_form.blade.php` | `IntercorrenciaEditContratoTest` | 🧪 sem veredito |
| UC-INTEDT-03 | Rascunho de outro empregador não abre | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `IntercorrenciaEditContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` **Anexo (PDF/JPG/PNG, máx 5MB)** — existe no `_form.blade.php` legado e no
  `IntercorrenciaRequest`, mas **não foi migrado**: o `Create.tsx` também não o oferece hoje,
  então migrá-lo aqui criaria uma assimetria entre criar e editar. Não vira UC agora porque
  não há código que o exerça — UC sem teste é órfão, e o `casos-gate` G-2 pune.
- `[BACKLOG]` A Blade `intercorrencias/edit.blade.php` virou fóssil (a 26ª do módulo) e
  **segue no repo de propósito**, como contrato de paridade desta migração. Limpar os
  fósseis é escopo próprio, não carona.

---

## UC-INTEDT-01 · Só rascunho é editável · `must`

- **Persona:** o colaborador (ou o DP por ele) que registrou uma ocorrência e quer corrigir
  um detalhe antes de mandar pro RH.
- **Aceite:** Dado uma intercorrência que **não** está em `RASCUNHO` · Quando tento abrir a
  edição dela · Então recebo **403** — nunca o formulário.
- **Teste:** `Modules/Ponto/Tests/Feature/IntercorrenciaEditContratoTest.php` — `UC-INTEDT-01`.
- **Contrato:** `CU-PONTO-05` (SDD §6.2) · `IntercorrenciaController@edit` (`abort_unless`).
- **Regressão que defende:** o ciclo `RASCUNHO → PENDENTE → APROVADA|REJEITADA → APLICADA`
  só significa alguma coisa se o passado parar de ser reescrevível. Uma intercorrência
  **aprovada** que volta a ser editável transforma a trilha de aprovação em ficção: o RH
  aprovou um texto e o registro guarda outro. O `abort_unless` é a única coisa entre as duas
  situações, e ele atravessou a migração de Blade para React sem mudar — este UC é o que
  garante que ele continue lá.
- **Nota de escrita:** o assert crava **403**, e não "não é sucesso". Aqui o código importa:
  403 é "existe, mas você não pode", que é exatamente a verdade — 404 esconderia um registro
  do próprio tenant e mandaria o operador procurar um bug que não existe.
- **Status: 🧪 sem veredito.**

---

## UC-INTEDT-02 · O form abre com os valores atuais do rascunho · `must`

- **Persona:** a mesma. Ela abriu para corrigir **um** campo — não para redigitar tudo.
- **Aceite:** Dado um rascunho meu · Quando abro a edição · Então os campos vêm preenchidos
  com o que está gravado (colaborador, tipo, data, justificativa, prioridade e flags).
- **Teste:** `IntercorrenciaEditContratoTest.php` — `UC-INTEDT-02`.
- **Contrato:** paridade com `_form.blade.php` (a Blade legada preenchia os mesmos campos) ·
  `IntercorrenciaRequest` (o vocabulário que o submit aceita).
- **Regressão que defende:** o modo de falha desta migração é **silencioso**: o backend
  monta um payload com chave que o `.tsx` não lê, ou o `.tsx` lê chave que o backend não
  manda, e o form abre **vazio**. O operador então "corrige um campo" e salva um registro
  esvaziado por cima do rascunho dele. Este módulo **já sofreu exatamente isso** duas vezes
  (US-PONTO-012: `entrada`/`saida` da escala e `linhas_criadas` da importação, ambos lidos
  de atributo inexistente, ambos exibindo vazio/zero em silêncio).
- **Nota de escrita:** o caso confere que o payload chega **com os valores**, não que a
  chave existe — chave presente com `null` é justamente o defeito que ele persegue.
- **Status: 🧪 sem veredito.**

---

## UC-INTEDT-03 · Rascunho de outro empregador não abre · `must` `[T0]`

- **Persona:** adversário — um id de intercorrência alheia na URL.
- **Aceite:** Dado um rascunho de **outro** empregador · Quando tento abrir a edição ·
  Então **não** recebo o formulário nem os dados dele.
- **Teste:** `IntercorrenciaEditContratoTest.php` — `UC-INTEDT-03`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) · LGPD Art. 7º.
- **Regressão que defende:** o `edit()` usa `findOrFail($id)` **sem** `where('business_id')`
  — a defesa é o global scope `HasBusinessScope` na entity. Funciona, e é **defesa única**:
  se o trait sair da `Intercorrencia`, esta tela passa a abrir rascunho de outro empregador
  com todos os campos preenchidos. O SDD registra esse mesmo padrão no F5 (aprovações) e
  marca o CU como `[T0]` por isso.
- **Nota de escrita:** o assert é *"não devolve o formulário"* e não crava 404 — com o
  global scope ativo o registro simplesmente não existe para este tenant, e um `findOrFail`
  nessas condições responde 404; mas se um dia a defesa virar um `abort(403)` explícito, a
  proteção continua correta e o caso não pode reprovar por isso.
- **Status: 🧪 sem veredito.**
