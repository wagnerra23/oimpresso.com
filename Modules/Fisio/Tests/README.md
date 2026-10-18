# Suíte TDD — Modules/Fisio · F1 "Constância"

Testes escritos **antes do código** (handoff `HANDOFF-fisio-f1-claude-code.md`, T-02 a T-10). Implemente até ficarem verdes **sem afrouxar asserção**. Se um teste estiver errado, mude o teste num commit próprio e diga por quê no PR.

**Total:** 17 arquivos, 138 blocos `it()`, **166 casos** (com datasets expandidos). 13 de Feature (117 casos) e 4 de Unit (49 casos puros, sem DB).

## Onde cada arquivo vai no repo

Os caminhos desta pasta já são os do repo (copie a árvore `Modules/` para a raiz):

| aqui | o que é |
|---|---|
| `Modules/Fisio/Tests/Feature/*.php` | contratos HTTP/DB (Pest + `Tests\TestCase` + `DatabaseTransactions`) |
| `Modules/Fisio/Tests/Unit/*.php` | regras puras (Pest **sem** TestCase: rodam em qualquer lane) |
| `Modules/Fisio/Tests/Support/FisioFixtures.php` | arranjo comum (fora de `Feature/` de propósito: o phpunit varre `Feature` recursivo) |
| `Modules/Fisio/Database/Factories/*.php` | factories dos models (para seed/demo; as fixtures dos testes não dependem delas) |
| `Modules/Fisio/Resources/js/Pages/Fisio/**.casos.md` | um `casos.md` por tela (ADR 0264). O `.charter.md` e o `.tsx` ficam com o implementador |

## Como rodar

1. Registre as duas pastas em `phpunit.xml`, na testsuite `Feature`, logo depois de `Modules/Producao/Tests/Feature` (sem isso os testes **não rodam**, e isso é proibido em `proibicoes.md`):
   ```xml
   <directory>./Modules/Fisio/Tests/Feature</directory>
   <directory>./Modules/Fisio/Tests/Unit</directory>
   ```
   Não precisa mexer em `tests/Pest.php`: cada arquivo declara o próprio `uses(...)`.
2. Rode no **CT 100 / CI MySQL**, nunca na máquina local (ADR 0358 §Onde o teste roda):
   ```bash
   vendor/bin/pest Modules/Fisio/Tests/Unit            # puro, roda em qualquer lugar
   vendor/bin/pest Modules/Fisio/Tests/Feature         # precisa MySQL com business 98 semeado
   ```
3. ⚠️ Em sqlite os Feature fazem **skip** (schema UltimatePOS e trigger append-only são MySQL). "0 failed" com tudo skip não prova nada (LC-13): conte os *passed*.
4. `npm run casos:check` (G-1/G-2): os 47 UCs dos cinco `casos.md` são todos citados por pelo menos um teste (conferido nesta entrega).

## Estado ao entrar no repo (US-FISIO-002, esqueleto)

- Registrada no `phpunit.xml` (testsuite `Feature`, depois do `Producao`). Ainda **não** está em nenhuma lane de CI por arquivo (`ci-sqlite-pest.list`, `verticais-pest.yml`): as lanes são allowlist verde, e entrar vermelho ou só com skip é proibido nelas.
- `FisioModuloContratoTest`: 3 de 4 verdes já no esqueleto (pacote, permissões, varredura de fonte). O de rotas fica vermelho até as rotas da F1 existirem; quando ficar verde inteiro, entra na `ci-sqlite-pest.list` (roda sem banco, como o `ProducaoModuloAtivacaoTest`).
- Os Feature com `F::motivoDeSkip()` pulam até as migrations `fisio_*` (US-FISIO-003); com as tabelas, entram na lane MySQL (`verticais-pest.yml`) junto com `Modules/Fisio/**` nos `paths`.
- Unit: 49 casos, todos vermelhos só por classe inexistente (nenhum erro de sintaxe), até os serviços puros chegarem.
- As 4 factories (`Database/Factories/*.php`) ficaram fora do esqueleto (referenciam `Modules\Fisio\Entities\*`, e o PHPStan ratchet acusava classe inexistente) e **voltaram na US-FISIO-003** junto com os models, com `@extends Factory<Model>`. As fixtures dos testes não dependem delas.

