# 05 — Preferências do Usuário

> ⚠️ **FÓSSIL (pré-Constituição v2) — NÃO usar como verdade.** Capturado com "Eliana/WR2" (cliente legado, não o usuário atual Wagner). Contém "Decida, não pergunte" que **CONTRADIZ** a regra-mestre atual da Constituição UI v2 (`wagner-request-refiner`: perguntar antes em pedido vago). Preferências canônicas: `memory/proibicoes.md` + `how-trabalhar.md` + `~/.claude/oimpresso-local/`. Ver `memory/governance/AUDITORIA-CONFLITOS-MEMORIA-2026-06-07.md`.

Capturado ao longo das conversas com a Eliana (WR2 Sistemas). Estas preferências têm precedência sobre escolhas padrão a menos que conflitem com requisitos legais.

## Comunicação

- **Idioma:** PT-BR sempre
- **Tom:** profissional mas direto, sem floreio
- **Formato de resposta:** curto por padrão; expandir só quando pedido
- **Ensino antes de execução:** quando envolve gasto de crédito significativo, ela prefere que eu *explique como funciona* antes de produzir
  - Exemplo literal: *"primeiro me ensine como funciona para eu não gastar credito"*
- **Decida, não pergunte** quando a decisão é técnica/infra que Wagner não domina (composer strategy, runtime split, ext PHP, lock drift, ordem de PRs). Tabela de 3 opções pra ele = fricção. Escolher caminho conservador e tocar; só escala se for irreversível alto-risco. Combina com [ADR 0040](decisions/0040-policy-publicacao-claude-supervisiona.md) (Claude decide push/PR/commit em zona neutra).

## Escopo de trabalho

- **Uma coisa por vez.** Prefere iterar 1 tela, validar, depois replicar, a gerar 9 telas de uma vez
- **Confirmar escopo antes de produção massiva.** Use AskUserQuestion com 2-4 opções + recomendação
- **Economizar tokens.** Evitar boilerplate desnecessário, reaproveitar componentes, não regenerar o que já está bom

## Sugestões persistentes (meta-regra Wagner 2026-05-09)

**Toda sugestão minha precisa virar artefato persistente com trigger automático pra reativar no momento oportuno** — Wagner: *"toda sugestão tem que ficar dessa forma e resugerida em momento oportuno"*. NUNCA deixar "próximos passos" soltos no chat — eles morrem na compactação ou são esquecidos quando saio.

