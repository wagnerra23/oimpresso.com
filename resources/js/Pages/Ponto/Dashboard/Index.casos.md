---
id: resources-js-pages-ponto-dashboard-index-casos
casos: Painel do Ponto · /ponto
irmaos: Index.charter.md (lei) · prototipo-ui/contrato/ponto-painel.contract.json (contrato visual) · memory/requisitos/Ponto/RUNBOOK-dashboard.md (F1 PLAN)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a home do módulo e o único lugar onde o gestor vê, antes de tentar fechar a competência, o que a impede de consolidar.
owner: wagner
last_run: "2026-08-24"
last_run_ci: "NÃO EXECUTADO NA LANE. Os 6 UC rodaram no CT 100 (container oimpresso-staging, MySQL oimpresso_staging) em 2026-08-23: 6 passed, 76 assertions, 0 skipped — assertions>0 prova execução, não `0 failed` (LC-13). Mas a lane `PHP / Pest (Ponto · MySQL)` roda uma ALLOWLIST explícita e este arquivo não está nela: teste fora da allowlist é verde impossível — existe e nunca roda por PR. Adicioná-lo exige editar .github/workflows/ponto-pest.yml, fora do prefixo deste PR. Veredito oficial vem do manifesto G-7, não desta linha nem da run manual."
smoke_prod: "2026-08-24, apos o deploy de 8e7583e05b (PR #6160), Chrome MCP em https://oimpresso.com/ponto, biz=1 WR2 Sistemas. CONFIRMADO no ar: as 6 legendas de KPI, o subtitulo '(0 pendentes)' da fila, a frase de vazio e o rodape da Portaria MTP 671/2021. NAO verificaveis nesta janela: NSR no feed e Estado na fila — nao havia marcacao nem intercorrencia no dia, entao nao ha linha pra exibir. DOIS DEFEITOS MEDIDOS no DOM, ambos anteriores a este PR: (1) so 3 das 4 ancoras do contrato existem na pagina — `painel-kpis` nunca chegou ao DOM porque o KpiGrid nao repassava a prop; (2) rotulo de KPI truncado (scrollWidth 157 > clientWidth 114 em 'Colaboradores ativos', com text-overflow: ellipsis) a 1440px de viewport."
---

# Casos de Uso & Aceite — Painel do Ponto

