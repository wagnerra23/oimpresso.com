---
sessao: "00b"
titulo: Lista completa da ponte — todo processo, ponta a ponta
autor: "[CC]"
criado: 2026-08-23
base: wagnerra23/oimpresso.com@main (tree d1ccdff91be9, lido 2026-08-23T15:18–15:24Z)
regra: numeração estável — nunca renumerar; item morto vira ~~riscado~~ com motivo
---

# Lista completa — 84 processos da ponte Cowork → produção

> ## ⚠️ ERRATA 2026-08-23 (pós-medição)
> **S7/S8 e a leitura de gargalo deste arquivo foram corrigidas** por `06-CORRECAO-MEDIDA.md`. O que muda: **T.06 morreu** (46 checks já são required com `enforce_admins`); **7.08 morreu** (flip já feito); a conta "50 processos são [W]" segue certa, mas [W] **não é o gargalo** — produção anda (5.811 merged, deploy contínuo). O gargalo é **artefato**: 29 telas prontas de 54, as 25 travadas em casos+UC e scorecard. Prioridade vigente: `04-PENDENTES.md` revisão 2.

> Legenda: **[CC]** eu · **[CL]** Claude Code · **[W]** Wagner · **[W2]** aprova merge · **[CA]** a11y · **CI** máquina
> Estado: ⬜ não começou · 🟡 em curso · ✅ feito · 🔴 bloqueado · ⛔ decisão [W]

---

## S1 · Build mecânico — Onda 1+2 (11 processos)

| # | Processo | Dono | Estado |
|---|---|---|---|
| 1.01 | Ler read-order do `main` (CLAUDE · PROTOCOL · PRE-FLIGHT · RUNBOOK-contrato · INDEX/proibicoes/LICOES) | [CC] | ⬜ |
| 1.02 | Confirmar ausência de colisão dos 5 nomes no `main` | [CC] | ⬜ |
| 1.03 | Portar `compras-grade-matrix.jsx` → `prototipo-ui/cowork/` | [CC] | ⬜ |
| 1.04 | Portar `compras-grade-matrix.css` trocando paleta bespoke por token `.cockpit` | [CC] | ⬜ |
| 1.05 | Validar `configuracoes.contract.json` no `contract.schema.json` | [CC] | ⬜ |
| 1.06 | Validar `patrimonio.contract.json` | [CC] | ⬜ |
| 1.07 | Validar `venda-menu.contract.json` | [CC] | ⬜ |
| 1.08 | Conferir que os 3 `alvo` EXISTEM no `main` (ponteiro podre corrige na descida) | [CC] | ⬜ |
| 1.09 | Passar o `cowork-ssot-guard.mjs` mentalmente (sem `?v=`, `.bak`, screenshot, process-doc) | [CC] | ⬜ |
| 1.10 | Escrever `_saida-S1.md` com pedido literal pro [CL] | [CC] | ⬜ |
| 1.11 | Colar 1× / abrir Issue `cowork-intake` → PR → merge | [W] | ⬜ |

**Bloqueio residual:** a outra metade da allowlist (`inventario-migracao`) depende de 3.25.

---

## S2 · Trio órfão — Onda 2b (13 arquivos, 16 processos)

