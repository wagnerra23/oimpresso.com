# COWORK_NOTES — Mensagens do Cowork pro Claude Code

> O **Cowork** anexa aqui pedidos, decisões e contexto que o **Claude Code** precisa saber na próxima sync.
> O Claude Code, ao processar uma sync, **lê este arquivo, age conforme, e marca cada item como [PROCESSADO YYYY-MM-DD] no final**.
> Mensagens muito antigas processadas vão pro fim do arquivo em "Histórico".

---

## 📥 Pendentes

### 2026-05-14 — Vendas (Sells/Index + Sells/Create): elevar a A+ (≥9.5)

**Tela:** `Sells/Index` + `Sells/Create` (mesmo PR, são par P0 do protocolo)
**Prioridade:** P0 — substitui o item P0 antigo da TELAS_REVIEW_QUEUE
**Estado atual (Auditoria Final, 13 mai 2026):**
- Nota: **8.7 (A)** — Estrutura 9 · Visual 8 · Domínio 9 · Interação 9
- IMPL React, multi-vertical (CV, Mecânica, Vestuário, Repair)
- Gargalo: **Visual** (sem identidade própria, sem tendência, sem fiscal inline)

**Meta:** **≥ 9.5 (A+)** — empatar com Oficina Auto.
Projeção alvo: Estrutura 9 · **Visual 9** · **Domínio 10** · **Interação 10** = 9.5

**Persona-driven:**
- **Larissa** (balcão ROTA LIVRE, monitor 1280×1024) é quem mais usa a tela.
  Atalho-first, densidade alta, KPIs gigantes, scan visual instantâneo.
- **Eliana [E]** (financeiro) precisa do recorte fiscal sem entrar na venda.

**Contexto:**
Vendas é a tela mais tocada do dia-a-dia da gráfica. Hoje funciona, mas
não é memorável como Compras (cream-and-navy) nem viva como Oficina (tweaks).
A gráfica brasileira média emite NF-e + NFS-e numa mesma venda (banner + instalação,
peça + mão-de-obra) — esse domínio precisa aparecer inline, não escondido.

---

#### Pedidos para [CC] — 8 itens

**1. Identidade visual própria**
Vendas precisa de assinatura que distingue de OS/Orçamentos. Sugestão:
tom warm forest-green (confirmação + dinheiro). Decisão livre, mas
comprometer um vocabulário. Padrão Compras = cream-and-navy é o nível.

**2. Hero KPIs com tendência (não chapado)**
Substituir "Faturado hoje R$ X" estático por:
- Sparkline 30 dias do faturamento diário no hero card preto
- Ticket médio com Δ% vs semana passada (verde/red)
- "A receber" com **ageing visual** (0–3d verde / 4–7d amber / >7d red)
- PIX hoje vs total — mini progress bar

Referência: Stripe Balance, Mercury Cashflow. Foi o que tirou Financeiro
de 6.5 → 8.0.

**3. Mini-stepper FSM inline em cada linha**
5 bolinhas por venda (orçamento → pedido → faturada → entregue → paga).
Visual scan instantâneo do pipeline. Hoje só tem badge de status texto.
Referência: Linear Issues, ServiceTitan Job Board.

**4. Avatar do vendedor + comissão calculada**
Coluna compacta com inicial+cor do vendedor + valor de comissão
calculada inline. Cria ownership e disputa saudável no balcão.
Larissa quer ver "minha comissão do dia" no header também.

**5. Tweaks inline (padrão Oficina Auto)**
Toggle no header: **Vista = Caixa | Faturamento | Comissão**
- Caixa: foco em hoje/PIX/dinheiro
- Faturamento: foco em NF-e/NFS-e/pendências fiscais
- Comissão: foco em vendedor/meta/ranking
Muda KPIs do hero + colunas extras na tabela. Persiste em localStorage.

**6. ⌘K rico**
Abrir com:
- 3 últimas vendas (continuar/duplicar)
- Atalho "Nova venda PIX rápida" (skip drawer cheio)
- Busca por NF (chave SEFAZ ou número)
- Busca por placa/IMEI/produto
Hoje o atalho N só abre drawer cego.

**7. Saved views ▾ + bulk actions**
- Saved views ▾ no header (Hoje · Pendentes · Faturadas semana · Atrasadas)
- Seleção múltipla na tabela → barra flutuante com:
  - Emitir NF-e em lote
  - Marcar como pagas
  - Exportar XML/PDF
  - Enviar lembrete WhatsApp (interno, sem cliente-facing)

**8. Documentos fiscais vinculados (NF-e + NFS-e) — núcleo do +Domínio**

Uma venda pode ter NF-e (produto), NFS-e (serviço), ou ambos.

**Na tabela (lista):**
- Coluna fiscal compacta: badge duplo `NF-e ✓ · NFS-e ✓`
- Estados: ✓ autorizada (verde) · ⌛ processando (amber) · ✕ rejeitada
  (red) · ⊘ cancelada (cinza) · — não aplicável
