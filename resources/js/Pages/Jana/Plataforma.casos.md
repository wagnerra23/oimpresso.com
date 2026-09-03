---
casos: Jana/Plataforma — /ia/superadmin/metas (F3 do MWART)
irmaos: Plataforma.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-08-31"
---

# Casos de Uso & Aceite — Jana/Plataforma

> Nascido de `criar-tela.mjs` e preenchido na F3. **Status:** ✅ passa · 🧪 teste cita o UC e
> passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> ⚠️ **A persona desta tela NÃO é a Larissa.** Ela é dona do `business_id=4` (ROTA LIVRE) e
> **leva 403 aqui** — é exatamente o que o [#6421](https://github.com/wagnerra23/oimpresso.com/pull/6421)
> consertou. O gerador carimba "Larissa" por default no UC-00; trocar não é preferência de
> redação, é impedir que um caso futuro seja escrito assumindo que ela alcança.
> A persona é **quem administra a plataforma** — medido em produção (31/08/2026): 5 usuários
> do `business_id=1`, todos pelo papel `Operacional#1`
> ([`RUNBOOK-plataforma.md` §1.1](../../../../memory/requisitos/Jana/RUNBOOK-plataforma.md)).

---

## UC-PLATAF-00 · Chego na tela pela aba, sem digitar URL
- **Persona:** quem administra a plataforma — abre a área da Jana e encontra a aba.
- **Aceite:** Dado usuário que passa nas 2 portas do gate · Quando abre `/ia` · Então a aba
  **Plataforma** existe na faixa e leva a `/ia/superadmin/metas` (200, sem digitar URL).
- **Regressão que defende:** a tela responder 200 e ninguém alcançar. Antes desta F3 o ghost
  **não podia existir**: controller devolvendo Blade faz o `<Link>` do `PageHeaderTabs`
  silenciar (click no-op) — é a razão literal que tirou o ghost `metas` do `DataController`.
- **Teste:** o **predicado** do ghost é provado por `UC-PLATAF-06` (mesmo helper que a rota usa).
- **Status: 🧪** — ⚠️ **limite declarado:** o teste prova que o ghost e a rota fazem a MESMA
  pergunta; ele **não** renderiza o menu. Que o `<Link>` de fato navega é o smoke real (R1).

---

## UC-PLATAF-01 · A rota entrega a tela Inertia, não a Blade AdminLTE
- **Persona:** quem administra a plataforma — quer a tela dentro do app, não uma página solta.
- **Aceite:** Dado usuário com a permissão real · Quando faz GET em `/ia/superadmin/metas` ·
  Então a resposta é o componente `Jana/Plataforma` **e** as duas listas vêm como props
  **deferidas** (`deferredProps`).
- **Regressão que defende:** alguém tirar o `Inertia::defer` e o `<Deferred fallback>` do `.tsx`
  virar enfeite — estado de carregando decorativo, que não corresponde a nada.
- **Teste:** `Modules/Jana/Tests/Feature/SuperadminPlataformaContratoTest.php` — `UC-PLATAF-01`.
- **Status: 🧪**

## UC-PLATAF-02 · Vejo as metas da plataforma (`business_id NULL`)
- **Persona:** quem administra a plataforma — precisa ver as metas que não são de nenhum cliente.
- **Aceite:** Dado uma meta com `business_id NULL` · Quando carrego a lista · Então ela aparece
  com **Nome · Unidade · Origem** (as 3 colunas do contrato da Blade).
- **Regressão que defende:** a migração reescrever as colunas em silêncio. Tem **pré-condição
  anti-vácuo**: a meta recém-criada precisa aparecer, senão lista vazia passaria como contrato ok.
- **Teste:** `SuperadminPlataformaContratoTest.php` — `UC-PLATAF-02`.
- **Status: 🧪**

## UC-PLATAF-03 · Vejo as metas de clientes, cross-business, com período e apuração
- **Persona:** quem administra a plataforma — quer saber quais clientes configuraram metas e se
  elas estão sendo apuradas.
- **Aceite:** Dado uma meta do tenant **99** com período atual e uma apuração · Quando carrego a
  lista logado no tenant **98** · Então a meta do 99 aparece, com `business_id`, `periodo_atual`
  e `ultima_apuracao` em ISO (`Y-m-d`).
- **Regressão que defende:** duas de uma vez — (a) o `withoutGlobalScope` sumir e a visão
  cross-business morrer; (b) alguém formatar a data no backend com `format_date`, que carrega o
  shift +3h preservado pra clientes legados ([ADR 0066](../../../../memory/decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md))
  e viraria data errada numa tela de auditoria.
- **Teste:** `SuperadminPlataformaContratoTest.php` — `UC-PLATAF-03`.
- **Status: 🧪**

## UC-PLATAF-04 · Meta nunca apurada chega marcada como tal
- **Persona:** quem administra a plataforma — precisa distinguir "meta sem movimento" de "meta
  que está sendo medida".
- **Aceite:** Dado uma meta sem nenhuma apuração · Quando carrego a lista · Então
  `ultima_apuracao` vem **nula** (é o que a tela usa para escrever *"nunca apurada"* e esmaecer
  a linha — o `state: "archived"` da fonte de design).
- **Regressão que defende:** o payload passar a mandar string vazia ou `'—'` já formatado, o que
  faria a tela perder a distinção sem nenhum teste cair.
- **Teste:** `SuperadminPlataformaContratoTest.php` — `UC-PLATAF-04`.
- **Status: 🧪**

## UC-PLATAF-05 · A tela NÃO inventa total de plataforma
- **Persona:** quem administra a plataforma — um número somado errado aqui vira decisão errada.
- **Aceite:** Dado qualquer conjunto de metas · Quando carrego a tela · Então o payload **não**
  tem chave agregada (`totais`/`total`/`agregado`/`resumo`/`kpis`).
- **Regressão que defende:** a promessa voltar por um `sum()` de conveniência. A agregação
  cross-business que o docblock antigo prometia **não existe** (medido em 27/08 e re-medido em
  31/08/2026), e a fonte de design toma a mesma posição na própria tela: *"Somar aqui na tela
  seria inventar total de plataforma no cliente"*. Os contadores de cabeçalho são contagem **do
  que está listado**. Ver `RUNBOOK-plataforma.md` §6.1 e o Non-Goal do charter.
- **Teste:** `SuperadminPlataformaContratoTest.php` — `UC-PLATAF-05`.
- **Status: 🧪**

## UC-PLATAF-06 · Dono de negócio não vê a aba **e** não entra pela URL
- **Persona:** dono de um negócio qualquer (o papel `Admin#{business_id}`) — **não** é público
  desta tela.
- **Aceite:** Dado um dono de negócio **sem** a permissão real · Quando o menu é montado · Então
  o predicado do ghost devolve `false`; e Quando ele abre a URL direto · Então recebe **403**.
  E o inverso: com a permissão real, os dois viram `true` **juntos**.
- **Regressão que defende:** o ghost usar `can('jana.superadmin')` — o predicado do dropdown
  legacy —, que o `Gate::before` torna `true` para **todo** dono de negócio. A aba apareceria e
  daria 403 ao clicar: aba visível que não abre é pior que aba ausente.
- **CONTROLE POSITIVO (o que torna este caso honesto):** o teste asserta antes que
  `can('jana.superadmin')` **é `true`** para o dono. Sem isso, o `false` do predicado poderia vir
  de o usuário não ter permissão nenhuma, e o caso mediria a ausência do bypass em vez da
  presença da defesa.
- **Teste:** `SuperadminPlataformaContratoTest.php` — `UC-PLATAF-06`.
- **Status: 🧪**

## UC-PLATAF-07 · Sem meta nenhuma, a tela diz exatamente o que dizia antes
- **Persona:** quem administra a plataforma — hoje, em produção, **é este o caso comum**:
  `jana_metas` tem 0 linhas (medido em 31/08/2026), então a tela abre vazia.
- **Aceite (duas pernas, dois donos):**
  - **Forma** — Dado qualquer estado · Quando carrego a tela · Então as duas props chegam como
    **lista**, nunca `null`. É isso que faz a tela cair no `EmptyState` em vez de quebrar no
    `.map`. **Teste:** `SuperadminPlataformaContratoTest.php` — `UC-PLATAF-07`.
  - **Copy** — Então leio, literal, **"Nenhuma meta da plataforma cadastrada."** e
    **"Nenhum cliente configurou metas ainda."** As duas strings estão pinadas em
    `prototipo-ui/contrato/jana-plataforma.contract.json` (seções `metas-plataforma` e
    `metas-clientes`), cobradas pelo gate `contrato-de-tela`. **Mordida provada** em 31/08:
    mutar a copy no contrato → `exit 1` nomeando a seção; restaurar → `exit 0`.
- **Regressão que defende:** (a) o payload virar `null` — um `->values()` removido, um
  early-return — e a tela mostrar **erro** no lugar do vazio, na tela que em produção **só**
  mostra o vazio; (b) a migração reescrever a copy da Blade em silêncio.
- **Status: 🧪** — ⚠️ **limite declarado:** o gate de copy prova que a string **existe no
  `.tsx`**, não que ela **renderiza** no estado vazio. Quem fecha isso é o smoke real (R1), e é
  por isso que a DoD do RUNBOOK exige as duas copies conferidas **literais** em produção, não
  "a tela abriu".

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** A aba **Plataforma** acende como ativa quando estou em `/ia/superadmin/metas`
  (hoje o `activeGhostKey` é passado pela Page; ninguém exercita o realce).
- **[BACKLOG]** Ordenação das duas listas — o controller não ordena, e `ativo=false` **não é
  filtrado** (meta desativada continua listada). É o comportamento herdado da Blade; mudá-lo é
  decisão [W], não conserto de passagem (`RUNBOOK-plataforma.md` §6 item 3).
- **[BACKLOG]** O título da 1ª seção mostra jargão de banco ao usuário —
  **"Metas da plataforma (business_id NULL)"**. É a copy **literal da Blade**, preservada de
  propósito nesta migração (contrato do §3 do RUNBOOK), então **não** foi "consertada" aqui.
  ⚠️ Registro do limite honesto: o lint `ds/no-db-jargon-in-ui` **não** morde nela porque só
  casa `JSXText`, e aqui a string vive numa **prop** — ou seja, ela passa por fronteira do
  detector, não por estar certa. Trocar para linguagem de negócio (ex.: *"Metas da plataforma
  (não pertencem a nenhum cliente)"*) é **decisão [W]**: mexe em copy de contrato e nas duas
  strings pinadas no `.contract.json`.

## Trilha do tempo
- 2026-08-31 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste).
  Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-08-31 · [CC] F3 do MWART: 8 UC preenchidos, persona do UC-00 corrigida (não é a Larissa —
  ela leva 403), 6 deles cobertos por `SuperadminPlataformaContratoTest.php` e 1 pelo gate
  `contrato-de-tela` com mordida provada. Refs: ADR 0104 · ADR 0093 · ADR 0358.
