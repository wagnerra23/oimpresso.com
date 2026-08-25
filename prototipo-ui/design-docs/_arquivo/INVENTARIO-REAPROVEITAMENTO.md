# _arquivo/INVENTARIO-REAPROVEITAMENTO.md — o que dá pra aproveitar do arquivo

> **Data:** 2026-06-07 · **Autor:** [CC] · **Pedido [W]:** "inventário dos itens arquivados, classifique por
> o que dá pra aproveitar e pra quê — salve pra referência futura."
> **Complementa** `_arquivo/INDEX.md` (manifesto por origem/tema). Aqui a lente é **REAPROVEITAMENTO**:
> cada item ganha uma nota de reuso + **o que especificamente se puxaria dele** pro trabalho atual
> (primitivos de layout ADR 0253 · Financeiro 9.75 · DS v6).
> **Regra do arquivo:** append-only — nada apagado, só classificado. Reuso = **ler/colher**, não mover.

## Legenda
- 🟢 **Aproveitável agora** — tem padrão/componente/decisão que puxo pro trabalho corrente.
- 🟡 **Parcial / datado** — algum valor, mas envelhecido (accent antigo, ponto-no-tempo). Puxar cirúrgico.
- 🔴 **Só histórico (lápide)** — superado por rota viva/git. Mantém pra rastreio, não pra colher.

---

## ★ TOP — o que puxar JÁ (alto valor pro trabalho atual)

| # | Item | O que se aproveitaria → pra quê |
|---|------|-------------------------------|
| 1 | 🟢 `exploracoes-2026-06-04/Método 9.75 Financeiro.html` | **A peça mais valiosa.** Rubrica de domínio do Financeiro: **5 princípios** (caixa antes de contabilidade · conciliação é o coração · fiscal grudado no dinheiro · cobrar é receita · IA classifica/humano decide) · **7 etapas de refino** · **22 features-tipo em 5 categorias** · diagnóstico honesto (**composto 7,6** — mesmo número da minha avaliação dos primitivos) · roadmap **7,6→9,75** · 3 tiers de integração bancária (OFX→Open Finance→API Inter/Itaú). → **Medir a prova viva contra isto** e priorizar o que falta (conciliação, cobrança, fiscal, drawer). |
| 2 | 🟢 `telas/Frescor - Clientes vs Financeiro.html` | **Regra de design do frescor:** o pill de ageing **encaixa em Clientes/CRM** (tempo esfria difuso) e é **redundante no Financeiro** (data dura = vencimento). Veredito: no Financeiro usar **badge de vencimento + FSM stepper**, não frescor. → Confirma que a v3 da prova acertou (vencimento/urgência, não pill de frescor). Guia onde aplicar ageing nas outras telas. |
| 3 | 🟢 `ds/Design System v5.html` | Seção **"Primitivos"** (Botões, Campos de form, switch…) + componentes que alimentaram o `ds-v6`. → Conferir specs de componente **antes de compor tela** com os primitivos; é o elo entre a régua CSS (ds-v6) e o histórico. |
| 4 | 🟢 `venda-estado-da-arte-2026-06-01/Venda Estado-da-Arte - NF-e + NFS-e.html` | Padrões de **emissão fiscal NF-e/NFS-e** (estado da arte). → Alimenta o "fiscal grudado no dinheiro" do Financeiro e o **drawer de detalhe** (NF-e/conciliação) que ainda falta na prova viva. |
| 5 | 🟢 `sessao-2026-05-30/Auditoria - O Melhor de Cada Tela.html` | Lista de **"o melhor de cada tela"** (padrões a proteger). → Checklist anti-regressão ao recompor telas com primitivos — não perder o que já era vencedor. |
| 6 | 🟢 `referencia/Diagnóstico Vendas KB-9.75.html` + `referencia/Metodo 9.75 - OS.html` | A **rubrica KB-9.75** (15 dimensões, score 0–10) **aplicada** a Vendas e OS. → Modelo de avaliação reutilizável pra qualquer tela (mesma régua que usei nos primitivos). |
| 7 | 🟢 `legado/uploads/Design System/resources/js/Components/shared/*` (`DataTable.tsx`, `PageHeader.tsx`…) | **Componentes shadcn reais** (TSX) do DS antigo. → Fonte pro `.tsx` do [CL]: o `Badge`/`Button`/`DataTable` que a prova mostra inline existem aqui como referência de implementação. |
| 8 | 🟢 `sessao-2026-05-30/Auditoria Sidebar + PageHeader.html` | Specs de **sidebar + PageHeader**. → O **shell** que falta na prova viva (sidebar 260px + breadcrumb) — escopo do [CL]. |

---

## Inventário completo por pasta

