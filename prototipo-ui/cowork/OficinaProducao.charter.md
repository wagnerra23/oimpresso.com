---
page: Oimpresso · Produção / Kanban da Oficina · window.OficinaPage
component: oficina-page.jsx (+ oficina-page.css, oficina-forms.jsx)
repo_alvo: Inertia OficinaAuto/ProducaoOficina/Index (controller já existe: ProducaoOficinaController.php)
status: F1 (protótipo Cowork) — DRAWER TRAVADO + MODELO (A) REPARO decidido por [W] 2026-06-02 · resto segue F1.5/F2
owner: wagner
last_validated: 2026-06-02
validated_against: oficina-page.jsx @ cowork-2026-06-02
referencia_travada: Shopmonkey (calma/polish) × Tekmetric (densidade/fluxo) × Linear (kanban) × Stripe Tax (split fiscal invisível)
nota_atual: 9.5 ([W])
irmao: Oficina.charter.md (Nova OS · window.OficinaOSPage) — NÃO confundir; este é a PRODUÇÃO/kanban, aquele é o CREATE/documento.
---

# Charter — Produção / Kanban da Oficina

> **Page charter da tela de produção** (pedido de [W] 2026-06-02). Escrito DEPOIS do build, como backfill que **trava o conceito** — em especial o **DRAWER**, declarado "conteúdos perfeitos" por [W]. Missão: dar à oficina um **painel vivo do pátio** — todos os veículos em produção num relance — com um drawer que é o **documento vivo da OS**, do check-in à entrega.
> Personas: Larissa (balcão, 1280px) · mecânico (tablet) · Wagner (governança).

## 🔒 DRAWER — TRAVADO (canônico · anti-regressão · não redesenhar)

> [W] 2026-06-02: "Drawer está com os conteúdos perfeitos, trave nisso." A ORDEM e a PRESENÇA das seções abaixo são contrato. Mudanças só por novo OK explícito de [W] (registrar na trilha do tempo).

Ordem canônica das seções do `Drawer` (componente em `oficina-page.jsx`):

1. **Header** — `OS #id · <etapa>` · modelo do veículo · cliente · botão **Editar** · fechar.
2. **Card Vendas×Oficina** *(só quando etapa = `pronto` E existe venda derivada)* — "esta OS gerou a venda #id"; grid **Total · Peças (NF-e) · Mão-de-obra (NFS-e)**; badges fiscais NF-e / NFS-e (ok/aguarda/erro); ações **Abrir venda ↗ · DANFE · DANFS-e · Ver no Caixa do dia**. É a ponte oficial Oficina→Vendas (origin:"oficina" + osRef).
3. **Hero do veículo** — Placa Mercosul · KM · Box · Mecânico · Valor.
4. **Sintoma reportado.**
5. **Vistoria Digital · DVI** — editor item a item, semáforo ok/atenção/reprovado, + "aprovar por WhatsApp".
6. **Aviso de aprovação** *(só etapa `pecas` + `approval`)* — "aguardando aprovação do cliente", botão Cobrar.
7. **Fotos & Laudo** — thumbs de entrada + adicionar foto.
8. **Peças & Mão de obra** — `ItemsEditor` (peça / mão de obra / terceiro), split por natureza.
9. **Checklist de etapa** — `StageGate`: bloqueia avanço enquanto a etapa não fecha.
10. **Linha do tempo** — eventos da OS com status done/now.
11. **Ações de rodapé** — Conversa cliente · Imprimir OS.

**Regras travadas do drawer:** seções acendem por contexto (não mostrar vazio); fiscal é split peça→NF-e / mão de obra→NFS-e e a tela **prepara**, não emite; abre por clique no card e fecha por backdrop/✕; scroll interno (header da OS fixo).

## 📋 Inventário de funções — status [W] (marque aqui)

