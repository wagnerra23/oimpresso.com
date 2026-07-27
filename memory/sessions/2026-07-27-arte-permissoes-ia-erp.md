---
date: "2026-07-27"
topic: "Estado da arte — modelo de permissões para assistente de IA embutido em ERP/CRM (PME, foco usabilidade)"
authors: [C]
module: Jana
tags: [permissoes, rbac, ia, jana, usabilidade, pme, lgpd, spatie]
pii: false
---

# Estado da arte — permissões de IA embutida em ERP/CRM (PME)

> **Pergunta do dono:** quantos níveis de permissão a Jana precisa, quais, e o que cada um enxerga.
> **Método:** Fase 1 pesquisa limpa (sem ler `memory/` antes) → Fase 2 compara com o repo vivo → Fase 3 avalia gaps.
> **Disciplina aplicada:** [proibicoes §5 2026-07-16](../proibicoes.md) — cada recomendação importada traz, na mesma frase, **por que o problema deles é o nosso** (ou por que não é).

---

## 1. Como os melhores fazem (Fase 1)

| Player | Mecanismo concreto | Tradução de premissa → vale aqui? |
|---|---|---|
| **Microsoft 365 Copilot / Dynamics 365 BC** | **Security trimming**: a IA consulta via Microsoft Graph, que já resolve ACL por documento + token do usuário. Zero permissão de conteúdo própria; a IA "não pode ler mais do que o usuário já lê". Acesso ao *produto* é **licença/seat**, não permissão granular. | **Premissa deles:** existe ACL **por objeto** (arquivo, item, e-mail) num único gateway (Graph). **Aqui NÃO existe** — o oimpresso tem ACL por *feature* (Spatie, 260+) + filtro `created_by` escrito à mão dentro de cada Controller (`view_own_sell_only`). Não há gateway único que a Jana possa atravessar e ganhar trimming "de graça". **A solução não transplanta inteira** — o *princípio* (IA não vê mais que o usuário) transplanta; o *mecanismo* (herdar de graça) não. |
| **Slack AI** | Mesma doutrina ("nunca mostra o que a busca normal não mostraria"), mas o **admin control é grosso**: nos planos abaixo de Enterprise Grid o toggle é binário **Everyone / No one** por feature; só no Grid tem por-grupo. ~13 toggles no total, todos de *feature*, nenhum de *dado*. | **Premissa deles:** o dado (mensagem) já carrega visibilidade herdada do canal. Aqui o dado (venda, cliente) não carrega ACL própria. **O que transplanta é o formato do controle**: toggles de *feature* poucos e grossos, decididos por admin, não permissões atômicas por usuário. Para uma loja com poucos usuários, "Everyone/No one" é literalmente o nível certo. |
| **HubSpot (Breeze)** | Modelo em **2 andares**: (1) Super Admin liga ~3 chaves de conta (`generative AI`, `Breeze Assistant`, `Breeze Studio`); (2) quem usa é definido por **tipo de seat** (Core Seat sim, View-Only não). Não existe permissão de IA por usuário. | **Premissa deles:** cobrança por seat já cria a fronteira de quem pode usar. Aqui **não temos seat** — mas temos o equivalente estrutural: as 3 camadas de habilitação (pacote superadmin → recursos do negócio → role). **Transplanta o formato "andar de conta + andar de pessoa"**, não o seat. |
| **Zoho Zia / Salesforce Agentforce** | IA herda RBAC do CRM ("agente = funcionário digital com as permissões do usuário"); a permissão *própria* que existe é **de habilitação/licença** (`Einstein Generative AI User`, `Access Agentforce Default Agent`, `Prompt Template User`) — ou seja, permissão para **usar a ferramenta**, nunca para **ver o dado**. | **Premissa deles:** o CRM tem sharing model por registro (owner/role hierarchy) que a IA consulta. Aqui isso existe **parcialmente** (`view_own_sell_only`, `so.view_own`, `access_own_shipping`) e **não é central**. **Transplanta a separação de eixos**: permissão de *ferramenta* ≠ permissão de *dado*. É o achado mais reaproveitável da pesquisa. |
| **Notion AI (Custom Agents)** | Doutrina de **default fechado + escalada explícita**: agente novo nasce **sem** acesso ao workspace; dar acesso amplo exige o criador aceitar um **modal de aviso**. Admin tem diretório de agentes + audit log. | **Premissa deles:** o agente é *criado por usuário* e pode virar um artefato compartilhado — daí o risco de um agente com privilégio maior que quem o invoca. **Aqui NÃO temos agentes criáveis pelo usuário** — a Jana é uma só, invocada sempre por um humano logado. O modal e o diretório **não transplantam**. O que transplanta é só o *default fechado*. |
| **Padrão de indústria 2026 (Microsoft Security, Okta, Cerbos)** | Convergência: **delegated user access** (agente age *em nome de* um usuário nomeado, herda as permissões dele) para tarefas interativas; **identidade própria do agente** só para workflows autônomos repetíveis. Dupla checagem (agente E humano delegante) onde o risco justifica. | **Vale aqui, e é a régua:** a Jana hoje é interativa (usuário digita, ela responde) → o modelo correto é **delegated**, não identidade própria. Se houver Jana rodando sozinha (job de brief, alerta proativo), aí é o outro caso — e o brief diário **já é** esse outro caso (roda em job, sem usuário na frente). |
| **Histórico de chat sob GDPR/LGPD** | Ninguém expõe "ler conversa do colega" como permissão de produto. No M365 o prompt/resposta vive numa pasta oculta da **caixa do próprio usuário**; o acesso de terceiro é por **eDiscovery/Purview** (caminho de compliance, com case, log e retenção), não por checkbox de papel. | **Premissa deles:** existe uma trilha jurídica separada (legal hold, e-discovery) com dono e auditoria. **Aqui não existe** e não vale construir. **O que transplanta é a fronteira**: leitura de conversa alheia **não é permissão de operação do dia-a-dia** — se um dia existir, é caminho excepcional e **auditado**, nunca uma checkbox a mais na tela de papéis. |

