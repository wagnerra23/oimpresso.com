---
slug: NNNN-handoff-v2-repo-nativo-fonte-unica
number: NNNN   # a atribuir pelo Code (monotônico · ADR 0028) — [CC] não numera
title: "Handoff v2 — repo como fonte única, entrega repo-nativa auditada e gate automático"
type: adr
status: autorizada-aguarda-versionamento
authority: derived
lifecycle: ativo
quarter: Q2-2026
proposed_at: 2026-06-17
proposed_by: [CC]
authorized_at: 2026-06-17
authorized_by: wagner
module: governance
tier: protocol
related_adrs: [0114, 0107, 0094, "UI-0013"]
parent_charter: prototipo-ui/PROTOCOL.md
supersedes: []
authors: [wagner, "claude-cowork"]
---

# ADR NNNN — Handoff v2: repo-nativo, fonte única, gate automático

> **Status:** ✅ **AUTORIZADA por [W] em 2026-06-17** ("concordo com tudo"). Vira **versionada**
> quando o Claude Code atribuir número monotônico (ADR 0028) e commitar — **o Code numera sempre** ([W]).
> Proposta por [CC]; altera o fluxo F1→F3 do PROTOCOL.md (ADR 0114). [CC] não numera/versiona.

---

## Contexto — o problema é de infraestrutura, não de esforço

O loop atual (F1 [CC] no Cowork → F3 [CL] no repo) sofre 3 falhas **nomeadas e medidas** na
indústria em 2026, não opinião:

1. **Humano como fio de integração.** Wagner copia/cola e ainda precisa *lembrar* de conferir o
   que chega no Code. Pesquisa de engenharia (Augment/DORA 2025): quando a adoção de agentes passa
   a confiança, **o engenheiro herda a checagem e a correção de quase-erros a cada transição**, e a
   carga **compõe** quando cada time tem setup próprio sem padrão/memória compartilhada. É
   exatamente a dor relatada por [W] (2026-06-17).
2. **Duas línguas.** O protótipo Cowork usa CSS cru (`.om-*`, oklch na mão); o repo usa Tailwind +
   shadcn + tokens (`bg-card`, `primary`, `foreground`, `cockpit.css`). Mandar `.om-*` pro Code =
   ele não acha o que estilizar e **improvisa** → saída sem cor/tipografia (relato [W] 2026-06-17).
   Indústria chama de perda de fidelidade no handoff; o conserto provado é **fonte única de tokens
   + mapeamento 1:1 de componente** (Figma Code Connect, Style Dictionary, design-system-como-contrato
   tipo Circuit DS/JumpCloud, code-based design tipo UXPin Merge).
3. **Gate na memória do humano.** "Pronto quando…" hoje depende de [W] lembrar de olhar. O padrão
   provado é **Definition-of-Done automatizada** (CI + screenshot-diff + testes), não checklist
   manual.

**Prova local (sessão 2026-06-17, não teoria):** com acesso de *leitura* ao `main`
(`wagnerra23/oimpresso.com`), [CC] auditou 4 "ondas" pedidas e descobriu que **3 eram inúteis**
— 2 já estavam feitas no repo (`ContextSidebarV4` já faz `hidden lg:block`/`lg:hidden`) e 1 não
existe no repo (comentário inline é feature só-Cowork). **Ler o `main` antes de escrever cortou
~75% da superfície de erro.** Isso é o núcleo desta ADR.

## Decisão — 3 regras duras

### R1 — Entrega SEMPRE repo-nativa e auditada contra o `main` (mata críticas #2 e a re-inferência)
- Antes de qualquer ponte F1→F3, [CC] **lê os arquivos reais do `main`** (GitHub read já
  disponível) e **audita**: o que já existe, o que não existe, o que diverge. O que já está feito
  vira "**NÃO TOCAR**"; o que não existe no repo é **descartado** da ponte.
- A ponte entrega o diff **na língua do repo**: classes Tailwind + **tokens existentes**
  (`bg-card`/`primary`/`foreground`/`border` e o bloco `.cockpit{}` de `cockpit.css`).
  **Proibido** entregar `.om-*` cru ou oklch literal quando existe token equivalente.
- Referência visual continua sendo o protótipo Cowork, mas como **fonte upstream**, não como
  código a ser colado (princípio "o sistema é a verdade, o protótipo é input" — JumpCloud/Circuit DS).
- **Pronto quando:** a ponte cita arquivo+linha reais do `main`, usa só tokens do repo, e marca
  explicitamente o que não muda.

### R2 — O barramento é o repo, não o clipboard do [W] (mata crítica #1)
- O handoff F1→F3 é **commitado no repo** como artefato persistente. Já existe o canal:
  `prototipo-ui/COWORK_NOTES.md` (INBOX [W]→[CC]/[CL], ADR 0114). A ponte de [CC] vira uma
  entrada estruturada ali — o Code lê do **repo**, no ritmo dele, sem [W] reintermediar.
