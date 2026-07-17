---
slug: 0116-pivot-gold-manifestacao-destinatario-emenda-0115
number: 116
title: "Pivot caso Gold — Manifestação do Destinatário (DFe) substitui escopo de emissão NF-e 55 (emenda 0115)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-09'
quarter: 2026-Q2
related:
  - 0103-eventos-fiscais-separados-por-modelo
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0115-recuperacao-cliente-gold-via-bundle-oimpresso
amends:
  - '0115'
pii: false
---

# ADR 0116 — Pivot caso Gold: Manifestação do Destinatário substitui escopo de emissão NF-e 55

**Status:** ✅ Aceita
**Data:** 2026-05-09
**Decisão por:** Wagner Rocha
**Categoria:** arq / correção de escopo
**Não supersede:** [ADR 0115](0115-recuperacao-cliente-gold-via-bundle-oimpresso.md). Estende e corrige o escopo técnico — estratégia comercial (recuperar Gold antes da fuga pra Mubsys) permanece intacta.

---

## Contexto

[ADR 0115](0115-recuperacao-cliente-gold-via-bundle-oimpresso.md) (mesma sessão, 2026-05-09) registrou a estratégia de recuperar **Gold Comunicação Visual** antes de migrar pra Mubsys, e definiu como bundle **emissão NF-e modelo 55** via `Modules/NfeBrasil` Fase 2 (já entregue).

Em troca subsequente na mesma sessão, Wagner esclareceu o caso de uso real:

> _"ela recebe um xml de nfe e tem que tirar uma nota DFe e tem prazo para informar que foi transportado"_

**Tradução técnica:** o gap de Gold não é emitir NF-e (Gold como vendedora B2B), e sim **manifestar sobre NF-e recebidas** (Gold como destinatária recebendo de fornecedores), dentro do prazo SEFAZ.

Os 4 eventos da NT 2014.002 que aplicam:

| Evento | tpEvento | Significado | Prazo SEFAZ |
|---|---|---|---|
| Ciência da Operação | 210 | "Vi que existe a nota emitida contra meu CNPJ" | 10 dias (recomendado) |
| **Confirmação da Operação** | **220** | **"Recebi a mercadoria, conforme"** — caso Gold | **180 dias** (NT 2014.002) |
| Desconhecimento | 230 | "Não conheço, alguém usou meu CNPJ" | 10 dias |
| Operação não Realizada | 240 | "Não recebi, dispensa pagamento" | 180 dias |

Em paralelo, o ambiente nacional SEFAZ disponibiliza **Distribuição DFe** (`sefazDistDFe($lastNSU)`) que entrega XMLs de NF-e emitidas contra o CNPJ do destinatário, evitando depender do fornecedor enviar por email.

## Descoberta crítica

Durante a investigação técnica, **encontrei base de código legada UltimatePOS já existente** mas órfã (sem rotas):

- [app/Manifesto.php](../../app/Manifesto.php) — model legado
- [app/ItemDfe.php](../../app/ItemDfe.php) — model legado
- [app/ManifestoLimite.php](../../app/ManifestoLimite.php) — model legado
- [app/Services/DFeService.php](../../app/Services/DFeService.php) — service legado
- [app/Http/Controllers/ManifestoController.php](../../app/Http/Controllers/ManifestoController.php) — controller legado (rotas ausentes em `routes/web.php`)

Status: feature foi implementada em versão antiga do UltimatePOS, perdeu rotas em algum upgrade, código permanece dormente. **Reaproveitamento direto** > codar do zero.

## Decisão

Pivotar o escopo técnico da recuperação Gold, **mantendo intactos** os artefatos da [ADR 0115](0115-recuperacao-cliente-gold-via-bundle-oimpresso.md):

### Mantém (ADR 0115 vigente)
- ✅ Estratégia "recuperar Gold antes da fuga Mubsys"
- ✅ Cliente como sinal qualificado ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md))
- ✅ Track paralelo ao Cycle 03 (não interrompe smoke biz=1)
- ✅ Runbook on-prem [`memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md`](../requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md) — fases 1-3 (discovery + proposta + upgrade) válidas
- ✅ Template proposta `PROPOSTA-COMERCIAL-vs-mubsys.md` — diferenciais vs Mubsys ainda aplicam
- ✅ US-NFE-042 (Discovery) — vai descobrir se Gold é só destinatária ou também emite

### Corrige (escopo técnico)

**Caso Gold = Manifestação do Destinatário**, não emissão NF-e 55:

1. Resgatar `app/Manifesto`, `app/ItemDfe`, `app/Services/DFeService` legados
2. Migrar pra `Modules/NfeBrasil/` (padrão atual + multi-tenant + Eloquent moderno)
3. Implementar `ManifestacaoService` usando `eduardokum/sped-nfe` (já em composer):
   - `Tools::sefazManifesta($chave, $tpEvento, $xJust='')` — eventos 210/220/230/240
4. Implementar `DistribuicaoDfeService` usando `Tools::sefazDistDFe($lastNSU)`:
   - Job agendado puxa XMLs novos por NSU
   - Storage em `nfe_dfe_recebidos` (nova tabela, multi-tenant `business_id`)
5. UI listar XMLs recebidos + 4 botões manifestar + alerta de prazo (180d countdown)

### Tasks dormentes (orientação Wagner: "guardar inativo")

