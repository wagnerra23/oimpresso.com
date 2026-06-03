---
date: 2026-06-03
hour: "14:16 BRT"
topic: Norte — Fluxo do Caminhão (handoff Claude Design → _Showcase/Norte.tsx + deploy staging CT100)
duration: ~2h
authors: [Wagner, Claude Code]
---

## Estado MCP no momento
Sessão `frosty-greider-83ab2f`, branch `feat/staging-ct100` (repo principal `D:/oimpresso.com`
está nessa mesma branch). HEAD local + origin + CT100 staging convergem em `98cd2711f`.
Não toquei cycle/tasks MCP — sessão foi implementação de design pontual, fora de US.

## O que aconteceu
Wagner mandou implementar um arquivo de design vindo de um **handoff bundle do Claude Design**
(`claude.ai/design`, 34MB tgz, 40 chats + project/). Segui o README do bundle: li transcripts
(o `chat40.md` é onde o "Norte" nasceu — sessão de dark theme + view Fila + DS-tempero + CRM
Ficha da Frota → culminou no North Star), li o `Norte - Fluxo do Caminhão.html` + imports
(`norte-data.jsx` + `norte-app.jsx`).

**É uma peça de VISÃO** ("North Star"), não tela CRUD: apresentação navegável de 7 cenas
contando a jornada de um caminhão de ponta a ponta pelo ERP (Recepção → Diagnóstico → Aprovação
→ Execução → Venda → Nota → Financeiro → volta pro CRM), com a **costura** (a passagem entre
módulos) como herói de cada cena.

Recriei como página Inertia/React (port verbatim dos 3 arquivos React-UMD+Babel → 1 TSX
idiomático). Verifiquei o visual rodando o protótipo-fonte num http server local (7 cenas) e,
após deploy, **autenticado no staging real** (Chrome MCP, cenas 1 e 6 — render perfeito,
tab title `Norte — Fluxo do Caminhão · OI Impresso`).

## Artefatos gerados
- `resources/js/Pages/_Showcase/Norte.tsx` (+507 linhas) — componente + 7 cenas + CSS escopado
- `routes/web.php` (+6) — rota `/showcase/norte` atrás de `superadmin` (espelha `showcase.components`)
- Commit `98cd2711f` `feat(showcase): Norte — Fluxo do Caminhão (North Star)`

## Persistência (3 canais)
1. **git:** commit `98cd2711f` pushado em `origin/feat/staging-ct100`
2. **CT100 staging:** deployado via `docker/oimpresso-staging/deploy.sh feat/staging-ct100` +
   `route:clear` + `docker restart` (Pegadinha #12). Vivo em **https://staging.oimpresso.com/showcase/norte**
3. **design loop:** entrada no `prototipo-ui/SYNC_LOG.md` (este fechamento)

## Decisões de governança (2, Wagner-aprovadas via AskUserQuestion)
1. **Onde aterrissa:** `_Showcase/Norte.tsx` (peça de visão interna, autocontida, sem controller/dados)
2. **Tokens:** bloco dark escopado em `.nx-root` (regras via `var()` — DS-GUARD/L-23). `--stage-*`
   escopados com TODO até o PR de tokens (Oficina dark/stage do chat40) entrar no main.

## Próximos passos pra retomar
Norte é **showcase/visão**, NÃO é o DS canônico. Se Wagner decidir tornar dark-DS + paleta
`--stage-*` o padrão global → caminho é **ADR proposto** (append-only, `supersedes:` UI-0009/UI-0013),
nunca deletar. `--stage-*` ainda não está no main. Nada urgente.

## Lições catalogadas
- Handoff bundle do Claude Design: README no topo manda **ler chats primeiro** — a intenção mora lá,
  não no HTML final. O `chat40.md` deu todo o "porquê" do Norte.
- Deploy CT100 staging: `deploy.sh` tem 2 furos pré-existentes — (#1) `composer: not found`
  (usa imagem mcp sem composer; não-fatal se não há dep PHP nova) e (#12) não faz `route:clear`+restart
  (rota nova fica em opcache → 302/405 fantasma até reiniciar container). Apliquei #12 na mão.
- Os cliques sintéticos do Chrome/Preview MCP não disparam handlers React do spine — navegar por teclado (`←/→`).

## Pointers detalhados
- Protótipo-fonte: bundle Claude Design (`oimpresso-erp-conunica-o-visual/project/Norte - Fluxo do Caminhão.html`)
- RUNBOOK staging: `memory/requisitos/Infra/RUNBOOK-staging-ct100.md` (Pegadinhas #1, #11, #12)
- DS canon que Norte NÃO contradiz (é aditivo/escopado): UI-0009 (sidebar light), UI-0013 (Constituição UI v2)