| # | Processo | Dono | Estado |
|---|---|---|---|
| 2.01 | Read-order | [CC] | ⬜ |
| 2.02 | **Reconfirmar** ausência dos 8 caminhos no `main` neste turno | [CC] | ⬜ |
| 2.03 | `Ponto/Conformidade` — charter + casos | [CC] | ⬜ |
| 2.04 | `Ponto/Fechamento` — charter + casos | [CC] | ⬜ |
| 2.05 | `Ponto/Index` — charter + casos | [CC] | ⬜ |
| 2.06 | `Ponto/RepP` — charter + casos | [CC] | ⬜ |
| 2.07 | `Relatorios/Index` — charter + casos | [CC] | ⬜ |
| 2.08 | `Ponto/Colaboradores/Index.casos.md` (charter já no main) | [CC] | ⬜ |
| 2.09 | `Ponto/Configuracoes/Index.casos.md` | [CC] | ⬜ |
| 2.10 | `Ponto/Escalas/Index.casos.md` | [CC] | ⬜ |
| 2.11 | Conferir frontmatter dos 13 contra o padrão vivo (`Ponto/Espelho/Index.casos.md`) | [CC] | ⬜ |
| 2.12 | Todo charter com Mission·Goals·Non-Goals·UX·Hooks·Anti-hooks·Pendências | [CC] | ⬜ |
| 2.13 | Todo casos com Rastreabilidade + Dado/Quando/Então + `[BACKLOG]` | [CC] | ⬜ |
| 2.14 | UC de tenant leva `[T0]` + ADR 0093 + "biz=1 vs fictício, nunca biz=4" (ADR 0101) | [CC] | ⬜ |
| 2.15 | Nenhum UC ✅ sem lane executada | [CC] | ⬜ |
| 2.16 | `_saida-S2.md` + ponte | [CC]/[W] | ⬜ |

---

## S3 · Curadoria do intake — Onda 3 (25 processos: 24 ⛔ [W] + 1 meu)

**Regra:** cada item termina em ISSUE · DESTILAR · MORRER.

| # | Item | Grupo | Estado |
|---|---|---|---|
| 3.01 | `PEDIDO-CL-applier-digest` | pedido [CL] | ⛔ |
| 3.02 | `PEDIDO-CL-programa-doc` | pedido [CL] | ⛔ |
| 3.03 | `PEDIDO-CL-programa-doc-react` | pedido [CL] | ⛔ |
| 3.04 | `MODULOS-F3-ONDAS-PARA-CODE` | pedido [CL] | ⛔ |
| 3.05 | `SUPERADMIN-F3-ONDAS-PARA-CODE` | pedido [CL] | ⛔ |
| 3.06 | `ACESSOS-F1` | F1 entregue | ⛔ |
| 3.07 | `CMS-F1` | F1 entregue | ⛔ |
| 3.08 | `MODULOS-F1` | F1 entregue | ⛔ |
| 3.09 | `NOTIFICACOES-F1` | F1 entregue | ⛔ |
| 3.10 | `SUPERADMIN-F1` | F1 entregue | ⛔ |
| 3.11 | `CATCHUP-F1` | F1 entregue | ⛔ |
| 3.12 | `JANA-CICLO-COMPLETO-PRODUCAO` | Jana | ⛔ |
| 3.13 | `JANA-FASE2` | Jana | ⛔ |
| 3.14 | `JANA-FUSAO` | Jana | ⛔ |
| 3.15 | `JANA-MODULO-ONDAS-PR` | Jana | ⛔ |
| 3.16 | `JANA-PAINEL-DARK-PARIDADE` | Jana | ⛔ |
| 3.17 | `FORJA-COCKPIT-CHARTER-V2-PROPOSTA` | Forja/plano | ⛔ |
| 3.18 | `FORJA-TOPNAV-3GRUPOS-LEVA1` | Forja/plano | ⛔ |
| 3.19 | `PLANO-BLADE-PARA-REACT` | Forja/plano | ⛔ |
| 3.20 | `PLANO-MESTRE-trilha-d-ciclo-completo` | Forja/plano | ⛔ |
| 3.21 | `INVENTARIO-L1-VENDAS-PDV` | Forja/plano | ⛔ |
| 3.22 | `FICHA-BL-home-index` | outro | ⛔ |
| 3.23 | Inventariar as 12 subpastas (acessos, casos-financeiro, cms, connector, essenciais, hrm, modulos, notificacoes, produto, produto-telas-novas, programa-doc, venda-menu) | [CC] | ⬜ |
| 3.24 | Veredito das 12 subpastas | [W] | ⛔ |
| 3.25 | **`inventario-migracao`: `Pages/Stocks/` ou `Pages/Inventario/`?** — trava a allowlist do guard e o S6 | [W] | ⛔ |

