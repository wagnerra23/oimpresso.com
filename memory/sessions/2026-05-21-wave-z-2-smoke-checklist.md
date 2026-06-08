---
slug: 2026-05-21-wave-z-2-smoke-checklist
title: "Wave Z-2 — Smoke checklist Cliente drawer 760px prod biz=1"
type: smoke-checklist
date: 2026-05-21
related_adrs: [0179, 0093, 0107, 0114]
related_prs: [1339, 1347, 1348, 1349]
deploy_script: scripts/deploy-cliente-drawer-wave-z-2.sh
status: pendente-wagner
---

# Wave Z-2 — Smoke checklist Cliente drawer 760px prod biz=1

Use APÓS rodar `scripts/deploy-cliente-drawer-wave-z-2.sh`. Marque cada item ao validar manualmente em Brave Wagner@WR2 SC biz=1.

## Setup (1x)

- [ ] SSH no Hostinger
- [ ] `cd /home/oimpresso/oimpresso.com && git pull origin main`
- [ ] `bash scripts/deploy-cliente-drawer-wave-z-2.sh` (deploy automatizado)
- [ ] Confirma `MWART_CLIENTE_INDEX=true` no `.env`
- [ ] Confirma `migrate --pretend` mostrou SQL aditivo correto antes de aplicar
- [ ] Confirma migrations rodaram (2 entries em `migrations` table)

## Bloco A — Listagem turbinada (dimensões 1-8)

Abrir `https://oimpresso.com/cliente` logado Wagner@WR2 SC.

- [ ] **Avatar HSL hash** — cada linha tem avatar colorido (12 cores distinguíveis). Mesmo nome → mesma cor sempre.
- [ ] **6 dropdowns filtro** no topo: Tipo (PF/PJ) · Status · UF · Tags · Sem compra há · Com saldo. Cada um abre e filtra.
- [ ] **Count inline** "X clientes encontrados" abaixo dos filtros (não "Lista de clientes com KPIs...").
- [ ] **Tabela colunas:** Avatar · Nome+sub-nome · TipoPill · Documento mascarado · Cidade/UF · FrescorPill · SaldoCell · Tags chips · Star.
- [ ] **FrescorPill** — pill colorida (fresc verde / recente azul / distante âmbar / frio cinza) + "há 4sem" relativo.
- [ ] **SaldoCell vermelho** — cliente devedor (`saldo > 0`) aparece em vermelho `text-rose-700`.
- [ ] **Tag chips coloridas** — 9 cores semânticas (varejo amarelo, atacado roxo, corporativo azul, evento rosa, parceiro verde, agência índigo, governo vermelho, vip dourado, reincidente laranja).
- [ ] **Star pessoal** — clicar Star → marca/desmarca → persiste em localStorage `oimpresso.cliente.favoritos` (F12 → Application → Local Storage).
- [ ] **Botão Exportar CSV** — header top-right → baixa CSV BOM UTF-8 (abre certo no Excel BR).
- [ ] **KB-9.75 atalhos preservados:** `⌘K` abre command palette · `?` abre cheat-sheet · `J/K` navega linhas · `Enter` abre drawer · `/` foca busca.

## Bloco B — Drawer 760px estrutura (dimensões 6 + 9 + 18)

- [ ] Clicar 1ª linha → **drawer abre lateral direita** com largura **760px exatos** (medir no DevTools).
- [ ] **Header drawer:**
  - [ ] Avatar grande (~56px) com mesma cor HSL da linha
  - [ ] Nome + "Pessoa jurídica · cadastrado há Xd"
  - [ ] Toggle PF/PJ no topo do header
  - [ ] Badge Ativo/Inativo/Bloqueado (verde/amarelo/vermelho)
  - [ ] Botão "Imprimir ficha" (dispara `window.print()`)
  - [ ] Botão "Falar com Copiloto →" (link `/jana/chat?context=cliente:{id}`)
- [ ] **8 tabs visíveis e clicáveis:**
  - [ ] Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria
- [ ] **Footer drawer:** "1 pendência" placeholder + botões Cancelar/Salvar.
- [ ] Drawer fecha com `X`, `Esc`, ou click no backdrop.

## Bloco C — 5 tabs cadastrais inline autosave (dimensões 10-14)

