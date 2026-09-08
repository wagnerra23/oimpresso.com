---
id: resources-js-pages-ponto-escalas-index-casos
casos: Lista de escalas (padrões de jornada) · /ponto/escalas
irmaos: Index.charter.md (lei) · Form.casos.md (a tela irmã, UC-ESCF-01..03) · RUNBOOK-escalas.md
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a escala é o molde da jornada — é contra ela que a apuração compara entrada, saída e intervalo. Uma escala sem turno não apura nada, e é justamente isso que a lista precisa deixar visível.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "2 UC rodados por mim no CT 100 (container oimpresso-staging, MySQL real), NAO em CI. Codigo do EscalaController@index e da entity Escala identico ao main no container (medido por git diff c1abe9548..origin/main, com controle positivo). CT100 != CI: base persiste entre runs — verde la e CANDIDATURA, nao veredito. A tela irma (Escalas/Form) ja tem contrato proprio, com DOIS UC que nascem failing-first por desenho (UC-ESCF-01 atributo fantasma no @edit, UC-ESCF-02 `validated()` em Request que nao e FormRequest); este arquivo NAO os duplica — cobre so o que e da lista."
---

# Casos de Uso & Aceite — Lista de escalas

> **Âncora:** `CU-PONTO-12` do [SDD §6.5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md)
> + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) + o
> `Index.charter.md` ao lado + CLT Art. 58 (a jornada contratada é o que a escala descreve).
> Os UC derivam do **contrato**, nunca do `Index.tsx`.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-ESCIDX-01 | A lista não traz escala de outro empregador | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `EscalaIndexContratoTest` | 🧪 verde no CT 100, sem veredito de lane |
| UC-ESCIDX-02 | Cada escala informa quantos turnos tem | must | charter §Goals + CLT Art. 58 | `EscalaIndexContratoTest` | 🧪 verde no CT 100, sem veredito de lane |

**[BACKLOG]** (pergunta aberta ao [W], ou contrato numa fonte só — não vira UC sem teste):

