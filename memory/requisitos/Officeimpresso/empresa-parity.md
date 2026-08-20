---
id: requisitos-officeimpresso-empresa-parity
titulo: "Paridade Blade↔React — Officeimpresso Empresa/Show (ficha + computadores)"
tipo: parity
status: active
owner: W
criado: '2026-08-20'
related:
  - RUNBOOK-empresa.md
  - ../_DesignSystem/PARITY-TEMPLATE.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0066-format-date-shift-3h-preservado-legacy-clientes'
---

# Paridade — Officeimpresso `Empresa/Show` (Onda 2, tela 4)

> Entregável da **F1/F2** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Os itens de severidade **alta** viram teste de comportamento em **F4** — *presença deste
> arquivo não conta pra nada* ([PARITY-TEMPLATE](../_DesignSystem/PARITY-TEMPLATE.md) §Enforcement).

## Metadados

- **Blade legado:** `Modules/Officeimpresso/Resources/views/licenca_computador/computadores.blade.php` (182 ln)
- **Controller:** `LicencaComputadorController::computadores()` **e** `::viewLicencas($id)` — as duas
  renderizam esta mesma view, com as mesmas 4 variáveis
- **Services:** `LicencaService::listarPorEmpresa()` · `Subscription::active_subscription()` · `Package::find()`
- **Tela React alvo:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresa/Show.tsx`
- **Rotas:** `GET /officeimpresso/computadores` · `GET /officeimpresso/licenca_computado/licencas/{id}`
  (typo histórico no path, nome `empresa.licencas` — **não corrigir nesta onda**) — as duas **intactas**,
  a React entra atrás da flag `useV2OfficeimpressoEmpresa`
- **Auditado em:** 2026-08-20 · **por:** Claude (leitura do Blade + controller + Service + entidade + schema)

> **Coluna "Está no React?"** nasce `⏳` em tudo — a F3 é que constrói a tela e preenche item por
> item. Este mapa é o **contrato de entrada** da F3, não relatório de algo pronto.

## Ficha da empresa

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 1 | Título "Licenças Office Impresso" + subtítulo "Gestão de desktops Delphi por empresa" | ⏳ | `computadores.blade.php:11-12` | média | UC-EMP-01 |
| 2 | Selo de estado no header do card: `officeimpresso_bloqueado` → "Bloqueada"/"Ativa" | ⏳ | `:19-23` | **alta** | UC-EMP-02 |
| 3 | Nome da empresa (`name`) como identidade da ficha | ⏳ | `:27` | **alta** | UC-EMP-03 |
| 4 | Razão social (`razao_social`) | ⏳ | `:28` | média | UC-EMP-03 |
| 5 | Endereço (`rua`) | ⏳ | `:29` | média | UC-EMP-03 |
| 6 | Telefone (`telefone`) | ⏳ | `:30` | média | UC-EMP-03 |
| 7 | Versão Obrigatória (`versao_obrigatoria`) | ⏳ | `:31` | **alta** | UC-EMP-04 |
| 8 | Versão Disponível (`versao_disponivel`) | ⏳ | `:32` | **alta** | UC-EMP-04 |
| 9 | Caminho Banco (`caminho_banco_servidor ?: '—'`) | ⏳ | `:33` | média | UC-EMP-03 |
| 10 | Último Acesso (`dt_ultimo_acesso ?: '—'`) | ⏳ | `:34` | média | UC-EMP-03 |
| 11 | Limite de Máquinas — **só renderiza se houver `package`**; `== 0` significa "Ilimitado" | ⏳ | `:36-40` | **alta** | UC-EMP-05 |
| 12 | Vencimento da assinatura — **só se houver `active`**; passa por `@format_date` | ⏳ | `:42-43` | **alta** | UC-EMP-06 |
| 13 | Dias restantes (`Carbon::today()->diffInDays($active->end_date)`) — **só se houver `active`** | ⏳ | `:44` | média | UC-EMP-06 |
| 14 | "Dias restantes" é **valor absoluto**: vencida há 10 dias mostra "10", não "-10" | ⏳ | `:44` + semântica do `diffInDays` | média | UC-EMP-06 |

## Ações da ficha

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 15 | "Ver pacote" → `SubscriptionController@index` (sai do módulo, destino Blade) | ⏳ | `:48-50` | média | UC-EMP-07 |
| 16 | "Editar" abre o modal de configuração | ⏳ | `:51-53` | **alta** | UC-EMP-08 |
| 17 | Toggle de bloqueio da EMPRESA (`business.bloqueado`) — rótulo "Bloqueada"/"Liberada" conforme o estado | ⏳ | `:54-62` | **alta** | UC-EMP-09 |
| 18 | Modal: campo `caminho_banco_servidor` | ⏳ | `:148-151` | **alta** | UC-EMP-08 |
| 19 | Modal: campo `versao_obrigatoria` | ⏳ | `:152-155` | **alta** | UC-EMP-08 |
| 20 | Modal: campo `versao_disponivel` | ⏳ | `:156-159` | **alta** | UC-EMP-08 |
| 21 | Modal: "Cancelar" descarta e **reseta o formulário** ao fechar | ⏳ | `:162` + `:178-180` | média | UC-EMP-08 |
| 22 | Flash de sucesso após salvar (`session('status')`), renderizado acima do header | ⏳ | `:174-176` + `businessupdate()` | média | UC-EMP-10 |

## Tabela de computadores — 10 colunas

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 23 | Header do card "Computadores (N)" com a contagem | ⏳ | `:71` | média | UC-EMP-11 |
| 24 | Link "Ver log da empresa" → `/officeimpresso/licenca_log?business_id={id}` | ⏳ | `:72-74` | **alta** | UC-EMP-12 |
| 25 | Col **#** — `id`, mono | ⏳ | `:96` | baixa | UC-EMP-13 |
| 26 | Col **Cadastro** — `dt_cadastro` | ⏳ | `:97` | média | UC-EMP-13 |
| 27 | Col **Máquina** — `user_win ?: '—'` | ⏳ | `:98` | média | UC-EMP-13 |
| 28 | Col **Executável** — `Str::limit(pasta_instalacao, 30)` **com `title` completo** | ⏳ | `:99` | média | UC-EMP-14 |
| 29 | Col **Versão** — `versao_exe ?: '—'` | ⏳ | `:100` | média | UC-EMP-13 |
| 30 | Col **IP** — `ip_interno ?: '—'` | ⏳ | `:101` | média | UC-EMP-13 |
| 31 | Col **Banco** — `Str::limit(caminho_banco, 20)` **com `title` completo** | ⏳ | `:102` | média | UC-EMP-14 |
| 32 | Col **Último Acesso** — `dt_ultimo_acesso ?: '—'` | ⏳ | `:103` | média | UC-EMP-13 |
| 33 | Col **Status** — pill "Bloqueada"/"Ativa" a partir de `bloqueado` | ⏳ | `:104-110` | **alta** | UC-EMP-15 |
| 34 | Col **Ações** — toggle da máquina (ícone só, sem rótulo) + link Log | ⏳ | `:111-122` | **alta** | UC-EMP-16 |
| 35 | Link Log da linha → `/officeimpresso/licenca_log?licenca_id={id}` | ⏳ | `:117-121` | **alta** | UC-EMP-12 |
| 36 | Vazio: `lang.no_records_found` = "Nenhum registro encontrado" | ⏳ | `:124-130` | média | UC-EMP-17 |
| 37 | **Não há busca, ordenação nem paginação** — esta tela não carrega DataTables | ⏳ | `:172-181` (o `@section('javascript')` só faz flash + reset do modal) | média | UC-EMP-18 |

## Guardas de acesso e escopo

| # | Feature | React? | Evidência | Severidade | Defendido por |
|---|---|---|---|---|---|
| 38 | 403 pra autenticado sem `superadmin` nem `officeimpresso.access` | ⏳ | `authorizeAccess()` | **alta** | `LicencasAcessoPermissionTest:115` ✅ |
| 39 | 200 pra quem tem `officeimpresso.access` | ⏳ | idem | **alta** | `LicencasAcessoPermissionTest:130` ✅ |
| 40 | Toggle da MÁQUINA exige `officeimpresso.licencas.gerenciar` | ⏳ | `authorizeGerenciar()` | **alta** | `LicencasAcessoPermissionTest:151` ✅ |
| 41 | Bloqueio da EMPRESA e edição exigem `officeimpresso.empresa.gerenciar` | ⏳ | `authorizeEmpresa()` | **alta** | `LicencasAcessoPermissionTest:197` ✅ |
| 42 | No-leak: gerir máquina **não** concede escopo empresa-inteira | ⏳ | guardas separadas | **alta** | `LicencasAcessoPermissionTest:179` ✅ |
| 43 | `computadores()` mostra a empresa do `business_id` da **sessão** | ⏳ | `computadores()` | **alta** (Tier 0) | UC-EMP-19 (F4) |
| 44 | `viewLicencas($id)` mostra a empresa do **`{id}` da URL, sem filtro** — mesma guarda `authorizeAccess()` | ⏳ | `viewLicencas($id)` | **alta** (Tier 0) | UC-EMP-20 (F4) — **depende da decisão [W] do §D1** |
| 45 | **Nenhum campo sensível chega ao cliente** (`senha`, `contra_senha`, `serial`, `token`) | ⏳ | `Licenca_Computador` sem `$hidden`; `$fillable` lista os 4 | **alta** (Tier 0) | UC-EMP-21 (F4) |

> ⚠️ **O item 45 não existe no Blade — é criado pela migração.** Não é paridade; é contramedida a um
> risco que **só a Inertia introduz** (a prop inteira é serializada no `data-page` do HTML). Mesmo
> mecanismo do item 29 do `licencas-parity.md`, porque as duas telas consomem o
> mesmo Service.

## Divergências deliberadas (não são regressão)

| # | O que muda | Por quê | Risco |
|---|---|---|---|
| **D1** | **Escopo do `viewLicencas` — decisão [W] PENDENTE, e precede a F2** | As duas rotas têm a mesma guarda (`authorizeAccess()`) e escopos diferentes: `computadores()` usa a sessão, `viewLicencas($id)` aceita qualquer id **sem filtrar**. Efeito no código de hoje: quem tem `officeimpresso.access` abre a ficha de qualquer empresa trocando o id da URL. Pode ser **intencional** (a WR2 é fornecedora dos clientes, e as telas de `Logs/` têm cross-empresa deliberado) — mas lá existe um `podeVerTodasEmpresas()` explícito e **aqui não há guarda nomeando a escolha** | **Não decidir sozinho.** As opções: (a) manter e nomear a intenção numa guarda explícita; (b) escopar a quem tem permissão de ver todas. A escolha define o que a fixture do item 44 assere |
| D2 | `toggle-block` e `business.bloqueado` saem de **GET** pra **POST**, com confirmação | São `Route::get` que **mudam estado** — e o bloqueio da empresa derruba **todos** os desktops do cliente de uma vez. Prefetch, crawler ou `<img src>` disparam | Durante o dual-run as duas rotas coexistem; as GET só saem no F5 |
| D3 | O `businessupdate` passa a validar `caminho_banco_servidor` | Hoje valida `caminho_banco` — nome que o formulário **não manda** — enquanto salva `caminho_banco_servidor`. Como a regra é `nullable`, nada quebra; a validação simplesmente não protege o campo que de fato é salvo | Baixo, e o sentido é o certo: passa a validar o dado real |
| D4 | A tabela de computadores **ganha** busca, ordenação e paginação via `<DataTable>` | Hoje não tem nenhuma das três (item 37). É **capacidade nova**, declarada de propósito em vez de acontecer por acaso ao usar o componente do DS | Nenhum — client-side, como nas telas irmãs |
| D5 | O flash de **erro** passa a aparecer | O controller já devolve `with('error', ...)` nos dois `catch`, e o Blade **só renderiza o `status`** — hoje a falha ao salvar é silenciosa | Nenhum; corrige um buraco de feedback |
| D6 | O modal Bootstrap 3 + `Form::` vira `<Dialog>` do DS + `useForm` | Camada 1 da Constituição UI v2. **Não** é drawer 760 ([ADR 0185](../../decisions/0185-drawer-760-canon-entidades-cadastrais.md) é pra entidade cadastral; aqui são 3 campos de configuração) | Nenhum — preservar o reset ao fechar (item 21) |
| D7 | `StatusBadge kind="empresa"` **novo**, distinto do `kind="licenca"` | O sujeito aqui é a EMPRESA (`officeimpresso_bloqueado`), não a máquina. Reusar o mesmo `kind` faria dois domínios compartilharem rótulo | Nenhum |
| D8 | As classes `oi-*` e o `design-system.blade.php` do módulo não atravessam | Módulo não tem DS próprio | Mapa `oi-*` → canon em [RUNBOOK-logs §4](RUNBOOK-logs.md) |

## O que NÃO muda, e é tentador mudar

- **`@format_date` no vencimento (item 12) mantém o shift +3h** — é customização preservada de
  propósito ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)).
  A React não formata a data por conta própria pra "consertar" o deslocamento.
- **"Dias restantes" segue em valor absoluto (item 14)** — portar como está; corrigir é decisão [W].
- **`officeimpresso_numerodemaquinas`** está no `$request->only()` do controller e **não existe** no
  formulário. Não inventar o input — seria capacidade nova.
- **O typo do path** (`licenca_computado`) fica. É rota nomeada e o `businessall` aponta pra ela.

## Pendências que este mapa NÃO fecha

- Os UC citados (`UC-EMP-*`) **ainda não existem** — nascem no `Show.casos.md` ao lado do `.tsx`, no
  PR da F3. A coluna "Defendido por" é o **contrato** de quais UC a F3 tem que criar.
- **23 de 45 itens são severidade alta:** 2, 3, 7, 8, 11, 12, 16, 17, 18, 19, 20, 24, 33, 34, 35,
  38, 39, 40, 41, 42, 43, 44, 45. Contados com
  `grep -cE '^\| [0-9]+ \|.*\*\*alta\*\*' empresa-parity.md` (o `-c` sobre a linha inteira, porque
  43, 44 e 45 escrevem `**alta** (Tier 0)`).
- **Já têm teste rodando 5**: 38, 39, 40, 41, 42 — via `LicencasAcessoPermissionTest`, na allowlist
  da lane `officeimpresso-pest`. **Faltam 18** — são a US-OI-013 (F4).
- **Decisão [W] bloqueante:** D1 (escopo do `viewLicencas`) — precisa vir **antes da F2**, porque
  define o que a fixture do item 44 assere.

---

**Última atualização:** 2026-08-20 — criado na F1 da Onda 2.
