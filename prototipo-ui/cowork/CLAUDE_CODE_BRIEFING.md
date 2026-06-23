# BRIEFING — Para o Claude Code que abrir este repositório

> **Você é o Claude Code rodando localmente em `D:\oimpresso.com\` (repo `wagnerra23/oimpresso.com`).**
> Este arquivo é seu ponto de entrada quando o usuário (Wagner) pedir algo relacionado ao protótipo de UI ou à migração Blade→React.

---

## 1. Quem é quem

Existem **dois Claudes** trabalhando neste projeto, em locais diferentes, com responsabilidades distintas:

| Claude | Onde roda | Responsabilidade | Ferramentas |
|---|---|---|---|
| **Claude Cowork** | claude.ai (Projects) | Desenha, prototipa, itera UI em HTML+JSX. Mantém `prototipo-ui/`. | Filesystem do projeto Cowork, preview, sem git |
| **Claude Code** (você) | Terminal local em `D:\oimpresso.com\` | Aplica mudanças no repo Laravel real. Sincroniza protótipo. Faz git/PR/merge. Porta JSX→TSX. | Filesystem real do repo, git, GitHub, MCP tools |

**Regra de ouro:** Cowork projeta, Code aplica. Nunca o contrário.

---

## 1.1 Capacidades e limites do [CC] — até onde ele vai (LER pra não esperar o impossível)

> Documenta, em termos de **ações**, o que o Cowork [CC] consegue e o que NÃO consegue. Serve pra você ([CL]) e pro [W] saberem a fronteira e nunca esperarem algo que não acontece sozinho.

### ✅ O que o [CC] CONSEGUE fazer
- **Ler/explorar** todo o projeto Cowork **e** o repositório git em **modo somente-leitura** (listar, ler, importar arquivos do GitHub pra dentro do Cowork).
- **Produzir e editar artefatos de design:** HTML, React/JSX, CSS — o shell `oimpresso.com.html` e os módulos `*-page.jsx/css`.
- **Pré-visualizar, tirar screenshot e auto-verificar** (subagente que checa console/layout antes de entregar).
- **Transformar arquivos em lote** e gerar imagens (canvas).
- **Gerar URLs públicas TEMPORÁRIAS (~1h)** de arquivos, pra você baixar via `curl`.
- **Fazer perguntas estruturadas** ao [W]; exportar PPTX/PDF/HTML standalone quando pedido.

### ❌ O que o [CC] NÃO CONSEGUE (limites duros — não prometer)
- **Escrever no git:** sem branch, commit, push, PR, merge ou issue. **Tudo read-only.**
- **Disparar/criar tarefa no Claude Code.** Não existe hook, API, fila ou lista de tarefas compartilhada. **O Code NÃO chama o [CC]; o [CC] NÃO chama o Code.** Os "comandos" (§4/§7) são coisas que **o [W] fala pra VOCÊ ([CL])**, nunca chamadas entre os dois Claudes.
- **Numerar/versionar/deletar ADR** ou tocar a constituição — soberania [W] (CARTA §0.1; ADR 0238). [CC] só **propõe**.
- **Persistir qualquer coisa direto no repo.** Nem afirmar que commitou/mergeou.

### 🔗 A única ponte (reforça §3)
Arquivos versionados + **[W] transporta 1×**. [CC] escreve em `COWORK_NOTES.md` (+ espelho `prototipo-ui-patch/` + URLs temp) → [W] cola no Code → **você ([CL]) valida contra o `main` sozinho** (PROTOCOL §10.4 — pedido do [CC] é **proposta**, não ordem) → age → escreve de volta em `CODE_NOTES.md`/`SYNC_LOG.md`. Não há atalho automático fora disso.

### 📤 Como extrair o "estado da arte" do [CC] (onde está o mais recente)
| Quer… | Olhe |
|---|---|
| O **design vivo** | `oimpresso.com.html` + `*-page.jsx/css` (raiz Cowork) |
| **Estado + regras** | `STATUS.md` (🚦 REGRA DE OURO no topo) + `memory/` |
| **Trabalho pendente pra você** | `COWORK_NOTES.md` → "📥 Pendentes" (marcar `[PROCESSADO]` ao agir) |
| **Erros a não repetir** | `memory/LICOES_CC.md` |
| **Contrato visual / DS** | `CODE_DESIGN_CONTRACT.md` + `Design System v4.html` |
| **Handoff formal** | espelho `prototipo-ui-patch/` + bloco `## new_design_memories` (ADR 0236) no fim do export |