> Pedido de [W] 2026-06-02: lista de possíveis funções com o que **já está aprovado** e o que **tem que melhorar**. Legenda: **✅ aprovado** (fica como está) · **⬜ a decidir/melhorar** · **💡 ideia nova [CC]** (proposta, ainda não no build). [W] edita os checkboxes.
>
> **Este é o PLACAR (visão de relance).** O debate de cada item ⬜/💡 vive no Register irmão **`OficinaProducao.decisoes.md`** (técnica *Decision Register* / anéis Avaliar→Testar→Adotar→Descartar). Quando um item é aprovado lá, ele **grada pra cá como ✅** e sai do debate. IDs `D-NN` abaixo referenciam o Register.

### A. Quadro / Kanban
- [x] ✅ Colunas por etapa: Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto p/ retirar
- [x] ✅ Card contextual por etapa (cada etapa mostra o que importa)
- [x] ✅ Tira/realce de OS **urgente**
- [ ] ⬜ Capacidade visível por coluna (ex.: execução = X/5 boxes) — existe só em execução, estender? `D-03`
- [ ] 💡 **Arrastar card entre colunas** (drag-and-drop) — hoje o card só abre o drawer; avançar etapa exige abrir e usar o StageGate `D-01`
- [ ] 💡 **Avançar etapa direto no card** (botão "→ próxima") respeitando o gate, sem abrir drawer `D-02`
- [ ] 💡 **Alerta de prazo estourando** no topo da coluna (contagem de atrasados) `D-04`

### B. Cards (conteúdo)
- [x] ✅ Placa Mercosul + veículo + KM + cliente + sintoma
- [x] ✅ Mecânico (avatar) · prazo · valor · countdown de urgência
- [x] ✅ Mini-extra: foto-tag + última atividade + StageGate mini
- [~] ⬜ Foto real de entrada no card (hoje é placeholder textual) `D-08` — **parcial 06-08: foto real (file picker/thumb) já no DRAWER; no card segue pendente**
- [x] ✅ **Cor da borda por SLA** (verde/âmbar/vermelho conforme prazo) `D-04` — graduado 2026-06-08 [W]; escopado por mood (calmo só vermelho, pressão mais forte)

### C. KPIs (faixa superior)
- [x] ✅ 6 KPIs: Recepção · Diagnóstico · Aguardando peças · Execução · Urgentes · Valor em curso
- [x] ✅ KPI clicável = filtra o quadro `D-05` — graduado 2026-06-08 [W]; ring no ativo + chip "limpar filtro"
- [ ] 💡 Mini-tendência no KPI (↑/↓ vs. ontem)

### D. Visões & Foco
- [x] ✅ 3 visões: Kanban · Lista · Grade (matriz veículo × serviço)
- [x] ✅ 3 focos: Etapa · Box · Mecânico (re-pivota o quadro)
- [x] ✅ Persistir visão/foco escolhido (lembrar na volta) `D-06` — graduado 2026-06-08 [W]; foco/densidade/pressão/view em localStorage
- [ ] 💡 Visão **Linha do tempo do dia** (agenda por hora/box)

