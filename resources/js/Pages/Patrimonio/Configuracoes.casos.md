---
casos: Patrimonio/Configuracoes — prefixos de código e notificações de manutenção (MWART, ADR 0104)
irmaos: Configuracoes.charter.md (lei) · memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md (F1 PLAN)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-08"
---

# Casos de Uso & Aceite — Patrimonio/Configuracoes

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2 (ADR 0264): UC declarado sem teste citando o id = órfão.
> Teste que os defende: [`Modules/AssetManagement/Tests/Feature/ConfiguracoesContratoTest.php`](../../../../Modules/AssetManagement/Tests/Feature/ConfiguracoesContratoTest.php).

> **Por que quatro UC.** Cada um tem teste que roda. Os cenários do protótipo que não têm
> backend (os três interruptores, a linha de retenção) ficam no `[BACKLOG]` — prosa honesta,
> sem id — porque declarar UC sem teste cria órfão e quebra o G-2.

> **Onde os testes rodaram:** CT 100 (`oimpresso-staging`, MySQL real) — nunca local
> ([`proibicoes.md §Ambiente`](../../../../memory/proibicoes.md)). Tenant fictício **98**
> (`seededTenant()`), adversário **99**; biz=1 é empresa real e biz=4 é ROTA LIVRE, ambos
> proibidos ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
>
> Veredito da suíte do arquivo: **5 passed · 35 assertions** (2026-09-08, seed 1788892480).
> Regressão do módulo no mesmo estado: **74 passed · 277 assertions**, 0 falhas.
> Leia *assertions*, não `0 failed`: teste que pula sai com exit 0.

---

## UC-CFG-01 · Configurar o patrimônio é do administrador, e só dele

- **Persona:** o dono da empresa — precisa saber que um operador comum não muda a numeração
  do patrimônio nem redireciona e-mail da empresa.
- **Aceite:** Dado um business com o módulo assinado · Quando um usuário **não-admin** abre
  `/asset/settings` · Então recebe **403**; e quando o **admin** do mesmo business abre a
  mesma URL, recebe **200**.
- **Teste:** `ConfiguracoesContratoTest.php` — **dois** `it()` citando `UC-CFG-01`: o negativo
  (não-admin → 403) e **o espelho** (admin → 200). O espelho não é enfeite: sozinho, o 403
  também passaria se a rota estivesse quebrada, se o módulo não estivesse assinado ou se
  qualquer outro gate barrasse antes — é ele que prova que o 403 veio do gate de **admin**, e
  não de outra coisa.
- **Regressão que defende:** o `|| ! $is_admin` no fim do `if`
  (`AssetSettingsController.php:43` e `:110`) é a única guarda desse tipo no módulo — as telas
  irmãs param em `superadmin || assinatura`. "Normalizar" essa linha para o padrão das outras
  abriria a configuração do módulo a qualquer usuário com `asset.*`.
- **Nota de método:** `is_admin` é `hasRole('Admin#'.$business_id)` (`app/Utils/Util.php:486`),
  não uma permission — por isso o fixture não-admin recebe `asset.view` e ainda assim é
  barrado. Fosse permission, o teste passaria pelo motivo errado.
- **Status: 🧪** — passa no CT 100 (run 2026-09-08, seed 1788892480).

---

## UC-CFG-02 · A tela de Configurações é Inertia, no endereço que o [W] decidiu

- **Persona:** o próprio time — a migração precisa ser verificável, não afirmada.
- **Aceite:** Dado o admin do business · Quando abre `/asset/settings` · Então recebe **200** e
  a página Inertia é o componente **`Patrimonio/Configuracoes`**, com as props `settings`,
  `templates` e `tags` no primeiro render (`usuarios` é **deferida** — ver nota de método).
- **Teste:** `ConfiguracoesContratoTest.php` — `it()` citando `UC-CFG-02`, com `assertInertia`.
- **Nota de método:** `usuarios` fica **fora** do assert de propósito. Ela é `Inertia::defer`
  (a única prop que cresce com o tenant), logo **não vem no primeiro render** — assertar
  `has(usuarios)` mediria a ausência dela, que é o comportamento correto do defer, e o teste
  reprovaria justamente quando a otimização está funcionando.
- **Regressão que defende:** a URL **não muda** na migração (`settings.index`, `GET
  /asset/settings`), então 200 sozinho não distingue "virou Inertia" de "continua Blade". O que
  distingue é o componente — e o assert do Inertia verifica que **o arquivo do componente
  existe**. Defende também o endereço da
  [ADR 0394](../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md):
  mover a tela de pasta quebra este teste.
- **Status: 🧪** — passa no CT 100 (run 2026-09-08, seed 1788892480).

---

## UC-CFG-03 · Os prefixos que eu vejo são os da MINHA empresa