> **Âncora.** Não há SDD do Painel (o SDD do módulo cobre espelho e jornada), então a âncora
> destes UC é, nesta ordem:
> **(1)** [`prototipo-ui/contrato/ponto-painel.contract.json`](../../../../../prototipo-ui/contrato/ponto-painel.contract.json)
> — copy e ordem literais, descido por decisão [W] em 2026-08-21;
> **(2)** [`Index.charter.md`](Index.charter.md) §Non-Goals / §Anti-hooks / §Automation hooks;
> **(3)** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0).
> Os UC **não** derivam do `.tsx` — o teste lê o contrato JSON e afirma contra ele
> (teste tautológico é proibido, [§5 2026-06-05](../../../../../memory/proibicoes.md)).
>
> 🔴 **`[V0]` — minuto de jornada é valor.** `he_mes_minutos` cai sob a REGRA MESTRE de
> [proibicoes.md](../../../../../memory/proibicoes.md). Os UC aqui provam **isolamento** do
> agregado (o número não se mexe quando nasce dado alheio), **nunca** o valor apurado.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **required** desde 2026-08-05
> ([ADR 0369](../../../../../memory/decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md), emenda 0314).
> Quem responde "o que é required" é
> [`governance/required-checks-baseline.json`](../../../../../governance/required-checks-baseline.json),
> não esta linha.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.
>
> 🔎 **Recibo da 1ª corrida** (CT 100 · container `oimpresso-staging` · MySQL `oimpresso_staging`
> · 2026-08-23 — medição datada, não afirmação atemporal): **6 passed · 76 assertions · 0 skipped**.
> Duas correções que a corrida provou, e que leitura nenhuma teria pego:
> **(a)** três casos reprovavam por `expect()->toContain($needle, $msg)` — o `toContain` do Pest é
> **variádico**, então a mensagem virava um **segundo needle** (mesma família do
> [§5 2026-07-28](../../../../../memory/proibicoes.md)); o mesmo vale pro `toHaveKey($k, $v)`, cujo
> 2º argumento é o **valor**. Trocados por `assertContains` / `assertStringContainsString` /
> `assertArrayHasKey`, que aceitam mensagem.
> **(b)** o UC-PAINEL-02 afirmava sobre a chave `codigo` da fila — e `buildAprovacoes` **não a
> expõe** (as chaves são `id` · `tipo` · `prioridade` · `data_inicio` · `data_fim` ·
> `justificativa` · `estado` · `created_at` · `colaborador`). Afirmar sobre chave que a tela não
> entrega reprova sem dizer nada do produto.
>
> 🦷 **Bite-test do guard Tier 0** (mesma sessão): com o filtro `business_id` removido de
> `buildAprovacoes` no staging, **o UC-PAINEL-02 reprovou** — *"Failed asserting that an array does
> not contain '0d47fc6e-…'"* — e **só ele**; os outros 5 seguiram verdes. Guard que não pode
> reprovar é carimbo; este morde, e a mordida é cirúrgica. O staging foi restaurado ao estado
> anterior (`git checkout` + `rm`, 4 arquivos sujos antes e depois, todos de outra sessão).
>
> 🔎 **Por que, ainda assim, TODOS estão 🧪 e nenhum ✅ (medido 2026-08-23).**
> A lane roda uma **allowlist explícita** de arquivos
> ([`.github/workflows/ponto-pest.yml`](../../../../../.github/workflows/ponto-pest.yml), passo
> *"Run Pest (Ponto · MySQL) — ALLOWLIST VERDE (catraca)"*): **11 de 37** testes do módulo
> executam por PR. `PontoDashboardContratoTest.php` **não está na allowlist**, logo não roda —
> e o próprio workflow nomeia a consequência: *"teste fora da allowlist é 'verde impossível' —
> existe e nunca roda por PR"*. Marcar ✅ aqui seria carimbar sem veredito.
> **A linha que falta**, para quem puder editar o workflow (fora do prefixo deste PR):
> `Modules/Ponto/Tests/Feature/PontoDashboardContratoTest.php \` no bloco do `vendor/bin/pest`.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PAINEL-01 | Os seis KPIs aparecem com a copy e na ordem que o contrato manda | must | contrato §`painel-kpis` | `PontoDashboardContratoTest` | 🧪 sem veredito |
| UC-PAINEL-02 | Dado de outro empregador não entra em KPI nenhum nem na fila | must `[T0]` `[V0]` | ADR 0093 + charter §Non-Goals | `PontoDashboardContratoTest` | 🧪 sem veredito |
| UC-PAINEL-03 | Sem intercorrência pendente a fila aparece com a frase de vazio | must | contrato §`painel-fila-aprovacoes` | `PontoDashboardContratoTest` | 🧪 sem veredito |
| UC-PAINEL-04 | O painel é somente leitura: a rota não aceita escrita e o controller não grava | must | charter §Anti-hooks | `PontoDashboardContratoTest` | 🧪 sem veredito |
| UC-PAINEL-05 | O polling recarrega apenas as props de leitura declaradas no charter | should | charter §Automation hooks | `PontoDashboardContratoTest` (lista do `only`) + `DashboardDeferredContractTest` (defer + `<Deferred>`) | 🧪 sem veredito |
| UC-PAINEL-06 | A nota do que trava o fechamento vem acima dos KPIs e reflete o estado real | must | contrato §`ordem` + §`painel-nota-fechamento` | `PontoDashboardContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` **Isolamento de `presentes_agora` e do feed de atividade.** As duas props saem de
  `ponto_marcacoes`, que tem `trg_ponto_marcacoes_no_delete` e `trg_ponto_marcacoes_no_update`
  **sem escape** (Portaria 671/2021 · `database/schema/mysql-schema.sql` linhas 7420 e 7441 —
  medido 2026-08-23). Fixture de marcação é **irreversível**, e o CT 100 roda contra base
  persistente: o lixo se acumularia a cada run. O UC-PAINEL-02 cobre os agregados **limpáveis**
  (colaborador · apuração · intercorrência); fechar esta perna exige decidir onde a fixture
  append-only pode nascer — é decisão [W], não caso de teste.
- `[BACKLOG]` **Ordem no DOM renderizado.** UC-PAINEL-01/03/06 provam ordem e copy no **source**
  do `.tsx`, ancorados no contrato. Ordem no DOM de verdade exige E2E (Playwright), e não há lane
  de browser para esta tela. O `contrato-de-tela.mjs` cobre a ordem das **âncoras**; a ordem das
  **copies dentro da seção** é o que estes UC acrescentam.
- `[BACKLOG]` **Os 3 estados da nota renderizados.** O contrato declara `com-pendencia`,
  `sem-pendencia` e `so-divergencia`. O UC-PAINEL-06 prova a **posição** da nota e que o servidor
  entrega `divergencias_mes` (o número que decide o estado), mas os 3 ramos são de render React —
  mesma dependência de E2E acima. ⚠️ Nota de divergência com o pedido original, registrada em vez
  de silenciada: o pedido pedia *"nota ausente quando não há divergência"*; o contrato e o
  protótipo declaram **3 estados, todos visíveis** (o estado sem pendência diz *"a competência
  pode consolidar"*). O UC seguiu o **contrato**, que é a fonte — não a suposição.
- `[BACKLOG]` **Custo do polling 30s com `defer`** (item aberto no charter §Pendências). O
  UC-PAINEL-05 prova **quais** props recarregam, nunca **quanto custam**. **Medido no CT 100 em
  2026-08-23** (`DB::getQueryLog`, mesma base do recibo acima):

  | Cenário | Queries |
  |---|---|
  | piso do shell (partial pedindo só `server_time`, prop eager — nenhuma closure roda) | **33** |
  | um ciclo de polling (as 5 props do charter) | **45** |
  | primeiro render (tudo deferido) | **44** |
  | carga completa (as 6 props deferidas de uma vez) | **47** |

  A leitura, e é o contrário do que o número cru sugere: **o `defer` está funcionando** — o painel
  em si custa **12** das 45 queries do polling; as outras **33 (73%)** são o shell do UltimatePOS
  (`SetSessionData` + `AdminSidebarMenu` + permissões), que o partial reload paga por inteiro a
  cada 30s. Otimizar não é mexer no painel: seria encurtar o middleware do shell no caminho de
  partial reload — decisão [W] e escopo de outro PR. Sem teto proposto aqui, por escolha: o pedido
  pediu medir e reportar.

---

**[BACKLOG] — aplicado do protótipo em 2026-08-23, ainda SEM UC que o cubra.**
Entra como prosa de propósito: criar `## UC-XX` sem teste que o cite só avermelharia o
G-2 e fabricaria cobertura. O que a lane cobre hoje são os 6 UC do contrato; os itens
abaixo estão **fora** do que o contrato declara, e por isso não têm gate.

