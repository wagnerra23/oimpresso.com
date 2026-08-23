---
id: requisitos-documentacao-anti-regressao-documentacao-blade
titulo: "Lista anti-regressão — superfície /documentacao (Blade → Inertia/React)"
tipo: anti-regressao
origem: "app/Http/Controllers/DocumentacaoController.php + resources/views/documentacao/*.blade.php no main"
parte: "4 rotas a migrar (índice · busca · programa · documento) + rail derivado compartilhado"
gerado: 2026-08-06
atualizado: 2026-08-23
observacao: "Contrato de paridade da migração MWART. A tela nova (Inertia/React) NÃO pode perder nenhum comportamento marcado ✅ sem decisão explícita de Non-Goal. Substitui o gate automático da Onda 0d, que está `proposto` e não existe como máquina."
---

# Lista anti-regressão — superfície `/documentacao` (Blade legado)

> **Propósito.** Catalogar cada comportamento observável da superfície Blade **antes** de trocar a
> camada de render, para servir de contrato de não-regressão na migração pra Inertia/React. Cada
> item `AR-DOC-NNN` é uma asserção verificável: a tela nova **preserva** o comportamento, ou o
> desvio vira **Non-Goal declarado** com aprovação de [W].
>
> **Por que este documento existe.** A decisão de [W] em 2026-08-06 foi migrar a superfície inteira,
> e o gate automático de paridade previsto na
> [Onda 0d](../_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md) está
> `status: proposto` — não existe como máquina. Medido em 2026-08-06: `git grep -l paridade` em
> `.github/workflows/` e `scripts/` não devolve gate algum, e o próprio 0d carrega a seção
> "O que construir". A paridade aqui é **manual e documentada**, no espírito da auditoria da
> `/perfil` (2026-07-02), que por esse caminho achou divergências reais que nenhuma régua via.
>
> **Fonte.** Seções 1–5 derivadas da leitura do controller e das views no `main` em 2026-08-06
> (`ee54ee50373`). **Seção 6 (rota `programa`) derivada em 2026-08-23 do `main` em `74135d9539`** —
> aquela rota entrou depois do primeiro sha e por isso faltava aqui. É contrato **extraído do
> código**, não medido em runtime — a coluna "como verifico" é o que fecha cada item depois da
> migração. Item sem verificação executada permanece ⬜.

## Legenda

| Marca | Significado |
|---|---|
| ✅ | comportamento a preservar — perder é regressão |
| ⚠️ | muda por decisão explícita de [W] — desvio consciente, não regressão |
| ⬜ | ainda não verificado na tela nova |

## 1. Rota `/documentacao` — índice (leitura guiada)

| ID | Comportamento a preservar | Âncora no legado | Como verifico depois |
|---|---|---|---|
| AR-DOC-001 ✅ | A fonte é `memory/GUIA-DO-SISTEMA.md` lido **do disco em runtime**, não uma cópia commitada | `DocumentacaoController::index` · `self::FONTE` | editar o Guia numa fixture muda a página sem tocar PHP/TSX |
| AR-DOC-002 ✅ | Arquivo fonte ausente → **503 nomeando o arquivo que falta** (`Documento fonte ausente no deploy: <path>`) — falha honesta, nunca página vazia | `index()` · `abort(503, …)` | renomear a fonte na fixture → 503 com o path na mensagem |
| AR-DOC-003 ✅ | Markdown convertido em HTML **no servidor** (`paraHtml`); o cliente não roda parser de markdown | `index()` · `paraHtml()` | payload Inertia carrega `html`, não `markdown` |
| AR-DOC-004 ✅ | Sumário (TOC) **recalculado a cada acesso** a partir do HTML — não é manifesto commitado | `comSumario()` | alterar títulos na fixture muda o TOC sem build |
| AR-DOC-005 ✅ | "Atualizado em" vem do **frontmatter do próprio documento** | `dataDoFrontmatter()` | alterar `updated_at` na fixture reflete na tela |
| AR-DOC-006 ✅ | A busca só é oferecida quando o corpus está acessível (`buscaDisponivel`) | `index()` · `corpusDisponivel()` | derrubar o corpus → a entrada de busca não é oferecida |
| AR-DOC-007 ✅ | A capa **não** marca item no rail (`atual = null`) | `index()` | nenhum item do rail vem com estado ativo em `/documentacao` |

## 2. Rail derivado — compartilhado pelas três rotas

| ID | Comportamento a preservar | Âncora no legado | Como verifico depois |
|---|---|---|---|
| AR-DOC-010 ✅ | Rail **derivado em runtime** do frontmatter (`File::glob(memory/reference/*.md)` + `nav_group`/`nav_order`/`lente`) — nenhum manifesto no repositório | `navegacao()` | doc novo com `nav_group` aparece sozinho; sem rebuild |
| AR-DOC-011 ✅ | **Opt-in**: doc sem `nav_group` não entra no rail; doc que perde o campo some junto | `navegacao()` | remover `nav_group` na fixture → item some |
| AR-DOC-012 ✅ | O ordinal numera a ordem **visível na lente ativa** (1, 2, 3 sem buracos). Ele **não** vem de `nav_order`, que só ordena — do contrário filtrar a lente deixaria buracos e o leitor pensaria que sumiu conteúdo | `navegacao()` | trocar de lente → ordinais seguem contínuos |
| AR-DOC-013 ✅ | Lentes `operar` e `construir`; a lente ativa vem do request | `LENTES` · `lenteAtiva()` | `?lente=operar` filtra e marca a lente |
| AR-DOC-014 ✅ | Documento oculto na lente ativa é **sinalizado**, não apagado em silêncio | `busca`/`layout` blade | item fora da lente continua explicável na tela |