### O eixo central que a pesquisa isola

Todos os sete separam **dois eixos que nós hoje temos misturados**:

| Eixo | O que decide | Quem configura | Granularidade típica |
|---|---|---|---|
| **A — habilitação da ferramenta** | "Esta pessoa pode falar com a IA?" | admin da conta | **grossa** (liga/desliga, 1-3 chaves) |
| **B — alcance do dado** | "O que a IA pode ler ao responder?" | **ninguém — é derivado** | **herda** das permissões que o usuário já tem no ERP |

Nenhum deles cria permissão nova no eixo B. Criar permissão de IA por tipo de dado ("Jana pode ver financeiro", "Jana pode ver estoque") é o **anti-padrão** que a pesquisa não encontrou em nenhum líder — porque duplica o eixo B e as duas cópias divergem.

---

## 2. Compara — o que o oimpresso tem hoje (Fase 2, medido no repo)

Tudo abaixo foi lido no código desta worktree. Onde não medi, digo.

| Dimensão | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| **Eixo A (habilitar ferramenta)** | 1-3 chaves grossas, admin liga | `jana.access` + `jana.chat` declaradas — mas o grupo `/ia` **não tem nenhum `can:`** ([`Modules/Jana/Http/routes.php:28-173`](../../Modules/Jana/Http/routes.php)); as chaves só escondem o item do menu. Única rota gateada do grupo: `/admin/jana-pro/preview` → `can:copiloto.superadmin` (L167). | **curta** — as chaves certas já existem, falta ligar 1 middleware |
| **Eixo B (alcance do dado)** | herda ACL do usuário | **NÃO herda.** `ContextSnapshotService` scopa só por `business_id` (L29/41/48/86/104/161) — dois usuários do mesmo business recebem contexto **idêntico**, mesmo que um deles seja `view_own_sell_only` na tela de vendas. | **longa** — é o gap real |
| **Isolamento entre tenants** | tabela-stakes | `business_id` global scope Tier 0, `Conversa` com `HasBusinessScope`, `TenancyLeakTest` existente. | **zero / bate o mercado** |
| **Privacidade da conversa** | conversa é do usuário; terceiro só por compliance | `abort_unless($conversa->user_id === auth()->id(), 403)` nos 4 pontos de leitura/escrita ([`ChatController.php:79,303,316,369`](../../Modules/Jana/Http/Controllers/ChatController.php)); listagem filtra `user_id`. **Não existe caminho algum** de ler conversa alheia — nem para admin. | **zero — mais restrito que o mercado.** É uma posição defensável, não um gap |
| **Fadiga de permissão** | poucos toggles, papéis prontos | 36 módulos registram `user_permissions()`; a tela `/roles/{id}/edit` acumula 260+ checkboxes. Jana já contribui com 5 visíveis + o resto do namespace (`Modules/Jana/Resources/permissions.php` declara **27 chaves**, a maioria MCP/CC — invisíveis pro cliente, mas no mesmo grupo conceitual). | **longa (para trás)** — já estamos além do ponto de dano |
| **Metadados de risco / dependência** | raro no mercado | **temos e o mercado geralmente não**: `PermissionRegistry` com `risk` (low/medium/high/critical) + grafo `requires`. Só 3 módulos aderiram (Jana, KB, NFSe). | **curta, e à frente** — ativo subutilizado |
| **Visibilidade de custo de IA** | dashboard admin (Copilot Governance, OpenAI Admin API) | `jana.admin.custos.view` + tela `/ia/admin/custos` scopada por business. | **zero** |