---

## S4 · Diff do resíduo — Onda 4 (10 processos, nenhum delete)

| # | Processo | Dono | Estado |
|---|---|---|---|
| 4.01 | Read-order | [CC] | ⬜ |
| 4.02 | Diff `prototipo-ui-patch/memory/` (maior risco de cópia única) | [CC] | ⬜ |
| 4.03 | Diff `prototipo-ui-patch/scripts/` | [CC] | ⬜ |
| 4.04 | Diff `prototipo-ui-patch/Modules/` · `Pages/` · `app/` | [CC] | ⬜ |
| 4.05 | Diff `prototipo-ui-patch/resources/` · `routes/` | [CC] | ⬜ |
| 4.06 | Diff `prototipo-ui-patch/prototipos/` · `prototipo-ui/` · `pageheader-canon-v4/` | [CC] | ⬜ |
| 4.07 | Triagem dos 4 prompts (ONDAS-FINANCEIRO-APLICAR, ERRADICA-LOCACAO-ACTIONS, FORJA-ABSORCAO-TEAMMCP, PROMPT_MESTRE_2026-06-29) | [CC] | ⬜ |
| 4.08 | Diff `resources/` e `scripts/` da RAIZ local | [CC] | ⬜ |
| 4.09 | Três listas nomeadas: 🔴 cópia única · 🟢 delete-seguro · 🟠 diverge | [CC] | ⬜ |
| 4.10 | Executar deletes da lista 🟢 | [W] | ⛔ |

---

## S5 · Contratos (17 processos)

| # | Processo | Dono | Estado |
|---|---|---|---|
| 5.01 | Revisar `ponto-dashboard/Index.casos.md` (6 UC, já escrito) | [CC] | 🟡 |
| 5.02 | Ler o charter da CaixaUnificada (33 KB) — pré-requisito de 5.03 | [CC] | ⬜ |
| 5.03 | Escrever `Atendimento/CaixaUnificada/Index.casos.md` | [CC] | 🔴 dep. 5.02 |
| 5.04 | **Schema do `financeiro-unificado.intent.json`**: família declarada ou contract próprio? | [W] | ⛔ |
| 5.05 | Promover/manter: superadmin-dashboard, 6 seções fora (funil trial→pago, churn 30d, receita por pacote, fila vencendo, o-que-fazer-primeiro) | [CC]/[W] | ⬜ |
| 5.06 | superadmin-negocios: bulkbar + seleção múltipla + FormDrawer (3 fora) | [CC]/[W] | ⬜ |
| 5.07 | superadmin-assinaturas: FormDrawer contado como 2 seções (1 fora) | [CC]/[W] | ⬜ |
| 5.08 | superadmin-pacotes: `form-pacote` — escreve `price`, cai na REGRA MESTRE de `proibicoes.md` | [CC]/[W] | ⬜ |
| 5.09 | modulos: `vazio` (copy diverge) + `drawer` PT-02 (dep. decisão D3) | [CC]/[W] | ⬜ |
| 5.10 | ponto-painel §1: "Presentes agora" em tempo real — consulta por carga ou refresh manual? | [W] | ⛔ |
| 5.11 | ponto-painel §2: copy fixa da nota de fechamento? | [W] | ⛔ |
| 5.12 | ponto-espelho §1: DSR na folha — de quem é a conta no vivo (Service, não tela)? | [W] | ⛔ |
| 5.13 | ponto-espelho §2: quantos meses navegáveis (`Config/retention.php`)? | [W] | ⛔ |
| 5.14 | jana-painel §1: título — "Painel" ou "Dashboard"? (sobrou em 2 lugares) | [W] | ⛔ |
| 5.15 | jana-painel §2: botão "Exportar relatório (em breve)" — some, `disabled` com motivo, ou entrega? | [W] | ⛔ |
| 5.16 | jana-painel §3: brief diário / TTS / retenção — viram config de verdade? (é backend) | [W] | ⛔ |
| 5.17 | **Paginação: 6/página (F1) vs 20 (produção)** — vale para negocios E assinaturas, decidir junto | [W] | ⛔ |