## Estado na US-FISIO-003 (migrations, models, append-only)

- Migration única `Database/Migrations/2026_10_18_000001_create_fisio_tables.php` com as 11 tabelas do HANDOFF, carregada pelo `FisioServiceProvider`. Triggers MySQL append-only em `fisio_records` (UPDATE/DELETE de linha com `signed_at`) e `fisio_game_ledger` (qualquer UPDATE/DELETE). Nenhuma FK de dado clínico com `ON DELETE CASCADE` (guarda de 20 anos).
- Models além dos seis da suposição: `Consent`, `Protocol`, `GameLedger` (ledger do jogo), `Questionnaire`, `PortalToken`. Exceção da camada do model: `Modules\Fisio\Exceptions\DadoClinicoImutavelException` (save de registro assinado, qualquer update de ledger, delete de paciente/programa/check-in/registro/consentimento/questionário).
- **Ajuste de fixture (não de asserção):** `fisio_program_items` ganhou `business_id` (cópia do programa, preenchida pelo model; business divergente é recusado) e o `ProgramItem` usa `HasBusinessScope` — o Tier 0 exige escopo em todo model (`MultiTenantScopeArchitectureTest`) e `business_id` em toda tabela (HANDOFF). Com isso, `F::itens()` passou a ler com `withoutGlobalScopes()` (comentado `// SUPERADMIN:`), porque o UC-FPS-01 do `FisioMultiTenantTest` lê os itens do programa da 98 **com a sessão da 99** para provar que nada mudou. `F::programa()` não mudou: o `create` não passa `business_id` e o model copia do programa.
- `FisioEsquemaContratoTest` (novo): contrato da camada de dados — 11 tabelas com `business_id` indexado, isolamento 98×99 em todo model, biblioteca global+própria, triggers append-only, únicos e bloqueio de exclusão. É o único arquivo do Fisio na lane MySQL (`verticais-pest.yml`) por enquanto; os demais têm casos que dependem das rotas das US-FISIO-004..010 e entram na lane quando a US de cada um os deixar verdes.
- Os `*.casos.md` ficam ao lado das Pages desde já: `npm run casos:check` não acusa violação nova com eles sem `.tsx`.
## Doutrina seguida (o que foi copiado do repo)

- Tenant **98** (`$this->seededTenant()`) contra **99** (`$this->seededSupportClientTenant()`), ADR 0358. Nunca 1 nem 4. Usuário novo com **exatamente** as permissões do caso (`usuarioComPermissoes`).
- Pacote simulado só para `fisio_module` (Mockery partial no `ModuleUtil`, idioma do `ClienteVeiculosModuleGateTest`/`ProducaoApontamentoTelaTest`).
- Toda prova de isolamento leva **controle negativo** (a mesma consulta sem escopo acha as duas linhas; a mesma tela como 99 traz só a 99).
- Toda recusa confere que **nada foi gravado**.
- Prop de tela lida como o browser lê (partial reload Inertia), idioma do `ProducaoRoteirosContratoTest`.
- `withoutGlobalScopes()` só em fixture e verificação, sempre comentado `// SUPERADMIN:`.
- Cabeçalho `// Cobre UC-...` em cada arquivo: é o que o G-2 lê.

## Mapa teste → regra do handoff