### Tab Identificação

- [ ] Toggle PF/PJ funciona — campos mudam (PJ: Razão social/Fantasia/CNPJ/IE/Cargo; PF: Nome/CPF/Nascimento/RG).
- [ ] Digitar CPF inválido (todos 1's) → erro inline mod 11 "CPF inválido. Confere os dígitos."
- [ ] Digitar CPF válido → erro some.
- [ ] Botão **"Buscar CNPJ"** (PJ) → spinner → autopreenche Razão social + Fantasia + situação.
- [ ] Editar "Nome fantasia" → blur (perde foco) → spinner ao lado do campo → CheckCircle verde 2s.
- [ ] Editar "Nome fantasia" + bater rede falsa (DevTools throttle) → rollback campo + toast erro.

### Tab Contato

- [ ] Digitar tel `11988887777` → autoformatar `(11) 9 8888-7777`.
- [ ] Digitar email inválido → erro regex inline.
- [ ] Radio canal preferido (WhatsApp / E-mail / Telefone / Presencial) seleciona único.
- [ ] Autosave on blur em cada campo.

### Tab Endereço (ViaCEP — feature flagship)

- [ ] Digitar CEP `01310100` no campo → autoformatar `01310-100` → blur dispara spinner.
- [ ] Em ~800ms (cache miss) **autopreenche** Logradouro="Av. Paulista", Bairro="Bela Vista", Cidade="São Paulo", UF="SP".
- [ ] Segundo cliente, mesmo CEP → autopreenche **instantâneo** (cache Redis hit < 200ms).
- [ ] CEP inválido `00000000` → erro inline "CEP não encontrado" (sem travar UI).

### Tab Comercial

- [ ] Limite crédito (number), prazo padrão (dias number), tabela preço (4 opções), pgto padrão (5 opções), obs textarea.
- [ ] Autosave on blur em cada.

### Tab Classificação

- [ ] Segmento radio (6 opções: varejo/atacado/agência/corporativo/evento/governo).
- [ ] Tags multi-select — clicar adiciona chip; clicar X remove (max 9 valores).
- [ ] Status select (ativo/inativo/bloqueado).
- [ ] Toggle VIP — chip dourado aparece na listagem ao marcar.

## Bloco D — Tab OSs wrapper (preserva Wave Final)

- [ ] Tab OSs renderiza **sub-nav vertical** à esquerda 120px com 8 sub-tabs:
  - [ ] Extrato · Vendas · Pagamentos · Documentos · Atividades · Pessoas · Assinaturas · Pontos
- [ ] Default = Extrato (LedgerTab) — range datas + Formato + filtro localização + export.
- [ ] Clicar Vendas → SalesTab carrega paginado.
- [ ] Clicar Pagamentos → PaymentsTab self-fetch AJAX `/contacts/payments/{id}`.
- [ ] Clicar Documentos → DocumentsTab upload + lista.
- [ ] Content area scrolla independente (não trunca em 760×640).
- [ ] **NÃO regrediu** Wave Final 2026-05-21 (PRs #1298-1307).

## Bloco E — Tab IA 4 cards Copiloto (Default ON sem gate Q4)

- [ ] Header card azul "Copiloto de cliente — 4 análises diferentes. IA propõe, você decide. Tudo é editável antes de aplicar."
- [ ] **Card 1 Resumo:** botão "Gerar resumo" → spinner ~5s → texto LLM Haiku PT-BR + Refresh icon depois.
- [ ] **Card 2 Reavaliar segmento + tags:** botão "Analisar" → spinner → segmento sugerido + tags array + justificativa.
- [ ] **Card 3 Próxima ação:** botão "Sugerir" → ação + urgência (alta/média/baixa) + justificativa.
- [ ] **Card 4 Score de risco:** RiscoClienteCard render imediato (sem LLM, determinístico) — score 0-10 + barra horizontal + label "cliente fiel" / "risco baixo" / "risco alto".
- [ ] **2º clique "Gerar resumo"** mesmo cliente → cache hit (response instantâneo, sem custo Brain B).
- [ ] DevTools Network: 3 chamadas POST `/cliente/{id}/ia/{resumo|segmento|proxima-acao}` retornam 200 < 6s.

## Bloco F — Tab Auditoria timeline LGPD Art. 18

- [ ] Header card cinza "Histórico de alteração — Tudo que aconteceu com essa ficha. Atende ao Art. 18 da LGPD."
- [ ] Botão "Exportar log" no topo-direito.
- [ ] Timeline 6+ tipos eventos: created (plus icon verde), updated (edit azul), tags (tag verde), telefone (edit azul), etc.
- [ ] Cada evento: ícone + descrição PT-BR + causer "Por Wagner" + "há 2h" relativo + timestamp.
- [ ] Paginação "Anterior" / "Próxima" se > 20 eventos.
- [ ] **Clicar "Exportar log"** → baixa CSV `auditoria-cliente-{id}-2026-05-21.csv` com BOM UTF-8.
- [ ] Abrir CSV no Excel BR → caracteres acentuados OK + colunas ID;Tipo;Descrição;Causer;Data.
- [ ] CSV NÃO mostra CPF/CNPJ plain (defesa PII Spatie + maskPiiValue regex).

## Bloco G — Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL)

- [ ] Logout Wagner@WR2 SC biz=1 → Login Wagner@biz-fictícia biz=2.
- [ ] Acessar `/cliente/{id}` de contact pertencente a biz=1 → **404** (NÃO 403 — não vaza existência).
- [ ] PATCH direto via curl `/cliente/{id-biz-1}/identificacao` body `{nome: 'hacked'}` autenticado biz=2 → **404**.
- [ ] GET `/cliente/lookup/cep/01310100` autenticado biz=2 → 200 (lookup não tem business_id, mas precisa auth).
- [ ] GET `/cliente/{id-biz-1}/auditoria` autenticado biz=2 → **404**.

## Bloco H — Smoke close

- [ ] Screenshot drawer aberto (8 tabs renderizando) → salvar `prototipo-ui/screenshots/cliente-drawer-760-prod-biz1-2026-05-21.png`.
- [ ] Append `prototipo-ui/SYNC_LOG.md`:
  ```
  2026-05-21 HH:MM [W2] approved screenshot cliente-drawer-760 prod biz=1 — Wave Z-2 OK
  ```
- [ ] Atualizar `memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md` frontmatter:
  - `status: ready-for-screenshot-approval` → `status: validated-prod`
  - `final_score: 88/100` (ou nota real Wagner)
  - `approved_by: Wagner 2026-05-21 HH:MM BRT prod biz=1`
- [ ] (Opcional) Skill `brief-update` → regenera `memory/requisitos/Crm/BRIEFING.md`.
- [ ] Commit + push em main:
  ```bash
  git add prototipo-ui/screenshots/ prototipo-ui/SYNC_LOG.md memory/requisitos/Crm/cliente-drawer-760-visual-comparison.md memory/requisitos/Crm/BRIEFING.md
  git commit -m "docs(cliente): Wave Z-2 — smoke prod biz=1 aprovado + screenshot + visual-comparison validated"
  git push
  ```

## Rollback rápido se algo quebrar

1. SSH Hostinger
2. `cp .env.backup-wave-z-2-* .env`
3. `php artisan config:clear && php artisan cache:clear`
4. Drawer desliga; `MWART_CLIENTE_SHOW=true` ainda funciona em `/cliente/{id}` full-page legacy.
5. Reportar em handoff novo + abrir issue GitHub se bug crítico.

## Critério de fechamento Wave Z-2

Wave Z-2 = **COMPLETA** quando:

- ✅ `MWART_CLIENTE_INDEX=true` em prod biz=1
- ✅ 2 migrations aditivas rodaram (`migrate:status` confirma)
- ✅ Screenshot drawer 8 tabs aprovado por Wagner
- ✅ SYNC_LOG.md tem `[W2] approved screenshot`
- ✅ Visual-comparison.md `status: validated-prod` com nota final
- ✅ Nenhum bug crítico em 24h (rollback opcional só após 7d canary se tudo OK)

Após Wave Z-2 fechada, sub-onda futura pode:
- Habilitar biz=4 Larissa (depois 7d canary biz=1 verde)
- Deletar Show.tsx código morto (Q1 Wagner: sunset zero — ficou na main mas inacessível pela rota)
- Migrar outros módulos pra mesmo paradigma drawer 760 (Fornecedor, Funcionário, etc)