---

## S6 · Implantação (9 portões × fila de 10 telas)

### Os 9 portões (nenhum pula)
| # | Portão | Dono | Prova |
|---|---|---|---|
| 6.1 | Charter com Non-Goals + Anti-hooks aprovados | [W] | gate ADR 0107 |
| 6.2 | `casos.md` com UC Dado/Quando/Então | [CC] | trio completo |
| 6.3 | Contrato no schema, `alvo` existente | [CC] | `contract.schema.json` |
| 6.4 | Âncoras `data-contract` na tela | [CL] | `grep -c data-contract` |
| 6.5 | Copy literal bate nos dois lados | CI | `scripts/contrato-de-tela.mjs` |
| 6.6 | Readiness ✅ | máquina | `scripts/qa/prototipo-readiness.mjs` |
| 6.7 | Lane Pest com UC citado + veredito | [CL] | teste real |
| 6.8 | a11y | [CA] | F3.5 |
| 6.9 | Screenshot 1280 (ROTA LIVRE) + 1440 | [W2] | aprova merge |

### Fila de telas — menor risco primeiro
| # | Tela | Portão aberto hoje | Estado |
|---|---|---|---|
| 6.01 | `Ponto/Dashboard/Index` | 6.2 (S5.01) · 6.4 · 6.7 | ⬜ |
| 6.02 | `Modules/Index` | 6.4 · 6.7 · `.tsx` não verificado por mim | ⬜ |
| 6.03 | `Backup/Index` | 6.4 · 6.7 | ⬜ |
| 6.04 | `superadmin/Pacotes` | 6.7 · recorte 5.08 | ⬜ |
| 6.05 | `superadmin/Negocios` | 6.7 · recortes 5.06 · paginação 5.17 | ⬜ |
| 6.06 | `superadmin/Assinaturas` | 6.7 · recorte 5.07 · paginação 5.17 | ⬜ |
| 6.07 | `superadmin/Dashboard` | 6.7 · 6 recortes (5.05) | ⬜ |
| 6.08 | `Ponto/Espelho/Show` + `Index` | 6.7 · pendências 5.12/5.13 | ⬜ |
| 6.09 | `Jana/Index` | 6.7 · pendências 5.14/5.15/5.16 | ⬜ |
| 6.10 | `Atendimento/CaixaUnificada` | 6.2 (S5.03) · prova do acordo `paired`/`connected` nos DOIS lados | ⬜ |
| 6.11 | `Financeiro/Unificado/Index` | 6.3 (schema 5.04) · E2E de fluxo, não grep | ⬜ |
| 6.12 | ~~`Financeiro/Unificado/Novo`~~ | charter+casos órfãos, **sem `.tsx`** — não é implantação, é decisão de escopo | ⛔ |

---

## Testes reais que faltam (o que nenhum grep prova)

| # | Teste | Tela | Por quê |
|---|---|---|---|
| T.01 | `PontoDashboardContratoTest` — 6 UC | Ponto/Dashboard | os 6 UC nascem ⬜; não existe lane |
| T.02 | Multi-tenant `[T0]` em cada agregado do painel | Ponto/Dashboard | um `sum()` sem `business_id` basta |
| T.03 | Query count do polling 30s com `Inertia::defer` | Ponto/Dashboard | pendência do próprio charter |
| T.04 | Acordo semântico `paired`/`connected` nos dois lados | CaixaUnificada | bug #2984; catraca de presença deixou passar |
| T.05 | E2E dos 5 fluxos (drawer, baixa, lote) | Financeiro/Unificado | `deve_conter` passa com a tela quebrada |
| T.06 | Flip do step "Intenção de fluxo" para required | CI | `modo:"enforcing"` é declarativo (ADR 0261) |

---

## Higiene pendente

