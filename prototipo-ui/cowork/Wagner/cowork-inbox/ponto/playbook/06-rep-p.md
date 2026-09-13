---
sessao: "06"
titulo: REP-P sem selfie — 7 rotas 501 → MobileMarcacaoController · app do colaborador · fila do gestor
dono: "[CL]"
base: e86130722de1
prefixo: Modules/Ponto/Http/routes.php (SÓ o bloco 2 — /ponto/api) · Http/Controllers/Api/MobileMarcacaoController.php · resources/js/Pages/Ponto/Mobile/** · prototipo-ui/contrato/ponto-rep-p.contract.json · Tests/Feature/Wave28MobileMarcacaoTest.php (estender)
nao_toca: Services/MarcacaoService.php (NSR/hash — canônico) · Services/NsrService.php · Services/MobileMarcacaoService.php (anti-fraude pronto: expor, não reescrever) · Database/ · ponto_marcacoes
depende: W10 (ratificar o escopo reescrito) — vaga 2
---
# 06 · REP-P

## O que a ADR 0383 mudou (2026-08-27, aceito, #6393) — lei desta thread
- **Sem selfie, sem biometria**: `SELFIE_MIN_BYTES`, `verificarBiometria()` e o anti-cheat da selfie foram deletados; `dispositivo_id = mobile:{device_uuid}`. O GUARD LGPD em `Wave28MobileMarcacaoTest` **falha** se qualquer termo voltar.
- Base legal: LGPD **Art. 5º, II + Art. 11** — o Art. 9º citado no plano antigo estava **errado**. Nenhuma copy da tela cita Art. 9º.
- Anti-fraude que fica (e a tela expõe): GPS accuracy ≤ 500 m (**recusa** sinal ruim — responde W5: não existe "bater mesmo assim") · clock-skew ≤ 30 s · geofence **sinaliza** para revisão humana · NSR + hash encadeado + append-only pelo `MarcacaoService`.
- A ADR diz textualmente: *"Onda 4 (REP-P) do plano do Ponto precisa ser reescrita"* — esta thread **é** a reescrita; W10 ratifica o escopo.

## Estado (lido no `main`)
`routes.php` bloco 2: **7 closures `abort(501)`** (`POST /marcar` · `GET /marcacoes/hoje` · `GET /saldo` · `GET|POST /intercorrencias` · `GET /escala/hoje` · `GET /dashboard/kpis`) — middleware `auth:api` (Passport). `Api/MobileMarcacaoController.php` (5 KB) **existe e nada o alcança** (a ADR mediu). Não há `Pages/Ponto/Mobile/`.

## A · Identidade — ancoragem dupla
- **alvo (layout):** `prototipo-ui/cowork/ponto-mobile.jsx` **depois da thread 10** (sem selfie): 3 telas do colaborador (`BaterPonto` · `MeuEspelho` · `Justificar`) dentro do `android-frame` + `ValidacaoMobile` (fila do gestor, `os-table` 8 `th`). Medido 04/09: 991 nós · `.pt-nota.info` (2) · `.ptm-wrap` (2). Remedir após a 10.
- **âncora (código):** `MobileMarcacaoController` + `MobileMarcacaoService` · irmã golden `Pages/Ponto/Espelho/Show.tsx` (pacote: tsx · charter · casos · contrato · `*ContratoTest` · e2e).
- Persona: Técnico Repair (celular, toque ≥44 px) · gestor (fila).

## B · Não inventar
- Dados: `ponto_marcacoes` (append-only) · `ponto_apuracao_dia` (meu espelho) · `ponto_intercorrencias` (justificar) · `ponto_escalas` (escala de hoje). Campo fora disso ⇒ `—`.
- Copy PT-BR: "Bater ponto", "Meu espelho", "Justificar", "Sinal de GPS fraco — aproxime-se de área aberta" (recusa, sem "mesmo assim"). Vocabulário: **marcação**, **intercorrência**, **colaborador**.
- Componentes `@/Components/ui/*` · `shared/*` · tokens do DS.

## C · Comportamento (EARS)
| elemento | TAG | QUANDO → O SISTEMA DEVE | persiste | reversível | prova |
|---|---|---|---|---|---|
| Bater ponto | BUTTON | acionado com GPS ok → `POST /ponto/api/marcar` → NSR server-side, `dispositivo_id=mobile:{uuid}` | grava (append) | **não** (anulação é do gestor) | Pest: marcação criada, hash encadeado |
| Bater ponto | BUTTON | accuracy > 500 m **ou** skew > 30 s → 422 com mensagem | não | — | Pest existente (Wave28) |
| geofence fora | — | marcar → grava **e sinaliza** (`revisar=true`) | grava | — | Pest: flag na fila |
| Justificar | BUTTON | enviado → cria intercorrência `PENDENTE` | grava | cancelar (rota existente) | Pest |
| Fila do gestor · Aprovar/Rejeitar | BUTTON | clique-no-filho `stopPropagation` → rotas `ponto.aprovacoes.*` existentes | grava | não | Pest existente |
Invariantes: `business_id` por tenant em toda query (Tier 0, `CrossTenantMarcacaoTest`) · 501 nunca é sucesso · nenhuma Page gera NSR · sem número inventado.

## Execução
```
ARQUIVOS A EDITAR : routes.php bloco 2 (7 closures → métodos do MobileMarcacaoController; nomes ponto.api.*)
                    Api/MobileMarcacaoController.php (métodos que faltam; reusar MobileMarcacaoService)
                    ${PAGES}/Mobile/{Index|Marcar}.tsx + MeuEspelho.tsx + Justificar.tsx (CRIAR via criar-tela.mjs Ponto/Mobile PT-0X)
                    ${PAGES}/Aprovacoes/Index.tsx NÃO — a fila do gestor reusa a tela viva com filtro `origem=mobile` (1 filtro, não tela nova)
                    prototipo-ui/contrato/ponto-rep-p.contract.json (gerador carimba)
                    Wave28MobileMarcacaoTest.php (estender com os UC da tela)
PASSO A PASSO     : 1) gh pr list 2) LER controller + service inteiros 3) rotas → controller (PR 1, ≤300 ln, com Pest)
                    4) criar-tela.mjs 5) Pages 6) contrato 7) placar no PR 8) _saida-06.md
PARAR SE          : (a) W10 não ratificada → não abrir  (b) qualquer termo de selfie/biometria → GUARD falha, parar
                    (c) fila do gestor exigir tela nova → parar: é filtro na Aprovações viva  (d) NSR no cliente → nunca
```

## Prova (PLACAR confere)
- `routes.php` **não** contém `abort(501, 'Implementar em MarcacaoApiController::marcar')` **e** contém `MobileMarcacaoController`
- `${PAGES}/Mobile/Index.tsx` ou `Marcar.tsx` · `contrato/ponto-rep-p.contract.json` com `alvo`+`secoes` · controller **sem** `selfie`
- `_saida-06.md` · lane `ponto-pest.yml` verde (GUARD incluído)
