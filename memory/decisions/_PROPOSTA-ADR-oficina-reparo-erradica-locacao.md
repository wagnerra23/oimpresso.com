# _PROPOSTA_ ADR — Oficina = **reparo é o único domínio** · erradicar resíduo de "locação de caçamba" · "Caçambas" = só nome comercial

> **Status:** PROPOSTA de [CC]. [W] autorizou no chat 2026-06-09 — textual: *"Pode apagar aluguel de caçamba e fundamentar para não voltar mais, eu não uso é alucinação"* + *"Eu decido que sim eu quero reparo. faça a ADR."*
> **Número/versão = só [W]/git** (soberania ADR 0238 · CARTA §0.1). [CC] não cunha número do git — Code confirma o próximo nº livre (provável 0254+) e versiona.
> **Tier 0** (enum/migration/seeder/DB do módulo OficinaAuto, importer Firebird Martinho) — entra no `main` via ponte zero-toque, sob OK explícito de [W] (já dado).

- **Data:** 2026-06-09
- **Sessão:** Cowork [CC] ↔ Wagner
- **Supersede/estende:** **fecha** a ADR 0194 (correção de domínio Martinho → mecânica pesada) — 0194 *reclassificou* mas **deixou resíduo** `order_type = locacao`; esta ADR **erradica** o resíduo. Mantém intactos ADR 0143 (FSM ServiceOrder), ADR 0093 (multi-tenant), ADR 0101 (biz=1 dev).

## Contexto

A ADR 0194 (2026-05-26) já reclassificou o domínio da Oficina de "locação de caçamba container" → **mecânica pesada de caminhão basculante** (CNAE 4520-0/01), preservando **"Caçambas" apenas como nome comercial** da empresa Martinho. O importer Firebird já normaliza `vehicle_type: cacamba → caminhao` (cacamba nem era valor válido do enum).

**Porém sobrou resíduo de "locação" no código** (lido ao vivo em `@main` 2026-06-09):
1. **`order_type` enum `{locacao | manutencao | mecanica}`** — `locacao` ainda existe como tipo de ordem.
2. **`ServiceOrderSummaryService`** computa "KPIs dashboard combinada (**locação** + manutenção + concluida_mes + atrasada)" — métrica de locação ativa.
3. **Nomes legados** `ProducaoOficina/_components/CacambaCard.tsx` · `CacambaKanbanColumn.tsx` e comentário stale de rota citando colunas `disponivel/locada`.
4. **Menu** `topnav.php` com item rotulado `'Caçambas'`.

**Veredito de [W] (dono do negócio, soberano do domínio):** locação de caçamba **não é processo que ele usa** — é alucinação herdada do legado WR Sistemas. A Oficina é **reparo/mecânica, ponto**. Isso **corrige um erro de enquadramento do próprio [CC]**, que na sessão anterior tratou a locação como "legado vivo intencional" (ver LICOES_CC L-deste-dia). 0194 mandou ler "locação" como "mecânica"; [W] agora manda **apagar**, não reinterpretar.

## Decisões

### D-1 — **Reparo é o único domínio operacional da Oficina**
O ciclo de vida de uma OS é exclusivamente de serviço/reparo: `recepcao → diagnostico → pecas → execucao → pronto` (apresentação) sobre a FSM canônica ServiceOrder (ADR 0143: `orcamento → aprovada → em_servico → concluida`). **Não existe fluxo de locação.**

### D-2 — **Erradicar `order_type = locacao`**
- Remover `locacao` do enum `order_type` → fica `{manutencao | mecanica}` (ou colapsar para um só, a critério de [CL] na F3).
- Migration de dados: linhas legadas com `order_type='locacao'` → `manutencao` (mesmo default que o importer já aplica a status legado). Idempotente, com `--dry-run` padrão (mesma trava da W28).
- Remover o ramo "locação" do `ServiceOrderSummaryService` (KPI dashboard) — fica manutenção + concluída_mês + atrasada.

### D-3 — **"Caçambas" sobrevive SÓ como nome comercial**
`Martinho Caçambas` permanece como razão/nome de cliente (string de negócio). **Nenhum rótulo de UI, enum, coluna de kanban ou métrica** usa "caçamba/locação/locada/disponível" como conceito de domínio. Renomear `CacambaCard/CacambaKanbanColumn` → nomes de reparo; menu `'Caçambas'` → `'Veículos'`; limpar comentário stale de rota.

### D-4 — **Fundamento anti-retorno (por que não volta)**
Registrar em 3 lugares para a alucinação não reaparecer:
- **`memory/proibicoes.md`** (nova linha): *"Oficina/OficinaAuto NÃO tem domínio de locação. 'Caçambas' = nome comercial do cliente Martinho, nunca tipo de ordem, coluna, KPI ou label. order_type ∈ {manutencao, mecanica}. Quem reintroduzir `locacao`/`locada`/`disponivel` como conceito de negócio viola esta ADR — [W] 2026-06-09."*
- **`memory/LICOES_CC.md`**: lição do [CC] ter tratado locação como "legado vivo intencional" (erro de enquadramento; a fonte de verdade do domínio é [W], não o código legado).
- **Esta ADR** referenciada no `CHANGELOG.md` do módulo (append, não reescreve história — L-22).

## Escopo / não-escopo
- **Escopo F3 [CL]:** enum + migration de dados + service KPI + rename componentes/menu + comentário de rota + lápide no CHANGELOG.
- **Preservado (Tier 0 irrevogável):** FSM ServiceOrder (0143), multi-tenant global scope (0093), idempotência `FB_LEGACY_ID` do importer, journey E2E Martinho biz=1 (só **deixa de exercitar** o ramo locação).
- **Controle-negativo no aceite:** grep `@main` pós-merge deve dar **zero** `order_type.*locacao`, **zero** KPI "locação", **zero** `Cacamba*` em componente/menu de UI. Nome comercial "Caçambas" em dados de cliente = ok (não é regressão).

## Trilha do tempo
- 2026-06-09 · [CC] redigiu a proposta após veredito de [W] ("apaga, eu não uso, é alucinação" + "quero reparo, faça a ADR"). Fecha o resíduo que a 0194 deixou. Handoff zero-toque: `prototipo-ui-patch/PROMPT_PARA_CODE_OFICINA-REPARO-ERRADICA-LOCACAO.md`.
