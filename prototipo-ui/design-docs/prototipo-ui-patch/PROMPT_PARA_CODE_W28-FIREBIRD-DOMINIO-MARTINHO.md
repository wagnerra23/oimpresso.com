# PROMPT_PARA_CODE — W28 importador Firebird + reconciliar domínio "Caçamba"→mecânica de caminhão (Martinho biz=164)

> **Origem:** re-sync [CC] 2026-06-03 (report `rep-martinho` no `metricas.html`). O cutover fiscal já está com [W] (`PROMPT_PARA_CODE_LIGAR-FISCAL-REAL-MARTINHO` → #2147 feito). **O que falta e é do [CL]** é fechar o que o próprio importador marca como pendente + curar um drift de domínio que peguei lendo o `main`.
> **Natureza:** §10.4 PROPOSTA — [CL] valida contra `origin/main` fresco e age sozinho no que for mergeable; **não** flipa fiscal, **não** numera ADR, **não** mergeia Tier 0.

## Passo 0 — verificar vs `origin/main` (não assumir)
1. `Modules/OficinaAuto/Console/Commands/ImportFirebirdMartinhoCommand.php` diz no docblock: *"Status: ESQUELETO W27 — script python de export + mapping fino entram em W28."* Confirmar que **W28 ainda não foi feito** (grep por `export-martinho-os.py` em `scripts/`; checar se o mapping fino já existe). Se já existir, **não duplicar** — só reportar.
2. Confirmar a correção de domínio: **Martinho = oficina de mecânica PESADA de caminhão**, não locação de caçamba (`ProducaoOficina`/`cacamba_*` = vertical legado, **ADR 0194**). O FSM novo `oficina_mecanica_os` já reflete isso; o importador **ainda não**.

## Achado que ancora (lido @main 06-03)
O importador hardcoda `'vehicle_type' => 'cacamba'` e se intitula *"Firebird Martinho Caçambas"*; o `CHANGELOG.md` + o journey E2E do módulo ainda citam *"cliente piloto Martinho Caçambas"*. São docs/defaults **anteriores** à virada de domínio (ADR 0194). Rodar o import como está etiqueta os **caminhões** da Martinho como caçamba.

## Tarefa A — fechar W28 (mapping fino) + reconciliar domínio
1. **`vehicle_type` correto:** trocar o default `'cacamba'` por um tipo de **veículo pesado de oficina** (alinhar ao enum/whitelist real do `Vehicle` — checar `add_cacamba_fields`/migrations; usar o valor canônico de caminhão, não inventar). Se o JSON do Firebird trouxer o tipo, mapear dele; senão default = caminhão, não caçamba.
2. **Mapping fino dos campos** ORDEM_SERVICO + ORDEM_ITENS → `ServiceOrder` + `ServiceOrderItem` (datas, status legacy→FSM, km/odômetro via `data_get` como já está, valor/qtd dos itens). Idempotência por `FB_LEGACY_ID` permanece.
3. **Script Python de export** `scripts/firebird/export-martinho-os.py` (Windows + firebird-driver) que dumpa o JSON normalizado no shape que o comando consome (`{ ordens: [{ ordem_id, placa, veiculo_id, order_type, status, entered_at, completed_at, km, notes, itens:[...] }] }`).
4. **Curar os docs do módulo:** `CHANGELOG.md` + README/journey — "Martinho Caçambas" → "Martinho · mecânica pesada de caminhão" com nota-lápide apontando ADR 0194 (append, não reescrever história silenciosamente — trilha-do-tempo L-22).
5. **Segurança:** manter `--dry-run` como caminho padrão de validação; o commit real só com diff aprovado. Rodar `oficina:migration-report {biz} --detail` + `oficina:sanity-check` depois, no fixture, pra provar (vendas órfãs / OS sem NFe / pendentes).

## Tarefa B — (OPCIONAL, se passar no seu filtro de ROI) preflight de cutover observável
Graduar a checklist de 5 passos do [W] (cert A1 · regime/CRT · série/numeração · município ISS · ambiente) num comando `oficina:fiscal-cutover-preflight {biz}` que **lê e reporta** o que já está presente/faltando **antes** do flip irreversível — "interceptar a ação", não depender da memória do Wagner. **Não flipa nada**; só diagnostica. Se achar over-scope, pula e reporta.

## Guards / Tier 0 (não cruzar)
- **NÃO** flipar `business.ambiente`/`nfse_provider_configs.ambiente` (irreversível = [W]).
- **NÃO** numerar ADR (soberania [W], ADR 0238) — propostas Jana ledger/advisor seguem slug-only até [W].
- **NÃO** rodar import real contra dado de prod biz=164 — fixture/dry-run; dado real é staging/prod do [W].
- **NÃO** mergeia Tier 0; abre PR e reporta em `CODE_NOTES.md` com o que ficou de [W].

## §10.4 / autorização
Tarefa A é mergeable autônomo (esqueleto → completo + docs, reversível, testável por fixture). Tarefa B é opcional. Retorno em `CODE_NOTES.md` com o checklist do que falta de [W] + os números do `migration-report` no fixture.

## new_design_memories
- tipo: anti-padrao · ref: importador Firebird Martinho · resumo: default `vehicle_type='cacamba'` + docs "Caçambas" são pré-ADR-0194 (domínio corrigido p/ mecânica pesada de caminhão); rodar como está etiqueta os caminhões errado — reconciliar no W28.
- tipo: golden · ref: cutover irreversível observável · resumo: graduar checklist humana de cutover fiscal num preflight que lê/reporta presença de cert+regime+ambiente antes do flip = "interceptar a ação" em vez de confiar na memória do [W].
