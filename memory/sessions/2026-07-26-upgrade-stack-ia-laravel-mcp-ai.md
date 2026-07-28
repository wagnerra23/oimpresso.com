---
date: "2026-07-26"
topic: "Upgrade da stack IA em 2 passos (laravel/mcp 0.7→0.9.1 · laravel/ai 0.6.3→0.10.1 + php ^8.3), ambos em prod — zero mudança nos agents da Jana, 3 correções achadas pelo CI, e a constatação de que o trabalho não tinha sinal de cliente (ADR 0105)"
authors: [C]
prs: [4800, 4805]
outcomes:
  - "laravel/mcp 0.7.0→0.9.1 (#4800): o bump NÃO era isolado — laravel/boost v2.4.5 prendia o mcp em ^0.7.0 e v2.4.13 é a 1ª versão que aceita ^0.9.0 (verificado versão a versão no Packagist). Escopo com --minimal-changes: 6 pacotes; sem a flag: 45."
  - "laravel/ai 0.6.3→0.10.1 + php ^8.1→^8.3 (#4805): delta de 2 pacotes. A constraint de PHP subiu porque o SDK 0.10 exige ^8.3 e o composer.json prometia ^8.1 — Hostinger medido por SSH em 8.4.19 (CLI e web) ANTES de mexer."
  - "Zero mudança nos agents da Jana: eles já implementam Laravel\\Ai\\Contracts\\Agent, HasTools, HasStructuredOutput, HasProviderOptions, trait Promptable e os atributos Provider/Model/MaxSteps. A ADR 0048 rejeitou a Vizra PARA adotar o SDK oficial, não para construir paralelo — premissa que eu tinha errado na abertura da sessão."
  - "3 correções que o CI pegou e o changelog não previa: 4× orderBy('...','DESC') que o framework 13.22 passou a rejeitar por tipo (varredura contada, 4 de 4), e o check de PHP do InstallController que reprovava PHP 8.0 desde o UltimatePOS — em 2 rodadas, porque a 1ª correção também era tautológica sob ^8.3. Baseline do PHPStan não regenerado nas 2 vezes."
  - "Testes no CT 100 com MySQL real, comparação NOMINAL por nome de teste: #4800 17/198/60 idêntico ao baseline; #4805 5/174/142 (1957 assertions) idêntico. Zero falhas novas nos dois."
  - "Ressalva: o trabalho não tinha sinal de cliente (ADR 0105) e entrega zero funcionalidade. Ganho concreto = 1 bug dormente + 4 higienes + saída de 4 minors de dívida 0.x. O ganho real (subtração de ~800 linhas do LaravelAiSdkDriver que o 0.10 habilita) segue por fazer."
  - "Recomendação registrada: NÃO fazer nWidart 10→13 agora — troca o carregamento dos 36 módulos e Vestuario (ROTA LIVRE, 99% do volume) é 1 dos 4 sem composer.json próprio."
  - "2 instâncias de LC-08 minhas catalogadas no ledger: grep -c $'\\r' casa string vazia (contou todas as linhas e quase 'consertei' um CRLF inexistente) e gh run watch --exit-status não distingue cancelled de failure (reportei 'deploy falhou' para um cancelamento de fila)."
related_adrs:
  - 0063-prevenir-composer-lock-drift
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0035-stack-ai-canonica-wagner-2026-04-26
---

# Sessão 2026-07-26 — upgrade da stack IA (laravel/mcp + laravel/ai)