- [W] cola **uma vez** (ou autoriza o sync) e para. Zero "lembrar de conferir o transporte".
- **Não usar** URLs públicas efêmeras (expiram ~1h) como canal — só como anexo opcional.
- **Alvo final (decisão de infra de [W]):** conectar o Code via MCP/automação pra ler o artefato
  sem nem o paste único. Fora do alcance de [CC] (não escrevo no git) — fica como norte.

### R3 — Definition-of-Done automatizada, não memória (mata crítica #3)
- Cada ponte declara critérios **verificáveis por máquina**, plugados na infra que já existe:
  `conformance-gate.mjs` (ratchet de conformância DS) + F1.5 critique (`critique-score.json` ≥80)
  + F2 screenshot. Meta: virar **bloqueio de merge** no PR, não checklist que [W] lê.
- Telas responsivas entram com screenshots @375/@768/@1280 obrigatórios no PR.
- **Pronto quando:** o PR só mergeia (F4) se o gate passou — [W] aprova exceção via override
  explícito (`/screenshot-override` etc, PROTOCOL §5), nunca por esquecimento.

## O que NÃO muda
- Os 6 papéis × 7 fases (ADR 0114). R1–R3 endurecem F1 e F3, não trocam o protocolo.
- Soberania: [CC] propõe, [W] decide, [CL] numera/commita. Esta ADR é proposta.
- Cowork segue read-only no git; [CC] nunca commita.

## Migração — incremental, baixo risco (não "big bang")
1. **Imediato (já aplicável):** toda ponte nova de [CC] segue R1 (ler `main` + entregar em tokens
   do repo + auditar). Custo zero de infra — só disciplina. **Já validado nesta sessão.**
2. **Curto:** padronizar o bloco de handoff dentro de `COWORK_NOTES.md` (template R2). [W] cola lá.
3. **Médio:** ligar `conformance-gate.mjs` + critique-score como **required check** no PR (R3).
4. **Norte (infra [W]):** MCP/sync Cowork↔repo pra zerar o paste.

## Responsabilidades
| Papel | Faz |
|---|---|
| **[W]** | autoriza esta ADR; decide o canal (COWORK_NOTES vs MCP); aprova overrides |
| **[CC]** | lê `main` antes de propor; entrega repo-nativo+auditado; nunca `.om-*` cru pro Code |
| **[CL]** | lê o artefato do repo; aplica; liga os gates como required checks; numera sob OK de [W] |
| **[CD]/[CA]** | critique-score / a11y como gate automatizado |

## Consequências
- **Positiva:** [W] sai do meio; Code para de improvisar (recebe tokens reais); ~75% menos
  retrabalho (medido nesta sessão); fidelidade de cor/tipografia preservada por construção.
- **Negativa:** [CC] gasta mais por ponte (ler o `main` antes). Aceitável — troca minutos de
  leitura por horas de retrabalho do Code e frustração de [W].
- **Risco:** R3 (gate como required check) pode travar PR legítimo → mitigado pelos overrides §5.

## Decisões de [W] (2026-06-17)
1. **Canal do R2:** investigar **MCP** — [W] já tem `mcp.oimpresso.com` rodando (TeamMcp · tokens/scopes/quotas/audit · Claude Code já é cliente `mcp__oimpresso__*`). Alvo: tool `handoff-pending` no servidor existente. _Ponte Cowork→repo é o único elo faltante (ver §MCP)._
3. ✅ **[CL] numera sempre** e abre PR (autorizado por [W]).
2. R3 (required check vs warning 1 sprint) — _a confirmar com [W]._

## §MCP — o que [W] já tem e o elo que falta (descoberto lendo o projeto 2026-06-17)
- **Já existe:** `mcp.oimpresso.com` (servidor MCP do ERP) com tools `decisions-search`, `brief-fetch`; `Modules/TeamMcp` com `mcp_tokens/scopes/quotas/audit_log/actors`, `McpTokenIssuer`, DXT 1-clique, identity-mesh humano×agente. **Claude Code já é cliente** (`mcp__oimpresso__*` aparece nele).
- **Elo faltante:** o handoff é produzido por [CC] no **filesystem do Cowork**, onde só [CC] escreve. Pro MCP servir isso ao Code, o artefato precisa **aterrissar na fonte que o servidor já lê** (repo/DB). [CC] não escreve lá.
- **Menor passo (zero infra):** paste-único em `COWORK_NOTES.md` → tool `handoff-pending` lê o arquivo do repo → Code puxa sozinho. Mantém 1 paste.
- **Zero-paste (infra de [W]):** um job de sync (cron/GitHub Action) que busca o export do Cowork e grava no repo/DB → aí o `handoff-pending` serve sem nenhum paste. Esse job é a única peça nova; [CC] não consegue construí-lo (sem git/escrita).

## Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | [W]+[CC] | PR #295 — protocolo v1.0 (6 papéis × 7 fases) |
| 2026-06-17 | [CC] (propõe) | Esta proposta — handoff v2 (repo-nativo · fonte única · gate automático) |
| 2026-06-17 | **[W] (autoriza)** | "concordo com tudo" · Code numera sempre · canal R2 = explorar MCP (`mcp.oimpresso.com`) |
