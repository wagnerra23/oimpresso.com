# Modules/ComunicacaoVisual

> Vertical gráfica rápida BR — CNAE 1813-0/01 (impressão de material para uso publicitário).
> Status: 🟡 em construção. A previsão de piloto em Q3/2026 passou sem piloto registrado; a nova data é decisão [W].
> ADR mãe: [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7.

## 1. Objetivo

ERP especializado para **gráficas rápidas e ateliês de comunicação visual** (lona, fachada, plotter, banner, adesivo, fachada ACM). O ciclo que o código cobre hoje:

```
Orçamento (cálculo m², salvar, arquivos)
   └─ Aceite do cliente pelo link (ou aprovação interna)
       └─ OS sem redigitar (comvis_os)
           └─ Etapas no quadro do PCP (Modules/Producao, roteiro padrão da empresa)
               └─ Apontamento por etapa (m² impresso, horas)
                   └─ Instalação agendada + aviso ao cliente
                       └─ Pós-cálculo: custo orçado × real
```

**Faturamento não faz parte do ciclo:** nenhum código liga a OS da gráfica a venda, NF-e ou NFS-e. É lacuna declarada, e se entra no roadmap é decisão [W]. O fluxo passo a passo, com os serviços e os casos de cada etapa, está no [SDD §5.5](SDD-tela-orcamento-m2-v1.0.md).

## 2. Arquitetura

```
Modules/ComunicacaoVisual/
├── module.json · composer.json
├── Config/
│   ├── config.php           ← config geral do módulo
│   └── retention.php        ← LGPD Art. 16 janelas de retenção
├── Console/Commands/        ← comvis:health, comvis:demo-seed
├── Entities/                ← Eloquent Models, todas com escopo de business_id
├── Events/                  ← InstalacaoAgendada (ouvido pelo Modules/Whatsapp)
├── Http/
│   ├── Controllers/         ← Orcamento, OrcamentoArquivo, OrcamentoAceite, Public/OrcamentoAceitePublico,
│   │                          RoteiroPadrao, Agenda, Instalacao, OsPosCalculo, Apontamento, AppOrcamentoRapido
│   └── Requests/            ← FormRequests com mensagens PT-BR
├── Services/                ← OrcamentoCalculator, OrcamentoGravador, MargemDoOrcamento, OrcamentoViraOs,
│                              AceiteDoOrcamento, RoteiroPadraoDaOs, AgendaInstalacao, PosCalculoDaOs, ApontamentoTracker
├── Database/Migrations/     ← comvis_* (orçamento, OS, aceite) + cv_* (acabamentos, instalações e schema sem fluxo)
├── Database/Seeders/        ← MaterialSeeder, RepairSettingsSeeder
├── Routes/web.php           ← /comunicacao-visual, /comunicacao-visual/api/*, /orcamento-cv/{token}
├── Resources/lang/          ← PT-BR strings
└── Tests/Feature/           ← Pest
```

Fora do módulo: telas em `resources/js/Pages/ComunicacaoVisual/` (Index, Agenda, AceitePublico), o seeder FSM em `database/seeders/FsmProcessoComunicacaoVisualSeeder.php`, e BRIEFING, SCOPE, SPEC e CHANGELOG nesta pasta (`memory/requisitos/ComunicacaoVisual/`). Inventário completo em [SUPERFICIE.md](SUPERFICIE.md).

## 3. Como o cliente usa (jornada típica)

**Persona:** Larissa-equivalente — dona/operadora de gráfica pequena (1-5 funcionários). Hipótese de mercado: não há registro de uso em produção.

### 3.1 Atender pedido novo
1. Cliente liga: "Preciso de 2 lonas 3m × 1,5m com bastões"
2. Vendedora abre `/comunicacao-visual`, escolhe o material no catálogo e digita as medidas
3. "Conferir no servidor" devolve área, mínimo por peça, perda, emenda e acabamentos; o total oficial é o do servidor
4. Salva o orçamento e anexa a arte
5. "Enviar no WhatsApp" gera o link de aceite e abre o WhatsApp da própria vendedora com a mensagem pronta

### 3.2 Aprovação → produção
6. Cliente abre `/orcamento-cv/{token}` no celular e aceita (ou pede ajuste, que volta o orçamento a rascunho)
7. O sistema cria a OS com as peças, os arquivos e o total do orçamento, sem recalcular
8. Com roteiro padrão definido, a OS entra no quadro `/producao/quadro` do Modules/Producao
9. O operador aponta cada etapa e informa o m² impresso; o pós-cálculo compara com o orçado e mostra o **drift** de m² e a margem real (JSON, flag `COMVIS_POS_CALCULO_ENABLED`)

### 3.3 Faturamento + entrega
10. **Faturamento:** não existe. Nem venda, nem NF-e da peça, nem NFS-e da instalação (US-COMVIS-008/009 pendentes)
11. **Entrega e instalação:** `/comunicacao-visual/agenda` agenda equipe e veículo na OS, bloqueia conflito de janela e avisa o cliente por WhatsApp com OS, dia e hora

### 3.4 Visibilidade Wagner / Dono
- Hoje: lista de orçamentos salvos no hub e o pós-cálculo da OS em JSON
- Planejado (SPEC US-COMVIS-014): dashboard ROI com m² produzido × drift × margem e perguntas à Jana. Não construído

## 4. Multi-tenant Tier 0 (IRREVOGÁVEL — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

- Toda Entity tem escopo de `business_id`
- Fora de sessão (fila, token do app, link público) o escopo não filtra; os serviços que rodam ali (`OrcamentoGravador`, `OrcamentoViraOs`, `AceiteDoOrcamento`) passam `business_id` explícito
- Na página pública a empresa vem do token (só o hash fica no banco), nunca da URL
- Cliente A NÃO enxerga dado de cliente B mesmo com bug de query

## 5. LGPD (Art. 16 + 18)

- `Config/retention.php` define janelas: Apontamento/Orcamento/Os = 5 anos (CCom Art. 195)
- Entities core (`Orcamento`, `Os`, `Apontamento`) com `LogsActivity` (Spatie)
- Direito ao esquecimento: anonimiza `observacoes` + preserva ids fiscais
- Superfícies de PII (aceite público, arquivos, aviso por WhatsApp) em [PII-LGPD.md](PII-LGPD.md)
- `Tests/Feature/LgpdComplianceTest.php` valida automaticamente

## 6. Testes

Teste roda no CT 100 ou no CI, nunca na máquina local nem no Hostinger ([proibicoes](../../proibicoes.md)):

```bash
tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test Modules/ComunicacaoVisual/Tests/Feature/OrcamentoViraOsContratoTest.php"
```

Teste novo usa o tenant fictício 98 contra 99 ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — NUNCA biz=4 (ROTA LIVRE) ou biz real. Em que lane de CI cada arquivo roda: `node scripts/governance/test-lane-coverage.mjs`.

## 7. Concorrentes (referência)

| Concorrente | Cobre | Falta |
|-------------|-------|-------|
| Mubisys | Cálculo m² + base SP | IA, dual-doc fiscal, Tier 0 |
| Zênite | PCP visual | Cálculo m² auto, NFe-boleto |
| Calcgraf | Calculadora grátis | PCP, apontamento, FSM |

## 8. Próximos passos

Decisões abertas do [W] que travam o fluxo: faturamento da OS, quem avança a etapa da OS, opt-in do aviso de instalação e a ratificação da [ADR 0436](../../decisions/0436-os-por-item-producao-dona-da-os-do-pedido.md). Estado consolidado em [BRIEFING.md](BRIEFING.md).

## 9. Comandos artisan

```bash
php artisan comvis:demo-seed --business=98 --clean   # dados demo end-to-end (orçamento + OS + apontamento)
php artisan comvis:health --detail                   # orçamentos, apontamentos, drift
```

## 10. Links relacionados

- SPEC completa: [SPEC.md](SPEC.md) · fluxo: [SDD-tela-orcamento-m2-v1.0.md](SDD-tela-orcamento-m2-v1.0.md)
- Capterra ficha: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)
- ADR 0121 modular vertical · ADR 0431 PCP no Producao · ADR 0093 multi-tenant