- **Persona:** quem administra o patrimônio — as settings são por empresa, e a numeração de uma
  não pode aparecer na outra.
- **Aceite:** Dado dois businesses com `asset_code_prefix` diferentes · Quando o admin de cada
  um abre `/asset/settings` · Então cada um recebe **o prefixo da sua** empresa e **não** o da
  outra.
- **Teste:** `ConfiguracoesContratoTest.php` — `it()` citando `UC-CFG-03`, com os dois lados
  assertados no mesmo cenário (o de 98 vê o de 98 e não o de 99; o de 99 vê o de 99 e não o de
  98). Os dois lados juntos pelo mesmo motivo do UC-CFG-01: um `not->toBe` sozinho passaria se
  a prop viesse vazia.
- **Regressão que defende:** `business.asset_settings` é lido por `business_id` explícito
  (`AssetUtil::getAssetSettings`) — não há global scope. Trocar esse `where` por sessão mal
  resolvida vazaria configuração entre empresas
  ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0).
- **Status: 🧪** — passa no CT 100 (run 2026-09-08, seed 1788892480).

---

## UC-CFG-04 · Desligar o e-mail desliga de verdade

- **Persona:** o admin que decidiu parar de mandar e-mail de manutenção — se o interruptor
  volta sozinho, a empresa continua disparando mensagem que alguém mandou parar.
- **Aceite:** Dado o e-mail de "enviado para manutenção" **ligado** · Quando o admin salva o
  formulário com o interruptor **desmarcado** · Então `enable_asset_send_for_maintenance_email`
  deixa de valer no JSON de `business.asset_settings`; e quando salva com ele **marcado**, o
  valor volta a valer.
- **Teste:** `ConfiguracoesContratoTest.php` — `it()` citando `UC-CFG-04`, exercitando os dois
  sentidos (desliga e religa) contra a coluna real.
- **Regressão que defende:** **a armadilha específica desta migração.** O `store()` decide por
  `$request->has(...)`, não `boolean(...)` (`AssetSettingsController.php:117-123`). Checkbox
  HTML desmarcado não envia a chave; um cliente Inertia que mandasse `enable_...: false` faria
  `has()` devolver **true** e gravaria **1** — e desligar deixaria de funcionar, silenciosamente,
  sem erro em lugar nenhum. Este UC é o que impede que a tela React introduza esse defeito, e
  ele não existia no Blade.
- **Nota de método:** o teste faz backup da coluna `asset_settings` do tenant e a restaura em
  `finally` — o CT 100 é base persistente que não se limpa entre execuções, e esta é a única
  suíte da frente que **escreve** em `business`.
- **Status: 🧪** — passa no CT 100 (run 2026-09-08, seed 1788892480).

---

## Divergências protótipo × backend — declaradas, não escondidas

Não são UC porque **não são comportamento que esta onda defende**; são o registro de que a
fonte visual e o código não coincidem, e de qual dos dois mandou.

| Protótipo (`patrimonio-page.jsx:589`) | Backend real | O que a tela fez |
|---|---|---|
| **3** prefixos (ativo, alocação, revogação) | **4** — tem também `asset_maintenance_prefix` | entregou os 4; omitir seria regressão vs. o Blade |
| 3 interruptores: garantia 30d · manutenção · alocação | 2 notificações, ambas de manutenção (`AssetSentForMaintenance`, `AssetAssignedForMaintenance`) | entregou os 2 reais; os outros não têm coluna, job nem `Notification` |
| rodapé "Retenção: 5 anos (`Config/retention.php`)" | `retention.php` é da **thread 05, BARRADA**; as tabelas que ele declara não existem | não renderizou |
| permissão `asset.settings` | a guarda real é `is_admin` (`Admin#{biz}`) | manteve `is_admin` |
| sem destinatários, sem assunto/corpo | multi-select + 4 campos de template | entregou — o Blade os tinha |

---

## [BACKLOG] — vira UC na onda que trouxer o teste

- [BACKLOG] O corpo do e-mail é editado com formatação visual (WYSIWYG), como o TinyMCE do
  Blade fazia, sem o autor precisar escrever HTML.
- [BACKLOG] Cada prefixo mostra qual será o próximo código gerado com ele.
- [BACKLOG] O formulário recusa prefixo com formato inválido, e diz por quê.
- [BACKLOG] Notificar quando a garantia entra nos últimos 30 dias (pede coluna, job e
  `Notification` — nada disso existe).
- [BACKLOG] Notificar o colaborador quando um bem passa a ser responsabilidade dele.
- [BACKLOG] O admin dispara um e-mail de teste do template sem sair da tela.
- [BACKLOG] As settings gravam por merge, preservando chave que o formulário não conhece —
  hoje o `store()` regrava o JSON inteiro (§8 do RUNBOOK).
