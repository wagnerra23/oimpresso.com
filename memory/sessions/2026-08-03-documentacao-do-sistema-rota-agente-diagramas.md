---
id: sessions-2026-08-03-documentacao-do-sistema-rota-agente-diagramas
type: session
date: "2026-08-03"
topic: "A documentação do sistema ganha URL, busca e diagramas; nasce o especialista de escopo travado; e o achado que interrompeu tudo — credencial em claro num repositório público"
authors: [C]
module: Governance
owner: W
related_adrs:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0333-emenda-0330-eixo-rodar-e-observar-submedido
  - 0130-handoff-append-only-mcp-first
pii: false
---

# Sessão 2026-08-03 — documentação do sistema: rota, busca, diagramas e o especialista

**TL;DR** — [W] pediu documentação do sistema e recebeu quatro desvios antes de receber a
documentação. No caminho: 9 agents que não carregavam há meses, uma ADR ratificada, um
documento canônico que ensinava o oposto do canon, e **credencial em texto claro num
repositório público** — inclusive a senha mestra do cofre. A documentação saiu
(`oimpresso.com/documentacao`, com busca e diagramas), e nasceu um especialista de escopo
travado cuja primeira regra é a que faltou: **não adivinhar o escopo, perguntar**.

## O que entrou em produção

