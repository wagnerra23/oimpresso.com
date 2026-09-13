---
id: governance-cowork-index-casos
charter: Index.charter.md
page: /governance (painel · políticas · auditoria · drift · notas dos módulos)
status: draft
last_validated: "2026-08-23"
---

# Casos de uso — módulo Governança

Formato Dado/Quando/Então com critério de aceite. Rastreabilidade: cada caso aponta a regra (R) ou o achado (A) do charter. ❌ = o caso **nasce vermelho** contra o `main` de hoje — é o conserto pedido, não regressão do F1.

## Navegação e endereço

**UC-GOV-01 — a raiz não abre painel** (R1)
Dado um usuário autenticado;
Quando ele acessa `/governance`;
Então é redirecionado para `/ia` com 302, e o painel segue em `/governance/dashboard`.
*Aceite:* nenhum link do módulo aponta para a raiz esperando painel; a tela exibe o endereço real.

**UC-GOV-02 — o módulo tem navegação própria** (A1)
Dado que o usuário tem `governance.dashboard.view` e o módulo instalado;
Quando abre qualquer vista;
Então a strip do módulo aparece com as cinco vistas, e a vista corrente está marcada.
*Aceite:* a lista vem de `DataController::modifyAdminMenu`, não duplicada na tela; sem a entry em `shell.menu`, a strip **some em silêncio** em vez de renderizar vazia.

## Painel

**UC-GOV-03 — a conformidade diz de onde vem** (A2)
Dado o KPI de conformidade da Constituição;
Quando [W] abre o painel;
Então o número aparece rotulado como auto-declarado, com a régua ao lado (7 artigos plenos · 2 parciais · 1 pendente).
*Aceite:* nasce vermelho — hoje `compliancePct` é a soma literal `(7*10)+(2*5)+0` renderizada como percentual sem origem. ❌

**UC-GOV-04 — o que espera decisão aparece primeiro**
Dado que existem ADRs com `status = proposto`;
Quando o painel carrega;
Então até 10 aparecem por data de atualização decrescente, com título e quanto tempo esperam.
*Aceite:* a consulta usa a coluna `status` de `mcp_memory_documents` (não frontmatter), e ignora `deleted_at`.

**UC-GOV-05 — ocorrências das últimas 24 h** (R2)
Dado registros de auditoria com resultado diferente de `ok` nas últimas 24 h;
Quando abro o painel;
Então vejo até 20, com ator, endpoint, ferramenta, resultado e duração.
*Aceite:* a mesma janela do KPI de ocorrências; nenhum controle escreve nessas linhas.

**UC-GOV-06 — tabela ausente vira travessão, não zero** (R10)
Dado que `failed_jobs`, `jana_mensagens` ou `jana_health_narratives` não existe nesta base;
Quando o painel carrega;
Então o KPI mostra travessão e explica que a fonte não está instalada.
*Aceite:* nenhum 500; nenhum zero que se confunda com "nada falhou".

**UC-GOV-07 — o que é caro chega depois** (R11)
Dado que o cartão SDD e a seção MCP são deferidos;
Quando a tela pinta;
Então o resto do painel já está legível e as duas áreas mostram esqueleto até chegar.
*Aceite:* a primeira pintura não espera nenhuma das duas.

**UC-GOV-08 — a seção MCP só aparece pra quem a via ontem** (R12)
Dado um usuário **sem** `jana.mcp.usage.all`;
Quando abre o painel;
Então a seção Governança MCP não é renderizada e nada é consultado em `mcp_audit_log`;
E o mesmo usuário continua vendo o resto do painel.
*Aceite:* o gate é exatamente `jana.mcp.usage.all` — somar `governance.dashboard.view` reprova o caso.

**UC-GOV-09 ❌ — o gate da seção MCP tem teste** (A3)
Dado a suíte do módulo;
Quando ela roda;
Então existe teste cobrindo com/sem a permissão e a whitelist de `mcp_preset`.
*Aceite:* nasce vermelho — o charter vivo declara a lacuna. ❌

**UC-GOV-10 — período fora da lista degrada, não explode**
Dado `?mcp_preset=xpto` ou `custom` sem as duas datas;
Quando o painel carrega;
Então o período volta a `30d` e a tela funciona.
*Aceite:* nenhum `Carbon::parse` de lixo; nenhum 500.

