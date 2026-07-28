---
id: resources-js-pages-fiscal-nfe-casos
casos: Notas NF-e / NFC-e · /fiscal/nfe
irmaos: Nfe.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-27"
---

# Casos de Uso & Aceite — Notas NF-e / NFC-e

> Persona: **Eliana (contadora)** + operador fiscal. Cockpit Fiscal (agregador thin sobre NfeBrasil).
> Âncora de contrato: SPEC `US-FISCAL-001` (lista) + `US-FISCAL-012` (cancelar/DF-e) +
> `US-FISCAL-013` (CC-e/inutilizar) + `US-FISCAL-014` (retransmitir) + `Nfe.charter.md`.
> Leis citadas: cancelamento e CC-e pelo **Ajuste SINIEF 07/2005** (janela legal; CC-e com texto de
> 15 a 1000 caracteres e até 20 sequências por NF-e); janela de 24h para NFC-e (modelo 65) e 168h
> para NF-e (modelo 55).
>
> **Status:** ✅ passa (UC-id com veredito `pass` no manifesto) · 🧪 UC citado por teste, veredito
> ainda não capturado no manifesto · ⬜ não verificado · ❌ quebrou.

## O que mudou aqui em 2026-07-27 (e por quê)

A versão anterior deste arquivo dizia que o débito era "**rastreabilidade, não ausência de teste**",
apoiada em "17 casos reais". A medição desmontou as duas metades:

| Afirmação anterior | O que a medição mostrou |
|---|---|
| "17 casos reais defendem o comportamento" | **13 dos 18** casos do `AcoesControllerTest` são **tautológicos** — montam um `validator([...], [...])` LOCAL com as regras reescritas à mão, ou assertam um array literal declarado na linha acima. Não tocam o `AcoesController`: trocar `min:15` por `min:5` **não os derruba**. |
| "o comportamento de leitura já é defendido" | O caso `isCancelavel` declarava um `$isCancelavel = function (...)` que **re-implementava a fórmula do Controller** e testava esse clone. |
| "os de validação/whitelist rodam sempre" | **Falso.** Medido: `NfeCockpitMultiTenantTest` + `AcoesControllerTest` = **21 skipped, 0 passed** no CT 100 — um `beforeEach` de arquivo skipava tudo quando faltava `nfe_emissoes`, inclusive os casos que nunca consultam banco. |

**O que foi feito:** as regras ganharam um contrato REAL em `AcoesContratoTest` (invoca os métodos do
Controller), o guard de banco desceu para os casos que de fato precisam dele, e o caso-espelho do
`isCancelavel` foi removido em favor do que invoca o método real. Resultado medido no mesmo dia:
**33 passed / 1 skipped** (o único que segue skipando é o que realmente precisa de `nfe_emissoes`).

## Recibo (medido 2026-07-27 — re-rode, não edite os números)

```
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan test Modules/Fiscal/Tests/Feature/AcoesContratoTest.php \
    Modules/Fiscal/Tests/Feature/AcoesControllerTest.php \
    Modules/Fiscal/Tests/Feature/NfeCockpitMultiTenantTest.php"
```

| Momento | Resultado |
|---|---|
| Antes | **21 skipped · 0 passed** |
| Depois | **33 passed · 1 skipped** (109 asserções) |
| Mordida provada (contrafactual) | afrouxar `min:15` → `min:5` no `AcoesController::cancelarNfe` deixa `UC-FNFE-04` **vermelho** (1 failed / 13 passed); Controller restaurado sem drift |

**Limite honesto:** o caso Tier 0 de isolamento (`UC-FNFE-01`) precisa de `nfe_emissoes` e **não
executa em nenhuma lane disponível hoje** — a lane de CI é SQLite in-memory e o staging do CT 100
não tem as migrations do NfeBrasil. É lacuna de ambiente, não defeito do teste.

