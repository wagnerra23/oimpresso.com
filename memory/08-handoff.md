# 08 — Handoff

> **Este é o arquivo que você lê PRIMEIRO quando retoma o trabalho.**
>
> Ele sempre reflete o estado mais recente. É sobrescrito a cada sessão.
> Para ver o que mudou ao longo do tempo, consulte `sessions/`.

---

## Estado em 2026-04-22 (sessão 10 — Setup local + React migration inicial + specs + IA stub)

### 🎯 Onde paramos

Branch `6.7-react` no repo principal `D:\oimpresso.com` rodando em `https://oimpresso.test` via **Herd + Laragon MySQL + dump Hostinger importado**. Login Wagner: user `WR23`, senha **`dev123`** (reset local — senha de produção é outra).

### ✅ Feito na sessão 10 (3 commits na branch `6.7-react`)

1. **`3c21836e` — shell**: dark mode por usuário (coluna `users.ui_theme` + anti-flash) + sidebar 2 níveis + menu dinâmico (`LegacyMenuAdapter` extrai de `Menu::instance('admin-sidebar-menu')`) + `HandleInertiaRequests` com `shell.menu` + ThemeToggle
2. **`86e37e45` — modulos**: Gerenciador React (`/modulos`) + `ModuleSpecGenerator` (`php artisan module:specs`) + **29 specs** em `memory/modulos/` + `memory/CHANGELOG.md` + limpeza (apagado `Modules/Officeimpresso1/`)
3. **`a6dca851` — ponto**: Dashboard React (6 StatCards + chart 7 dias) + Welcome + Relatórios + **Intercorrências Create com IA** (`IntercorrenciaAIClassifier` via OpenAI gpt-4o-mini JSON mode, cache 24h, mascara CPF/PIS/email pra LGPD)

**4 telas React dentro do AppShell**: `/ponto/react`, `/ponto/relatorios`, `/ponto` (Dashboard), `/ponto/intercorrencias/create`, `/modulos`.

**Fixes**: HasFactory import em 5 models, AiAssistance/Officeimpresso DataController (action() quebrada desativada), scandir `public/assets/css/color/` (pasta vazia criada), permissões `ponto.*` registradas e concedidas a `Admin#1`.

### ⚠️ Pendências imediatas — Wagner precisa fazer

1. **Ativar IA no `.env`** para testar o botão "Preencher com IA" em Intercorrências/Create:
   ```
   AI_ENABLED=true
   AI_CLASSIFICACAO_INTERCORRENCIA=true
   OPENAI_API_KEY=sk-proj-...
   ```
   Sem isso, botão fica disabled (comportamento correto).

2. **Decisões em aberto** (ver `memory/modulos/RECOMENDACOES.md`):
   - Chat module — tinha backend Z-API/Evolution ou era stub?
   - IProduction vs Grow — overlap ou complementares?
   - codecanyon-ticketing — renomeado pra Grow ou módulo diferente?
   - **Grow** — avaliar viabilidade (mini-ERP 797 rotas / 957 views CodeCanyon)

### 🛠 Próximo passo sugerido — F13.4 (Telas restantes PontoWR2)

Menor complexidade primeiro:
1. **Aprovações** (index Inertia + Dialog Aprovar/Rejeitar)
2. **Espelho** (index + show com totalizadores + chart)
3. **Banco de Horas** (index + show + form ajuste manual)
4. **Intercorrências Index + Show** (completar CRUD já iniciado)
5. **Escalas** CRUD
6. **Importações** com upload AFD + polling
7. **Colaboradores** busca + edit
8. **Configurações** + CRUD REPs

### 🚫 Não mexer sem consultar Wagner

- `modules_statuses.json` — lista decidida em 2026-04-22
- `Modules/Grow/` — prioridade declarada, avaliar antes
- Telas legadas AdminLTE funcionando — migrar 1 por vez

### 📚 Onde está tudo

- **Specs 29 módulos**: `memory/modulos/` + `INDEX.md` + `RECOMENDACOES.md`
- **Roadmap M1-M10**: `memory/07-roadmap.md`
- **Changelog**: `memory/CHANGELOG.md`
- **Prefs Wagner**: `C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\`

### 🔑 Dev local

- Site: `https://oimpresso.test` (Herd SSL)
- MySQL: Laragon `127.0.0.1:3306` root sem senha, DB `oimpresso`
- PHP: 8.4 Herd
- Composer: `--ignore-platform-req=php` por causa de mpdf/lcobucci velhos

### 🧭 Comandos úteis

```bash
cd D:\oimpresso.com
npm run build:inertia                    # Build Inertia bundle
php artisan module:specs                 # Regerar specs dos 29 módulos
php artisan optimize:clear               # Limpar caches
C:\Users\wagne\.config\herd\bin\herd.bat restart   # Restart Herd (opcache)
```

---

## Estado em 2026-04-21 (sessão 09 — upgrade stack Laravel 9.51/PHP 8.3, correção PontoWR2, git configurado)

### O que foi feito (sessão 09)