- Hover na badge → tooltip com chave + data autorização

**No drawer da venda, seção "Fiscal":**
- Cards lado a lado: NF-e (se houver) | NFS-e (se houver)
- Cada card mostra:
  - Status SEFAZ com cor + timestamp
  - Chave de acesso 44 dígitos formatada (0000 0000 0000 ...) + botão copiar
  - Número + série + data emissão
  - CTAs: Baixar DANFE/DANFS-e PDF · Baixar XML · Enviar por e-mail
  - Mini-timeline SEFAZ inline (emitida → autorizada → entregue → paga)
  - CC-e (Carta de Correção) accordion expansível se houver
- Se status = rejeitada → motivo SEFAZ em destaque red no topo do card

**Ação rápida no header da venda:**
- Se não emitida → CTA "Emitir NF-e" / "Emitir NFS-e" (depende do que
  tem na venda: produto vs serviço)
- Se emitida → CTA secundário "Cancelar nota" (com janela 24h SEFAZ)

**Multi-fiscal numa venda mista:**
- Sub-tabs `NF-e` | `NFS-e` no drawer fiscal com contador (1/1)
- Total da venda = soma dos dois documentos (mostrar breakdown)

**Referências:** Conta Azul (cards fiscais), Tiny ERP (timeline SEFAZ),
Bling (chave clicável → portal SEFAZ), Asaas (status badges).

---

#### Proibições explícitas (charter)

- ❌ Sem CTA WhatsApp cliente-facing
- ❌ Sem modal full-screen pra detalhe — drawer lateral apenas
- ❌ Sem rounded-xl+ (manter rounded-md do tokenset CLAUDE_DESIGN_BRIEFING §4)
- ❌ Sem inglês em UI cliente-facing (Sells = "Vendas", Customer = "Cliente")
- ❌ Sem emoji
- ❌ Sem cores fora dos tokens canônicos

#### Comparáveis a estudar (CLAUDE_DESIGN_BRIEFING §5)

- **Shopify Orders** — ageing + bulk actions + saved views
- **Stripe Payments** — sparkline + status pills + ⌘K
- **Ramp Bill Pay** — densidade + identidade visual forte
- **ServiceTitan Invoices** — fiscal inline + multi-doc
- **Conta Azul** — NF-e/NFS-e dual nativa (mercado BR)
- **Linear Issues** — mini-stepper + saved views

#### Entrega esperada de [CC]

- `prototipos/sells/page.tsx` (Index + Create no mesmo arquivo)
- `prototipos/sells/COMPARISON.md` (15 dimensões CLAUDE_DESIGN_BRIEFING §5)
- Critique-alvo F1.5: **≥ 90** (entrega A+)
- Tokens canônicos rigorosos (sem inventar cor, radius, animação, foco)
- Padrão Cockpit V2 (sidebar + header sticky + body cards + footer sticky + drawer)

---

### 2026-04-27 — Sync inicial
**Contexto:** primeira ida do protótipo pro repo. Cowork está em ~88% do escopo do shell + Fases 2-3 (OS, Clientes, Orçamentos, Produtos) prontas.

**Pedidos para o Claude Code:**

1. **Sync inicial** — extrair zip do Cowork em `prototipo-ui/`, branch `feat/prototipo-ui-cockpit`, abrir PR, mergear na main.

2. **Verificar estrutura esperada** após sync:
   - `prototipo-ui/Oimpresso ERP - Chat.html` (entry)
   - `prototipo-ui/README.md`
   - `prototipo-ui/CLAUDE_CODE_BRIEFING.md` (este briefing)
   - `prototipo-ui/SYNC_LOG.md`
   - `prototipo-ui/COWORK_NOTES.md` (este arquivo)
   - `prototipo-ui/memory/HANDOFF.md`
   - 12+ arquivos `.jsx` e `styles.css`

3. **Decidir:** `LARAVEL_REPO_CONTEXT.md` que está dentro do export — esse arquivo é redundante com o `CLAUDE.md` raiz do repo. Apaga após confirmar que `CLAUDE.md` raiz está atualizado.

4. **Anexar primeira entrada em SYNC_LOG.md** descrevendo o sync inicial.

5. **Confirmar** com Wagner que a integração está funcionando: leia este arquivo, escreve confirmação em `CODE_NOTES.md`, peça pra Wagner colar pro Cowork.

**Status atual do protótipo (lê HANDOFF.md pra detalhes):**
- Fase 1 (shell): ✅ pronto
- Fase 2 (OS piloto): ✅ pronto (lista, detalhe, Nova OS, Aprovar arte, bulk export, atalhos)
- Fase 3 (Clientes/Orçamentos/Produtos): ✅ pronto (CRUD básico, KPIs, filtros)
- Fase 4 (Produção): 🔴 só placeholder
- Fase 5 (decommission Blade): 🔴 não iniciada

---

## ✅ Histórico (processadas)

(vazio)
