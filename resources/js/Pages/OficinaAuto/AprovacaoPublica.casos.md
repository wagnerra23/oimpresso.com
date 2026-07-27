---
id: resources-js-pages-oficina-auto-aprovacao-publica-casos
casos: Aprovação pública da OS · /aprovar-os/{token}
irmaos: AprovacaoPublica.charter.md (lei) · ../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
related_us: [US-OFICINA-014, US-AUTO-009]
related_cu: [CU-OFI-12, CU-OFI-15]
---

# Casos de Uso & Aceite — Aprovação pública da OS (link + PIN)

> **Nasce neste PR** (chip Onda 2 do passo 5, [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)).
> Os UC **derivam do §6 do SDD** ([`CU-OFI-12`](../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md)),
> do `AprovacaoPublica.charter.md` e do fluxo **F8** do §5.3 — **não** do `.tsx` (caso derivado do
> código é tautológico · [proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> ⚠️ **Piloto LIVE** (Martinho, produção). Estes casos **fotografam** o comportamento vivo —
> nenhum deles pede mudança.
>
> **Status:** ✅ passa (veredito no manifesto) · 🧪 teste cita o UC, sem veredito coletado ·
> ⬜ não verificado · ❌ quebrou. **O veredito é da lane, nunca desta leitura** (G-7).
>
> ⚖️ **Força do veredito:** os testes citados rodam na lane `PHP / Pest (OficinaAuto · MySQL)`,
> criada neste mesmo PR e **ADVISORY** — reprova fica **visível** e **não bloqueia merge**
> (não está em `governance/required-checks-baseline.json`). Também entram na suíte completa
> noturna (registrados em `phpunit.xml`).

## Rastreabilidade

| UC | O que defende | Peso | Âncora (CU/US) | Teste |
|---|---|---|---|---|
| UC-OAP-01 | link válido abre a tela com o form de PIN | must | CU-OFI-12 · US-OFICINA-014 | `WhatsAppAprovacaoPinTest` |
| UC-OAP-02 | PIN correto + aprovar move a OS | must | CU-OFI-12 · US-OFICINA-014 | `WhatsAppAprovacaoPinTest` |
| UC-OAP-03 | lockout depois de 5 tentativas | must | CU-OFI-12 · US-OFICINA-014 | `AprovacaoOsTokenTest` |
| UC-OAP-04 | token adulterado não vale | must | CU-OFI-12 · US-OFICINA-014 | `AprovacaoOsTokenTest` |
| UC-OAP-05 | token de outro cliente não abre a OS | must | CU-OFI-15 · US-AUTO-009 | `AprovacaoOsTokenTest` |
| UC-OAP-06 | PIN correto é de uso único | must | CU-OFI-12 · US-OFICINA-014 | `AprovacaoOsTokenTest` |
| UC-OAP-07 | rejeitar não muda o estado da OS | should | CU-OFI-12 · US-OFICINA-014 | `WhatsAppAprovacaoPinTest` |

---

## UC-OAP-01 · O link do WhatsApp abre a tela de aprovação
- **Persona:** cliente final (dono do caminhão) — **não tem conta no sistema**.
- **Como usa:** recebe o link no celular, toca, e vê o resumo do orçamento com um campo de PIN.
- **Aceite:** Dado um link de aprovação válido · Quando o cliente abre `/aprovar-os/{token}` ·
  Então a página responde 200 e apresenta o formulário de PIN.
- **Regressão que defende:** o link virar erro genérico e o cliente ligar pro balcão (o custo que
  o charter G2 existe pra derrubar: ~2h → ~3min).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/WhatsAppAprovacaoPinTest.php`
- **Status: 🧪**

## UC-OAP-02 · Aprovar com o PIN certo faz a OS andar
- **Persona:** cliente final.
- **Aceite:** Dado um link válido e o PIN correto · Quando o cliente confirma a aprovação ·
  Então a OS **sai** do estado de orçamento e passa para aprovada.
- **Regressão que defende:** aprovação que "some" — cliente aprova e o pátio não vê.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/WhatsAppAprovacaoPinTest.php`
- **Status: 🧪**

## UC-OAP-03 · Cinco chutes de PIN travam a tentativa `[must]`
- **Persona:** invariante de segurança (o link é público na internet).
- **Aceite:** Dado um link válido · Quando o PIN errado é enviado 5 vezes · Então a tentativa
  seguinte é recusada mesmo se o PIN estiver certo (lockout).
- **Regressão que defende:** força bruta de 4 dígitos (10 mil combinações) num link público.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/AprovacaoOsTokenTest.php`
- **Status: 🧪**

## UC-OAP-04 · Link adulterado não vale `[must]`
- **Persona:** invariante de segurança.
- **Aceite:** Dado um link cujo trecho de assinatura foi alterado · Quando é aberto · Então
  **nenhuma** OS é resolvida — e a tela mostra o mesmo estado vazio de link expirado, **sem**
  revelar qual condição quebrou.
- **Regressão que defende:** virar oráculo (mensagem diferente por causa) permite enumerar OS.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/AprovacaoOsTokenTest.php`
- **Status: 🧪**

## UC-OAP-05 · Link de um cliente não abre a OS de outro `[T0]`
- **Persona:** invariante multi-tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Aceite:** Dado um link gerado para a oficina A · Quando é usado contra uma OS da oficina B ·
  Então não valida — o business viaja **assinado** dentro do link, não como parâmetro.
- **Regressão que defende:** vazamento de orçamento entre clientes do ERP.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/AprovacaoOsTokenTest.php`
- **Status: 🧪**

## UC-OAP-06 · O PIN vale uma vez só
- **Persona:** invariante de segurança.
- **Aceite:** Dado um PIN já usado com sucesso · Quando é reapresentado · Então não valida de novo.
- **Regressão que defende:** replay do link em conversa de WhatsApp encaminhada.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/AprovacaoOsTokenTest.php`
- **Status: 🧪**

## UC-OAP-07 · Rejeitar não bagunça a OS
- **Persona:** cliente final que **não** autoriza o serviço.
- **Aceite:** Dado uma OS em orçamento · Quando o cliente rejeita · Então a OS **permanece** em
  orçamento (o balcão renegocia) — rejeitar é idempotente, não é um estado terminal.
- **Regressão que defende:** rejeição jogar a OS num beco sem saída no pátio.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/WhatsAppAprovacaoPinTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` A tela renderiza sem zoom em 360px (alvo do charter G4) — hoje sem prova automatizada.
- `[BACKLOG]` Abrir o link em rede lenta responde em ≤800ms p50 (UX target do charter) — sem medição no CI.
- `[BACKLOG]` Toda aprovação/rejeição gera registro auditável com IP e horário (charter G3) — o
  comportamento existe no serviço, mas nenhum teste o afirma como contrato.