- `[BACKLOG]` O `Route::resource` de escalas registra **`show`**, mas `EscalaController` **não tem
  método `show`** (varredura contada no arquivo: `index · create · store · edit · update · destroy`,
  seis métodos, nenhum `show`). Ou seja, `GET /ponto/escalas/{id}` é rota registrada sem handler. A
  lista **não** linka para lá — os atalhos vão para `create` e `edit` —, então isto não é defeito
  *desta tela*; é rota órfã do resource, e a família é a do `CU-PONTO-14` (*"o catálogo não promete o
  que não entrega"*). Fica registrado porque é decisão de [W] entre implementar o `show` ou declarar
  `only([...])` no resource.
- `[BACKLOG]` O charter pergunta em §Non-Goals se a **exclusão** de escala entra na UI (*"rota destroy
  existe no resource, mas a UI não expõe — confirmar com Wagner"*). Enquanto não houver resposta não
  há contrato para testar. Nota para quem decidir: apagar escala usada por colaborador tem efeito na
  apuração já gravada — a FK é `on delete set null` em `ponto_colaborador_config.escala_atual_id`, o
  que silenciosamente deixaria colaborador sem molde de jornada.
- `[BACKLOG]` Nada cobre a **paginação** (20/pág) nem o empty state com CTA. São contrato de charter
  sem consequência legal; entram quando alguém precisar deles.

---

## UC-ESCIDX-01 · A lista não traz escala de outro empregador · `must` `[T0]`

- **Persona:** gestor de RH. A escala revela o padrão de jornada praticado pela empresa — quantas
  horas por dia, se opera em 12x36, se usa banco de horas. É informação concorrencial e trabalhista.
- **Aceite:** Dada uma escala cadastrada em **outro** empregador · Quando abro `/ponto/escalas` ·
  Então o nome e o código dela **não** aparecem na lista.
- **Teste:** `Modules/Ponto/Tests/Feature/EscalaIndexContratoTest.php` — `UC-ESCIDX-01`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
  charter §Non-Goals (*"Não lista escala de outro business"*).
- **Regressão que defende — e o limite dela, medido:** aqui a defesa é **dupla** (o
  `where('business_id', …)` do controller, que nesta consulta funciona porque não há `orWhere` para
  neutralizá-lo, **e** o global scope do model `Escala`), ao contrário da busca de colaboradores,
  onde só o scope segura (`UC-COLIDX-01`). Consequência honesta: este caso **só morde quando as duas
  caem**. Bite-test no CT 100 — com **apenas** o trait removido do model, ele passou verde
  (`1 passed`, 3 assertions); com o trait **e** o filtro do controller removidos, reprovou
  (`1 failed`). Ou seja, ele é rede contra a perda **completa** do isolamento desta lista, não um
  detector de defesa-única enfraquecida. Um caso que mordesse na queda de *qualquer uma* teria que
  afirmar sobre a query, não sobre o que a tela devolve — e aí deixaria de ser contrato de
  comportamento.
- **Nota do módulo, e uma ressalva sobre o D-6:** o
  [SDD §9 D-6](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) registra
  *"`EscalaTurno` sem `HasBusinessScope`"*. **Literalmente verdade, mas induz a erro** — e vale
  dizê-lo aqui porque a redação anterior desta linha repetia a indução. Medido no model: ele usa
  **`BelongsToBusinessViaParent`** com `$businessParentRelation = 'escala'`, que é o padrão
  canônico do repo para *child* sem coluna própria (injeta `whereHas` no parent; mesmo trait de
  `Modules/Essentials` e `Modules/Accounting`). A tabela `ponto_escala_turnos` **não tem**
  `business_id` por desenho, e o isolamento é **transitivo**, não ausente. Ou seja: não é buraco
  de scope a fechar. Este UC olha a *lista*, não o turno; o eixo cross-tenant de escala/turno já
  tem dono na lane (`Wave27CrossTenantEscalaTest`).
  **Crédito:** a imprecisão foi apontada por sessão paralela (`claude/ponto-casos-config-escalas`)
  e verificada aqui no model antes de a correção entrar.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**

---

## UC-ESCIDX-02 · Cada escala informa quantos turnos tem · `must`

- **Persona:** gestor de RH montando a jornada. Uma escala recém-criada é uma **casca**: tem nome,
  tipo e carga, mas nenhum turno por dia da semana — e sem turno a apuração não tem contra o que
  comparar entrada e saída. A contagem na lista é o que distingue escala pronta de escala pela metade.
- **Aceite:** Dada uma escala do meu empregador com **um** turno configurado · Quando abro a lista ·
  Então a linha dela informa a quantidade de turnos, e essa quantidade é **1** — não ausente, não zero.
- **Teste:** `EscalaIndexContratoTest.php` — `UC-ESCIDX-02`.
- **Contrato:** charter §Goals (*"Lista paginada (20/pág) de escalas com contagem de turnos"* +
  *"Colunas: nome, código, tipo (badge), carga/dia, carga/semana, BH, turnos"*) · CLT Art. 58 ·
  fluxo do próprio módulo: `store()` redireciona para a edição com *"Escala criada. Configure os
  turnos por dia da semana"* — ou seja, a casca sem turno é um estado esperado e precisa ser visível.
- **Regressão que defende — dois eixos, e o segundo quase escapou:** a contagem vem de
  `withCount('turnos')`, que produz `turnos_count`. Perder o `withCount` faz o atributo resolver
  **`null` → 0**, e a lista passa a dizer que **toda** escala tem zero turnos — família dos
  "atributos fantasma" do [SDD §9 D-1/D-8](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md).
  O segundo eixo é **trocar o agregado por um que ignore o vínculo** (um `count()` global da tabela
  de turnos): a coluna continua existindo, com número plausível, e a tela informa a contagem errada
  para cada escala.
- **Por que o caso cria DUAS escalas, com 1 e 2 turnos:** porque a primeira versão criava só uma,
  com 1 turno, e **passava por sorte**. Medido no CT 100: com o agregado trocado por um total
  global, a tabela tinha exatamente 1 turno naquele instante, o total global devolvia **1** e o
  teste ficava **verde com o agregado quebrado** (`2 passed`). Com contagens diferentes, qualquer
  agregado sem vínculo devolve o mesmo número nas duas linhas, e pelo menos um assert cai. Depois
  da mudança, o caso reprova nas duas mutações (`1 failed` em cada). **Crédito:** o eixo do agregado
  foi apontado por sessão paralela (`claude/ponto-casos-config-escalas`); meu primeiro experimento
  mediu o eixo errado — vazamento de *linha*, que o `UC-ESCIDX-01` já cobre — e concluiu que não
  procedia. Procede, e o furo estava neste teste.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**