> **TL;DR** — 2 PRs mergeados e em prod ([#4800](https://github.com/wagnerra23/oimpresso.com/pull/4800), [#4805](https://github.com/wagnerra23/oimpresso.com/pull/4805)). Zero feature nova. O que a sessão realmente produziu foi **método**: como medir o escopo real de um upgrade, e como não fabricar regressão inexistente na comparação. Handoff: [`2026-07-26-2300-upgrade-stack-ia-mcp-ai.md`](../handoffs/2026-07-26-2300-upgrade-stack-ia-mcp-ai.md).

## Como começou

Pergunta de conhecimento do [W]: *"como trabalhar com módulos no laravel? saiu algo novo que eu posso usar?"* — seguida de *"medir a superfície, eu quero saber sobre IA já deve ter algo melhor integrado com módulo"*.

A medição inicial já corrigiu uma premissa minha: eu descrevi os agents da Jana como "construídos à mão depois de rejeitar a Vizra" e ofereci o SDK oficial como alternativa. Medido no código: **eles já SÃO do SDK oficial** — `implements Laravel\Ai\Contracts\Agent`, `use Promptable`, `#[Provider]`/`#[Model]`. A [ADR 0048](../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) rejeitou a Vizra *para adotar o `laravel/ai`*, não para construir paralelo. Eu tinha derivado da leitura do CLAUDE.md em vez de medir o código — LC-08 na origem da conversa.

## Inventário medido (vale como referência)

| Métrica | Valor |
|---|---|
| Imports de `Laravel\Ai` / `Laravel\Mcp` | **128** em 56 arquivos |
| Tools MCP próprias | **45** (39 `Jana` · 4 `TeamMcp` · 1 `Brief` · 1 `ADS`) |
| Assinaturas uniformes | 44× `handle(Request): Response` · 42× `schema(JsonSchema): array` |
| `LaravelAiSdkDriver` | **839 linhas**, 5 métodos públicos |
| `Services/Ai` + `Services/Memoria` | **5.866 LOC** |
| Transport MCP customizado / uso do MCP client | **0 / 0** (100% server-side) |
| nWidart: `->enabled()`/`->disabled()` | **0** (já migrado pro `isEnabled()`) |
| nWidart: módulos com `composer.json` próprio | **32 de 36** (faltam `Admin`, `Arquivos`, `Brief`, `Vestuario`) |

## Os dois passos

### #4800 — `laravel/mcp` 0.7.0 → 0.9.1

O achado: **o bump não era isolado.** `laravel/boost` v2.4.5 restringe o mcp a `^0.5.1|^0.6.0|^0.7.0`. Testando versão a versão via API do Packagist, **v2.4.13 é a primeira que aceita `^0.9.0`** — 2.4.7, 2.4.8, 2.4.9 e 2.4.12 não servem. Por isso a constraint do boost subiu de `^2.4` para `^2.4.13`: `^2.4` permitiria um `composer install` futuro resolver 2.4.5 e reintroduzir o conflito.

Escopo: **6 pacotes** com `--minimal-changes` (sem a flag: **45**). Incluiu `laravel/framework` 13.6 → 13.22, arrastado pelo boost.

Minha previsão de "superfície zero" estava **errada**. O breaking documentado do 0.9 (`Transport::setProtocolVersion`) de fato não atinge o projeto, mas o framework arrastado junto estreitou `Builder::orderBy()` — `$direction` passou a aceitar só `'asc'|'desc'|SortDirection`. Quatro sites usavam maiúsculo e derrubaram o `PHPStan ratchet` (required).

### #4805 — `laravel/ai` 0.6.3 → 0.10.1 + `php` `^8.1` → `^8.3`

Com o mcp 0.9 no lugar: **2 pacotes** (`laravel/ai` + `jmespath`). **Zero mudança nos agents.**

A constraint de PHP: `laravel/ai` 0.10.1 exige `^8.3`, o projeto prometia `^8.1`. O composer resolvia pela plataforma real (8.4) e não acusava, mas o `composer.json` mentia. Confirmado por SSH que Hostinger roda **8.4.19** (CLI e web) antes de mexer — não assumido do CLAUDE.md.

Efeito colateral: apertar a constraint apertou a inferência do PHPStan, que passou a saber que `PHP_MINOR_VERSION` é `int<3,5>` e acusou `always true` no `InstallController`. O check legado era **genuinamente quebrado**: `MAJOR >= 7 && MINOR >= 1` reprovava PHP 8.0 (minor `0 >= 1` é false). Minha 1ª correção (`PHP_VERSION_ID >= 80300`) **também** foi acusada — e corretamente: sob `^8.3` qualquer comparação com o mínimo é tautológica, porque o composer garante antes do app bootar. Terminou como `$output['php'] = true` com a explicação no código, sem `@phpstan-ignore`.

## Testes (CT 100 · `oimpresso-staging` · MySQL real)

| PR | Baseline | Upgrade | Diff nominal |
|---|---|---|---|
| #4800 | 17 failed, 198 skipped, 60 passed | 17 failed, 198 skipped, 60 passed | 0 novas, 0 resolvidas |
| #4805 | 5 failed, 174 skipped, 142 passed (1957 assert) | 5 failed, 174 skipped, 142 passed (1957 assert) | 0 novas, 0 resolvidas |

As falhas são pré-existentes e de ambiente (tabela `mcp_memory_documents` ausente no staging · `ProcessFailedException` no FlagTool), idênticas nos dois estados. Os 174 skipped do #4805 são **`sqlite-only`**, não falta de API key; dos 142 que passaram, **13 exercitam a API de `Agent` diretamente** (`instructions`/`messages`/`model`/`provider`/structured output) — o contrato que 4 minors poderiam quebrar.

## Lições de método (o valor real da sessão)

### 1. Upgrade de dependência não é isolado — e só a resolução real conta
Changelog e docs não dizem que o `boost` prendia o `mcp`. Só `composer require` de verdade revela. **Ler release notes não substitui resolver.**

### 2. `--minimal-changes` muda o escopo em ~7×
6 pacotes vs 45 no mesmo bump. Sem a flag, o composer arrasta tudo que pode.

### 3. Baseline tem que ser o main ATUAL, não o estado do staging
O container staging estava num commit anterior ao #4800. Medir o #4805 contra ele somaria os dois upgrades e atribuiria ao 2º PR o que era do 1º. Tive que instalar o lock do main atual no staging antes de medir o baseline.

### 4. Comparar por NOME de teste, nunca por contagem
Um run do #4800 deu **21 failed** e o run controlado seguinte, **no mesmo estado**, deu **17**. A variação estava *dentro* do estado com upgrade, não entre estados — flakiness das falhas de ambiente. Se eu tivesse comparado 21 vs 17 por contagem, teria reportado 4 regressões inexistentes.

### 5. Deploy `cancelled` ≠ `failure`
`gh run watch --exit-status` retorna != 0 para cancelamento. Reportei "deploy falhou" quando era `conclusion: cancelled` — a fila do deploy (`concurrency` com `cancel-in-progress: false`) mantém só o pendente mais recente, e o main estava recebendo merges de sessões paralelas. Quatro deploys entraram e foram substituídos antes de o `250fd638` rodar até o fim e publicar o acumulado.

### 6. Duas instâncias de LC-08 minhas nesta sessão
- **`grep -c $'\r'` casa string vazia** → contou *todas* as linhas dos dois arquivos (20632 e 163) e eu reportei "CRLF no lock — a armadilha conhecida". Os instrumentos certos eram `file` (não diz "with CRLF line terminators") e o próprio `git diff --numstat` (25/17 linhas — se houvesse conversão de EOL, seriam as 20632). Quase "corrigi" um problema inexistente.
- **exit code do `gh run watch`** (lição 5 acima) — mesma família: ler o instrumento errado e chamar de veredito.

Ambas registradas no [`LICOES_CODE.md`](../LICOES_CODE.md) LC-08.

## Hooks que morderam (e estavam certos)

| Hook | O que barrou |
|---|---|
| `block-destructive` | `rm -rf` em `/tmp` do container · e um `git push --force` |
| `block-destructive` | `composer update` sem `--lock` → forçou o caminho canônico `composer require` ([ADR 0063](../decisions/0063-prevenir-composer-lock-drift.md)) |
| `block-instrumento-sem-porta-viva` (P3) | `git log` pedindo datas num clone **shallow** — eu ia datar arquivos pelo piso do clone (LC-08 P3, incidente 2026-07-24) |

Os três evitaram erro real. O P3 em particular teria produzido exatamente a classe de erro que ele existe para impedir.

## Ressalva: este trabalho não tinha sinal

Pelo critério da [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), **não deveria ter virado trabalho ativo**. Nenhum cliente reportou, nenhuma métrica acusou drift. Nasceu de pergunta de conhecimento e eu segui o plano de 3 passos sem checar se havia sinal justificando. [W] perguntou direto — *"o que eu ganho fazendo isso?"* — e a resposta honesta é: pouco no imediato.

**Ganho concreto:** 1 bug dormente (PHP 8.0 reprovado no install), 4 higienes de tipo, e saída de 4 minors de dívida em pacote `0.x`.
**Ganho zero:** funcionalidade, performance, bug que afetasse alguém.
**Ganho real, não realizado:** a subtração de código que o 0.10 habilita.

## O que o 0.10 habilita e NÃO foi feito

| Hoje, escrito à mão | Nativo no 0.10 |
|---|---|
| `responderChatStream()` — `\Generator` manual | `stream()` → `StreamableAgentResponse`, `->then()`, broadcast |
| `Conversa` + `Mensagem` + 2 migrations | trait `RemembersConversations` + tabelas próprias |
| `OtelHelper::spanBiz` repetido em cada método | `HasMiddleware` + `make:agent-middleware` |
| `try/catch` → fixture | `#[Provider(Lab::Anthropic, Lab::OpenAI)]` failover |
| 45 tools + wiring manual | `Client::web(url)->tools()` spread |

Sem equivalente (continua nosso): `SemanticCacheService`, `MemoriaContrato` + Meilisearch, `mascararDocumentos()` (redact PII BR, Tier 0), `dry_run`/fixtures, `business_id` em tudo.

É refactor com risco próprio em módulo de IA que roda em produção — merece PR e teste próprios, não caronar num bump.

## Recomendação sobre o passo 3 (nWidart 10→13)

**Não fazer agora.** O v11 troca o carregamento dos 36 módulos (autoload raiz → `wikimedia/composer-merge-plugin`), e `Vestuario` (ROTA LIVRE, 99% do volume) é um dos 4 sem `composer.json` próprio. Superfície de código quase nula, mas o risco não é o refactor — é o carregamento. Encostar só quando algo quebrar ou uma feature exigir v13.