Por solicitação Wagner ("não abandona o projeto. guarde inativo"), as tasks com escopo de emissão **não são canceladas** — ficam em `status: blocked` aguardando discovery:

- US-NFE-043 — Proposta comercial vs Mubsys → **blocked**
- US-NFE-044 — Upgrade plataforma on-prem → **blocked** (parcialmente reusável, parte do upgrade serve à manifestação)
- US-NFE-045 — Configuração fiscal cert+IE+regime → **blocked** (aplicável também à manifestação — destinatário também precisa de cert pra assinar evento)
- US-NFE-046 — Smoke NF-e 55 SEFAZ-SP → **blocked** (só ativa se discovery confirmar Gold também emite)
- US-NFE-047 — Treinamento + cutover NF-e 55 → **blocked**
- US-NFE-048 — Refinar runbook on-prem → **blocked** (continua relevante; só pós discovery)

**Critério de reativação:** se [US-NFE-042 Discovery] descobrir que Gold também emite NF-e 55 (vendas B2B dela), reativar US-NFE-043..047.

### Tasks novas (escopo Manifestação — ATIVAS)

| ID | Título | Estimate |
|---|---|---|
| US-NFE-049 | Migrar models/service legados Manifesto/ItemDfe/DFeService pra `Modules/NfeBrasil/` | 4h |
| US-NFE-050 | `ManifestacaoService` (`sped-nfe::sefazManifesta`) — eventos 210/220/230/240 | 4h |
| US-NFE-051 | `DistribuicaoDfeService` + Job agendado puxa XMLs por NSU (`sefazDistDFe`) | 5h |
| US-NFE-052 | UI listar XMLs recebidos + 4 botões manifestar + alerta prazo 180d | 4h |
| US-NFE-053 | Smoke homologação SEFAZ-SP eventos 210/220 biz=Gold | 2h |

**Total ativo manifestação:** 19h codáveis. Discovery (US-NFE-042) define se entra Cycle 04 ou paralelo.

## Consequências

**Positivas:**
- Reaproveita ~5 arquivos legados — esforço dev reduzido
- **Diferenciador real**: Mubsys/Bling/Omie cobrem emissão; **manifestação automática + DFe download por NSU** é menos comum em ERPs verticais ([CAPTERRA-FICHA NfeBrasil](../requisitos/NfeBrasil/CAPTERRA-FICHA.md) cita TecnoSpeed/PlugNotas)
- Resolve problema de **prazo legal** (180d Confirmação) que pode gerar contingência fiscal real pra Gold
- Caminho aplicável a **outros 49 dormentes** que recebem NF-e (todo destinatário PJ)
- Mantém ADR 0115 vigente — sem retrabalho de governança

**Negativas / Riscos:**
- Code legado pode ter dependências quebradas (versão UltimatePOS antiga); descobrir na US-NFE-049
- `sefazDistDFe` consome chamadas SEFAZ — taxa controlada (~5min cooldown por NSU)
- Storage XMLs recebidos cresce linear; planejar retenção 5 anos legal vs purge
- Cert A1 da Gold também precisa estar válido pra assinar **eventos** (não só emissão); pré-requisito mantém

**Critérios de sucesso:**
- 1ª manifestação 220 (Confirmação) cstat 135 em homologação SEFAZ-SP biz=Gold em 14 dias
- Job agendado puxa XMLs do NSU diariamente sem timeout
- Gold cancela migração Mubsys

## Alternativas consideradas

| Alternativa | Por que rejeitada |
|---|---|
| **Cancelar US-NFE-043..048** | Wagner explicitamente: "não abandona o projeto. guarde inativo" — possibilidade de Gold também emitir não pode ser descartada antes de discovery |
| **Codar manifestação do zero em `Modules/NfeBrasil/`** | Existe code legado — reusar é mais rápido e menos arriscado |
| **Manter `app/Http/Controllers/ManifestoController.php` no padrão antigo** | Viola padrão modular ([ADR 0011](0011-alinhamento-padrao-jana.md)); quebra multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)) |
| **Criar módulo `Modules/Manifestacao/` separado** | Reuso cert A1 + lib `sped-nfe` + storage XML — mesmo SoC do NfeBrasil. Módulo separado duplicaria infra |

## Plano de execução pós-aprovação

1. ✅ ADR 0116 criada (este arquivo)
2. ⏳ `tasks-update US-NFE-043..048 status:blocked` + comment justificativa
3. ⏳ Criar 5 tasks novas US-NFE-049..053 (sprint `Gold-Reativacao`)
4. ⏳ Apender seção "Manifestação Destinatário" no [runbook on-prem](../requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md)
5. ⏳ Apender seção "Manifestação automática vs Mubsys" no [template proposta](../requisitos/Officeimpresso/PROPOSTA-COMERCIAL-vs-mubsys.md)
6. ⏳ Discovery US-NFE-042 (Wagner) → determina se reativa US-NFE-043..047

## Notas

- **Lib confirmada:** `eduardokum/sped-nfe` (já em composer; ADR ARQ-0002 NfeBrasil) — métodos `Tools::sefazManifesta` + `Tools::sefazDistDFe`
- **Multi-tenant:** todo o stack manifestação roda sob `business_id` global scope ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))
- **Cycle 03 não é interrompido** — Gold-Reativacao continua paralelo