- `[BACKLOG]` **Legenda dos 6 KPIs** (`description`): "com controle de ponto", "última
  marcação H:i", "além da tolerância de N min", "sem marcação e sem intercorrência",
  "limite Nh/dia (Art. 59)", "N urgentes"/"nada urgente". ⚠️ A tolerância citada é a
  `tolerancia_maxima_diaria_minutos` (10) — a chave que o KPI "Atrasos hoje" realmente
  filtra — e **não** a `tolerancia_minutos_por_marcacao` (5) que o protótipo escreve no
  rótulo. Copiar o rótulo do protótipo faria a legenda descrever um número que a
  contagem não aplicou.
- `[BACKLOG]` **Os 6 KPIs navegam** (eram 2). Presentes/Atrasos/Faltas → `/ponto/espelho`,
  HE → `/ponto/banco-horas`, como no protótipo.
- `[BACKLOG]` **NSR no feed de atividade** — identificador legal da marcação (Portaria
  MTP 671/2021); é por ele que se amarra a linha do AFD ao evento. Exigiu expor `nsr` no
  payload de `buildAtividadeRecente`, que não o entregava.
- `[BACKLOG]` **Estado da intercorrência na fila** — o protótipo tem coluna própria; a
  tela mostrava só prioridade, então dava pra ver o que era urgente mas não em que ponto
  da decisão cada item estava. Também entrou o recorte de hora (`dia_todo` /
  `intervalo_inicio`–`intervalo_fim`), que o payload não expunha.