---

## 1.2 Responsabilidades por papel (quem faz o quê — 6 papéis × fases)

> Canon: `PROTOCOL.md §1` (git) + `CLAUDE.md`. Espelhado aqui pra você ver a cadeia inteira, não só [CC]↔[CL].

| Sigla | Quem | Responsabilidade | Fase | Entrega |
|---|---|---|---|---|
| **[W]** | Wagner | Escreve o pedido, é o "carteiro" (transporta 1×), aprova | **F0** brief · gates F2/F4 | pedido em `COWORK_NOTES.md` |
| **[CC]** | Claude Cowork | **Desenha/prototipa/itera UI** (design é dele, dentro dos tokens) | **F1** design | `prototipos/<tela>/page.tsx` + `COMPARISON.md` |
| **[CD]** | Claude Design | **Crítica visual** — bench 15 dimensões, nota | **F1.5** critique | `critique-score.json` (≥80 passa) |
| **[CL]** | Claude Code (você) | **Aplica no repo real** — porta JSX→TSX, git/PR/merge, sync | **F3** code | `resources/js/Pages/<Mod>/<Tela>.tsx` |
| **[CA]** | Claude Accessibility | **Revisão a11y** (WCAG 2.1 AA) | **F3.5** a11y | `a11y-report.md` |
| **[W2]** | Wagner aprovador | **Aprova síncrono** — screenshot e merge | **F2** + **F4** | OK visual + PR merge |

**Cadeia (7 fases):** `F0 [W]` → `F1 [CC]` → `F1.5 [CD]` → `F2 [W2] screenshot` → `F3 [CL]` → `F3.5 [CA]` → `F4 [W2] merge`.

**Fronteiras que não se cruzam:**
- [CC] **projeta**, [CL] **aplica** — nunca o contrário (Regra de ouro §1).
- [CC] **não escreve Inertia/TSX de produção** (é F3, do [CL]); [CL] **não edita o protótipo** `*.jsx` por conta própria (é jurisdição do [CC] — §6).
- Decisão/merge é **[W]**; numerar ADR / mexer na constituição é **[W]** (soberania, CARTA §0.1).

---

## 2. Hierarquia de leitura (faça nesta ordem na primeira vez)

1. `CLAUDE.md` (raiz) — primer geral do repo Laravel
2. `LARAVEL_REPO_CONTEXT.md` — não existe ainda no repo; vem do Cowork. Se ausente, pular.
3. `prototipo-ui/README.md` — o que é o protótipo, mapa protótipo→produção
4. `prototipo-ui/CLAUDE_CODE_BRIEFING.md` — este arquivo
5. `prototipo-ui/memory/HANDOFF.md` — estado vivo da migração
6. `prototipo-ui/SYNC_LOG.md` — log de sincronizações entre Cowork e Code
7. `memory/decisions/0039-ui-chat-cockpit-padrao.md` — ADR do padrão
8. `memory/sessions/` (último) — última sessão registrada

---

## 3. Protocolo de comunicação Cowork ↔ Code

Os dois Claudes **não conversam diretamente**. A comunicação é via arquivos versionados no git:

### Cowork → Code (mensagens descendo)
- **`prototipo-ui/HANDOFF.md`** — estado atual do protótipo, escopo, próximos passos
- **`prototipo-ui/COWORK_NOTES.md`** — notas avulsas que o Cowork deixa pra você (decisões pendentes, pedidos explícitos)
- O próprio diff dos arquivos `prototipo-ui/*.jsx` em cada sync

### Code → Cowork (mensagens subindo)
- **`prototipo-ui/SYNC_LOG.md`** — você anexa entrada toda vez que faz sync, descreve o que mudou e impacto
- **`prototipo-ui/CODE_NOTES.md`** — você deixa notas pro Cowork (problemas detectados na portagem, sugestões de simplificação, conflitos)
- Wagner copia/cola o conteúdo desses arquivos no chat do Cowork pra ele entender

### Wagner é o "carteiro"
- Cowork escreve em `HANDOFF.md` / `COWORK_NOTES.md` → Wagner exporta zip → você sincroniza no repo
- Você escreve em `SYNC_LOG.md` / `CODE_NOTES.md` → Wagner abre os arquivos no Cowork → Cowork lê

---

## 4. Comandos canônicos que Wagner vai te pedir