### O fato que mais muda o desenho (e quase todo mundo esquece)

[`app/Providers/AuthServiceProvider.php:34-47`](../../app/Providers/AuthServiceProvider.php):

```php
Gate::before(function ($user, $ability) {
    // ... else:
    if ($user->hasRole('Admin#'.$user->business_id)) { return true; }
});
```

**Quem tem o papel `Admin#{biz}` passa em TODA permissão, sempre.** Larissa é a admin da biz=4 → **nenhuma permissão que criarmos afeta a Larissa**. Isso inverte o alvo do desenho: permissão de IA não é para o dono da PME configurar *para si* — é **só** para os funcionários dele. Numa loja com poucos usuários, o número de pessoas efetivamente afetadas por cada checkbox nova é pequeno, e o custo de configurá-la é integral. Essa assimetria é o argumento mais forte contra granularidade.

### Achado colateral (hipótese, NÃO medida em runtime)

A migration `2026_05_09_140000_rename_copiloto_permissions_to_jana.php` renomeou `copiloto.*` → `jana.*` no banco. `routes.php:167` ainda usa `can:copiloto.superadmin`, e `tests/Feature/Modules/Copiloto/TenancyLeakTest.php:217` ainda dá `givePermissionTo('copiloto.superadmin')`. **Se** a permission row não existir mais com o nome antigo, esse `can:` seria falso para todos — mascarado pelo `Gate::before` (o admin passa mesmo assim), o que faria a rota parecer funcionar. **Não medi**: não rodei nada contra o banco (testes só no CT 100). O que provaria: `SELECT name FROM permissions WHERE name LIKE 'copiloto.%'`, ou um Pest com usuário não-admin. Registro como hipótese porque a lápide [§5 2026-07-15](../proibicoes.md) proíbe apresentar isso como achado sem o vermelho.

---

## 3. Avalia — gaps rankeados (Fase 3)