| # | Item | Nota |
|---|---|---|
| H.01 | `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` **não apareceu** na varredura do `main` 2026-08-23, embora o `CLAUDE.md` mande lê-lo todo chat | confirmar caminho ou corrigir o `CLAUDE.md` |
| H.02 | `EXEMPLO.contract.json` falha de propósito | correto por design — não "consertar" |
| H.03 | Backup tem charter/casos duplicados em `design-docs/handoff/` | qual é o canônico? |
| H.04 | Ponto/Espelho tem charter/casos duplicados em `design-docs/` | idem |
| H.05 | `jana-painel.fonte` aponta pra tela viva, não protótipo | único caso; script que assuma "fonte=protótipo" quebra nele |
| H.06 | Protótipo legado `prototipo-ui/prototipos/compras-grade-matrix/` | propor `_arquivo/` após 1.11 |

---

## S7 · Pós-merge — rollout ao cliente vivo (14 processos)

> A tela mergeada **não é** tela implantada. Este bloco é o que separa "está no `main`" de "a Larissa usou".

| # | Processo | Dono | Estado |
|---|---|---|---|
| 7.01 | Deploy do `main` no ambiente do piloto | [CL] | ⬜ |
| 7.02 | Smoke real em **1280px** na máquina do balcão (ROTA LIVRE / Larissa) — não em emulação | [W] | ⬜ |
| 7.03 | Smoke em 1440px (escritório / Wagner) | [W] | ⬜ |
| 7.04 | Smoke em tablet (Técnico Repair), toque ≥44px | [W] | ⬜ |
| 7.05 | Conferir **biz=164 LIVE** (Martinho/Oficina) sem vazamento de outro tenant | [CL] | ⬜ |
| 7.06 | Checar que `Inertia::defer` não multiplicou query em carga real | [CL] | ⬜ |
| 7.07 | Observar a lane advisory por 1 ciclo antes de promover a required | CI | ⬜ |
| 7.08 | **Flip para required** dos checks estáveis — dono é `governance/required-checks-baseline.json` | [W] | ⛔ |
| 7.09 | Colher a primeira reclamação da Larissa (é dado, não ruído) | [W] | ⬜ |
| 7.10 | Registrar divergência protótipo↔vivo que apareceu no uso | [CC] | ⬜ |
| 7.11 | Se a copy mudou no vivo por decisão [W]: corrigir o contrato **no mesmo PR** (regra do perdedor se corrige junto) | [CC] | ⬜ |
| 7.12 | `status: draft` → `status: live` no charter, só após 7.02–7.04 | [W] | ⛔ |
| 7.13 | Escrever a lição em `memory/LICOES_CC.md` se algo quebrou | [CC] | ⬜ |
| 7.14 | ADR se a decisão foi estrutural (não é toda mudança que vira ADR) | [W] | ⛔ |

---

## S8 · Encerramento da esteira (10 processos)

> O objetivo final da ponte é o Cowork **ficar vazio de tudo que não é build**. Enquanto houver cópia única aqui, a ponte não acabou.

| # | Processo | Dono | Estado |
|---|---|---|---|
| 8.01 | Reconferir a fronteira: aqui só `oimpresso.com.html` + jsx/css que ele carrega + `_ds/` + `github.md` + `CLAUDE.md` | [CC] | ⬜ |
| 8.02 | Zero `.charter.md` / `.casos.md` no Cowork (todos no `main`) | [CC] | ⬜ |
| 8.03 | Zero `.contract.json` no Cowork | [CC] | ⬜ |
| 8.04 | Zero process-doc fora de `cowork-inbox/` | [CC] | ⬜ |
| 8.05 | `prototipo-ui-patch/` eliminado (após S4) | [W] | ⛔ |
| 8.06 | `cowork-inbox/` esvaziado ou espelhado como Issues (após S3) | [W] | ⛔ |
| 8.07 | `COWORK_NOTES.md` segue congelada para itens novos | [CC] | ✅ |
| 8.08 | `github.md` com `## Last sync` real (data ISO verdadeira, commit só se conhecido) | [CC] | ⬜ |
| 8.09 | `cowork-ssot-guard.mjs` verde com allowlist **vazia** — a prova mecânica de que acabou | CI | ⬜ |
| 8.10 | Declarar o ciclo fechado; próxima tela começa por `PRE-FLIGHT-TELA.md`, não por esta lista | [W] | ⬜ |