- `[BACKLOG]` **Subtítulo da fila `(N pendentes)`** — vem de `kpis.aprovacoes_pendentes`,
  NÃO de `aprovacoes.length`: a fila é limitada a 5 no controller, então o length mentiria
  a partir da 6ª pendência.
- `[BACKLOG]` **Rodapé legal** — "Registros protegidos pela Portaria MTP 671/2021 —
  marcações são imutáveis (append-only)", o `<Legal />` do protótipo.

**Não aplicado, e é decisão [W]:** o protótipo desenha a fila como TABELA de 5 colunas.
A tela mantém a lista compacta. O contrato **não** declara as colunas (só as 3 frases),
então converter seria mudança de layout sem âncora de contrato — e o que faltava de
informação (Estado + intervalo) foi aplicado dentro da lista. Se você quiser a tabela,
é ato seu no contrato.

**Custo medido do polling:** o ciclo de 30s foi de **13 → 15 queries** (+2: contagem de
urgentes e `max(momento)` do dia, ambas em `buildKpis`). Segue sem teto declarado.

## UC-PAINEL-01 · Os seis KPIs aparecem com a copy e na ordem que o contrato manda · `must`

- **Persona:** gestor de RH/DP abrindo o painel de manhã para saber, de um olhar, o estado do dia.
- **Aceite:** Dado o contrato `ponto-painel` · Quando abro `/ponto` · Então os 6 KPIs aparecem
  dentro da seção `painel-kpis`, com a copy **literal** do contrato e na **ordem** que ele declara:
  Colaboradores ativos → Presentes agora → Atrasos hoje → Faltas hoje → HE do mês → Aprovações pendentes.
- **Teste:** `Modules/Ponto/Tests/Feature/PontoDashboardContratoTest.php` — `UC-PAINEL-01`.
- **Contrato:** `prototipo-ui/contrato/ponto-painel.contract.json` §`secoes[painel-kpis].copy`.
  O teste **lê o JSON** e afirma contra ele: se o contrato mudar, o caso acompanha sem edição —
  e se a tela divergir, o vermelho é o sinal honesto.
- **Status: 🧪 sem veredito** (a lane nao executa este arquivo: allowlist do ponto-pest.yml).

## UC-PAINEL-02 · Dado de outro empregador não entra em KPI nenhum nem na fila · `must` `[T0]` `[V0]`

- **Persona:** qualquer gestor — o painel é a primeira tela do módulo, e um agregado que vaza não
  deixa linha para ninguém notar.
- **Aceite:** Dado dado do meu empregador que o painel já reflete · Quando nasce colaborador,
  apuração e intercorrência em **outro** empregador · Então **nenhum** KPI meu muda e a
  intercorrência alheia **não** aparece na fila.
- **Teste:** `PontoDashboardContratoTest` — `UC-PAINEL-02`. Empregador alheio = fictício **99**
  (`PontoTestCase::garantirBizAlheio`), nunca biz=4 (ADR 0101).
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  + charter §Non-Goals (*"Não agrega dados de outro business"*).
- **Nota de método:** o caso tem **controle positivo** — antes de afirmar que o alheio não entra,
  prova que o painel **reage** ao dado próprio. Sem isso, um painel quebrado devolvendo zeros
  passaria por imobilidade, não por isolamento.
- ⚠️ **Se este UC reprovar, não é PR — é incidente Tier 0.**
- **Status: 🧪 sem veredito** (a lane nao executa este arquivo: allowlist do ponto-pest.yml).

## UC-PAINEL-03 · Sem intercorrência pendente a fila aparece com a frase de vazio · `must`

- **Persona:** gestor que abre o painel num dia sem pendência e precisa saber que **não há nada
  a decidir** — diferente de "a fila não carregou".
- **Aceite:** Dado nenhuma intercorrência pendente · Quando abro `/ponto` · Então a seção
  `painel-fila-aprovacoes` está presente **e** contém a frase
  *"Nenhuma intercorrência aguardando decisão."*