| Regra (handoff + atualização de escopo) | Arquivo · UC |
|---|---|
| `business_id` em tudo; isolamento entre 2 businesses | `FisioMultiTenantTest` (UC-FPI-02, FPS-01, FPN-06), `FisioBibliotecaTest` (FBI-01/03), `FisioPortalAcessoTest` (FPP-07) |
| Menu e rotas invisíveis sem o módulo | `FisioModuloVisibilidadeTest` (FPN-01, FPI-04), `FisioModuloContratoTest` |
| Paciente é um contact (único por business); meta 1–7 | `FisioPacienteCrudTest` (FPI-01, FPI-03, FPS-02) |
| **Excluir = `blocked_at`; nenhum forceDelete; bloqueado sem portal** | `FisioPacienteCrudTest` (FPS-07), `FisioModuloContratoTest` (varredura), `FisioPortalAcessoTest` (FPP-07), `FisioWhatsappJobTest` (FPS-12) |
| **`fisio_records` append-only; carimbo signed_by/crefito/at; campos mínimos; correção só por adendo** | `FisioProntuarioTest` (FPS-03, 04, 05) |
| Editor: criar, adicionar, reordenar, editar dose, remover | `FisioProgramaEditorTest` (FPS-06, 08, 09) |
| **Alerta de contraindicação avisa (não bloqueia) e registra a confirmação** | `FisioProgramaEditorTest` (FPS-10) |
| Portal: token válido, adulterado, expirado, revogado, substituído | `FisioPortalAcessoTest` (FPP-01, 02) |
| PIN errado até o lockout; lockout expira | `FisioPortalAcessoTest` (FPP-03, 04) |
| **`fisio_consents` separados e versionados; sem teleatendimento não há dado; revogação pelo portal** | `FisioPortalAcessoTest` (FPP-05, 06) |
| Check-in idempotente por dia; dor 0–10; esforço; item fora do programa | `FisioCheckinTest` (FPP-08, 09, 11, 12) |
| **Cada check-in → `fisio_records` telemonitoramento com modality/channel; reenvio não duplica** | `FisioCheckinTest` (FPP-10) |
| **Red flags notificam o profissional e entram na fila na hora** | `FisioCheckinTest` (FPP-13, FPN-04) |
| **Jogo: XP por exercício e por check-in (inclusive dor alta); nada por exceder ou item fora; sem XP duplicado; ledger append-only** | `FisioJogoTest` (FPP-14, 15, 16) |
| **Nível derivado do XP, sem coluna editável** | `FisioJogoTest` (FPP-17), `NivelCalculatorTest` |
| **Sequência por dias prescritos; descanso e pausa não quebram** | `FisioJogoTest` (FPP-18), `SequenciaCalculatorTest` |
| **Dor ≥ limiar (padrão 7) ou red flag → só `stop_instruction`** | `FisioPortalSegurancaClinicaTest` (FPP-19) |
| **Portal sem série histórica de dor** | `FisioPortalSegurancaClinicaTest` (FPP-20) |
| WhatsApp: job enfileirado, idempotente, sem disparo sem módulo | `FisioWhatsappJobTest` (FPS-11, 12) |
| **Consentimento de WhatsApp revogado bloqueia o lembrete; no máximo 1 lembrete por paciente por dia** | `FisioWhatsappJobTest` (FPS-12, FPN-07) |
| Adesão 7d (sem programa, meta 0 proibida, acima da meta = 100%) | `FisioPainelTest` (FPN-02), `AdesaoCalculatorTest` |
| Fila de atenção (dor subiu ≥2 e persiste, dor ≥7, sem registro ≥4 dias, sinal de alerta) | `FisioPainelTest` (FPN-03, 04), `AtencaoDetectorTest` |
| Biblioteca global × própria | `FisioBibliotecaTest` (FBI-01..04) |

Em **negrito**, as regras que vieram na atualização de escopo.

## Decisões que os testes fixam

Onde o handoff deixava escolha, o teste escolheu. Mudar qualquer uma delas é mudar o teste junto, de propósito:

1. **Reenvio do check-in no prontuário:** reenvio igual não cria nada; reenvio com relato diferente cria **1 `adendo`** com `amends_id` para o telemonitoramento do dia, que não muda. Motivo: o telemonitoramento é append-only e o prontuário precisa mostrar que o paciente mudou o relato.
2. **XP:** `config('fisio.xp.exercicio_concluido')` por vez feita, até o `times_per_day` do item; `config('fisio.xp.checkin')` uma vez por dia. Reenvio lança só a diferença positiva e nunca estorna. Os testes usam 10 e 5.
3. **Sequência:** folga = `ceil(7 / sessions_per_week) - 1` dias seguidos sem registro. Dia de pausa (dor ≥ limiar ou red flag) não soma e não quebra. Hoje não conta como buraco.
4. **"Dor subiu e persiste":** é a comparação dos **2 registros mais recentes com dor informada**. Se o último voltou a cair, não entra.
5. **Adesão:** arredondamento meio-para-cima; sem programa ativo dá `null` e fica fora da média.
6. **Contraindicação:** 1ª tentativa devolve **409** `{erro:'contraindicacao', flags:[...]}` e não grava; com `confirmar_contraindicacao=true` grava e registra `contraindication_ack_flags/by/at` no item.
7. **Lembrete diário:** não vai para quem já registrou o dia nem para quem não tem programa ativo.
8. **Novo link revoga o anterior** (um token vivo por paciente).
9. **Consentimento de versão antiga não vale**: a versão vigente vem de `config('fisio.consent_versions.<purpose>')`, nunca do corpo.

