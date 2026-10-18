---
date: "2026-10-18"
topic: "Refutação GT-G5 r1 do lote #9536 (docs do módulo Fisio em memory/requisitos/Fisio) — 36 itens, 11 refutados, REPROVADO; refutador NÃO é sessão fresca"
authors: ["C"]
prs: [9536]
outcomes:
  - "Lote REPROVADO na r1: 11 de 36 itens refutados (30,56%) — contradições entre ADR 0437, etapas, SPEC/BRIEFING e tasks"
  - "8 dos 11 corrigidos no texto do próprio PR; 3 ficam para [W]/coordenação (2 no corpo da ADR 0437 aceita, 1 no HANDOFF)"
  - "Rodada INVÁLIDA para o gate: o refutador é subagente da mesma sessão Claude que gerou o lote (Claude-Session idêntico nos commits). Falta uma r2 em sessão fresca"
---

# Session log 2026-10-18 — Refutação GT-G5 · lote PR #9536 · rodada r1

## TL;DR

**Veredito: REPROVADO**, e a rodada **não vale para o gate**. 36 itens verificados, 11 refutados (30,56%), muito acima do aceite de 2%. Oito erros foram corrigidos no texto deste PR; três ficam pendentes de [W]. A rodada não satisfaz o §2.2 do protocolo: o refutador (claude-opus-5-5) é um subagente lançado pela mesma sessão (`session_01N3wVsrq8k9PEbdgsDfhE3n`) que autorou o lote — todos os commits `docs(fisio)` do PR carregam esse `Claude-Session` — e já tinha lido ADR, HANDOFF e etapas para implementar a US-FISIO-002. Por isso a entry no ledger vai com `sessao_fresca: false`. **Falta a r2:** refutador em sessão fresca (ex.: `.claude/workflows/refutador-gt-g5.js`, instância nova), sobre o lote inteiro já corrigido.

## Cabeçalho

| Campo | Valor |
|---|---|
| PR / lote | #9536 · tipo **prosa** · 16 arquivos em `memory/requisitos/` (14 de prosa + 2 derivados de máquina: `SUPERFICIE.md`, `_BACKLOG-GENERATED.md`) |
| Base | `origin/main` = `f9b406abdd` (merge-base) |
| Amostra | seed **437**, `Get-Random -Count 5 -SetSeed 437` sobre os 14 de prosa ordenados → `etapas/06-lancamento.md`, `SCOPE.md`, `etapas/05-f4-ia-questionarios.md`, `etapas/03-f2-camera.md`, `HANDOFF-F1.md` (5/14 = 35,7%). Mais leitura cruzada dirigida de ADR 0437, SPEC, BRIEFING, `etapas/02`, trio `features/f1-constancia/` |
| Sessão fresca | **não** (ver TL;DR) |
| Modelo | claude-opus-5-5, igual ao gerador (teto de política opus, §4.2) |
| Método | cada afirmação cruzada com a outra fonte do lote, com o código do PR (`Modules/Fisio`) e com a ADR (que vale em conflito, §13 dela) |

## §3 Checklist do refutador

- [ ] Sessão fresca — **não**: subagente da mesma sessão do gerador
- [x] Modelo de tier ≥ gerador no teto de política (opus × opus)
- [x] Amostra prosa ≥ 30% com seed declarada (5/14, seed 437)
- [x] Itens verificados contra a outra fonte / o código, não contra o resumo
- [x] Cada REFUTADO anotado com evidência (tabela abaixo)
- [x] Scan PII no diff: 2742 linhas adicionadas; CPF/CNPJ formatados, e-mail: 0; 11 e 14 dígitos crus e telefone: 6 hits, todos ids dentro de URL de fonte (falso positivo). Controles positivos (CPF, telefone, e-mail) casaram. **0 hits reais**
- [x] `error_rate_pct` calculado: 30,56 (≥ 2 → reprovado)
- [x] Entry no ledger no mesmo PR

## Itens

