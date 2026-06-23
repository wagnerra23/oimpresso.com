# Sessão 2026-06-09 (f) — Reavaliação Financeiro vs @main + o que falta

**Pedido [W]:** "reavaliar o financeiro e o que falta"

## O que foi feito
1. **Releitura viva @main 076c546 (Portão 1)** — 6 charters lidos neste turno: `Unificado/Index.charter.md` v13 · `Fluxo/Index.charter.md` v2 · `Cobranca/Index.charter.md` · `Conciliacao/Index.charter.md` · `Dre/Index.charter.md` · `ProvaViva.charter.md` + tree completo de `Pages/Financeiro` (22 entradas).
2. **Relatório:** `Reavaliacao Financeiro - 2026-06-09.html` — re-score da rubrica Método 9.75 + gaps P0/P1/P2 com refs.

## Achados (canônico)
- **Git muito à frente da memória local (de novo — classe L-26):** a rubrica 7,6 que a memória carregava era do protótipo 06-02. No @main já são LIVE: **ProvaViva.tsx** (fecha critério-de-pronto ADR 0253 — a ponte do pilot foi executada pelo [CL]) · Conciliação OFX+extrato-API (ADR 0236 F1, 6 GUARDs) · Cobrança unificada 96/100 (funil 5 etapas, wizard, drawer por tipo) · Fluxo Projetado 35d + Realizado 12M (16 GUARDs) · DRE hierárquico + Balancete/Balanço · Unificado v13 (FinBaixaSheet com baixa parcial/conta/forma, filtros de data WR, cancelado não soma + Arquivados, plano de contas em filtro/edit/insert, anexos NF, aprovação pagamento).
- **Re-score (régua = charters+GUARDs @main, não QA em staging):** composto **7,6 → 8,3**. Caixa&Fluxo 8,5 · Conciliação 7,5 · Cobrança 8,0 · **Fiscal 5,5** · IA&DRE 8,0.
- **Fiscal = o pilar parado dentro do Financeiro:** só anexo XML existe; sem card NF-e/SEFAZ no drawer, sem impostos-a-recolher, sem calendário. ⚠ módulo Fiscal fora de Pages/Financeiro NÃO auditado — gap afirmado é "fiscal na visão financeira".
- **P0 (design, nosso): US-FIN-029** — header 3 lentes + ··· + sidebar, aprovado [W] 2026-05-31, registrado no charter v10/v13 como pendente, live ainda usa filter-chips. Único lugar onde direção aprovada nossa não chegou no produto. Exige MWART + F1.5.
- **P1 (domínio):** CNAB 240 C6 + baixa por retorno + régua real (Cobrança) · Conciliação F2 ADR 0236 (N:N, desfazer, regra configurável) · Fluxo F2 (margem configurável, drill US-FIN-019, período UI, alerta) · DRE períodos/orçado/mapping.
- **P2:** US-FIN-022/023/024/025/026/028/051 + decisão legacy ContasPagar/Receber.

## Higiene executada
- `PROMPT_PARA_CODE_FINANCEIRO-PILOT-PRIMITIVOS.md` → `prototipo-ui-patch/_processados/` (landou como ProvaViva).
- STATUS.md linha Financeiro atualizada (nota 8,3 + próximos passos).
- `Financeiro.charter.md` local carimbado superseded→Unificado v13.

## Residual / aberto
- Realinhar `Financeiro.casos.md` (7 UCs do protótipo) ao Unificado v13 — tarefa curta, próxima sessão de Financeiro.
- ~~Censo do módulo Fiscal~~ — **EXECUTADO no mesmo dia** (“sim” do [W]): Fiscal/ (Cockpit emissão+alertas · NF-e pill SEFAZ+janela cancelamento+drawer · NFS-e · DF-e · Eventos · SPED · Config · sefaz-actions · SendToContabil) + NfeBrasil/ (Manifestação · Tributação · NFC-e) + Nfse/ — charters Fiscal ainda draft. **Gap P1 refinado = COSTURA:** (a) vínculo título↔nota nos drawers dos 2 lados (`origem_id`) · (b) impostos-a-recolher + calendário de obrigações = único pedaço que não existe em lugar nenhum (⚠ inferido dos charters Cockpit/Nfe lidos).
- ~~Handoff US-FIN-029~~ — **EXECUTADO no mesmo dia**: MWART `prototipo-ui-patch/memory/requisitos/Financeiro/unificado-3-lentes-visual-comparison.md` + `PROMPT_PARA_CODE_US-FIN-029-3-LENTES.md` (URLs públicas ~1h embutidas; regenerar se expirar) + enfileirado em `COWORK_NOTES.md → 📥 Pendentes`. Não-Tier-0, merge autônomo com CI verde + MWART + screenshots. Spec: lentes Caixa/A receber/A pagar `?lente=` clamp caixa · chips refinam dentro · KPI-click seta lente · `···` · extrair `<FinModuleTopnav>` (DRE 2ª tela) · charter v14 · `UnificadoLentesGuardTest` · FinSubNav intocado.
- Próximo F1 fiscal-no-financeiro (se [W] quiser): desenhar a lente “Fiscal” do drawer do título + tela impostos-a-recolher — agora com terreno conhecido (Regra 7 cumprida).

## Refs
- `Reavaliacao Financeiro - 2026-06-09.html` · charters @main listados acima · ADR 0236 · ADR 0253 · US-FIN-029.

## Próximo passo
[W] cola `prototipo-ui-patch/PROMPT_PARA_CODE_US-FIN-029-3-LENTES.md` 1× no Claude Code (transporte único). Depois do PR landar: [CC] re-lê @main e atualiza casos/charter-espelho.