## UC-FNFE-01 — A contagem do cockpit nunca mostra nota de outro business (Tier 0)
Status: 🧪 (`NfeCockpitMultiTenantTest::UC-FNFE-01 · global scope HasBusinessScope…` — **skipa** hoje, ver §recibo)
Dado 1 emissão do biz=1 e 2 do biz=99 · Quando a lista conta na sessão do biz=1 · Então vê **1**,
enquanto `withoutGlobalScopes` vê 3. Âncora: ADR 0093 + charter anti-hook "não acessar NfeEmissao sem
global scope". Teste com biz=1 e biz=99 fictício, nunca biz=4 (ADR 0101).
**Pronto quando:** a contagem escopada e a não-escopada divergem exatamente nas notas do outro tenant.

## UC-FNFE-02 — A janela legal de cancelamento é 24h na NFC-e e 168h na NF-e
Status: 🧪 (`AcoesContratoTest::UC-FNFE-02 · isCancelavel do Controller respeita 24h NFC-e vs 168h NF-e` — **passa**)
Dado nota autorizada · Quando a tela calcula se ainda dá pra cancelar · Então NFC-e (modelo 65) é
cancelável só até 24h e NF-e (modelo 55) até 168h da emissão; nota não-autorizada nunca é cancelável.
Âncora: charter Goal 3 + Ajuste SINIEF 07/2005.
**Pronto quando:** 10h/30h no modelo 65 e 48h/200h no modelo 55 dão true/false/true/false, medidos
pelo método **do Controller** (não por uma cópia da fórmula dentro do teste).

## UC-FNFE-03 — O código SEFAZ vira status legível com o tom certo
Status: 🧪 (`NfeCockpitMultiTenantTest::UC-FNFE-03 · sefazCodes retorna mapa…` — **passa**)
Dado a pílula SEFAZ · Quando lê o mapa de códigos · Então contém ao menos 100/110/220/539/691/778/999,
com 100 = `ok`, 220 = `bad` e 691 = `warn`. Âncora: charter Goal 2 + UX targets (verde autorizada ·
âmbar atenção · vermelho rejeição).
**Pronto quando:** o mapa do Controller traz os 7 códigos e os tons não invertem.

## UC-FNFE-04 — Cancelar exige motivo de 15 a 255 caracteres
Status: 🧪 (`AcoesContratoTest::UC-FNFE-04 · REJEITA <15` / `REJEITA >255` / `ACEITA válido` — **passam**)
Dado o pedido de cancelamento · Quando a justificativa tem menos de 15 ou mais de 255 caracteres ·
Então o Controller recusa com erro no campo `motivo`; com motivo válido, a validação não barra.
Âncora: US-FISCAL-012 + Ajuste SINIEF 07/2005 (justificativa mínima).
**Pronto quando:** a asserção usa a regra **do Controller** — afrouxá-la lá derruba este UC.

## UC-FNFE-05 — Carta de Correção respeita texto 15–1000 e sequência 1–20
Status: 🧪 (`AcoesContratoTest::UC-FNFE-05 · texto fora de 15–1000` / `n_seq fora de 1–20` / `aceita válido` — **passam**)
Dado uma CC-e · Quando o texto sai da faixa de 15 a 1000 caracteres, ou a sequência sai de 1 a 20 ·
Então recusa apontando o campo; dentro das faixas, passa. Âncora: US-FISCAL-013 + SINIEF 07/2005
Art. 14 (limite de 20 CC-e por NF-e). CC-e corrige texto — **nunca valor**.
**Pronto quando:** 'curto', 1001 chars, e as sequências 0/21/-1/100 falham no campo certo.

## UC-FNFE-06 — Inutilização valida modelo, faixa e justificativa
Status: 🧪 (`AcoesContratoTest::UC-FNFE-06 · modelo fora de 55/65` / `faixa invertida` / `justificativa <15` / `aceita válido` — **passam**)
Dado a inutilização de uma faixa numérica · Quando o modelo não é 55 nem 65, a faixa está invertida
(`numero_ate < numero_de`), ou a justificativa tem menos de 15 caracteres · Então recusa no campo
correspondente; payload válido passa nos dois modelos. Âncora: US-FISCAL-013.
**Pronto quando:** os 3 caminhos de recusa apontam `modelo` / `numero_ate` / `justificativa`.

