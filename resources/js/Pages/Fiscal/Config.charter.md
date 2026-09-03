---
id: resources-js-pages-fiscal-config-charter
page: /fiscal/config
component: resources/js/Pages/Fiscal/Config.tsx
related_prototype: prototipo-ui/cowork/fiscal-subpages.jsx
bundle_source: fiscal-page.jsx
page_id: fiscal-config
url: /fiscal/config
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-009, US-NFE-041]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0358-doutrina-de-teste-tenant-98-supersede-0101, 0104-processo-mwart-canonico-unico-caminho]
prototypes: [prototipo-ui/cowork/fiscal-subpages.jsx]
---

# Charter — `Fiscal/Config`

## Mission

Superfície **unificada** de configuração fiscal do business: cert A1, ambiente SEFAZ, contingência,
regime e tributação default. Cert e ambiente são **editados aqui**; regime e tributação seguem
linkando para `/nfe-brasil/tributacao`.

> ⚠️ **Reconciliado em 2026-09-02.** A Mission dizia *"estado **read-only** … Edição completa via
> `Modules/NfeBrasil/.../Configuracao/Certificado.tsx` existente (link no header)"*. **Estava stale
> nos dois pontos**, e o código refutava: (a) `Config.tsx` **posta** desde a unificação — `uploadForm.post('/nfe-brasil/configuracao/certificado')`
> e `ambienteForm.post('…/certificado/ambiente')`; (b) o diretório `resources/js/Pages/NfeBrasil/Configuracao/`
> **não existe** — a Page foi removida e `Modules/NfeBrasil/Routes/web.php` redireciona o GET legado
> para `/fiscal/config` com o comentário *"UNIFICADA — Wagner 2026-05-27"*, 7 dias depois do
> `created: 2026-05-20` deste charter.
>
> Precedência aplicada: *teste verde > casos > charter > SPEC* (`proibicoes.md`), mesma disciplina
> da reconciliação de `Nfe.charter.md` (2026-07-27): **nenhum Non-Goal novo foi inventado** — só saiu
> o que o código refutava, e o que ele NÃO refuta (regime/tributação) seguiu como [W] aprovou.

## Goals (DoD PR #3)

1. Status cert A1 (`NfeCertificado::ativos()`) — valido_ate + dias restantes + cnpj titular
2. Regime tributário (`NfeBusinessConfig.regime`)
3. Tributação default cascata (JSON resumido)
4. Pílula temporal de vencimento (crit ≤7d, warn ≤60d)
5. Link "Editar" → `/nfe-brasil/configuracao/certificado` (módulo emissor canon)
6. Permissão `fiscal.config.edit`

## Non-Goals (PR #3 · reconciliado 2026-09-02)

- ❌ Edição inline de **regime e tributação** — segue em `/nfe-brasil/tributacao`
- ❌ Renovação automática de cert (backlog ADR futuro)
- ❌ Histórico de certs (apenas atual ativo)
- ❌ Retransmitir nota em contingência a partir daqui — a fila é do `RetentarContingenciaJob`;
  esta tela só LIGA/DESLIGA o modo (US-NFE-006)

> ⚠️ Saiu de "Edição inline" a parte **"upload novo cert"**: ela é feita aqui desde a unificação
> (`Config.tsx` `uploadForm.post(…)`). O resto do item — regime e tributação — o código **não**
> refuta e permanece. O Non-Goal de retransmissão é **novo, mas não inferido**: decorre do desenho
> já aprovado da ADR TECH-0002 (a fila é FIFO e do job), e existe para impedir que uma sessão
> futura crie um segundo caminho de transmissão a partir da tela de configuração.

## Anti-hooks

- 🚫 `encrypted_password` está em `$hidden` no Model — NUNCA expor no payload Inertia
- 🚫 `cnpj_titular` exibido OK (admin do business já tem acesso a esse dado)
- 🚫 **Não auto-ativar contingência** — nem por sinal da SEFAZ, nem por timer. A ADR TECH-0002
  rejeitou auto-ativação com razão escrita ("pode ativar em falsa-detecção: rede do servidor caiu,
  não SEFAZ"). Sugerir é permitido; ligar é ato humano com motivo declarado.
- 🚫 **Não prometer transmissão ao desativar** — desligar significa "as PRÓXIMAS saem normais",
  nunca "as anteriores foram transmitidas". Copy que confunde os dois mente sobre nota fiscal real.
- 🚫 **Não calcular a duração da contingência no browser** — `diasAtiva` vem do servidor; no cliente
  dependeria do relógio da máquina do operador, e é justamente esse número que mitiga o risco
  "tenant esquece ligado".

> ⚠️ Saiu o anti-hook *"🚫 Não criar UPDATE Controller — esta tela é read-only por design"*:
> **refutado pelo `main`**, que posta desta tela desde 2026-05-27 (cert + ambiente). Mantê-lo
> instruiria uma sessão futura a desligar código correto — o mesmo motivo da reconciliação de
> `Nfe.charter.md`.