**UC-GOV-11 — trocar período recarrega só a seção MCP**
Dado o painel aberto;
Quando troco o preset;
Então só `mcp` e `mcp_filters` são buscados de novo.
*Aceite:* a Constituição e o SDD não são reconsultados; a rolagem se mantém.

## Políticas

**UC-GOV-12 — alternar é ação direta** (R5)
Dado uma política ativa;
Quando aciono o interruptor;
Então ela muda na hora e um aviso fugaz diz "Política #X desativada", sem modal.
*Aceite:* nenhuma caixa de confirmação; a rolagem e o estado da tela se mantêm.

**UC-GOV-13 — desligada continua na lista** (R6)
Dado políticas ativas e desligadas na mesma categoria;
Quando a lista carrega;
Então todas aparecem — ativas primeiro, depois categoria, depois chave.
*Aceite:* não existe filtro que esconda desligadas por padrão.

**UC-GOV-14 ❌ — desligar avisa que não fica registrado** (A4)
Dado que `mcp_governance_rule_history` ainda não existe;
Quando desligo uma política;
Então a tela diz, em texto, que a mudança **não** deixa rastro e que a auditoria fica cega para ela.
*Aceite:* nasce vermelho contra o `main` (a tabela e o aviso não existem); some quando o histórico for criado. ❌

**UC-GOV-15 — cada regra mostra o que é**
Dado uma regra qualquer;
Então a linha traz chave em monoespaçada, nome, descrição, versão e quantas vezes disparou.
*Aceite:* `condition_json` não aparece cru na lista.

**UC-GOV-16 — primeira vez explica o que é política**
Dado um banco sem nenhuma regra;
Quando abro a vista;
Então em vez de tabela vazia vejo o que é uma política de governança, quem a cria e o que acontece quando dispara.
*Aceite:* vazio com motivo do DS, não "nenhum resultado".

## Auditoria

**UC-GOV-17 — registro é imutável e a tela declara** (R2)
Dado a vista de auditoria;
Quando procuro editar, apagar ou corrigir uma linha;
Então não existe caminho nenhum, e um selo diz que o registro é append-only por trigger.
*Aceite:* qualquer UPDATE/DELETE é bloqueado no banco ([ADR 0084](../../memory/decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md)); alterar linha é incidente P0.

**UC-GOV-18 — o teto de 200 é visível** (R3)
Dado uma janela com mais de 200 registros;
Quando a tabela carrega;
Então vejo 200 linhas e o rodapé diz o teto e manda refinar o filtro.
*Aceite:* o teto não é silencioso; a contagem do KPI se refere à mesma amostra.

**UC-GOV-19 — quatro filtros combinam** (R4)
Dado período, ator, endpoint e resultado;
Quando combino qualquer subconjunto;
Então a tabela e os KPIs respondem juntos, sem recarregar a página inteira.
*Aceite:* a rolagem se mantém e o histórico do navegador não enche (substituição de entrada).

**UC-GOV-20 — não existe janela maior que 30 dias** (R4)
Dado o seletor de período;
Então as opções são 1 h, 24 h, 7 dias e 30 dias — não há "tudo".
*Aceite:* período fora da lista degrada para 24 h.

**UC-GOV-21 — filtro sem resultado explica**
Dado um filtro que não retorna nada;
Então a tela diz qual combinação zerou e oferece limpar os filtros.
*Aceite:* vazio com motivo; o botão devolve ao padrão de 24 h.

**UC-GOV-22 — a tela declara que é cross-tenant** (R9)
Dado que `mcp_audit_log` não tem `business_id`;
Quando abro a vista;
Então o cabeçalho marca `superadmin · cross-tenant` e nenhum filtro de negócio é oferecido.
*Aceite:* não inventar escopo onde a tabela não tem coluna — exceção formal coberta por `CrossTenantPolicyTest`.

## Drift

**UC-GOV-23 — drift mostra o conserto junto** (R7)
Dado um módulo com controller não declarado no `SCOPE.md`;
Quando abro a vista;
Então vejo o módulo, os controllers em divergência, o total real, e as três saídas: declarar em `contains[]`, mover o arquivo, ou registrar em `drift_alerts[]`.
*Aceite:* nenhum botão de auto-corrigir; a tela não escreve no `SCOPE.md`.

**UC-GOV-24 — não existe silenciar** (R7)
Dado qualquer divergência listada;
Quando procuro adiar ou ignorar;
Então não há ação disso na interface.
*Aceite:* caso negativo; a ausência do controle é o aceite.