| # | Gap | Impacto | Esforço (IA-pair, [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) | Pré-req bloqueante? |
|---|---|---|---|---|
| 1 | **`/ia` sem `can:`** — quem sabe a URL entra mesmo sem `jana.access` | **alto** (a permissão existe e mente: aparenta controlar e não controla) | ~30-45 min (1 middleware no grupo + Pest com user não-admin) | não |
| 2 | **Jana não herda ACL do usuário no contexto** — um vendedor `view_own_sell_only` vê o faturamento da loja inteira pela Jana | **alto** (é o único vazamento *dentro* do tenant) | ~4-8 h (mapear quais blocos do snapshot dependem de qual permissão + gate por bloco) | depende de decidir a política do §4 antes |
| 3 | **Rota admin com chave possivelmente morta** (`copiloto.superadmin`) | médio | ~20 min (após confirmar com Pest no CT 100) | depende do gap 1 (mesmo PR) |
| 4 | **Ausência de papel pré-definido** — não há "role template" que já venha com o conjunto certo | médio (é o antídoto contra fadiga, não contra vazamento) | ~2-3 h | depende do §4 |
| 5 | 22 chaves MCP/CC no mesmo grupo visual "Copiloto" da tela de papéis | baixo (ruído, não risco) | ~1 h (mover pra grupo "MCP / Time interno") | não |

---

## 4. Proposta concreta — **3 permissões, não 15**

Desenhada para a Larissa marcar em 30 segundos, sabendo o que cada marca faz.

| # | Chave | Rótulo na tela (PT-BR, sem jargão) | O que libera **de fato** | Risco |
|---|---|---|---|---|
| 1 | `jana.access` *(já existe)* | **Usar a Jana** | Entra em `/ia`, conversa, vê o próprio histórico, vê o brief. **Sem esta, 403 na rota** (hoje só some do menu). | low |
| 2 | `jana.metas.manage` *(já existe)* | **Definir metas e alertas do negócio** | CRUD de metas/fontes/alertas — configura o que a Jana cobra de todo mundo. | medium |
| 3 | `jana.admin.custos.view` *(já existe)* | **Ver quanto a IA está custando** | `/ia/admin/custos` do próprio business. | high |

**Nenhuma permissão nova.** As três que ficam já existem, já estão declaradas com `risk` e `requires`, e já têm rótulo. O trabalho é **subtrair e ligar**, não somar.

### O que sai

- **`jana.chat` — funde em `jana.access`.** Hoje são duas chaves para uma decisão só: não vejo uso legítimo para "acessa a Jana mas não pode conversar" — o chat é o produto. Manter as duas é pedir para a Larissa acertar uma combinação sem significado. (Se algum dia existir um modo consulta-sem-chat, a chave volta com um caso de uso real por trás.)
- **`jana.superadmin` — sai da tela do cliente.** É permissão de plataforma (nossa, biz=1), não do negócio dele. Continua existindo; muda de grupo visual.
- **As 22 chaves MCP/CC** — mesmo tratamento: grupo separado "MCP / Time interno".

Resultado para a Larissa: o grupo "Jana" na tela de papéis passa de **~5 visíveis (de 27 no namespace)** para **3**, e cada uma responde uma pergunta que ela consegue responder sobre um funcionário.

### O eixo B (o que a Jana lê) — a recomendação

**Herdar, sem criar permissão para isso.** Concretamente: `ContextSnapshotService` passa a receber o `User` e a montar o snapshot **bloco a bloco**, cada bloco condicionado à permissão que já governa a tela equivalente:

| Bloco do contexto | Condição (permissão que **já existe** no ERP) |
|---|---|
| faturamento / vendas agregadas | `sell.view` (ou `direct_sell.view`); com `view_own_sell_only` → filtra `created_by = user.id` |
| contas a pagar/receber, DRE | permissão do Financeiro correspondente |
| estoque / produtos | `product.view` |
| clientes / contatos | `customer.view` |
| metas | `jana.access` (a meta é do negócio, não de um usuário) |

Bloco sem permissão **não entra no prompt** — não é filtrado depois. Isso é o *princípio* do security trimming aplicado ao nosso mecanismo (Controllers), não o mecanismo deles (Graph). Custo honesto: é trabalho manual por bloco, e nasce incompleto — mas é a única forma que não inventa um segundo modelo de permissão paralelo ao do ERP.