## UC-FNFE-07 — Manifestação DF-e aceita 4 ações e só exige justificativa em duas
Status: 🧪 (`AcoesContratoTest::UC-FNFE-07 · whitelist` / `EXIGE justificativa` / `NÃO exige` — **passam**)
Dado a manifestação do destinatário · Quando a ação não é `cienciar`/`confirmar`/`desconhecer`/
`nao_realizada` · Então 404. Quando é `desconhecer` ou `nao_realizada` · Então justificativa é
obrigatória (≥15 chars); `cienciar` e `confirmar` não a exigem. Âncora: US-FISCAL-012.
**Pronto quando:** as 4 ações canon passam pela whitelist e a exigência condicional bate.
_(A tela DF-e propriamente dita é a sub-página `Fiscal/Dfe` — aqui só o contrato da ação.)_

## UC-FNFE-08 — A superfície de ações existe: métodos, rota e Services
Status: 🧪 (`AcoesControllerTest::UC-FNFE-08 · …5 métodos públicos` / `…signature int/int → NfeEmissao` / `…route POST registrada` / `NfeCartaCorrecaoService…` / `NfeInutilizacaoService…` — **passam**)
Dado o contrato do módulo · Quando outra camada chama uma ação fiscal · Então o `AcoesController`
expõe `cancelarNfe`/`manifestarDfe`/`cartaCorrecao`/`inutilizar`/`retransmitir`, a rota
`fiscal.acoes.nfe.retransmitir` está registrada, `NfeService::retransmitir(int,int): NfeEmissao` tem a
assinatura canônica, e a lógica de CC-e/inutilização mora nos Services do NfeBrasil (não duplicada no
Fiscal). Âncora: US-FISCAL-013/014.
**Pronto quando:** os 5 métodos, a rota e os 2 Services existem com as assinaturas esperadas.

## Backlog de casos (sem id — entram quando um teste de COMPORTAMENTO os cobrir)

- **[BACKLOG · ⬜ sem teste · Tier 0] Gate de permissão `fiscal.nfe.view` bloqueia a leitura da lista** — Dado usuário sem `fiscal.nfe.view` nem `superadmin` · Quando faz `GET /fiscal/nfe` · Então 403. O guard existe no `NfeCockpitController::index` e o charter (Goal 7) e o SPEC dão como coberto, mas **nenhum teste o exercita** — inclusive o docblock do `NfeCockpitMultiTenantTest` prometia esse caso e ele não existe. Precisa de lane com `users`+`permissions` (o teste de contrato daqui isola o gate justamente pra não depender delas).
- **[BACKLOG · ⬜ sem teste] Lista deferida filtra por tab/status/busca com paginação 50** — `rows` é `Inertia::defer`; sem teste do payload filtrado (`buildRowsPayload`, ordem `emitido_em DESC`).
- **[BACKLOG · tautológico] Whitelist de status retransmissíveis (`rejeitada`/`denegada`/`erro_envio`)** — o caso existente asserta um array literal escrito no próprio teste. Vira UC quando exercitar o `retransmitir` do Controller com uma nota em cada status.
- **[BACKLOG · ⬜ sem teste] Drawer: mapa "Jana sugere" por cstat rejeitado, atalhos J/K + Enter, pílula temporal na linha** — comportamento de UI; sem cobertura Feature nem E2E (a tela não aparece em `tests/Browser`).

## Como rodar a suíte
1. **Pest:** lane Fiscal no CT 100 (ADR 0062) — comando no §recibo acima. `AcoesContratoTest` roda em
   qualquer lane (não toca banco); `UC-FNFE-01` exige `nfe_emissoes`.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão fiscal.

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas. 17 testes mapeados, 0 citavam UC-id.
- 2026-07-27 · [CC] fecha a G-2 com 8 UC (`UC-FNFE-01..08`). Criado `AcoesContratoTest` (contrato REAL
  das regras do Controller, mordida provada); guard de banco movido pros casos que precisam dele
  (21 skipped → 33 passed); removido o caso-espelho do `isCancelavel`. Corrigidas duas afirmações
  falsas da versão anterior deste arquivo ("17 casos reais" e "validação/whitelist rodam sempre").
  Prefixo `UC-FNFE-` em vez do `UC-FISCAL-` planejado: as 6 telas Fiscal compartilhariam o id e a G-2
  casa por substring — colisão viraria cobertura falsa cruzada.