### E. Toolbar & Busca
- [x] ✅ Busca livre (placa/veículo/cliente/sintoma/#OS) + contador
- [x] ✅ Filtro por box/elevador
- [ ] ⬜ Filtros combinados (mecânico + etapa + urgência) salvos como "vistas"

### F. Tweaks inline
- [x] ✅ Foco · Densidade · Pressão
- [ ] ⬜ Densidade "detalhe" revisada (o que muda exatamente no card?)

### G. Ações de topo
- [x] ✅ Nova OS (abre create) · Editar (abre edit)
- [x] ✅ Imprimir fila
- [x] ✅ Atalho de teclado: `N` nova OS · `/` foca busca · setas navegam cards · Enter abre · Esc fecha `D-07` — graduado 2026-06-08 [W]

### H. Drawer (documento vivo)
- [x] ✅ **TRAVADO** — ver seção "🔒 DRAWER" acima (conteúdos aprovados por [W], nota 9.5). Não redesenhar.

---

## Goals — PRECISA TER (kanban / página)

- **Kanban por etapa** (Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto p/ retirar), card por OS com placa Mercosul, veículo, KM, cliente, sintoma, mecânico, prazo, e tira de urgente.
- **Faixa de 6 KPIs**: recepção · em diagnóstico · aguardando peças (quantos aguardam OK) · em execução · urgentes · valor em curso.
- **3 focos** (pivot do kanban): **Etapa · Box · Mecânico** — reorganiza colunas sem trocar de tela.
- **3 views**: Kanban · Lista · Grade (matriz veículo × serviço).
- **Tweaks inline**: Foco · Densidade (compacto/padrão/detalhe) · Pressão (calmo/padrão/...).
- **Busca livre** na toolbar (placa · veículo · cliente · sintoma · #OS) com contador de resultados.
- **Nova OS** + Editar abrem o drawer de create/edit (`oficina-forms.jsx`).
- **Cartões contextuais por etapa** (CardRecepcao/Diagnostico/Pecas/Execucao/Pronto) — cada etapa mostra o que importa (ETA, partsStatus, progresso, pago/não-pago).

## Non-Goals — NÃO FAZ

- **NÃO emite nota** na tela — o split fiscal é preparado; emissão é listener backend.
- **NÃO é a Nova OS** (`oficina-os-page.jsx`) — esta é a visão de pátio; o documento de criação é a outra superfície.
- **NÃO é POS / venda comercial** — sem bipe-código, sem Consumidor Final default, sem NFC-e como caminho.

## ⚠️ Modelo decidido por [W] 2026-06-02 — referência (A) reparo automotivo

> [W] 2026-06-02: **"referência é a A".** Decisão cravada — o kanban segue o modelo de **oficina de reparo automotivo** (boxes/elevadores, mecânico, diagnóstico, ETA, partsStatus). Não é mais default provisório.

- Modelo do kanban + drawer = **reparo** (este protótipo). É a verdade de design da tela.
- A **produção real** (`producao-oficina`, Martinho · biz 164) roda hoje **locação de caçamba / mecânica pesada basculante** (CNAE 4520): colunas `disponivel · locada · aguardando(recolhimento/overdue) · manutencao · pronta`; payload de card de **locação** (delivery_address, entered_at, expected_return, dias_locacao, daily_rate, valor_receber, atendente de transaction.createdBy). ADR 0194 marca a dívida técnica (`cacamba_locacao` legado → `mecanica_pesada_basculante`).
- **Consequência da decisão (A):** a produção caçamba é que sobe pro nível do protótipo de reparo — NÃO o contrário. Quando a F3 chegar, o `ProducaoOficina/Index` adota colunas/cards/drawer de reparo; o fluxo de locação vira caso particular (ou vertical separada), não o molde.
- **Opções B/C descartadas** por [W] nesta rodada.

## UX Targets + Tests

- 1280px sem overflow horizontal; colunas rolam internamente.
- Drawer abre/fecha sem layout shift; seções na ordem canônica acima.
- `valor em curso` = soma consistente (não parse frágil de string `R$` no destino real).
- Foco=Box mostra capacidade ocupada por box; Foco=Mecânico agrupa por responsável.
- design-critique ≥ 80 (F1.5) antes de F2.

## Refs
- `oficina-page.{jsx,css}` · `oficina-forms.jsx` (build F1) · `Oficina - Benchmark Estado da Arte.html`
- Produção: `Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php` · canon visual `prototipo-ui/prototipos/producao-oficina/visual-source.html`
- Irmão: `Oficina.charter.md` (Nova OS) · ADR FSM 0129/0143 · ADR 0194 (sub-vertical Martinho)

## Evolução / trilha do tempo
- 2026-06-02 · criado por [CC] (backfill pós-build). **DRAWER travado por [W].** Risco de divergência de modelo (reparo × caçamba) registrado, sem ação. Nenhum anterior superseded.
- 2026-06-02 · **[W] decidiu modelo (A) reparo automotivo** como referência. B/C descartadas. Produção caçamba é que sobe pro nível do protótipo na F3.