**Ordem sugerida:** gap 1 primeiro (barato, alto impacto, sem pré-req), gap 2 depois com escopo pequeno — só o bloco de **vendas/faturamento**, que é onde `view_own_sell_only` já é um contrato real e verificável.

---

## 5. O que explicitamente NÃO fazer

1. **Não criar permissão de IA por tipo de dado** (`jana.ver.financeiro`, `jana.ver.estoque`). Nenhum dos sete líderes faz. Cria um segundo modelo de permissão paralelo ao do ERP, e os dois divergem no primeiro mês — a tela de vendas passa a dizer uma coisa e a Jana outra. O eixo B **herda**, não se declara.
2. **Não criar permissão "ver conversa de outro usuário".** No mercado isso não é permissão de produto — é caminho de compliance (eDiscovery), com case e auditoria. O estado atual (`abort_unless user_id === auth()->id()`, sem exceção nem para admin) é mais restrito que o mercado e **defensável**; abrir uma checkbox aqui converte um limite claro em decisão recorrente do admin, e sob LGPD isso é pior. Se um dia for necessário (investigação, incidente), o caminho é comando artisan auditado + registro, não papel.
3. **Não usar retenção/expurgo como controle de dado sensível** — regra do dono ([W] 2026-07-27: num ERP não se apaga PII). Coerente com o desenho: o controle é acesso. Nota de precisão: já existe `RetentionPurgeCommand` no módulo e um `EVIDENCE-retention-purge-dry-run-2026-07-12.md` — **não medi** o que ele apaga nem se conflita com a regra; vale conferir antes de tratá-lo como alinhado.
4. **Não confiar em permissão para conter o dono da PME.** `Gate::before` faz `Admin#{biz}` passar em tudo. Qualquer desenho que dependa de "a Larissa não pode X" está errado por construção. Só faz sentido desenhar contra **funcionários**.
5. **Não copiar o modal de aviso do Notion nem o diretório de agentes.** A premissa deles (usuário cria agentes que viram artefatos compartilhados com privilégio próprio) não existe aqui — a Jana é uma só, sempre invocada por um humano logado. Importar isso seria a lápide [§5 2026-07-16](../proibicoes.md) em pessoa.
6. **Não usar "número máximo de papéis" da literatura de RBAC como régua.** A literatura de role explosion é sobre organizações com centenas/milhares de papéis e mais papéis que usuários — a ROTA LIVRE tem poucos usuários. O problema aqui **não é contagem de papéis; é contagem de checkboxes por papel** (260+ numa tela só). O número que importa é o segundo, e ele já está estourado.

---

## 6. Onde tenho incerteza (não medi)

- **Quantos usuários a ROTA LIVRE tem, e quantos são não-admin.** Isso decide se o eixo A tem alguém para conter. Não consultei o banco. Se todos os usuários da biz=4 forem `Admin#4`, as três permissões são decorativas lá e o valor real está nos clientes futuros.
- **Se `copiloto.superadmin` ainda resolve** (§2, hipótese). Exige Pest com usuário não-admin no CT 100.
- **Se `ContextSnapshotService` é a única porta de dado da Jana.** Li o snapshot; **não varri** as tools do agente nem o pipeline de recall (`Services/Memoria/*`) — pode haver outra porta que também só scopa por `business_id`. **Não contei os consumidores**; antes de fechar o gap 2, rodar a varredura completa (a lápide §5 2026-07-15 exige o número, não a impressão).
- **Se `RetentionPurgeCommand` conflita com a regra "não se apaga PII"** (§5.3).
- **Quantas das 260+ permissões estão de fato marcadas em algum papel da biz=4** — a fadiga real é medível (`role_has_permissions`), e eu estimei pelo tamanho da tela, não pelo uso.

---