## 3. Rota `/documentacao/buscar` — busca no acervo

| ID | Comportamento a preservar | Âncora no legado | Como verifico depois |
|---|---|---|---|
| AR-DOC-020 ✅ | O nome real é **`buscar`** (não `busca`) e a rota é registrada **antes** de `{slug}` — invertida, a busca vira 404 de "documento buscar não encontrado" | `routes/web.php` · símbolo `Route::get('/documentacao/buscar'` e o comentário logo acima dele — relocalize com `git grep -n "documentacao/buscar" routes/web.php` | `route('documentacao.buscar')` resolve; `/documentacao/buscar` não cai no handler de documento |
| AR-DOC-021 ✅ | Sem corpus → a tela diz **`indisponivel`**. Não finge resultado vazio e **não** dá 503 | `buscar()` | derrubar o corpus → estado "índice indisponível", HTTP 200 |
| AR-DOC-022 ✅ | Termo com menos de 2 caracteres → resultados vazios **sem consultar o banco** | `buscar()` · `Str::length($termo) >= 2` | `?q=a` não emite query |
| AR-DOC-023 ✅ | A consulta é `MATCH … AGAINST` **OU** `LIKE` no título. O LIKE é rede de segurança, não substituto: sem ele, "NFe" e "MCP" (abaixo de `ft_min_word_len`) devolveriam vazio | `buscar()` | buscar "MCP" devolve resultado |
| AR-DOC-024 ✅ | Ordenação por relevância (`MATCH … DESC`), limitada a `POR_PAGINA` | `buscar()` | ordem e teto preservados |
| AR-DOC-025 ✅ | Cada resultado traz `slug`, `type`, `module`, `title`, `git_path` e um **trecho com o termo destacado** | `buscar()` · `trecho()` | payload com os 6 campos |

## 4. Rota `/documentacao/{slug}` — documento

| ID | Comportamento a preservar | Âncora no legado | Como verifico depois |
|---|---|---|---|
| AR-DOC-030 ✅ | Slug restrito a `[A-Za-z0-9._-]+` e rota registrada **depois** das irmãs | `routes/web.php` · símbolo `Route::get('/documentacao/{slug}'` — relocalize com `git grep -n "documentacao/{slug}" routes/web.php` | rota irmã nova não é engolida pelo `{slug}` |
| AR-DOC-031 ✅ | Sem corpus → **503** | `documento()` | corpus fora → 503 |
| AR-DOC-032 ✅ | Slug inexistente, de tipo fora da documentação, ou sem permissão → **404 honesto** | `documento()` | slug de `session`/`handoff` → 404, não vazamento |
| AR-DOC-033 ✅ | Links relativos resolvidos a partir da **pasta do próprio documento** (`pastaDe($doc->git_path)`), não de `memory/` — o acervo tem doc em subpasta e o link foi escrito de onde ele mora | `documento()` · `resolveRelativo()` | link relativo em doc de `memory/requisitos/<Mod>/` resolve certo |
| AR-DOC-034 ✅ | `atual = slug` marca o item ativo no rail | `documento()` | item correspondente vem ativo |

## 5. Escopo compartilhado e acesso

| ID | Comportamento | Âncora no legado | Como verifico depois |
|---|---|---|---|
| AR-DOC-040 ✅ | `escopoTipos`/`escopoProsa` alcançam **toda** a superfície, inclusive o layout — que carrega o `aria-label` da busca e não recebe payload de método nenhum | `__construct()` · `View::share` | o rótulo de escopo aparece nas 3 telas |
| AR-DOC-041 ✅ | Tipo sem rótulo falha **no PHPStan e no teste**, nunca em runtime com slug cru na tela | `escopoEmProsa()` + `DocumentacaoRouteTest` | a invariante segue coberta pelos dois caminhos |
| AR-DOC-050 ⚠️ | **MUDA por decisão de [W] em 2026-08-06.** Hoje o grupo é `auth`-only, deliberadamente sem `SetSessionData`/`AdminSidebarMenu` ("leitura pura, não depende de `business_id`", no comentário logo acima de `Route::get('/documentacao'`). A migração pro `AppShellV2` adota o stack completo, no precedente da rota `/modulos`. **Consequência aceita:** quem não tem business em sessão deixa de ler a documentação | `routes/web.php` · o grupo `Route::middleware(['auth'])` que abriga `/documentacao` — relocalize com `git grep -n "Route::get('/documentacao" routes/web.php` | o comentário do routes é atualizado no mesmo PR — doc que contradiz o código é instrução ativa pra regressão |
| AR-DOC-051 ✅ | Nenhum payload da superfície carrega segredo, token ou host | — | inspeção do payload Inertia nas 3 telas |