**Stack atualizada:** Eliana atualizou o servidor para UltimatePOS v6.7 (Laravel 9.51 + PHP 8.3). Toda restrição de sintaxe PHP 7.1 foi revogada.

**PontoWR2 corrigido para Laravel 9:**
- `PontoWr2ServiceProvider`: removido `Illuminate\Database\Eloquent\Factory` (classe removida no L9) e `registerFactories()`
- 5 factories convertidas para formato class-based Laravel 9
- 5 models com trait `HasFactory`
- Removidos legados: `RouteServiceProvider.php`, `Routes/`, `PontoWr2_backup/`

**Git configurado:**
- `D:\oimpresso.com` → branch `6.7-bootstrap` (GitHub `wagnerra23/oimpresso.com`)
- Branch `producao` criado com estado real do servidor (90k+ arquivos)
- ADR 0012 criada documentando o upgrade

### O que a Eliana precisa fazer no servidor

```bash
# Na outra máquina que conecta ao servidor:
ssh -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
git pull origin 6.7-bootstrap   # pega as correções do PontoWR2
php artisan module:enable PontoWr2
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

### Pendências remanescentes

- **Erro 500 em `/sells/create`** — descoberto nesta sessão, causa desconhecida. Verificar `storage/logs/laravel.log`
- **Módulo PontoWR2** ainda desativado no servidor — aguarda deploy acima
- **7 relatórios** ainda `RuntimeException` (AFD, AFDT, AEJ, HE, BH, Atrasos, eSocial)
- **Piloto runtime** com AFD real ainda não realizado

### Próximo passo sugerido

1. Ativar PontoWR2 no servidor (comandos acima)
2. Investigar erro 500 em `/sells/create`
3. Se módulo ativo → piloto runtime com 1 colaborador + AFD de teste

Ver `sessions/2026-04-21-session-09.md` para log detalhado.

---

## Estado em 2026-04-20 (sessão 08 — backend batch: hash chain, AFD parser, CLT completa, BH FIFO, PDF, jobs, testes)

### O que acabou de ser feito (sessão 08)

Eliana autorizou ("liste o que falta, e faça") implementação em lote de toda a pendência backend da sessão 07. **14 arquivos** criados/reescritos, zero sintaxe PHP 8+ / Laravel 9+, todos os services agora funcionais.

**MarcacaoService (novo, `Services/MarcacaoService.php`)** — centraliza criação de marcações com transação + lock pessimista para NSR sequencial + hash SHA-256 encadeado:

- `registrar(array $dados)` → `payloadCanonico()` → `sha256($payload)` → vincula `hash_anterior` com última marcação do REP → grava com `$self = $this` + closure (PHP 7.1 compat para `DB::transaction`)
- `anular(Marcacao, $usuarioId, $motivo)` → cria nova marcação ANULACAO ligada à original (não mexe na original, append-only Portaria 671/2021)
- `verificarIntegridade($repId)` → varre por NSR e recomputa cada hash; retorna primeira divergência
- `payloadCanonico($d)` → `business_id|colaborador_config_id|rep_id|nsr|momento|origem|tipo|hash_anterior|usuario_criador_id`

`Marcacao::anular()` agora delega para este service (removida lógica inline).

**AfdParserService (rewrite, `Services/AfdParserService.php`)** — parser da Portaria 671/2021 funcional de ponta a ponta:

- Constructor injeta `MarcacaoService` (resolve via container)
- Header (tipo 1): extrai CNPJ/CPF, identificador REP (17 chars pos 219), `Rep::firstOrCreate` por business_id + identificador
- Marcação (tipo 3): PIS → `Colaborador::where('pis', ...)` com scope de business_id; dedup por (`rep_id`, `nsr`) — reimport idempotente; tipo inferido por sequência do dia (0=ENTRADA, 1=ALMOCO_INICIO, 2=ALMOCO_FIM, 3=SAIDA)
- Retificações (tipos 4/5) reusam `parseMarcacao`
- Progresso incremental a cada 100 linhas via `Importacao->update(['linhas_processadas' => N])`
- Try/catch por linha: amostra até 20 erros em `erro_mensagem`

**ApuracaoService (rewrite completo, `Services/ApuracaoService.php`)** — regras CLT agora todas implementadas:

- `aplicarRegraTolerancia()` — Art. 58 §1º (5min/marcação, 10min/dia)
- `aplicarRegraIntrajornada()` — Art. 71 (60min mínimo se jornada > 6h; viola se insuficiente)
- `aplicarRegraInterjornada()` — Art. 66 (11h entre jornadas)
- `aplicarRegraHoraExtra()` — Art. 59 (limite 2h/dia) agora **separa HE diurna e noturna** via `dividirDiurnoNoturno(Carbon $inicio, Carbon $fim)`
- `aplicarRegraAdicionalNoturno()` — Art. 73 (22h-5h, +20%, hora reduzida 52'30")
- `aplicarRegraDsr()` — Lei 605/49 Art. 9º (repercussão HE sobre DSR)
- `aplicarIntercorrencias()` — ATESTADO/CONSULTA abonam, REUNIAO/VISITA = trabalhado, ESQUECIMENTO ajusta marcação, OUTRO gera divergência
- `dividirDiurnoNoturno()` — janelas [00:00-05:00] + [22:00-24:00] por dia, suporta cruzamento de meia-noite via `intersecaoMinutos()` genérico
- `addDivergencia($a, $chave, $msg)` padroniza o array de divergências

**BancoHorasService::expirarSaldosAntigos (completo)** — FIFO com idempotência:

- Identifica créditos anteriores ao corte via `data_referencia`
- Calcula residual = `soma_creditos_antigos - soma_debitos_historicos`
- Se residual > 0, emite `movimentar(-residual, EXPIRACAO)` com observação marcador `FIFO-YYYYMMDD`
- Idempotente: verifica existência do marcador antes de gravar — rodar várias vezes no mesmo dia é seguro

**ReportService (novo, `Services/ReportService.php`)** — PDFs via `barryvdh/laravel-dompdf`:

- `espelhoPdf(Colaborador $c, $mes)` — A4 portrait, view `pontowr2::reports.espelho-pdf`, retorna `\Barryvdh\DomPDF\PDF` (chama `stream()` ou `download()` no controller)
- `espelhoPdfNome()` — filename padrão `espelho-{matricula}-{YYYY-MM}.pdf`
- Stubs (RuntimeException): afd, afdt, aej, he, bancoHoras, atrasos, eSocial

**Jobs:**

- `Jobs/ProcessarImportacaoAfdJob.php` — `ShouldQueue`, tries=3, timeout=600, `handle(AfdParserService)`, `failed()` marca `ESTADO_FALHOU`
- `Jobs/ReapurarDiaJob.php` — aceita `colaboradorId` + `data` como string (Carbon serializa mal em propriedade pública), reidrata com `Carbon::parse($this->data)` no handle

**Factories (5 novas, formato Laravel 5.8 array-based — sem HasFactory):**

- `Database/factories/ColaboradorFactory.php`, `EscalaFactory.php`, `EscalaTurnoFactory.php`, `MarcacaoFactory.php`, `IntercorrenciaFactory.php`
- Padrão: `$factory->define(Class::class, function (Faker $faker) { return [...]; });`

**Seeders (2):**

- `DevPontoSeeder.php` — idempotente (`firstOrCreate`), pula em produção; cria escala `DEV-44H` + 5 turnos (seg-sex 08:00-17:00 com almoço 12:00-13:00) + REP-P `DEV0000000000001` + vincula até 2 users com `controla_ponto=true` + `usa_banco_horas=true`
- `PontoWr2DatabaseSeeder.php` — só chama `DevPontoSeeder`, sem referências a seeders inexistentes

**Testes Unitários (2 arquivos, 9 testes):**

- `Tests/Unit/MarcacaoServiceTest.php` — determinismo do payload canônico; hash muda quando campo relevante muda
- `Tests/Unit/ApuracaoServiceTest.php` — 7 casos: tolerância dentro/além, intrajornada insuficiente, jornada curta sem intrajornada, HE com split diurno/noturno, `dividirDiurnoNoturno` em 3 cenários (madrugada inteira, cruza 22h, inteiramente diurno)

**Service Provider:** `PontoWr2ServiceProvider::register()` agora registra `MarcacaoService` e `ReportService` como singletons.

**EspelhoController::imprimir** — removido `abort(501)`, agora chama `$this->reports->espelhoPdf($colab, $mes)->stream($nome)`.

### Diagnóstico do `laravel-2026-04-20.log`

Eliana anexou log de produção com 48 eventos. **Nenhum é do módulo PontoWr2.** Três problemas distintos de infra Hostinger:

1. **Cache directory missing** (10:24:04, único com stack trace) — `storage/framework/cache/data/XX/YY/` não existe; `ThrottleRequests` middleware falha ao gravar rate-limiter. Fix no servidor: `mkdir -p storage/framework/cache/data && chmod -R 775 storage/framework` OU mudar `CACHE_DRIVER=redis` (ou `array`) no `.env`.
2. **WooCommerce HttpClient JSON errors** (01:00h e 13:00h, crons horárias) — plugin WooCommerce em outro domínio/site no mesmo host batendo em API que devolve não-JSON. Fora do escopo PontoWr2.
3. **"Session store not set on request"** (01:00h e 13:00h) — clássico de job fora de contexto HTTP chamando `session()`. Parece ser o mesmo cron WooCommerce. Também não é do PontoWr2.

### O que a Eliana precisa fazer no servidor

```bash
cd /home/u906587222/domains/oimpresso.com/public_html
git pull
mkdir -p storage/framework/cache/data
chmod -R 775 storage/framework
php artisan view:clear
php artisan cache:clear
composer dump-autoload
# opcional: rodar seeder de dev pra ter 1 escala + 1 REP + 2 colaboradores
php artisan module:seed PontoWr2
```

Depois:

1. Subir um AFD de teste em `/ponto/importacoes/create` → job enfileira → worker do Horizon/queue processa
2. Rodar `php artisan queue:work` (ou confirmar que o supervisor já está rodando)
3. Conferir `ponto_marcacoes` — hash SHA-256 deve estar preenchido e encadeado
4. Abrir um espelho mensal → clicar "Imprimir PDF" → DomPDF renderiza

### Pendências remanescentes

**Ação manual Eliana (sandbox não permite rm):**

- `rm -f Modules/PontoWr2/Providers/RouteServiceProvider.php` (esvaziado)
- `rm -rf Modules/PontoWr2/Resources/lang/pt-BR/` (delegate)

**Fora da fase 1/2 (próximos ciclos):**

- Integração eSocial S-1010/S-2230/S-2240 real (stubs existem)
- Certificado ICP-Brasil para assinatura PKCS#7 do AFD
- App REP-P mobile (React Native, fora deste repo)
- RBAC granular por papel
- Homologação Mintrab/INSS
- Relatórios 7/8 (só espelho funcional hoje; AFD/AFDT/AEJ/HE/BH/atrasos/eSocial são `RuntimeException`)

### Próximo passo sugerido

**Piloto runtime com 1 colaborador real + AFD de teste.** Ciclo: upload AFD → `php artisan queue:work` → verificar `ponto_marcacoes` populada → rodar apuração manual (`$service->apurar($colab, $data)`) → checar `apuracao_dia` com HE/atraso/intrajornada → imprimir espelho PDF. Se tudo fechar, fase 2 CLT está efetivamente pronta para staging.

Alternativa: escrever os 7 relatórios remanescentes (AFD.txt, AFDT.txt, AEJ.txt, HE, Banco de Horas, Atrasos, eSocial) — todos são views simples + loop de dados já existentes.

Ver `sessions/2026-04-20-session-08.md` para log detalhado.

---

## Estado em 2026-04-19 (sessão 07 — batch final: 7 telas Tailwind → AdminLTE; Fase 1 de UI concluída)

### O que acabou de ser feito (sessão 07)

Eliana autorizou ("pode fazer todos") conversão em lote das 7 telas restantes. **12 arquivos** criados/reescritos em um único push, mantendo o mesmo vocabulário visual do Dashboard/Aprovações/Espelho (content-header → filtros `components.filters` → widget `components.widget` → paginação server-side).

**Intercorrências** (4 arquivos + 1 form):

- `_form.blade.php` (partial compartilhada create+edit) com select de colaborador via query inline (`controla_ponto=true` + `whereNull('desligamento')`), select de tipo via `__('pontowr2::ponto.intercorrencia.tipos')`, "dia todo" via checkbox com hidden-0 pattern, justificativa 10-2000 chars, flags `impacta_apuracao` e `descontar_banco_horas`, input file anexo (PDF/JPG/PNG).
- `index.blade.php` reescrito — tabela com botões condicionais (Editar+Submeter só se RASCUNHO; Submeter com `confirm()` inline).
- `create.blade.php` / `edit.blade.php` — envelopes com `@include` do form.
- `show.blade.php` — 8+4: dados + rastreio (solicitante/aprovador/motivo rejeição); ações por estado (Editar+Submeter se RASCUNHO; Cancelar se RASCUNHO ou PENDENTE).

**Banco de Horas** (2 arquivos):

- `index.blade.php` — 4 `small-box` (crédito/débito total + #colab com crédito/débito) + tabela com cor verde/vermelho pelo sinal. `$fmtMin` usa prefixo `−` em vez de sinal negativo padrão.
- `show.blade.php` — small-box dinamicamente colorida pelo sinal + form inline de ajuste manual (minutos ± + observação obrigatória) + tabela paginada de histórico. Rodapé legal sobre append-only.

**Escalas** (3 arquivos + 1 form):

- `_form.blade.php` — nome/código/tipo (5 tipos validados), `carga_diaria_minutos` 60–600, `carga_semanal_minutos` 0–3600, flag `permite_banco_horas`. Se edit e há turnos, mostra tabela read-only com placeholder.
- `index.blade.php`, `create.blade.php`, `edit.blade.php` — padrão.

**Importações AFD** (3 arquivos):

- `index.blade.php` — tabela com closure `$fmtBytes` para KB/MB/GB + botões Ver e Baixar original.
- `create.blade.php` — select AFD/AFDT, input file obrigatório, callout sobre dedup SHA-256 e `ProcessarImportacaoAfdJob`.
- `show.blade.php` — 2 colunas: metadados do arquivo + resumo do processamento (linhas processadas/criadas/ignoradas/erro + `erro_mensagem` em alert-danger).

**Relatórios** (1 arquivo):

- `index.blade.php` — loop sobre 8 relatórios estáticos do controller em grid de `info-box` com cor/ícone mapeados por chave. Botão "Gerar" → `ponto.relatorios.gerar` (501 hoje).

**Colaboradores** (2 arquivos):

- `index.blade.php` — busca única `q` sobre nome/matrícula/CPF (controller já monta where-with-whenhas). Callout explicando que nome/e-mail vêm do HRM.
- `edit.blade.php` — 4+8: dados HRM read-only + form de configuração de ponto (escala via query inline, matrícula/CPF/PIS/admissão/desligamento + flags com hidden-0).

**Configurações** (2 arquivos):

- `index.blade.php` — 4 widgets read-only sobre `config('pontowr2')`: CLT (box-primary, 9 tolerâncias com art. citados), Banco de Horas (box-success), REP + imutabilidade (box-info), AFD + eSocial (box-warning).
- `reps.blade.php` — 5+7: form de cadastro (tipo ∈ {REP_P, REP_C, REP_A}, identificador size:17, descrição) + tabela paginada.

**Verificação:** 2 greps em `Modules/PontoWr2/Resources/views/` retornaram 0 matches para `\?->|\bfn\s*\(|\bmatch\s*\(|HasFactory|HasUuids|::factory\(\)` e para typed properties / CPP.

### Status geral da Fase 1 (UI AdminLTE)

**10 telas administrativas convertidas** — Dashboard, Aprovações (index+tabela), Espelho (index+show), Intercorrências (CRUD completo com form partial + show), Banco de Horas (index+show), Escalas (CRUD com form partial), Importações (index+create+show), Relatórios (index), Colaboradores (index+edit), Configurações (index+reps).

**0 telas restantes em Tailwind.** Fase 1 estruturalmente fechada. Sobra apenas a tela de Login de REP-P que é mobile (React Native) e fora do escopo de Blade.

### O que a Eliana precisa fazer no servidor

```bash
git pull
php artisan view:clear
php artisan cache:clear
composer dump-autoload
```

**Tour sugerido** (ordem de valor imediato):

1. `/ponto/colaboradores` → marcar ≥1 colaborador com `controla_ponto` ON + vincular escala
2. `/ponto/escalas` → criar 1 escala "44h semanais, 8h diárias"
3. `/ponto/intercorrencias/create` → criar rascunho → submeter
4. `/ponto/aprovacoes` → aprovar ou rejeitar o item submetido
5. `/ponto/banco-horas` → ver totalizadores; abrir colaborador → registrar ajuste manual
6. `/ponto/importacoes/create` → upload de AFD de teste (enfileira job que ainda é stub)
7. `/ponto/relatorios` → cards aparecem; "Gerar" retorna 501 (esperado)
8. `/ponto/configuracoes` → inspecionar parâmetros CLT carregados do `config.php`

Ver `sessions/2026-04-19-session-07.md` para log detalhado.

### Pendências remanescentes (não tocadas nesta sessão)

**Backend CLT com `// TODO`:**

- `ApuracaoService` — adicional noturno, DSR, aplicação de intercorrências
- `BancoHorasService::expirarSaldosAntigos` — algoritmo FIFO
- `AfdParserService` — parsers dos tipos 1–9 Portaria 671/2021
- `Marcacao::create` — hash encadeado ainda é placeholder
- `ReportService::espelhoPdf` e demais geradores — não existem
- Jobs `ProcessarImportacaoAfdJob`, `ReapurarDiaJob` — referenciados mas não criados

**Infra ausente:**

- Factories, seeders, unit tests CLT (Art. 58, 66, 71, 59)
- Remoção física de `Providers/RouteServiceProvider.php` (esvaziado) e `Resources/lang/pt-BR/` (delegate)

### Próximo passo sugerido

Trocar o foco de **UI → backend**: implementar `ApuracaoService` (regras CLT de tolerância, intrajornada, interjornada, HE) para que o fluxo Intercorrência → Aprovação → Aplicação realmente altere a apuração diária. Testes unitários das regras (Art. 58, 66, 71, 59 CLT) deveriam vir junto.

Alternativa: rodar o piloto em runtime primeiro com 1 colaborador real e só depois escolher o próximo gargalo.

---

## Estado em 2026-04-19 (sessão 06 — Aprovações + Espelho de Ponto convertidos para AdminLTE)

### O que foi feito (sessão 06)

**Aprovações (lista completa):**

- **`Resources/views/aprovacoes/index.blade.php`** — criada do zero com `content-header`, bloco de filtros (`@component('components.filters')`) para Estado e Tipo (selects alimentados via `__('pontowr2::ponto.intercorrencia.estados/tipos')`), widget principal (`@component('components.widget', ['class' => 'box-primary'])`) e paginação server-side que preserva filtros via `appends(request()->query())`.
- **`Resources/views/aprovacoes/_tabela.blade.php`** — acrescentada coluna **Estado** com labels coloridos, botões Aprovar/Rejeitar só renderizam quando `$a->estado === 'PENDENTE'`, mensagem "empty" genérica, captura do motivo de rejeição via `prompt()` inline.

**Espelho de Ponto:**

- **`Resources/views/espelho/index.blade.php`** — reescrita a partir do stub Tailwind. Filtro `type="month"` dentro de `components.filters`, widget com tabela de colaboradores ativos com `controla_ponto = true`, ação "Ver espelho" que propaga `mes` via querystring, paginação 25/página.
- **`Resources/views/espelho/show.blade.php`** — criada do zero. Navegação (voltar + mês anterior/próximo com rollover + imprimir PDF), cabeçalho do colaborador (3 colunas), 6 totalizadores em `small-box` (Trabalhado/Atraso/Falta/HE/BH+/BH−), alerta de divergências, tabela dia-a-dia com `bg-warning` em DIVERGENCIA, chips de marcações por dia, abreviação do dia da semana via array PT indexado por `format('w')` (substituindo `strftime()` que é deprecated).

Nenhum controller/rota mudou. Grep por sintaxe PHP 8+ em ambas as pastas → 0 matches.

Ver `sessions/2026-04-19-session-06.md` para log detalhado.

---

### Estado em 2026-04-19 (sessão 05 — emergência 500 no Dashboard, sintaxe PHP 8/Laravel 9 removida)

### O que foi feito na sessão 05

Eliana reportou **500 no Dashboard** após deploy da sessão 04. Log apontou `HasFactory not found` e `T_PROTECTED syntax error` (Constructor Property Promotion). Causa raiz: `CLAUDE.md` dizia "Laravel 10 / PHP 8.1" mas a instância real é **Laravel 5.8.38 + PHP ^7.1.3**.

Varri `Modules/PontoWr2/` por sintaxe incompatível e corrigi tudo:

- **5 models** — removido `HasFactory`/`HasUuids`, adicionado `boot()` com `Str::uuid()` manual + `$incrementing=false`/`$keyType='string'` (`Colaborador`, `BancoHorasMovimento`, `Intercorrencia`, `Marcacao`, `Rep`)
- **7 controllers** — Constructor Property Promotion expandida, argumentos nomeados → posicionais, arrow fns → closures (`Aprovacao`, `BancoHoras`, `Importacao`, `Intercorrencia`, `Espelho`, `Colaborador`, `Dashboard`)
- **3 services** — CPP expandida em `ApuracaoService`, typed property removida em `AfdParserService`, argumentos nomeados convertidos em `BancoHorasService`
- **1 request** — null-safe `?->` em `ImportacaoAfdRequest` substituído por ternário
- **Intercorrencia.php** — null-safe na linha 83 (`$this->colaborador?->escalaAtual?->...`) convertido para `optional(optional(...))->...`
- **CLAUDE.md + memory/02-technical-stack.md** — stack real atualizada, lista explícita de sintaxe PHP 7.1 e Laravel 5.8 proibida

**Verificação:** 3 greps (`\?->|\bfn\s*\(|CPP`, `HasFactory|HasUuids`, argumentos nomeados) retornam zero matches em `Modules/PontoWr2/`.

### O que a Eliana precisa fazer agora no servidor

```bash
cd /path/to/oimpresso.com
# git pull (subir as mudanças)
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
```

Depois recarregar `/ponto` — Dashboard deve abrir sem 500.

Ver `sessions/2026-04-19-session-05.md` para log detalhado.

### Estado anterior (sessão 04 — conversão Tailwind → AdminLTE, passos 1 e 2)

Layout do módulo e Dashboard convertidos de **Tailwind** (que não renderiza no UltimatePOS) para **AdminLTE 2.x + Bootstrap 3** puro, seguindo o padrão de `Modules/Repair`.

Arquivos alterados:

- `Resources/views/layouts/module.blade.php` — navbar horizontal com 10 abas, ícones FA5, estado ativo via `segment(2)`, badge Bootstrap para aprovações
- `Resources/views/dashboard/index.blade.php` — 6 KPIs em `small-box` coloridos (aqua/green/yellow/red/purple/maroon) + fila de aprovações em `@component('components.widget')` + atividade recente em callouts
- `Resources/views/aprovacoes/_tabela.blade.php` — partial convertido para `table table-striped` AdminLTE com botões Aprovar/Rejeitar

As outras 9 views (Espelho, Aprovações/index, Intercorrências, Banco de Horas, Escalas, Importações, Relatórios, Colaboradores, Configurações) **continuam em Tailwind** — serão convertidas uma por vez após validação do Dashboard em runtime.

Ver `sessions/2026-04-19-session-04.md` para o log detalhado.

### Estado da sessão 03 (menu no AdminLTE)

### O que acabou de ser feito (sessão 03)

Após o `module:migrate PontoWr2` rodar com sucesso e o módulo aparecer como instalado no painel **Manage Modules**, o sintoma seguinte foi: o item **não apareceu na sidebar do AdminLTE**.

**Causa raiz identificada:** faltava `Modules/PontoWr2/Http/Controllers/DataController.php`. O core UltimatePOS usa a convenção `Modules\<Nome>\Http\Controllers\DataController@modifyAdminMenu` (descoberta via middleware `AdminSidebarMenu`) para que cada módulo injete seus itens na sidebar. Sem esse arquivo, o módulo é pulado.

**Correções aplicadas nesta sessão:**

1. Criado `Modules/PontoWr2/Http/Controllers/DataController.php` com:
   - `modifyAdminMenu()` → dropdown "Ponto WR2" (ícone `fa fas fa-business-time`, ordem 25) com 10 sub-itens (Dashboard, Espelho, Aprovações, Intercorrências, Banco de Horas, Escalas, Importações, Relatórios, Colaboradores, Configurações). Sub-itens respeitam permissões específicas (ex.: Aprovações só para `ponto.aprovacoes.manage` ou superadmin).
   - `superadmin_package()` → registra o pacote `ponto_module` no painel Superadmin (feature flag por subscription).
   - `user_permissions()` → registra 5 permissões no cadastro de Roles (`ponto.access`, `ponto.colaboradores.manage`, `ponto.aprovacoes.manage`, `ponto.relatorios.view`, `ponto.configuracoes.manage`).
2. Adicionadas chaves de rótulo de permissão em `Resources/lang/pt/ponto.php` (`permissao_acesso`, `permissao_colaboradores`, `permissao_aprovacoes`, `permissao_relatorios`, `permissao_configuracoes`).
3. Criado `memory/09-modulos-ultimatepos.md` com inventário completo dos módulos UltimatePOS da instância WR2 e documentação do padrão de sidebar.
4. `memory/INDEX.md` atualizado para referenciar o novo documento.

Ver `sessions/2026-04-19-session-03.md` para o log detalhado.

### Estado do refactor anterior (2026-04-18)

Refactor crítico do `Modules/PontoWr2/` para alinhar com o padrão `Modules/Jana/` (referência canônica do UltimatePOS). Motivação: o scaffold da sessão 01 foi subido para o servidor de produção `oimpresso.com` e causou crash em loop por usar convenções incompatíveis com a versão antiga do nWidart/laravel-modules embutida no UltimatePOS. Veja `sessions/2026-04-18-session-02.md` e ADR 0011.

Mudanças concretas do refactor (preservadas aqui para histórico):

**Refactor crítico do `Modules/PontoWr2/` para alinhar com o padrão `Modules/Jana/`** (referência canônica do UltimatePOS). Motivação: o scaffold da sessão 01 foi subido para o servidor de produção `oimpresso.com` e causou crash em loop por usar convenções incompatíveis com a versão antiga do nWidart/laravel-modules embutida no UltimatePOS. Veja `sessions/2026-04-18-session-02.md` e ADR 0011.

Mudanças concretas:

1. Criado `start.php` na raiz do módulo
2. Criado `Http/routes.php` unificando rotas web + API + install em `Route::group` estilo Jana
3. Refatorado `PontoWr2ServiceProvider` espelhando `JanaServiceProvider`
4. Reescrito `module.json` com `"files": ["start.php"]`, sem `priority`/`version`/`requires`
5. Neutralizado `Providers/RouteServiceProvider.php` (arquivo esvaziado — aguarda remoção física)
6. Removida pasta `Routes/`
7. Criado `Resources/lang/pt/ponto.php`; `pt-BR/` virou delegate `require` (aguarda remoção física)
8. ADR 0011 criada; CLAUDE.md, 05-preferences.md atualizados; log `2026-04-18-session-02.md` criado
9. **Correção seguinte**: criado `Http/Controllers/InstallController.php` (faltava — a rota `/ponto/install` apontava pra uma classe que não existia); adicionados `module_version` e `pid` em `Config/config.php`. Ver addendum no session log 02.
10. **Terceira correção**: convertidas 8 migrations de anonymous class (`return new class extends Migration`) para classe nomeada (`class CreatePontoXxxTable extends Migration`). Ver addendum 2 no session log 02.
11. **Quarta correção**: trocados métodos Blueprint modernos (Laravel 8+) pela API clássica nas 8 migrations — `id()` → `increments('id')`, `unsignedBigInteger` → `integer()->unsigned()`, `foreignId()->constrained()` → par explícito, `cascadeOnDelete()` → `onDelete('cascade')`, `nullOnDelete()` → `onDelete('set null')`, `uuid()` → `char(36)`. Ver addendum 3 no session log 02.
12. **Quinta correção**: removida FK self-reference `ponto_marcacoes.marcacao_anulada_id → ponto_marcacoes.id` (MySQL errno 150 em char(36)). Substituída por índice simples + validação em app layer via `MarcacaoService::anular()`. Ver addendum 4.
13. **Sexta correção (2026-04-19)**: nome explícito no índice composto `ponto_banco_horas_movimentos (colaborador_config_id, data_referencia)` — o auto-gerado tinha 72 chars e estourava o limite de 64 chars do MySQL. Trocado para `ponto_bh_mov_colab_data_idx` (27 chars). Verificação defensiva nos 8 migrations: maior identificador remanescente tem 63 chars (passa por folga). Ver addendum 6.

### Estado de recuperação (concluído)

Eliana executou o reset total (opção B) — todas as tabelas `ponto_*` e entradas em `migrations` foram dropadas. Após isso, `php artisan module:migrate PontoWr2` rodou limpo e as 8 tabelas foram criadas. O módulo aparece em **Manage Modules** como **PontoWr2 v0.1** com botão "Uninstall" (= instalado).

### O que está funcionando (em arquivos, não testado em runtime ainda)

- Estrutura do módulo alinhada com Jana
- Layout Blade `Resources/views/layouts/module.blade.php` usa nomes de rota `ponto.*` que batem com `Http/routes.php`
- Middlewares do UltimatePOS: `web, SetSessionData, auth, language, timezone, AdminSidebarMenu, CheckUserLogin, ponto.access`
- API com `auth:api` (Passport) — compatível com UltimatePOS
- `ponto.access` middleware aliasado via array `$middleware` no provider, mesmo padrão Jana

### O que AINDA NÃO funciona / stub (inalterado desde sessão 01)

- **AfdParserService** — parseMarcacao/parseHeader só com comentário de layout
- **ApuracaoService** — regras de adicional noturno e intercorrências com `// TODO`
- **BancoHorasService::expirarSaldosAntigos** — placeholder
- **Jobs** `ProcessarImportacaoAfdJob`, `ReapurarDiaJob` — referenciados mas não criados
- **Factories** — não criadas
- **Views** — apenas dashboard + partial de aprovações têm conteúdo

### Arquivos com remoção física pendente (a fazer manualmente no repositório/servidor)

O ambiente Cowork não permitiu `rm` nesses dois. Ficaram neutralizados mas não atrapalham o boot. Execute quando possível:

```bash
rm -f Modules/PontoWr2/Providers/RouteServiceProvider.php
rm -rf Modules/PontoWr2/Resources/lang/pt-BR
```

### Próximo passo concreto sugerido

**Deploy do `DataController.php` e reload de caches.** No servidor:

```bash
# após o git pull / upload do DataController.php + ponto.php atualizado
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
```

Depois recarregar qualquer tela do UltimatePOS. O dropdown **"Ponto WR2"** deve aparecer na sidebar com os 10 sub-itens.

Se funcionar → Fase 1 fecha. Próximos candidatos (escolher conforme prioridade Eliana):

- **Fase 2** — regras CLT completas (`ApuracaoService`, intercorrências, adicional noturno — tudo com `// TODO` hoje).
- **Testes** — piloto em ambiente real com 1-2 colaboradores.
- **AfdParserService** — parser do layout Portaria 671/2021 (stub atualmente).
- **Views faltantes** — hoje só Dashboard + partial de Aprovações têm conteúdo.

### Decisões pendentes aguardando cliente

- **Middleware `ponto.access`** — posso usar alguma permission existente do UltimatePOS/Essentials em vez de criar um middleware próprio? Decidir depois do primeiro deploy
- **Tema visual real**: AdminLTE nativo ou Tailwind? Decisão de médio prazo (Fase 5+)
- **Certificado ICP-Brasil**: onde armazenar? (local, S3, HSM?)
- **eSocial ambiente**: homologação primeiro ou produção direta?
- **Cliente piloto**: quem testa primeiro? Afeta Fase 11

### Bloqueios

Nenhum bloqueio técnico. O crash de produção foi revertido (site voltou após desativar o módulo).

### Arquivos de referência

- Documentos de design (fora do repo, em outputs do Cowork): `projeto_ponto_eletronico_wr2.md`, `especificacao_tecnica_laravel_wr2.md`, `ultimatepos6_hrm_especificacao_e_adaptacao.md`, `design_projeto_ponto_wr2.md`
- Protótipo visual: `dashboard_gestor_preview.html`
- **Referência canônica viva:** `Modules/Jana/` — sempre olhar antes de criar ou mudar estrutura

### Se você está retomando agora, faça isto

1. Leia este arquivo até aqui ✓
2. Leia `sessions/2026-04-18-session-02.md` para o contexto do refactor
3. Leia ADR 0011 em `decisions/0011-alinhamento-padrao-jana.md`
4. Abra `Modules/Jana/` em paralelo com `Modules/PontoWr2/` — use o primeiro como gabarito
5. Pergunte à Eliana se já testou o módulo refatorado em staging
6. **Atualize este arquivo** quando concluir a próxima sessão

---

**Última atualização:** 2026-04-21 (sessão 09 — upgrade L9.51/PHP 8.3, PontoWR2 corrigido, git configurado, ADR 0012)
**Próxima sessão esperada:** após Eliana rodar `git pull + view:clear + cache:clear + composer dump-autoload` e corrigir o cache directory do Hostinger. Próximo foco sugerido: **piloto runtime** com AFD real + 1 colaborador, OU implementar os 7 relatórios remanescentes (AFD/AFDT/AEJ/HE/BH/atrasos/eSocial).
**Estado geral:** 🟢 Fase 1 (UI AdminLTE) completa — 10/10 telas; 🟢 Fase 2 (backend CLT) completa — MarcacaoService com hash chain, AfdParserService full, ApuracaoService com 5 regras CLT + DSR, BancoHoras FIFO, ReportService (espelho PDF funcional), 2 Jobs, 5 factories, 2 seeders, 9 testes unitários; 🟡 7 relatórios ainda `RuntimeException`; 🟡 integração eSocial / ICP-Brasil / app REP-P mobile pendentes (fases 3+); remoção física de RouteServiceProvider + pt-BR/ pendente manual no servidor.