| # | Afirmação verificada | Resultado | Evidência / correção |
|---|---|---|---|
| 1 | ADR §3.1: Fisio reusa contacts, Financeiro, NFSe, Whatsapp e o link público da OficinaAuto | CONFIRMADO | `SCOPE.md` not_contains/depends_on batem |
| 2 | ADR §3.3: menu só para empresa com o módulo | CONFIRMADO | `DataController::modifyAdminMenu` + `FisioAcesso` |
| 3 | ADR §4: guarda de 20 anos, excluir = bloquear | CONFIRMADO | HANDOFF regras duras; `FisioModuloContratoTest` varre forceDelete |
| 4 | ADR §5/§13.5: gráfico de dor e MCID só na visão do profissional | CONFIRMADO | etapa 02, etapa 05 T-F4-13, AC-7 |
| 5 | ADR §6: XP só pelo prescrito, dor alta dá XP, sem ranking | CONFIRMADO | etapa 02 §3.4 |
| 6 | ADR §13.3: 100 XP/dia (70+30), 10 níveis, 2 escudos | CONFIRMADO | etapa 02 §3.4 |
| 7 | ADR §13.4: série com câmera vale o mesmo XP | CONFIRMADO | etapa 03 §3.4 e §3.5 |
| 8 | ADR §8: EVA, RMDQ, PSFS, LEFS, SPADI liberados | CONFIRMADO | etapa 05 tabela (RMDQ domínio público pela SRALab); validações BR seguem "a conferir" na própria etapa |
| 9 | ADR §2 alavanca 9: grátis até ~10 pacientes, R$ 49–79 | **REFUTADO** | ADR §14 nº 10 e etapa 06 dizem grátis até **5**. Contradição interna da ADR aceita — **pendente [W]** (não editei corpo de ADR aceita) |
| 10 | ADR §2 alavanca 1: lembrete custa ~R$ 0,05 por mensagem | **REFUTADO** | vale só para API oficial; a §13.1 da mesma ADR escolheu `whatsmeow` (sem custo por mensagem, etapa 02 cabeçalho). **Pendente [W]** |
| 11 | etapa 02 §3.5: aviso fixo "ligue 192" para todos | **REFUTADO** | ADR §14 nº 9 segura o aviso até parecer regulatório e a ADR vence. **Corrigido**: nota de pendência na etapa 02 |
| 12 | etapa 02 §3.5: check-in pelo botão do WhatsApp | **REFUTADO** | depende de template/botão da API oficial; driver decidido é `whatsmeow`. **Corrigido**: condicionado a `meta_cloud`, suporte a botão no whatsmeow marcado [não confirmado] |
| 13 | etapa 06 §plano Autônomo: lembrete "pelo WhatsApp oficial" | **REFUTADO** | ADR §13.1: `whatsmeow`, não oficial. **Corrigido** |
| 14 | etapa 05 §3.3: taxa de notificação R$ 1.406, preparo 6–12 meses e R$ 150–400 mil | **REFUTADO** (diverge) | etapa 03 §5: taxa por porte (R$ 175,72 a R$ 3.514,32), 9–15 meses, R$ 110–320 mil. **Corrigido**: nota de divergência e "conferir na tabela oficial antes da decisão §14 nº 7" |
| 15 | SPEC `status_nota`: "Modules/Fisio ainda não existe" | **REFUTADO** | o esqueleto está neste PR. **Corrigido** |
| 16 | SPEC US-FISIO-002: "_pendente_ — `Modules/Fisio/` inexistente" | **REFUTADO** | idem. **Corrigido** (segue _pendente_, com o motivo verdadeiro) |
| 17 | BRIEFING: "sem código ainda" / "`Modules/Fisio` ainda não existe" | **REFUTADO** | idem. **Corrigido** |
| 18 | tasks.md T-01…T-11 = tarefas do HANDOFF | **REFUTADO** | numeração deslocada em 1 (T-01 daqui = T-02 do HANDOFF). **Corrigido**: nota de mapeamento |
| 19 | HANDOFF: a suíte que acompanha traz "factories" | **REFUTADO** | as 4 factories saíram do PR (PHPStan: models só na US-FISIO-003). HANDOFF é arquivo do coordenador neste PR — **pendente**, não editei |
| 20 | SPEC `blocked_by` × tasks.md `blocked_by` | CONFIRMADO | cadeias equivalentes (ex.: US-006 ← 004, 005 = T-05 ← T-03, T-04) |
| 21 | requirements AC-2…AC-10 citam testes que existem | CONFIRMADO | 13 Feature + 4 Unit em `Modules/Fisio/Tests` |
| 22 | SCOPE url_prefixes `/fisio/*` e `/fisio/p/{token}/*` | CONFIRMADO | `Routes/web.php` + suposições do README da suíte |
| 23 | HANDOFF modelo de dados × colunas extras da suíte | CONFIRMADO | README da suíte lista as extras |
| 24 | HANDOFF telas × casos.md | CONFIRMADO | 5 casos.md, uma por tela |
| 25 | ADR §9 fases × HANDOFF "Fora da F1" | CONFIRMADO | |
| 26 | etapa 03 §1 linha regulatória × ADR §5 | CONFIRMADO | |
| 27 | etapa 03: sem gravação de vídeo × ADR §7 (processamento no aparelho) | CONFIRMADO | |
| 28 | etapa 05: prescrição por IA "não fazer agora" × ADR §5/§9 | CONFIRMADO | |
| 29 | etapa 05: MCID fora do portal × ADR §13.5 | CONFIRMADO | |
| 30 | etapa 06: R$ 59/mês, R$ 49 no anual × ADR §14 nº 10 | CONFIRMADO | |
| 31 | etapa 06: sem depoimento/antes-depois × ADR §4 (Res. 424/2013) | CONFIRMADO | |
| 32 | etapa 06: 50% para estudante × ADR §14 nº 10 | CONFIRMADO | |
| 33 | BRIEFING próxima ação (US-001/002, feature-lint) | CONFIRMADO | |
| 34 | SCOPE excludes questionários pagos × ADR §8 | CONFIRMADO | |
| 35 | ADR cabeçalho "número a confirmar (último visto 0436)" | CONFIRMADO (desatualizado, não errado) | `adr-index-generate --check` sem colisão para 0437 |
| 36 | SCOPE: envio de WhatsApp é do módulo Whatsapp via ChannelDriverFactory | CONFIRMADO | ADR §13.1, AC-9 |

**error_rate = 11 / 36 = 30,56%.**

## Achados fora do lote (código de teste do PR)

A suíte TDD (`Modules/Fisio/Tests`) discorda da etapa 02 em 4 pontos (modalidade do telemonitoramento, razão do XP de registro, fórmula do XP de exercício, modelo de sequência). Registrados no `Modules/Fisio/Tests/README.md` para decidir nas US-FISIO-007/008.

## Pendente

1. **r2 em sessão fresca** sobre o lote inteiro (14 de prosa, mais os 2 derivados), por instância nova sem contexto deste PR — é o que libera o ledger-check.
2. **[W]:** itens 9 e 10 pedem emenda na ADR 0437 aceita (preço do grátis; custo por mensagem com `whatsmeow`).
3. **Coordenação:** item 19 (HANDOFF menciona factories que voltam só na US-FISIO-003).