## Recomendação final

**Comece pelo gap 1: gatear o grupo `/ia` com `can:jana.access`.** Alto impacto (hoje a permissão mente — aparenta controlar e não controla), ~30-45 min IA-pair, sem pré-requisito bloqueante, e é pré-condição honesta para qualquer conversa sobre o eixo B: não faz sentido discutir *o que a Jana lê* enquanto *quem entra na Jana* não é decidido por permissão nenhuma.

**Próxima ação hoje:** abrir 1 PR com (a) `->middleware('can:jana.access')` no grupo da L28 de [`Modules/Jana/Http/routes.php`](../../Modules/Jana/Http/routes.php), (b) um Pest com **usuário não-admin da biz=1** (nunca biz=4 — [ADR 0101](../decisions/0101-tests-business-id-1-nunca-cliente.md)) provando 403 sem a permissão e 200 com ela, rodado no CT 100. Esse mesmo teste, ao usar um usuário não-admin, é o instrumento que resolve de quebra a hipótese do `copiloto.superadmin` (§2).

A fusão `jana.chat` → `jana.access` e a mudança de grupo das 22 chaves MCP são **decisão do [W]**, não consequência da pesquisa — e viram PR separado, depois.

---

## Fontes (Fase 1)

- [Security for Microsoft 365 Copilot — Microsoft Learn](https://learn.microsoft.com/en-us/microsoft-365/copilot/security-microsoft-365-copilot) · [Security and authentication for Copilot APIs](https://learn.microsoft.com/en-us/microsoft-365/copilot/extensibility/copilot-apis-security-authentication) · [Copilot FAQ — Business Central](https://learn.microsoft.com/en-us/dynamics365/business-central/copilot-overview)
- [Manage access to AI features in Slack](https://slack.com/help/articles/28244420881555-Manage-access-to-AI-features-in-Slack) · [Security for AI features in Slack](https://slack.com/help/articles/28310650165907-Security-for-AI-features-in-Slack)
- [HubSpot — Set up an account to adopt AI features](https://knowledge.hubspot.com/ai/set-up-a-hubspot-account-to-adopt-ai-features) · [HubSpot user permissions guide](https://knowledge.hubspot.com/user-management/hubspot-user-permissions-guide)
- [Zoho CRM — AI governance / Zia Agents](https://www.zoho.com/agents/resources/help/conceptual-articles/ai-governance.html) · [Salesforce — Give Users Access to Agentforce](https://help.salesforce.com/s/articleView?id=ai.copilot_setup_user_access.htm&language=en_US&type=5)
- [Notion — Custom Agents sharing and permissions](https://www.notion.com/help/custom-agents-sharing-and-permissions) · [Notion AI security & privacy practices](https://www.notion.com/help/notion-ai-security-practices)
- [Least privilege for AI agents: identity, access, and tool binding — Microsoft Security Blog](https://www.microsoft.com/en-us/security/blog/2026/07/16/least-privilege-for-ai-agents-identity-access-and-tool-binding/) · [Okta — How to implement least privilege for AI agents](https://www.okta.com/identity-101/how-to-implement-least-privilege-for-ai-agents/) · [Cerbos — Access control for RAG LLMs](https://www.cerbos.dev/blog/access-control-for-rag-llms)
- [Microsoft Purview — data security & compliance for Copilot](https://learn.microsoft.com/en-us/purview/ai-m365-copilot) · [Collecting Copilot data with Purview eDiscovery](https://techcommunity.microsoft.com/blog/microsoft-security-blog/collecting-microsoft-365-copilot-data-with-microsoft-purview-ediscovery/4516489)
- [Evolveum — Role Explosion](https://docs.evolveum.com/iam/role-explosion/) · [Proton — Principle of least privilege for SMBs](https://proton.me/business/blog/principle-of-least-privilege)
- [Intercom Fin — workspace settings / roles](https://fin.ai/help/en/articles/10911239-your-workspace-settings)