## 6. Rota `/documentacao/programa` — Trilha D

> **Seção ADITIVA, escrita em 2026-08-23.** Ela não existia no corpo original porque a rota
> **não existia** no sha de origem deste contrato (`ee54ee50373`, 2026-08-06). `programa()`,
> `programa.blade.php` e a rota entraram em `main` depois — conferido em `74135d9539`. Sem esta
> seção o contrato de paridade teria um buraco: a migração cobriria 3 das 4 rotas e a quarta
> sumiria sem ninguém notar. Numerada 6 (e não 4) de propósito: renumerar as seções existentes
> mexeria em texto que ninguém revisou nesta passagem. Derivada da **leitura do método**, não de
> runtime — como todo o resto deste documento, a coluna "como verifico" é o que fecha o item.

| ID | Comportamento a preservar | Âncora no legado | Como verifico depois |
|---|---|---|---|
| AR-DOC-060 ✅ | A fonte é `memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` lido **do disco em runtime** | `programa()` · `self::PLANO` | editar o plano numa fixture muda a página sem tocar PHP/TSX |
| AR-DOC-061 ✅ | Plano ausente → **503 nomeando o arquivo** (`Plano ausente no deploy: <path>`) | `programa()` · `abort(503, …)` | renomear o plano na fixture → 503 com o path na mensagem |
| AR-DOC-062 ✅ | As subseções são recortadas pelo **CÓDIGO** (`D.3`…`D.7`), nunca pelo título — o título é prosa e pode ser reescrito sem aviso | `secaoDoPlano()` | renomear o título da § mantendo o código → a tela continua montando |
| AR-DOC-063 ✅ | Qualquer das 5 estruturas vazia → **503 nomeando qual** (`Estrutura ausente na § Trilha D do plano: D.4 estações`). Falha honesta: seção vazia mentiria dizendo "o programa não tem ondas" | `programa()` · laço de validação | esvaziar a § D.4 na fixture → 503 citando `D.4 estações` |
| AR-DOC-064 ✅ | `atualizadoEm` vem do **frontmatter do próprio plano** | `dataDoFrontmatter()` | alterar `updated_at` na fixture reflete na tela |
| AR-DOC-065 ✅ | `blob` linka o plano no GitHub a partir de `self::BLOB` — a tela nunca hospeda cópia do plano | `programa()` | o link aponta pro arquivo em `main` |
| AR-DOC-066 ✅ | Compartilha o rail derivado e **não marca item nele** (`atual = null`) | `programa()` · `navegacao(lenteAtiva())` | nenhum item do rail vem ativo em `/documentacao/programa` |
| AR-DOC-067 ✅ | Registrada **antes** de `{slug}` — `programa` casa `[A-Za-z0-9._-]+` e viraria 404 de documento | `routes/web.php` · símbolo `Route::get('/documentacao/programa'` — relocalize com `git grep -n "documentacao/programa" routes/web.php` | `/documentacao/programa` não cai no handler de documento |
| AR-DOC-068 ⚠️ | **DIVERGÊNCIA ABERTA — decisão de [W], não conserto meu.** `Programa.casos.md` (UC-PROGRA-01) e `e2e/documentacao-programa.spec.ts` afirmam *"o estado da onda vem do MCP, nunca do markdown"*. O código que **já está em `main`** faz o oposto: `execucaoDaTrilha()` acha a onda atual por regex (`/\bD(\d+)\b\s+em execução/`) na linha de tabela que contém "Trilha D" **dentro do próprio markdown**. Os UC foram escritos em 2026-08-06, quando a rota não existia — são intenção de design, e o código shipou por outro caminho. Nenhum teste verde arbitra: os 4 e2e são `test.fixme`. **Não reescrevi nenhum dos dois lados**: qual vence é decisão de [W] | `execucaoDaTrilha()` × `Programa.casos.md` UC-PROGRA-01 | [W] decide: ou o UC passa a descrever a derivação por markdown, ou a F3 troca a fonte pro MCP e o UC vira teste real |

## Non-Goals declarados desta migração

- **Trocar a fonte de dados.** O rail continua saindo do frontmatter em disco e o conteúdo do
  corpus `mcp_memory_documents` com o mesmo fallback. O que muda é **só a camada de render**.
- **Converter markdown no cliente.** Continua no servidor (AR-DOC-003).
- **Gerar manifesto de navegação commitado.** Seria cópia da estrutura, e cópia drifa
  ([ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)).
- **Construir o gate automático da Onda 0d.** Continua `proposto`; esta lista é o substituto manual
  desta migração, não a implementação daquela onda.

## Estado da verificação

Nenhum item foi verificado ainda — a tela nova não existe. Todos permanecem ⬜ até a fase de QA da
migração, quando esta tabela é preenchida com o resultado real e o desvio, se houver, vira Non-Goal
aprovado por [W] ou defeito a corrigir antes do cutover.
