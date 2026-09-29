---
id: resources-js-pages-ponto-escalas-index-casos
casos: Lista de escalas (padrões de jornada) · /ponto/escalas
irmaos: Index.charter.md (lei) · Form.casos.md (a tela irmã, UC-ESCF-01..03) · RUNBOOK-escalas.md
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a escala é o molde da jornada — é contra ela que a apuração compara entrada, saída e intervalo. Uma escala sem turno não apura nada, e é justamente isso que a lista precisa deixar visível.
owner: wagner
last_run: "2026-09-29"
last_run_ci: "69 de 69 UC do Ponto com veredito pass no manifesto scripts/casos-test-results.json (fonte: test-results/pest-ponto-junit.xml). Lane PHP / Pest (Ponto - MySQL) run 34215745965 em main (sha dced5fd3d8, 2026-09-08T10:32Z): 302 passed - 1 skipped - 1009 assertions, coherent=true, provou_algo=true. Li ASSERTIONS, nao a conclusion: 1009 > 0 prova que a suite rodou e nao caiu no skip-as-pass da lane (LC-13). O unico skipped da run nao e UC (o coletor trata skip como nao-pass, e os 69 vieram pass). A lane e ADVISORY: reprova e visivel, nao bloqueia merge."
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
| UC-ESCIDX-01 | A lista não traz escala de outro empregador | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `EscalaIndexContratoTest` | ✅ verde na lane |
| UC-ESCIDX-02 | Cada escala informa quantos turnos tem | must | charter §Goals + CLT Art. 58 | `EscalaIndexContratoTest` | ✅ verde na lane |
| UC-ESCIDX-03 | "Remover" só aparece sem vínculo; com vínculo, o motivo com a contagem | must | `D-ESC-DESTROY` ([W] 2026-09-14) + charter §Non-Goals | `ponto-escalas-remover-vinculo.test.tsx` | 🧪 teste cita o UC, sem veredito |
| UC-ESCIDX-04 | O servidor recusa remover escala em uso — o botão é conveniência, a rota é pública | must | `D-ESC-DESTROY` + CLT Art. 58/59 (jornada esperada) | `EscalaRemocaoContratoTest` | 🧪 teste cita o UC, sem veredito |
| UC-ESCIDX-05 | Remover confirma no diálogo do DS e, confirmado, remove de fato | must | `D-ESC-DESTROY` ([W] 2026-09-14, R3) | `ponto-escalas-remover-vinculo.test.tsx` | 🧪 teste cita o UC, sem veredito |
| UC-ESCIDX-06 | A linha mostra o horário do 1º turno da própria escala, ou que ela não tem turno | should | protótipo `ponto-telas.jsx` (`Escalas`, sub-linha do Nome) + UI-0029 + charter §Goals | `EscalaIndexContratoTest` + `ponto-escalas-index-forma.test.tsx` | 🧪 teste cita o UC, sem veredito |
| UC-ESCIDX-07 | `GET /ponto/escalas/{id}` não promete tela que não existe — 405, nunca 500 | must | `CU-PONTO-14` ("o catálogo não promete o que não entrega") + `RUNBOOK-escalas` §2(a) | `EscalaIndexContratoTest` | 🧪 teste cita o UC, sem veredito |

**[BACKLOG]** (pergunta aberta ao [W], ou contrato numa fonte só — não vira UC sem teste):

- ~~`[BACKLOG]` O `Route::resource` de escalas registra `show`, mas `EscalaController` não tem
  método `show`~~ — **RESOLVIDO 2026-09-29**, virou `UC-ESCIDX-07`: o resource passou a ser
  `except('show')` e o GET passa a dar **405** (não 404: `PUT`/`DELETE` seguem na mesma URI).
  Medido antes do conserto: `GET /ponto/escalas/{id}` dava 500 com id numérico
  (rota registrada sem handler). Das duas saídas deste bullet, `except` e não implementar o `show`:
  não há tela de detalhe no protótipo nem no charter, e a lista leva a `create`/`edit` — criar o
  `show` seria desenhar tela sem fonte. Nome de rota `ponto.escalas.show`: zero consumidores no
  repo (`git grep`, controle positivo `escalas.edit` achou 3).
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