---

## Revisão desta lista — erratas encontradas na releitura

| # | Errata | Correção |
|---|---|---|
| R.01 | O total dizia **84**; a soma dos blocos dá **91** | corrigido abaixo. A conta velha esquecia 6 testes + parte da higiene |
| R.02 | "45 meus / 40 [W]" não fechava com 84 | fecha com 91: 45 + 40 + 6 ([CL]) = 91 |
| R.03 | S5 rotulado "16 processos", tabela tem 5.01–5.17 | rótulo corrigido para 17 |
| R.04 | S3 rotulado "todos ⛔", mas 3.23 (inventariar subpastas) é meu | rótulo corrigido |
| R.05 | Fila do S6 tem 12 linhas mas 6.12 é ⛔ de escopo, não implantação | são **11 telas implantáveis** + 1 decisão |
| R.06 | Faltava o pós-merge — a lista terminava no merge, e merge não é produção | S7 acrescentado |
| R.07 | Faltava o critério de FIM da ponte | S8 acrescentado (8.09 é a prova mecânica) |
| R.08 | `Jana/Index.tsx` e `Modules/Index.tsx` marcados "não verificado" — escaparam do meu filtro de árvore, não do repo | verificar em S6, sem afirmar ausência |

---

## Contagem final (revisada)

| Bloco | Processos | [CC] | [W] | [CL]/CI |
|---|---|---|---|---|
| S1 · Build mecânico | 11 | 10 | 1 | — |
| S2 · Trio órfão | 16 | 15 | 1 | — |
| S3 · Curadoria | 25 | 1 | 24 | — |
| S4 · Diff resíduo | 10 | 9 | 1 | — |
| S5 · Contratos | 17 | 6 | 11 | — |
| S6 · Portões | 9 | 2 | 2 | 5 |
| S6 · Fila de telas | 11 (+1 ⛔) | — | — | — |
| S7 · Pós-merge | 14 | 3 | 5 | 6 |
| S8 · Encerramento | 10 | 6 | 3 | 1 |
| Testes reais | 6 | — | — | 6 |
| Higiene | 6 | 4 | 2 | — |
| **Total** | **124 + 12 telas** | **56** | **50** | **18** |

---

## O caminho crítico, em uma linha cada

1. **3.25** — path da `inventario-migracao`. Fecha a allowlist do guard. Trava S1, S8.09.
2. **5.17** — paginação 6 vs 20. Destrava Negocios e Assinaturas de uma vez.
3. **5.04** — schema do `intent.json`. Sem isso o Financeiro não tem contrato válido; é a última tela da fila e a de maior risco.
4. **7.08** — flip para required. Enquanto advisory, toda catraca é conselho, não tranca.
5. **T.01–T.03** — a lane Pest do Ponto/Dashboard. É a primeira tela da fila e a única sem nenhum teste real.

**Se você só fizer três coisas:** 3.25, 5.17 e mandar o [CL] escrever T.01. Isso move 4 telas e dá o primeiro veredito real da esteira.

---

## O que esta lista NÃO cobre (declarado, não esquecido)

- As telas do **nível Norte** do Cowork (Clientes/CRM, Atendimento índice, Oficina Auto, PT-01/05/07): nenhuma tem contrato, nenhuma está na fila. São ciclo próprio, não ponte.
- **Módulos sem nada aqui**: Orçamentos, Vendas/PDV, Produção/OP, Comunicação visual, Estoque, Compras, Fiscal NF-e, BI. A ponte cobre Ponto, Superadmin, Financeiro, Backup, Modules, Jana, Atendimento — sete frentes, não o ERP.
- **Conteúdo das 12 subpastas do intake** — 3.23 é justamente descobrir o que há lá. Até rodar, o total pode crescer.