### "Sincroniza o protótipo"
Quando Wagner colocar um zip novo do Cowork em `D:\downloads\`:
```
1. Backup atual: cp -r prototipo-ui/ /tmp/prototipo-ui-bak-$(date +%s)
2. Extrair zip novo numa pasta temp
3. rsync ou cp para prototipo-ui/ (preservando .git e SYNC_LOG.md, CODE_NOTES.md)
4. Ler diff: git diff --stat prototipo-ui/
5. Ler COWORK_NOTES.md se existir — agir conforme
6. Anexar entrada em SYNC_LOG.md com data/hora, arquivos mudados, observações
7. Commit em branch chore/prototipo-sync-YYYY-MM-DD
8. Push e abrir PR (gh pr create)
9. Reportar a Wagner o link do PR
```

### "Porta a tela X do protótipo pra produção"
```
1. Ler prototipo-ui/<X>.jsx
2. Ler memory/decisions/0039-ui-chat-cockpit-padrao.md
3. Conferir tokens em resources/css/app.css
4. Criar resources/js/Pages/<Modulo>/<Tela>.tsx convertendo JSX→TSX
5. Substituir mocks por props Inertia (use<Page>)
6. Ligar atalhos J/K/E/A se for master/detail
7. Adicionar bloco LinkedApps se tela tiver contexto vinculado
8. Anotar em CODE_NOTES.md: o que foi simplificado, o que ficou pendente
9. Branch feat/<modulo>-<tela>-react, push, PR
```

### "Compara protótipo com produção atual"
```
1. Listar prototipo-ui/*-page.jsx
2. Para cada, achar a contraparte em resources/js/Pages/ (se houver)
3. Gerar tabela: Tela | Status protótipo | Status produção | Gap
4. Salvar em prototipo-ui/CODE_NOTES.md
```

### "Atualiza o LARAVEL_REPO_CONTEXT do Cowork"
```
1. Copiar CLAUDE.md (raiz) atualizado
2. Anexar lista de ADRs novos desde último sync
3. Salvar como prototipo-ui/LARAVEL_REPO_CONTEXT.md
4. Avisar Wagner pra colar lá no Cowork (manual)
```

---

## 5. Setup inicial — "estou conectado?"

Quando Wagner rodar `claude` pela primeira vez no repo, faça este auto-check:

```
✓ pwd está em D:\oimpresso.com (raiz do repo)?
✓ git status retorna repo limpo ou com mudanças esperadas?
✓ git remote -v mostra wagnerra23/oimpresso.com?
✓ ls prototipo-ui/ existe? Se não, dizer "Wagner, precisa fazer sync inicial"
✓ ls prototipo-ui/README.md, HANDOFF.md, CLAUDE_CODE_BRIEFING.md presentes?
✓ Ler HANDOFF.md → resumir em 3 linhas pra Wagner
```

Reporte:
> "Conectado em `wagnerra23/oimpresso.com` (branch X, head Y).
> Protótipo: <lido HANDOFF, resumo>.
> Última sync: <ler SYNC_LOG.md, última entrada>.
> Pronto. O que precisa?"

---

## 6. Limites — o que NÃO fazer sem confirmação

- **Não modificar `prototipo-ui/*.jsx` por conta própria.** O protótipo é jurisdição do Cowork. Você só sincroniza, lê e porta.
- **Não mergear PR direto na main** sem Wagner confirmar.
- **Não rodar `php artisan migrate` ou comandos destrutivos** sem confirmação explícita.
- **Não criar nova ADR** sem perguntar — Wagner abre.
- **Não responder em inglês.** Cliente é PT-BR.

---

## 7. Frases que Wagner vai dizer (e o que significam)

| Frase | Ação |
|---|---|
| *"sync"* / *"sincroniza"* | Comando 4.1 acima |
| *"porta a OS"* | Comando 4.2 com tela = OS |
| *"o que mudou no protótipo?"* | `git log prototipo-ui/ --oneline -20` + resumo |
| *"abre PR"* | `gh pr create` com title e body baseados nos commits |
| *"manda nota pro Cowork sobre X"* | Anexa em `CODE_NOTES.md` |
| *"li uma nota do Code"* | Você diz pra Wagner abrir `CODE_NOTES.md` e copiar pro Cowork |

---

## 8. Última coisa

Se Wagner começar uma sessão e você não souber em que pé estão as coisas, sempre comece lendo:
1. `prototipo-ui/HANDOFF.md` (o que o Cowork está fazendo agora)
2. `prototipo-ui/SYNC_LOG.md` última entrada (o que VOCÊ fez por último)
3. `git log --oneline -10` (o que o repo viu por último)

E só depois pergunte.
