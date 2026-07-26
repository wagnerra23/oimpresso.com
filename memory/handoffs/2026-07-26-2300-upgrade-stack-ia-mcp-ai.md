---
date: "2026-07-26"
time: "23:00 BRT"
slug: "upgrade-stack-ia-mcp-ai"
tldr: "Subiu laravel/mcp 0.7.0→0.9.1 (#4800) e laravel/ai 0.6.3→0.10.1 + php ^8.1→^8.3 (#4805), ambos em prod e verificados por SSH. Zero mudança nos agents da Jana — eles já usavam os contratos oficiais do SDK. Três correções de código saíram de brinde, todas achadas pelo CI e nenhuma prevista por changelog. Ressalva honesta: o trabalho não tinha sinal de cliente (ADR 0105) e não entrega feature nenhuma; o ganho real (subtração de ~800 linhas do driver) segue por fazer."
decided_by: [W]
prs: [4800, 4805]
next_steps:
  - "Subtração de código no LaravelAiSdkDriver (839 linhas): stream() nativo, RemembersConversations, HasMiddleware, failover multi-provider — PR e teste próprios, NÃO caronar em bump"
  - "nWidart 10→13: RECOMENDADO NÃO FAZER agora (ver §Recomendação) — troca o carregamento dos 36 módulos e Vestuario/ROTA LIVRE é um dos 4 sem composer.json próprio"
  - "5 scorecards congelados há 71d (admin/auditoria/governance/vestuario/comunicacaovisual) derrubam o watchdog G6 — dívida alheia a estes PRs"
related_adrs:
  - 0063-prevenir-composer-lock-drift
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0035-stack-ai-canonica-wagner-2026-04-26
---

# Handoff 2026-07-26 23:00 — upgrade da stack IA (laravel/mcp + laravel/ai)

## Estado MCP no momento do fechamento

| Consulta | Resultado |
|---|---|
| `cycles-active` | **Nenhum cycle ATIVO em COPI** |
| `my-work` | 8 tasks, **todas em REVIEW** @wagner (US-TR-309/310/311, US-PG-008, US-PROD-025/027, US-TR-305/306) |
| `decisions-search "upgrade dependencia composer laravel stack IA"` | 0063 (composer.lock drift) · 0222 (Renovate supply chain) · 0034 (laravel AI SDK) · 2 MemCofre |
| Handoffs irmãos hoje | `1700-obra-parada-sentinela-entrega` · `1729-errata-schema-handoff-1700` · `1700-reguas-grade-completa` (temas distintos, sem colisão) |

## O que aconteceu

Começou como pergunta de conhecimento ("como trabalhar com módulos no Laravel, saiu algo novo?") e virou execução em dois passos. O terceiro passo do plano **não** foi feito, por recomendação.