## UC-ESCIDX-03 · "Remover" só aparece sem vínculo; com vínculo, o motivo com a contagem · `must`

- **Persona:** gestor limpando escalas que sobraram de um regime antigo. Ele não tem como saber de
  cabeça quem ainda usa cada uma — se a tela oferecer "Remover" e o servidor recusar depois, ele
  descobre por tentativa e erro, uma escala por vez.
- **Aceite:** Dada uma escala **sem** colaborador vinculado · Então a linha oferece **"Remover"**.
  Dada uma escala **com** N vinculados · Então **não** há "Remover" na linha, e no lugar dela leio
  **"Em uso por N colaborador(es)"**, concordando em número.
- **Teste:** [`tests/js/ponto-escalas-remover-vinculo.test.tsx`](../../../../../tests/js/ponto-escalas-remover-vinculo.test.tsx) — `UC-ESCIDX-03`.
- **Contrato:** `D-ESC-DESTROY` em
  [`ponto-telas.jsx`](../../../../../prototipo-ui/cowork/Wagner/ponto-telas.jsx) — *"entra na UI, mas
  INDISPONÍVEL com vínculo … com o motivo escrito"* — ratificado por [W] em **2026-09-14**
  (*"é liberado ser igual ao protótipo"*), respondendo o §Non-Goals do charter, que até então
  **perguntava** se a UI devia expor a ação. Eixo FORMA ⇒ protótipo soberano ([ADR UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).
- **Por que o motivo é TEXTO e não tooltip:** botão desabilitado não recebe foco, então o Tooltip do
  DS ficaria inalcançável por teclado — o gestor que navega sem mouse não leria o motivo nenhum.
- **Regressão que defende — as DUAS pontas:** o caso cobra que o botão **desapareça** com vínculo
  **e** que **apareça** sem vínculo. Só a primeira metade passaria num `return null` da célula
  inteira, e aí a feature simplesmente não existe. Um 4º caso renderiza duas escalas (uma livre,
  uma em uso) e exige **exatamente um** "Remover" — controle contra aplicar a condição fora do
  `map`. Bite-test por mutação: condição sempre-falsa → 3 failed · sempre-verdadeira → 2 failed ·
  plural cravado → 1 failed · restaurado → 4 passed.
- **Status: 🧪 teste cita o UC, sem veredito de lane.**

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

---

## UC-ESCIDX-05 · Remover confirma no diálogo do DS e, confirmado, remove de fato · `must`

- **Persona:** o mesmo gestor do UC-ESCIDX-03, já na escala livre. Ele precisa de uma pergunta que
  diga **o que se perde** antes de apagar, e de um botão que de fato apague quando ele confirma.
- **Aceite:** Dada uma escala **sem** vínculo · Quando clico em "Remover" · Então abre o diálogo do
  DS com o título **"Remover <nome>?"**, **Cancelar** e **"Remover escala"**, e nada foi removido
  ainda. Quando confirmo · Então sai o `DELETE /ponto/escalas/{id}` daquela escala, e enquanto a
  resposta não chega o botão fica desabilitado ("Removendo…") e o diálogo segue aberto. Quando
  cancelo · Então o diálogo fecha sem remover. Em nenhum momento o `window.confirm` nativo é usado.
- **Teste:** [`tests/js/ponto-escalas-remover-vinculo.test.tsx`](../../../../../tests/js/ponto-escalas-remover-vinculo.test.tsx) — `UC-ESCIDX-05`.
- **Contrato:** `D-ESC-DESTROY` na
  [ata 2026-09-14](../../../../../prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/ATA-DECISOES-2026-09-14.md)
  — *"`window.confirm` não era pergunta — usa o dialog do DS (R3)"*. Forma do diálogo:
  [`ponto-telas.jsx`](../../../../../prototipo-ui/cowork/Wagner/ponto-telas.jsx) (eixo FORMA ⇒
  protótipo soberano, [ADR UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).
- **Regressão que defende:** a ata registra que a 1ª versão do protótipo abriu um modal **sem botão de
  ação**, e passou porque o ramo sem vínculo nunca rodou. Aqui o caso vai até a chamada que remove.
  Mutação: com o `window.confirm` original de volta, os 4 casos reprovam; sem o `preventDefault` no
  botão de confirmar (o Radix fecharia o diálogo no clique), o caso "enquanto processa" reprova.
  O efeito no servidor da remoção sem vínculo é o `UC-ESCIDX-04` (ponta positiva).
- **Status: 🧪 teste cita o UC, sem veredito de lane.**

---

## UC-ESCIDX-07 · `GET /ponto/escalas/{id}` não promete tela que não existe — 405, nunca 500 · `must`

- **Persona:** quem chega por URL — link colado, histórico do navegador, um `/escalas/12` digitado
  por analogia com as outras telas. Não existe tela de detalhe de escala; o que não pode acontecer
  é a página "Server Error", que parece defeito do sistema e não "esta página não existe".
- **Aceite:** Dada uma escala **real** do meu empregador · Quando abro `GET /ponto/escalas/{id}` ·
  Então recebo **405** (a URI segue viva para `PUT`/`DELETE`, então GET nela é *método não
  permitido*) — nunca 500. E a mesma escala segue editável em `/ponto/escalas/{id}/edit` (200).
- **Teste:** `EscalaIndexContratoTest.php` — `UC-ESCIDX-07`.
- **Contrato:** `CU-PONTO-14` (*"o catálogo não promete o que não entrega"*) aplicado ao router +
  [`RUNBOOK-escalas.md`](../../../../../memory/requisitos/Ponto/RUNBOOK-escalas.md) §2(a), que já
  registrava o par rota-declarada/método-ausente.
- **Regressão que defende:** a escala usada no caso **existe** — então a recusa não é "registro
  não achado", é a rota que não aceita GET. Se o `show` voltar ao resource sem método no controller, o GET
  cai em 500 e o caso reprova; o `/edit` = 200 prova que o `except` tirou só o show.
- **Status: 🧪 teste cita o UC, sem veredito de lane.**

## UC-ESCIDX-06 · A linha mostra o horário do 1º turno da própria escala, ou que ela não tem turno · `should`

- **Persona:** o mesmo gestor do UC-ESCIDX-02. A contagem diz *quantos* turnos a escala tem; o
  horário diz *qual jornada* ela descreve — e é o que ele procura ao escolher a escala de um
  colaborador novo ("a de 07:00 ou a de 08:00?") sem abrir cada uma.
- **Aceite:** Dada uma escala com turnos · Quando abro a lista · Então sob o nome dela leio
  **entrada–saída do turno de menor dia da semana** (ex.: `08:00–17:00`), mesmo que outro turno
  tenha sido cadastrado antes. Dada uma escala **sem** turno · Então leio **"sem turno
  configurado"**, e não o horário de outra escala.
- **Teste:** `EscalaIndexContratoTest.php` — `UC-ESCIDX-06` (o payload `primeiro_turno`) ·
  [`tests/js/ponto-escalas-index-forma.test.tsx`](../../../../../tests/js/ponto-escalas-index-forma.test.tsx)
  — `UC-ESCIDX-06` (a tela mostra o que recebeu, por linha).
- **Contrato:** sub-linha do Nome no protótipo
  [`ponto-telas.jsx`](../../../../../prototipo-ui/cowork/Wagner/ponto-telas.jsx) (símbolo `Escalas`:
  `turnos[0].entrada–saida`, senão "sem turno configurado"); [W] 2026-09-28 — *"o protótipo está
  correto, mas a produção é muito inferior"*; eixo FORMA ⇒ protótipo soberano
  ([ADR UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).
  "1º turno" = menor `dia_semana` (0..6), desempate por id — o protótipo usa a ordem do array,
  que no banco não existe; a ordem por dia é a leitura determinística dela.
- **Regressão que defende:** três escalas, cada uma para uma mutação — turnos inseridos **fora**
  de ordem (sem o `orderBy('dia_semana')` vem o primeiro inserido), horários **diferentes** entre
  escalas (um "1º turno" global repetiria o mesmo texto) e uma **casca** sem turno (tem de vir
  `null`). O turno herda o isolamento pela escala (`BelongsToBusinessViaParent`); se o escopo do
  eager-load descartasse o turno do próprio empregador, o horário viria `null` e o caso cai. Na
  UI, com a sub-linha trocada por texto fixo, o teste reprova (`1 failed`, medido 2026-09-28).
- **Status: 🧪 teste cita o UC, sem veredito de lane.**