| PR | O que |
|---|---|
| [#5168](https://github.com/wagnerra23/oimpresso.com/pull/5168) | **9 agents voltam a existir** — `description:` plano com `<example>` na coluna 0 fazia o YAML ler `Context:`/`user:`/`assistant:` como chaves irmãs de `name:`; frontmatter inválido, agent descartado em silêncio. Entre os mortos: `whatsapp-doctor`, `capterra-senior`, `memoria-senior`. Discriminador medido **24/24**; correção `description: \|` |
| [#5171](https://github.com/wagnerra23/oimpresso.com/pull/5171) | **ADR 0333 ratificada** (eixo rodar-e-observar sub-medido) |
| [#5170](https://github.com/wagnerra23/oimpresso.com/pull/5170) | vista registrada em `VISTAS-PUBLICADAS.md` + linha no `README.md` |
| [#5173](https://github.com/wagnerra23/oimpresso.com/pull/5173) | **`DESIGN.md` ensinava "sidebar light"** citando UI-0009/0014 — dois saltos atrás do canon (UI-0023, dark-fixo, aceito 2026-07-16). Instrução ativa para regressão, verde em todos os gates |
| [#5186](https://github.com/wagnerra23/oimpresso.com/pull/5186) | ledger: LC-08 (40→41) e LC-13 (7→8) + lápide §5 do detector medido e reprovado |
| [#5188](https://github.com/wagnerra23/oimpresso.com/pull/5188) | credencial removida dos 4 arquivos editáveis |
| [#5182](https://github.com/wagnerra23/oimpresso.com/pull/5182) | **rota `/documentacao`** — a página **é** o `GUIA-DO-SISTEMA.md` renderizado em runtime |
| [#5199](https://github.com/wagnerra23/oimpresso.com/pull/5199) | **busca** no acervo via FULLTEXT que já existia em `mcp_memory_documents` |
| [#5201](https://github.com/wagnerra23/oimpresso.com/pull/5201) | GUIA §A7–A13 (as 3 eras · Jana · IA-OS · indexação · observabilidade · decisão · os 4 fluxos) + §B6.1 (fluxo de 6 passos) |
| [#5210](https://github.com/wagnerra23/oimpresso.com/pull/5210) | **diagramas em Mermaid** + a lib servida do próprio domínio |
| [#5205](https://github.com/wagnerra23/oimpresso.com/pull/5205) | **agente `documentacao-sistema`** (auto-merge armado no fim da sessão) |

Smoke real pós-deploy: `/` 200 · `/login` 200 · `/documentacao` 302→login (contratado) ·
`/js/mermaid.min.js` **200 com os 3.565.102 bytes exatos**.

## O desenho que sustenta a página

**Zero HTML commitado.** A rota lê o markdown do dono e converte a cada acesso. Não existe
cópia para envelhecer — se o Guia muda num PR, a página muda no request seguinte. Foi recusado
o caminho fácil (gerar e commitar o HTML) justamente por ser o vício que a [ADR 0256] descreve.

Decisões com motivo registrado: `/documentacao` e **não** `/docs` (aquele caminho já é servido
por arquivo estático **não-versionado** no servidor, que tem precedência sobre rota Laravel);
Mermaid servido de `public/js` e **não de CDN** (a página não carrega recurso externo, e passar
pelo Vite exigiria mexer no build do ERP — `manifest: false` quebra o helper `@vite`).

## 🔴 O achado que interrompeu tudo — e continua aberto

Ao reconciliar `INFRA.md` (órfão, 2 meses parado) apareceu **credencial em texto claro**. O
levantamento completo (12.850 arquivos varridos) achou **99 hits**, dos quais reais:

- a credencial de dev, **em 7 arquivos** — e **3 são append-only** (1 ADR + 2 handoffs), que
  não podem ser limpos sem quebrar a lei que protege a linhagem;
- **essa mesma senha é a do Vaultwarden** — o cofre que guarda os outros **27 segredos**;
- `SYSDBA`/`masterkey` (senha de fábrica do Firebird) em ~15 scripts de migração;
- 5 tokens `Bearer`.

**E o repositório é PÚBLICO** — eu havia afirmado o contrário sem medir, e isso inverteu o
veredito que já tinha sido entregue ao [W]. Corrigido por auto-adversário a pedido dele.

**Rotação é a única mitigação real** e é ato do [W]. Limpar arquivo é cosmético: o histórico
público permanece, e três cópias são intocáveis por lei.

## Por que nenhuma máquina viu

Três, nenhuma quebrada isoladamente — o buraco é da **composição**:

| Máquina | Corpus | Detector |
|---|---|---|
| `gitleaks` (PR + histórico semanal) | ✅ repo inteiro | ❌ procura assinatura de token, não par usuário/senha |
| `memory-health` (`checkSecretsInMemory`) | ❌ só `memory/`; a raiz está fora | ❌ exige a palavra `senha`/`password` colada no `:` |
| RAG/MCP | ❌ indexa `memory/*` | — |

Corolário que ficou escrito no §5: *"o scan de segredo está verde"* nunca significa *"não há
segredo"* — significa *"nenhum padrão conhecido apareceu onde ele olha"*.

## O especialista, e por que ele existe

Quatro desvios do mesmo pedido. [W]: *"eu peço mais sempre desvia do foco"* e *"por que está
puxando para documentação dos módulos se estou pedindo do sistema"*.

`documentacao-sistema` nasce com: **regra um — não adivinhe, pergunte**; escopo travado em
tabela é-seu/não-é-seu (charter por tela, BRIEFING por módulo, ADR, gate e segurança estão
**fora**); a rotina de **espionar as máquinas** antes de escrever (4 comandos, e *"a divergência
é o trabalho"*); o **loop de 5 tempos** (medir → traduzir → publicar → vigiar → **aprender**); e
a régua de qualidade com **critérios, não notas** — nota decorada apodrece.

## As duas grades

Pesquisa fresca (2026-08-03). A primeira estava **errada de categoria** — comparei documentação
de **sistema** com Diátaxis/Swimm/Mintlify, que são de **produto**. [W] pegou.

| Régua | Nota | Observação |
|---|---|---|
| errada (produto) | 52 | penalizava por não fazer o que não é escopo |
| **certa** (arc42 · C4 · ADR Nygard · Living Documentation) | **64** | mesma documentação |

Perfil da régua certa: **decision log 10** (369 ADRs append-only, índice gerado, merge = ratificação)
· fonte única 9 · **diagramas como código 2** ← o gap fechado nesta sessão · multi-stakeholder 4.

## Lições registradas (ledger + §5)

- **LC-08 → 41**: afirmei "o repo é privado" sem rodar `gh repo view`; e "gitleaks só vê linhas
  novas" sem abrir o workflow (existe scan de histórico, semanal, verde).
- **LC-13 → 8**: meu próprio script de varredura reportou `0 hits` com **corpus vazio** —
  `execSync` no Windows cai em `cmd.exe`, que não remove aspas simples; o glob virou literal.
- **§5 novo**: ampliar o regex de segredo para par usuário/senha — **medido e reprovado**
  (`painel_userpass`: 122 hits, esmagadoramente FP). O eixo defensável é **estender o corpus**
  (custo medido: 3 hits, os 3 reais).

## Padrão observado, não mecanizado

**Três vezes no mesmo dia, duas sessões**: alguém adiciona máquina e não regenera o censo
(`MAQUINAS-INVENTARIO.md`). O caso mais revelador é o terceiro — um PR que **já regenerou**
precisa regenerar **de novo** quando o `main` anda. Não é descuido; é ciclo.

Isso derruba a solução intuitiva (hook ao criar arquivo): ela não cobriria o caso 3. O padrão
que o projeto já usa para o índice de ADRs — **regenerar no merge** — não teria esse custo.
Registrado; armar exige FP medido e decisão [W].

## Pendente com [W]

1. **Rotacionar** — Vaultwarden primeiro (abre os outros 27); depois Firebird, VoIP, 5 tokens.
2. **Decidir se o repositório segue público** (o `README.md` declara "Software proprietário").
3. **Abrir `/documentacao` logado** e confirmar que os diagramas desenham.
4. **Sessão nova** para o especialista existir — agents carregam no início da sessão.
5. Opcionais da régua: **Vale** (prosa, único gap em zero com ferramenta madura) ·
   **multi-stakeholder** · censo no merge.