**Razão:** Wagner explicitamente reconhece que vai esquecer. Sugestão em chat sem âncora persistente é trabalho perdido. Pattern modelo: skill `automem-pending` + manifesto `AUTO-MEM-PENDING.md` ([PR #288](https://github.com/wagnerra23/oimpresso.com/pull/288)) — auto-mems pendentes ficam num manifesto vivo + skill auto-trigger por contexto que reativa no momento certo.

**Como aplicar — escolher o artefato certo conforme tipo:**

| Tipo de sugestão | Artefato persistente | Trigger automático |
|---|---|---|
| Próximos passos de feature/módulo | `tasks-create` no MCP (`tasks-list module:X` lista) | quando Wagner consultar backlog do módulo |
| Convenção/regra técnica reusável | apender em `04-conventions.md` ou criar ADR | match contextual (skill ou code review) |
| Receita/RUNBOOK reproduzível | `memory/requisitos/<X>/RUNBOOK-<tema>.md` | skill auto-ativa por contexto (`Modules/X/`, comando, termo) |
| Observação que reativa em contexto específico | manifesto `*-PENDING.md` + skill Tier B auto-trigger | edição/leitura de path mapeado |
| Decisão arquitetural | ADR formal Nygard em `memory/decisions/NNNN-*.md` | `decisions-search` MCP retorna |
| Hipótese de feature sem cliente pagando | ADR feature wish status `historical` ([ADR 0105](decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) | só re-sugere se cliente pagar OU métrica detectar drift |
| Bug/regressão observada | task MCP P0/P1 + sentinela em test | CI quebra OU `my-inbox` mostra |

**Anti-padrão:** terminar resposta com "## Próximos passos sugeridos" em prosa solta. Se a sugestão importa, ela merece artefato + trigger. Se não importa o suficiente pra virar artefato, não importa o suficiente pra dizer.

**Exceção:** observações puramente conversacionais ("achei interessante isso", "talvez X seja melhor que Y") — ficam no chat mesmo. A regra vale pra recomendações de **ação**.

## Arquitetura

- **Não reinventar a roda.** UltimatePOS já existe — estender, não substituir
- **Não invadir core.** Mudanças ficam no módulo WR2
- **Compliance como requisito, não diferencial.** Portaria 671, CLT, LGPD, eSocial são mínimos, não features premium
- **Jana é a referência canônica para estrutura de módulo** (ver ADR 0011). Antes de criar qualquer arquivo novo em `Modules/Ponto/`, olhar o equivalente em `Modules/Jana/` e imitar. Não inventar estrutura "moderna" — o UltimatePOS congelou convenções de uma versão antiga do nWidart/laravel-modules e qualquer divergência quebra em produção

## UI / UX

- **Sidebar única com 1 entrada para o módulo.** UltimatePOS já tem várias entradas; o Ponto WR2 **ocupa apenas uma**
  - Citação literal: *"deve conter apenas 1 item no meu [menu], e ter um meu [menu] no topo com os itens do ponto, meu ultimatepos je tem muitos mudulos"*
- **Menu horizontal em abas** para as sub-seções do módulo
- **Estética shadcn/Tailwind** para protótipos visuais (mesmo que produção use AdminLTE)
- **Paleta preferida:** neutro (zinc/slate) + azul como acento
- **Dados mock brasileiros realistas** em protótipos (João Silva, Maria Oliveira, departamentos BR)

## Preview de UI

- **Prefere HTML estático auto-contido** a projetos Node/React complexos para validação visual (aprendido na sessão 01: React+Babel via CDN falhou; HTML puro funcionou)
- **Chart.js via CDN** é aceitável para gráficos em protótipos

## Browser automation

- **Claude dirige o browser direto** via `mcp__Claude_in_Chrome__*` (Chrome do Wagner, com cookies/sessões) ou `mcp__Claude_Preview__*` (browser isolado, dev server em `.claude/launch.json`). Não pedir pro Wagner copiar erros/screenshots — usar `read_console_messages` / `read_network_requests` / `computer` (screenshot). Só perguntar pro Wagner quando depende de julgamento dele ou credencial sem acesso.

## Documentação

- **Markdown com Mermaid** para diagramas (C4, ERD, sequência, state)
- **Documentos técnicos em PT-BR**
- **Compliance legal citada textualmente** (artigos da CLT, portaria, leis)

## Decisões arquiteturais já tomadas (consolidadas)

1. **Opção C — Estender UltimatePOS** (não Build, não Fork)
2. **nWidart/laravel-modules** como sistema de módulos (versão antiga, congelada — imitar Jana)
3. **Bridge `ponto_colaborador_config`** em vez de modificar `users`
4. **Append-only com triggers MySQL** para marcações e movimentos de BH
5. **UUID** para entidades auditáveis (Marcacao, Intercorrencia, Rep, BancoHorasMovimento, Importacao)
6. **BigInt** para entidades de lookup (Escala, ApuracaoDia, Colaborador)
7. **Multi-tenancy lógica via `business_id`** (não physical por ora)
8. **Sidebar 1-item + tabs horizontais** para navegação interna
9. **Blade/AdminLTE** em produção, **HTML/Tailwind/Chart.js** em protótipos
10. **Padrãa Jana** — `start.php` + `Http/routes.php` + 1 ServiceProvider (ADR 0011)
11. **API com `auth:api` (Passport)**, não Sanctum — padrão UltimatePOS
12. **Lang folder com código curto** (`pt/`, não `pt-BR/`)

## Anti-preferências (o que ela NÃO quer)

- React + shadcn oficial como preview rápido — falhou, não insistir sem necessidade
- Projetos com `npm install` quando a pergunta é só "como fica visualmente?"
- Documentação com headings/bullets excessivos — ela lê e processa melhor prosa
- Explicações redundantes do óbvio
- Gastar crédito em preview de 9 telas de uma vez
- Usar convenções Laravel "modernas" no módulo UltimatePOS (provoca crash em produção — aconteceu em 2026-04-18 17:12)
- Código meu sobe pro servidor sem eu ter alertado antes sobre pré-requisitos/risco — se for para produção, **sempre** avisar primeiro
- **Anotar nomes/personas de cliente** em CLAUDE.md como filtro de prioridade — primer técnico não é CRM. Citar `business_id` por número técnico OK; pendurar nome de cliente como "fora dos testes ativos" ❌

---

**Última atualização:** 2026-05-09 (apend 5 — meta-regra "sugestões persistentes com trigger")