## Divergências conhecidas com `etapas/02-f1-constancia.md` (achadas na refutação de 2026-10-18)

A suíte e a etapa 02 foram escritas separadas e discordam em quatro pontos. Nenhum foi resolvido aqui; cada um se decide na US indicada, mudando o teste **ou** a etapa num commit próprio, dito no PR:

1. **Modalidade do telemonitoramento:** a suíte fixa `modality='assincrona'`; a etapa 02 §3.5 escreve `modality=assincrono` (e admite `channel=whatsapp_botao`). → US-FISIO-007.
2. **Razão do XP de registro:** a suíte usa `fisio_game_ledger.reason='checkin'` e `config('fisio.xp.checkin')`; a etapa 02 §3.4 chama de `registro_do_dia` e o enum dela tem mais 6 razões (`missao`, `desafio_semanal`, `medalha`, `escudo_ganho`, `escudo_usado`, `pausa_protegida`). → US-FISIO-008.
3. **XP de exercício:** a suíte dá `xp.exercicio_concluido` **por vez feita** (até `times_per_day`); a etapa 02 dá 70 × (itens feitos ÷ prescritos), com normalização 4 ÷ n e teto semanal. Os padrões do `Config/config.php` seguem a etapa (70/30); as fixtures sobrescrevem com 10/5. → US-FISIO-008.
4. **Sequência:** a suíte usa folga `ceil(7 / sessions_per_week) - 1`; a etapa 02 usa escudos (1 a cada 7 dias prescritos, estoque máximo 2). → US-FISIO-008.
## Suposições de nome — o implementador CONFIRMA (ou ajusta os testes num commit só)