**UC-GOV-25 — zero drift é estado de sucesso, não vazio**
Dado nenhuma divergência;
Então os KPIs viram tom de sucesso e a tela diz que os 30 módulos batem com o declarado.
*Aceite:* tom muda de aviso para sucesso quando o total é zero.

**UC-GOV-26 — módulo sem `SCOPE.md` é achado próprio**
Dado módulos sem arquivo de escopo;
Então eles aparecem em bloco separado, não como drift zero.
*Aceite:* o KPI de "sem SCOPE.md" é distinto do de módulos com drift.

**UC-GOV-27 — histórico vazio diz por quê** (A5)
Dado que o cron de detecção não roda e `mcp_alertas` não aceita a categoria `module_drift`;
Quando abro o card de histórico;
Então ele explica isso em texto, e diz que aceitar a categoria exige migração + ADR.
*Aceite:* nunca "nenhum resultado" seco.

**UC-GOV-28 — boilerplate não conta como drift**
Dado `DataController`, `InstallController`, `SuperadminController` e `Controller` em qualquer módulo;
Quando o scan roda;
Então eles são filtrados antes da comparação.
*Aceite:* paridade lógica com `bin/check-scope.php` é obrigatória.

**UC-GOV-29 — YAML quebrado não derruba a tela**
Dado um `SCOPE.md` com YAML inválido;
Então o módulo aparece marcado como ilegível, o erro vai para log estruturado, e o resto da lista continua.
*Aceite:* nenhuma exceção sobe para a interface.

## Notas dos módulos

**UC-GOV-30 — a tela é de leitura** (R8)
Dado a lista de notas;
Quando procuro editar nota, peso de rubrica ou disparar avaliação;
Então não existe ação disso.
*Aceite:* caso negativo; rubrica muda por ADR 0154 (append-only).

**UC-GOV-31 ❌ — travessão não é zero** (A7)
Dado um módulo sem as dimensões D6–D9 avaliadas na v3;
Quando a linha aparece;
Então as colunas mostram travessão, e a média do projeto **não** conta esse módulo como zero nessas dimensões.
*Aceite:* nasce vermelho se a média usar zero; o teste de sub-dimensões cobre. ❌

**UC-GOV-32 — faixa filtra, dimensão não**
Dado as cinco faixas (excelente, bom, médio, crítico, embrião);
Quando aciono uma;
Então a lista filtra por faixa, com contagem em cada botão.
*Aceite:* não existe filtro por dimensão — o detalhe é no drill-down.

**UC-GOV-33 — o filtro não sobrevive ao recarregar** (R8)
Dado uma faixa aplicada;
Quando recarrego;
Então volta ao padrão.
*Aceite:* nada em `localStorage`.

**UC-GOV-34 — o gate de CI aparece na tela**
Dado o rodapé da lista;
Então ele explica que nota que cai bloqueia o merge e qual etiqueta libera a exceção, com os links do fluxo e da linha de base.
*Aceite:* somente links externos — sem botão, sem estado, e sempre abaixo da tabela.

**UC-GOV-35 — a nota é cara e chega depois** (R11)
Dado que o serviço faz I/O de filesystem em 34 módulos;
Quando abro a vista;
Então vejo esqueleto e a tela pinta antes das notas chegarem.
*Aceite:* nada de carregamento adiantado; alvo de primeira pintura abaixo de 2 s.

## Rastreabilidade

| Caso | Regra/Achado | Nasce vermelho |
|---|---|---|
| 01 | R1 | — |
| 02 | A1 | — |
| 03 | A2 | ❌ |
| 04, 05 | R2 | — |
| 06 | R10 | — |
| 07, 35 | R11 | — |
| 08, 10, 11 | R12 | — |
| 09 | A3 | ❌ |
| 12 | R5 | — |
| 13 | R6 | — |
| 14 | A4 | ❌ |
| 15, 16 | estado de tela | — |
| 17 | R2 | — |
| 18 | R3 | — |
| 19, 20 | R4 | — |
| 21 | estado de tela | — |
| 22 | R9 | — |
| 23, 24 | R7 | — |
| 25, 26, 28, 29 | R7 · paridade CLI | — |
| 27 | A5 | — |
| 30, 32, 33 | R8 | — |
| 31 | A7 | ❌ |
| 34 | gate de CI (ADR 0155) | — |