### `telas/` — telas de exploração
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `Frescor - Clientes vs Financeiro.html` | 🟢 | **(TOP #2)** regra de onde aplicar frescor/ageing |
| `Cadastro Cliente - Pagina Inteira DS 4.2.html` | 🟡 | molde **PT-03** (cadastro = página inteira, não Sheet) — pra os 3 cadastros |
| `Shell Real.html` | 🟡 | teste de paridade com **AppShellV2** do repo — referência de shell |
| `Integração Vendas × Oficina - Storyboard.html` | 🟡 | técnica de **storyboard** de fluxo entre módulos |
| `Clientes.html` · `Oimpresso ERP - Clientes.html` | 🔴 | rota viva (`clientes-page.jsx`) já cobre — só histórico |

### `referencia/` — diagnósticos por tela (método)
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `Diagnóstico Vendas KB-9.75.html` | 🟢 | **(TOP #6)** rubrica 15-dim aplicada |
| `Metodo 9.75 - OS.html` | 🟢 | **(TOP #6)** método aplicado à OS |
| `Cadastro de Contacts - Diagnóstico KB-9.75.html` | 🟡 | diagnóstico do cadastro de cliente |

### `sessao-2026-05-30/` — rascunhos de decisão
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `Auditoria - O Melhor de Cada Tela.html` | 🟢 | **(TOP #5)** checklist de proteção por tela |
| `Auditoria Sidebar + PageHeader.html` | 🟢 | **(TOP #8)** specs de sidebar/header |
| `Piloto Vendas - Antes Depois.html` | 🟡 | técnica **antes/depois** (útil pra apresentar a prova viva) |
| `Painel Cowork - Estado Atual.html` | 🔴 | espelho visual da espinha — `STATUS.md` é a fonte |

### `ds/` + `ds-historico/` — Design Systems
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `ds/Design System v5.html` | 🟢 | **(TOP #3)** primitivos + componentes → `ds-v6` |
| `ds/Design System v4.html` · `v4.2 - Evolucao.html` | 🟡 | data-viz primitives, evolução de componente (cockpit/fiscal/sla) — puxar cirúrgico |
| `ds/Design System v3.html` | 🟡 | tokens semânticos (camada de intenção) — histórico de spec |
| `ds-historico/` (v1 · v1.1 · v1.2 · v2 · DS v2 Plano/Reavaliação) | 🔴 | linhagem antiga (accent azul 220/identidade-por-tela) — só rastreio |

### `exploracoes-2026-06-04/` — explorações/benchmarks standalone
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `Método 9.75 Financeiro.html` | 🟢 | **(TOP #1)** rubrica de domínio do Financeiro |
| `Oimpresso ERP - Benchmark Estado-da-Arte.html` | 🟡 | critérios de benchmark vs Linear/Stripe/Mercury |
| `Oficina - Benchmark Estado da Arte.html` | 🟡 | benchmark da Oficina (estado da arte) |
| `Mobile App v5.html` · `Office Impresso Mobile.html` | 🟡 | padrões **mobile** (persona técnico, touch ≥44px) |
| `Produção - Tela Real.html` | 🟡 | migrado → `oficina-page.jsx`; referência de produção |
| `Web Dashboard.html` · `metricas.html` | 🟡 | explorações de dashboard/métricas |
| `Roadmap Martinho - Migração.html` · `Jana Pro - Paywall CC.html` · `Guia Homologação Oficina.html` | 🔴 | ponto-no-tempo / one-off |

### `venda-estado-da-arte-2026-06-01/`
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `Venda Estado-da-Arte - NF-e + NFS-e.html` | 🟢 | **(TOP #4)** padrões de emissão fiscal NF-e/NFS-e |

### `relatorios/` — relatórios meta (consolidados em `metricas.html`)
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `Governança Scorecard vs Estado-da-Arte CC.html` | 🟡 | metodologia de scorecard de governança |
| `Estrutura de IA - Avaliação CC.html` · `Diagnóstico de Projeto - CC v2.html` | 🟡 | **estilo/método de avaliação** (mesma família dos meus relatórios) |
| `Governança - Avaliação Champion CC.html` · `Diagnóstico de Projeto - CC.html` | 🔴 | versões superadas |

### `runner-casos-domgrep-2026-06-02/`
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `oficina-casos.jsx` · `vendas-casos.jsx` | 🟡 | **cenários/dados de domínio** realistas (mock data pra prova viva) |

### `bridge-processados/` · `auditoria/` · `legado/`
| Item | Classe | O que se aproveitaria |
|------|:---:|----------------------|
| `legado/uploads/Design System/.../shared/*.tsx` | 🟢 | **(TOP #7)** componentes shadcn reais |
| `bridge-processados/*.md` (12 prompts) | 🔴 | prompts zero-toque já processados — rastreio |
| `auditoria/*.png` (68) + `vendas.css.bak` | 🔴 | histórico visual (screenshots) |
| `legado/backups/` · `legado/scraps/` · `legado/memory-para-github/` | 🔴 | legado pesado (movido pra desinflar export) |

---

## Resumo — o que NÃO reaproveitar (só lápide)
Rotas vivas já cobrem (`Clientes.html`…), linhagem antiga do DS (`ds-historico/`), prompts processados (`bridge-processados/`), screenshots de auditoria, e os legados pesados (`legado/backups|scraps|uploads` exceto os `.tsx` do DS). Mantidos por **append-only (ADR 0003 · L-07)** — rastreio, não colheita.

## Como usar este inventário
1. **Vai compor/refinar uma tela?** → confere o TOP #1 (rubrica Financeiro) ou o KB-9.75 (#6) + "Melhor de Cada Tela" (#5) ANTES.
2. **Precisa de um componente real (`.tsx`)?** → #7 (shadcn no `legado/uploads`) + #3 (`ds-v5`).
3. **Dúvida de domínio (fiscal, conciliação, frescor)?** → #1, #2, #4.
4. **Atualizar:** item novo arquivado → classifica aqui (🟢/🟡/🔴 + o que se colheria). Append-only.