**Passo 1 — [#4800](https://github.com/wagnerra23/oimpresso.com/pull/4800): `laravel/mcp` 0.7.0 → 0.9.1.** O bump não era isolado: `laravel/boost` v2.4.5 restringe o mcp a `^0.5.1|^0.6.0|^0.7.0`, e **v2.4.13 é a primeira versão do boost que aceita `^0.9.0`** (2.4.7/2.4.8/2.4.9/2.4.12 não servem — verificado versão a versão na API do Packagist). Escopo com `--minimal-changes`: **6 pacotes** (sem a flag, 45), incluindo `laravel/framework` 13.6→13.22 arrastado pelo boost.

**Passo 2 — [#4805](https://github.com/wagnerra23/oimpresso.com/pull/4805): `laravel/ai` 0.6.3 → 0.10.1 + `php` `^8.1` → `^8.3`.** Com o mcp 0.9 no lugar, a resolução ficou limpa: **2 pacotes**. A constraint de PHP subiu porque o `laravel/ai` 0.10.1 exige `^8.3` e o projeto prometia `^8.1` — o composer resolvia pela plataforma real e não acusava, mas num ambiente 8.1/8.2 o install quebraria. Confirmado por SSH que Hostinger roda **8.4.19** (CLI e web) antes de mexer.

**Zero mudança nos agents da Jana.** Eles já implementam `Laravel\Ai\Contracts\Agent`, `HasTools`, `HasStructuredOutput`, `HasProviderOptions`, trait `Promptable` e os atributos `#[Provider]`/`#[Model]`/`#[MaxSteps]` — a [ADR 0048](../decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) rejeitou a Vizra *para usar o SDK oficial*, não para construir framework paralelo. O que existe por cima é camada de aplicação (driver 839 linhas, cache semântico 255, redact PII, memória), não SDK caseiro.

### Três correções que o CI pegou e o changelog não previa

| Correção | Causa |
|---|---|
| 4× `orderBy('created_at', 'DESC'/'ASC')` → minúsculo | framework 13.22 estreitou `$direction` para `'asc'\|'desc'\|SortDirection`. Varredura contada: 4 no repo inteiro, 4 corrigidas, 0 restantes — 1:1 com o log do CI |
| `InstallController` check de PHP | **bug real dormente**: `MAJOR >= 7 && MINOR >= 1` reprovava PHP 8.0. Existia desde o UltimatePOS |
| idem, 2ª rodada | `PHP_VERSION_ID >= 80300` **também** é tautológico sob `^8.3` — o composer já garante antes do app bootar. Virou `true` com nota, sem `@phpstan-ignore` |

Em nenhum caso o baseline do PHPStan foi regenerado — os erros apontavam código genuinamente errado.

## Persistência

| Canal | Estado |
|---|---|
| git | 2 PRs mergeados (`58ed1ee802`, `8146873936`), 5 commits |
| prod | ✅ verificado por SSH: `ai=v0.10.1 · mcp=v0.9.1 · framework=v13.22.0 · php="^8.3"`; smoke `/login 200`, `/ 200`, `mcp /api/mcp 401` |
| MCP | sem task criada — trabalho não tinha US (ver §Ressalva) |

## Ressalva honesta — este trabalho não tinha sinal

Pelo critério da [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (*"backlog só recebe item se cliente paga + reporta OU métrica detecta drift"*), **isto não deveria ter virado trabalho ativo**. Nenhum cliente reclamou, nenhuma métrica acusou drift. Nasceu de uma pergunta de conhecimento e eu segui o plano sem checar se havia sinal. Custo: ~3h, 2 deploys em prod, 3 correções que o CI teve que pegar. Entrega de funcionalidade: **zero** — a Larissa não vê diferença nenhuma.

O ganho defensável é o pedágio de `0.x` (cada minor é breaking; a dívida encarece) e o bug dormente do PHP 8.0. O ganho **real** está no que não foi feito.

## Recomendação sobre o passo 3 (nWidart 10→13): NÃO fazer agora

Mesmo raciocínio, risco muito maior. Superfície de código é quase nula (0 chamadas `->enabled()`/`->disabled()`; 1 site de produção usando a API — [`JanaServiceProvider.php:258`](../../Modules/Jana/Providers/JanaServiceProvider.php); 32/36 módulos já com `composer.json` no formato v11), mas o v11 **troca como os 36 módulos carregam** (autoload raiz → merge-plugin). E `Vestuario` (ROTA LIVRE, 99% do volume) é um dos 4 sem `composer.json` próprio, junto com `Admin`, `Arquivos` e `Brief`. Encostar nisso só quando algo quebrar ou quando uma feature exigir v13.

## Próximos passos pra retomar

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'cd ~/domains/oimpresso.com/public_html && git log --oneline -1 && grep -m1 laravel/ai composer.json'
```

## Lições catalogadas

Detalhadas no [session log](../sessions/2026-07-26-upgrade-stack-ia-laravel-mcp-ai.md) §Lições. Resumo:

1. **Upgrade de dependência não é isolado** — só a resolução real revela o travamento (boost prendia o mcp). Changelog não conta isso.
2. **`--minimal-changes` muda o escopo em 7×** — 6 pacotes vs 45.
3. **Baseline tem que ser o main ATUAL**, não o lock velho do staging — senão o 2º PR leva o crédito/culpa do 1º.
4. **Comparar por NOME de teste, não por contagem** — um run deu 21 failed e outro 17 no mesmo estado (flakiness de ambiente). Contagem sozinha teria fabricado uma regressão inexistente.
5. **Apertar a constraint de PHP aperta a inferência do PHPStan** — e expõe código legado que estava dormente.
6. **2 instâncias de LC-08 minhas** — registradas no [ledger](../LICOES_CODE.md) (`grep -c $'\r'` casa string vazia; `gh run watch --exit-status` não distingue `cancelled` de `failure`).

## Pointers detalhados

- Session log: [`2026-07-26-upgrade-stack-ia-laravel-mcp-ai.md`](../sessions/2026-07-26-upgrade-stack-ia-laravel-mcp-ai.md)
- PRs: [#4800](https://github.com/wagnerra23/oimpresso.com/pull/4800) · [#4805](https://github.com/wagnerra23/oimpresso.com/pull/4805)
- ADRs tocadas conceitualmente: [0063](../decisions/0063-prevenir-composer-lock-drift.md) (o hook que barrou `composer update` sem `--lock` vem dela) · [0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) · [0035](../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)