- **Teste:** `PontoDashboardContratoTest` — `UC-PAINEL-03`.
- **Contrato:** contrato §`painel-fila-aprovacoes` (estado `vazio`). O caso exige a frase **dentro**
  da seção, não em qualquer lugar do arquivo: âncora numa seção e copy noutra é a forma de passar
  parecendo que passou ([RUNBOOK-dashboard §8](../../../../../memory/requisitos/Ponto/RUNBOOK-dashboard.md)).
- **Status: 🧪 sem veredito** (a lane nao executa este arquivo: allowlist do ponto-pest.yml).

## UC-PAINEL-04 · O painel é somente leitura · `must`

- **Persona:** o próprio sistema — o painel é read-only por desenho, e marcação é append-only por
  força de lei (Portaria MTP 671/2021).
- **Aceite:** Dado o painel · Quando chega POST/PUT/PATCH/DELETE em `/ponto` · Então nenhum
  responde 200; e o `DashboardController` não chama primitiva de escrita nenhuma.
- **Teste:** `PontoDashboardContratoTest` — `UC-PAINEL-04`.
- **Contrato:** charter §Anti-hooks (*"Não muta nada — dashboard é read-only"*) + §Non-Goals
  (*"Não bate ponto aqui"*, *"Não aprova/rejeita intercorrências"*, *"Não edita marcações"*).
- **Nota de método:** o caso roda o **controle positivo do detector** — prova que a mesma lista de
  primitivas **encontra** escrita no `EscalaController`, que grava. Sem isso, uma lista de agulhas
  errada daria verde em qualquer arquivo, e o teste mediria a si mesmo.
- **Status: 🧪 sem veredito** (a lane nao executa este arquivo: allowlist do ponto-pest.yml).

## UC-PAINEL-05 · O polling recarrega apenas as props de leitura declaradas no charter · `should`

- **Persona:** gestor com o painel aberto na parede da sala — a tela se atualiza sozinha e não pode
  ficar cara nem mostrar dado velho.
- **Aceite:** Dado o refresh de 30s · Quando o polling dispara · Então recarrega **exatamente**
  `kpis`, `presenca_agora`, `atividade_recente`, `alertas`, `server_time` — nem mais, nem menos.
- **Teste:** `PontoDashboardContratoTest` — `UC-PAINEL-05` (a **lista** do `only`) +
  `DashboardDeferredContractTest` (o `Inertia::defer` no controller e o wrap `<Deferred>` na Page).
  Dois donos, contratos vizinhos e distintos — o segundo já existia e **não foi duplicado**.
- **Contrato:** charter §Automation hooks (lista literal) + §Anti-hooks (*"o polling só recarrega
  props de leitura"*) + [RUNBOOK-inertia-defer-pattern](../../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md).
- **Nota de método:** o caso compara a lista **inteira**, não só presença. Prop a mais recarrega de
  graça a cada 30s; prop a menos deixa o painel mostrar dado velho — e só conferir presença deixaria
  as duas passar.
- **Status: 🧪 sem veredito** (a lane nao executa este arquivo: allowlist do ponto-pest.yml).

## UC-PAINEL-06 · A nota do que trava o fechamento vem acima dos KPIs e reflete o estado real · `must`

- **Persona:** gestor tentando fechar a competência. Dia em `DIVERGENCIA` não é detalhe de
  relatório: impede a apuração de consolidar **e** faz o AFD sair com a jornada errada — então ele
  precisa ver isso **antes** de tentar fechar, não depois.
- **Aceite:** Dado o contrato, que declara a nota como 1ª seção · Quando abro `/ponto` · Então a
  seção `painel-nota-fechamento` precede `painel-kpis` e nomeia `DIVERGENCIA`; e quando nasce um dia
  em divergência na competência, `divergencias_mes` sobe de N para N+1.
- **Teste:** `PontoDashboardContratoTest` — `UC-PAINEL-06`.
- **Contrato:** contrato §`ordem[0]` + §`painel-nota-fechamento` + RUNBOOK-dashboard §4 (a redação
  dos 3 estados, lida do protótipo).
- **Status: 🧪 sem veredito** (a lane nao executa este arquivo: allowlist do ponto-pest.yml).