**Classes**
- Models em `Modules\Fisio\Entities\`: `Patient`, `Record`, `Exercise`, `Program`, `ProgramItem`, `Checkin` (casts `array` em `goals`, `condition_flags`, `steps`, `done_item_ids`, `red_flags`, `body`, `contraindication_*`; `datetime` em `signed_at`).
- `Exercise` com escopo próprio `business_id = sessão OR business_id IS NULL` (o `HasBusinessScope` cru esconderia os globais e o `FisioBibliotecaTest` falha).
- `Modules\Fisio\Http\Controllers\DataController` (`superadmin_package`, `user_permissions`, `modifyAdminMenu`), item de menu com label **"Fisio"**.
- `Modules\Fisio\Services\PortalTokenService`: `emitir(Patient): array{token, pin}`, `validar(string): ?Patient`, `revogar(Patient): void`.
- `Modules\Fisio\Services\JogoService::nivel(Patient): int`.
- Serviços puros: `AdesaoCalculator::calcular(...)`, `AtencaoDetector::motivos(...)`, `SequenciaCalculator::calcular(...)`, `NivelCalculator::nivel/progresso(...)` (assinaturas no docblock de cada Unit).
- Jobs: `Modules\Fisio\Jobs\EnviarProgramaWhatsappJob(int $businessId, int $programId)` e `EnviarLembreteDiarioJob(int $businessId)`, `handle()` **sem argumentos** (dependências via `app()`), reaproveitando `Modules\Whatsapp\Jobs\SendWhatsappMessageJob`.
- Comando agendado `fisio:lembretes`.
- Notificação `Modules\Fisio\Notifications\SinalDeAlertaNotification` com props públicas `patientId`, `businessId`, `redFlags`, enviada ao `created_by` do programa.
- O Fisio resolve o `ModuleUtil` pelo container (injeção ou `app()`), **nunca** `new ModuleUtil()`; senão o mock do pacote não pega. (O `DataController` da Producao faz `new` — o Fisio não pode.)

**Pacote, permissões, config**
- Pacote `fisio_module`; permissões `fisio.access` e `fisio.editar`.
- `config/fisio.php` (no módulo): `xp.exercicio_concluido`, `xp.checkin`, `niveis` (mapa nível→XP mínimo), `consent_versions.{teleatendimento,whatsapp,...}`, `portal.max_tentativas` (5), `portal.lockout_minutos` (30), `pain_alert_threshold_padrao` (7), `red_flags` (lista fechada). As fixtures sobrescrevem esses valores.

**Rotas** (prefixo `/fisio`, autenticadas; portal público em `/fisio/p/{token}`)
- `GET /fisio` (Painel) · `GET|POST /fisio/pacientes` · `GET|PUT|DELETE /fisio/pacientes/{id}` · `POST /fisio/pacientes/{id}/registros` (JSON, **sem** PUT/DELETE → 405) · `POST /fisio/pacientes/{id}/programas` · `POST /fisio/programas/{id}/itens` · `PUT /fisio/programas/{id}/itens/ordem` (`{ids:[...]}`) · `PUT|DELETE /fisio/programas/{id}/itens/{item}` · `POST /fisio/programas/{id}/enviar` (202) · `GET /fisio/biblioteca` · `POST /fisio/biblioteca/exercicios` · `PUT /fisio/biblioteca/exercicios/{id}`.
- Portal: `GET /fisio/p/{token}` (Inertia `Fisio/Public/Portal`) · `POST .../pin` (200 / 422 `pin_invalido` + `tentativas_restantes` / 423 `bloqueado` / 404 `link_invalido`) · `POST .../consentimentos` (201) · `DELETE .../consentimentos/{purpose}` · `POST .../checkin` (201 criou / 200 atualizou / 403 `consentimento_pendente` / 422). A sessão do portal guarda o PIN validado (o cliente HTTP do teste mantém a sessão entre chamadas).
- Edição do paciente e criação do paciente são form Inertia (redirect + `assertSessionHasErrors`); editor de programa, prontuário e portal são JSON.

**Props das telas**
- Painel: `kpis{pacientes_ativos, adesao_media_7d, dor_media}`, `atencao[{patient_id, motivos[]}]`, `pacientes[{id, adesao_7d, ultima_dor, nivel, sequencia}]`.
- Pacientes/Index: `pacientes[{id,...}]`, filtro `?q=`. Show: `paciente{id, nome}`, `prontuario[{id, kind, amends_id,...}]`, `serie_dor[{date, pain}]`.
- Biblioteca: `exercicios[{id,...}]`, filtros `?region=` e `?q=`.
- Portal: `erro` (`null`|`link_invalido`), `etapa` (`pin`|`consentimento`|`hoje`), `tentativas_restantes`, `consentimento{purpose, version}`, `hoje{itens[]}`, `jogo{xp, nivel, sequencia, em_pausa}`, `modo` (`normal`|`pausa`), `instrucao`. Resposta do check-in em pausa: **exatamente** `{modo, instrucao, checkin_id}`.

**Colunas além do handoff** (precisam existir na migration)
- `fisio_program_items`: `contraindication_ack_flags` (json), `contraindication_ack_by`, `contraindication_ack_at`.
- Valores fechados: `fisio_records.modality='assincrona'`, `channel='portal'`; `fisio_game_ledger.reason ∈ {exercicio_concluido, checkin}` (enum no banco, e insert fora dele falha); `fisio_programs.status` encerrado ≠ `ativo`.
- Append-only por **trigger MySQL** em `fisio_records` (UPDATE/DELETE de linha com `signed_at` preenchido) e em `fisio_game_ledger` (qualquer UPDATE/DELETE), como o `pcp_apontamentos`. Rollback de transação não dispara trigger, então `DatabaseTransactions` limpa.
- `UNIQUE(patient_id, date)` em `fisio_checkins`; `UNIQUE(business_id, contact_id)` em `fisio_patients`.
- Idempotência do lembrete e do envio: chave **persistida**, à escolha do implementador. O teste mede o número de `SendWhatsappMessageJob`, não a tabela.

## Limites conhecidos desta entrega

- Lint (`php -l`, PHP 8.3.6) passou em todos os 22 arquivos PHP. **Nenhum teste foi executado**: o código ainda não existe, e o repositório não foi montado aqui.
- `FisioModuloContratoTest` (rotas) depende dos nomes de URI acima; se o implementador mudar uma rota, troque aqui e na tela juntos.
- Não há teste de navegador (Playwright) nem de render React; os `[BACKLOG]` dos `casos.md` dizem o que ficou de fora.
- Questionários (T-11), protocolos-modelo, missões e medalhas: fora desta suíte.
