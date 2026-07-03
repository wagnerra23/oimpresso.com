---
casos: Notas NF-e / NFC-e · /fiscal/nfe
irmaos: Nfe.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Notas NF-e / NFC-e

> Persona: **Eliana (contadora)** — leitura/conferência fiscal. Tela do cockpit Fiscal (agregador thin sobre NfeBrasil).
> Passo 3 do template-onda-modulo (régua por tela) — complementa a CAPTERRA-FICHA Fiscal (nota 75) sem roadmap paralelo.
>
> **Status:** ✅ passa (UC-id citado por teste no manifesto) · 🧪 tem teste Feature mas **sem UC-id** (débito rastreabilidade G-2 · ADR 0264) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito desta tela = rastreabilidade, não ausência de teste.** O comportamento de leitura já é defendido por `NfeCockpitMultiTenantTest` (3 casos: isolamento, janela de cancelamento, mapa SEFAZ). As **ações de mutação** (cancelar/CC-e/inutilizar/retransmitir/DF-e), que a tela apenas reserva desabilitadas (Non-Goal PR #1), são contratadas por `AcoesControllerTest` (14 casos). Falta a G-2: nenhum teste **cita** um `UC-FISCAL-NN`. Cada item vira `UC-FISCAL-NN` no mesmo PR que adicionar o id ao teste existente. Testes rodam no CT100 (ADR 0062).

## Backlog de casos (sem id — entram quando um teste citar o UC-id)
- **[BACKLOG · 🧪 tem teste · Tier 0] Contagem do cockpit esconde emissões cross-tenant** — Dado 1 emissão biz=1 e 2 biz=99 com a mesma tag · Quando conta na sessão biz=1 · Então vê só 1 (HasBusinessScope ADR 0093), enquanto `withoutGlobalScopes` vê 3. _Coberto por `NfeCockpitMultiTenantTest::('global scope HasBusinessScope esconde emissões cross-tenant na contagem do cockpit')`._
- **[BACKLOG · 🧪 tem teste] Janela legal de cancelamento respeita 24h NFC-e vs 168h NF-e** — Dado nota autorizada · Quando calcula `isCancelavel` · Então NFC-e(65) só é cancelável ≤24h e NF-e(55) ≤168h (CONFAZ SINIEF 07/2005 Art. 14). _Coberto por `NfeCockpitMultiTenantTest::('isCancelavel respeita janela legal 24h NFC-e (modelo 65) vs 168h NF-e (modelo 55)')`._
- **[BACKLOG · 🧪 tem teste] Mapa de códigos SEFAZ entrega tom/label por status** — Dado a SefazPill · Quando lê `sefazCodes()` · Então contém ao menos 100/110/220/539/691/778/999 com tom correto (100=ok, 220=bad, 691=warn). _Coberto por `NfeCockpitMultiTenantTest::('sefazCodes retorna mapa com pelo menos 100, 110, 220, 539, 691, 778, 999')`._
- **[BACKLOG · 🧪 tem teste] Cancelar NF-e exige motivo ≥15 chars (CONFAZ)** — Dado ação de cancelamento · Quando motivo <15 chars · Então rejeita; ≥15 aceita. _Coberto por `AcoesControllerTest::('cancelarNfe rejeita motivo < 15 chars…')` e `::('cancelarNfe aceita motivo válido ≥15 chars')`. (Ação reservada na tela — Non-Goal PR #1.)_
- **[BACKLOG · 🧪 tem teste] Carta de Correção valida texto (15–1000) e n_seq (1–20)** — Dado CC-e · Quando texto/seq fora do range · Então rejeita; dentro aceita. _Coberto por `AcoesControllerTest::('cartaCorrecao rejeita texto correção <15 chars…')`, `::('…>1000 chars…')`, `::('…n_seq_evento fora de 1-20…')`, `::('…aceita texto válido…')`._
- **[BACKLOG · 🧪 tem teste] Inutilização valida modelo (55/65), faixa (ate≥de) e justificativa (≥15)** — Dado inutilização de faixa · Quando payload inválido · Então rejeita por campo; payload válido passa. _Coberto por `AcoesControllerTest::('inutilizar valida modelo…')`, `::('…faixa inválida…')`, `::('…justificativa <15 chars…')`, `::('…aceita payload válido…')`._
- **[BACKLOG · 🧪 tem teste] Retransmitir só aceita rejeitada/denegada/erro_envio, com rota e signature de Service** — Dado nota em erro · Quando retransmite · Então status válido ∈ {rejeitada, denegada, erro_envio}, rota `fiscal.acoes.nfe.retransmitir` existe e `NfeService::retransmitir(int,int): NfeEmissao`. _Coberto por `AcoesControllerTest::('retransmitir contrato: status válidos…')`, `::('…signature int/int → NfeEmissao')`, `::('…route POST registrada…')`._
- **[BACKLOG · 🧪 tem teste] Manifestação DF-e whitelist 4 ações com regra de justificativa** — Dado ação DF-e · Quando ação ∉ {cienciar,confirmar,desconhecer,nao_realizada} · Então rejeita; desconhecer/nao_realizada exigem justificativa. _Coberto por `AcoesControllerTest::('manifestarDfe whitelist exatamente 4 ações canon SEFAZ')` e `::('manifestarDfe desconhecer/nao_realizada exigem justificativa…')`._
- **[BACKLOG · ⬜ sem teste] Gate de permissão `fiscal.nfe.view` bloqueia leitura** — Dado usuário sem `fiscal.nfe.view` nem `superadmin` · Quando faz GET /fiscal/nfe · Então recebe 403. (Guard existe no `NfeCockpitController::index`; charter cita, mas nenhum teste Feature exercita o 403 no `index` hoje.)
- **[BACKLOG · ⬜ sem teste] Lista deferida filtra por tab/status/busca com paginação 50** — Dado emissões · Quando aplica tab(55/65)/status/search · Então `buildRowsPayload` retorna 50/pág ordenado por `emitido_em DESC`. (Prop `rows` é `Inertia::defer`; sem teste Feature do payload filtrado.)

## Como rodar a suíte
1. **Pest (MySQL real):** lane Fiscal no CT100 (ADR 0062) — `NfeCockpitMultiTenantTest` (leitura) + `AcoesControllerTest` (contratos de mutação) verdes. DB-touching skipa em SQLite (`nfe_emissoes`/`nfe_dfe_recebidos` requerem schema MySQL); os de validação/whitelist rodam sempre.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela), complementando a CAPTERRA-FICHA. Débito = UC-traceability (0 UC-id apesar de baseline Feature forte: 3 casos de leitura em `NfeCockpitMultiTenantTest` — 1 Tier 0 — + 14 contratos de ação em `AcoesControllerTest`).
