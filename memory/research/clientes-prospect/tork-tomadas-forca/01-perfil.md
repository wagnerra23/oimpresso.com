---
slug: tork-tomadas-forca
status: prospect
date_first_analysis: 2026-05-26
date_last_update: 2026-05-26
controlador: Wagner
vertical_real: industria-pto-kit-hidraulico-caminhao-pesado
size: medio
tipo_relacao: prospect-via-martinho
cidade: "Capivari de Baixo"
uf: SC
cnpj: 24758624000130
---

# Perfil — Tork Tomadas de Força (prospect indústria PTO)

> **Prospect identificado 2026-05-26** durante correção de domínio Martinho ([ADR 0194](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)). Tork é fornecedor B2B na cadeia comercial onde Martinho (cliente atual biz=164 oimpresso) está — vetor de prospecção via cliente atual.

## 1. Identificação

| Campo | Valor |
|-------|-------|
| Razão social | Tork Tomadas de Força (a confirmar razão social exata via CNPJ lookup) |
| CNPJ | 24.758.624/0001-30 ([cnpj.biz](https://cnpj.biz/24758624000130)) |
| Endereço | Rua Antonia de Bitencourt Barcelos, sala 2, n. 84 — Capivari de Baixo/SC |
| Site | [lp.tork.ind.br](https://lp.tork.ind.br/) |
| Fundação | 2016 |
| Vertical real | **Indústria de PTO (Power Take-Off / tomada de força) + kits hidráulicos** pra caminhão pesado |
| Porte | médio (referência nacional declarada no próprio site) |
| Status comercial | **prospect** (ainda não cliente oimpresso) |

## 2. Tipo de negócio

**Fábrica industrial** especializada em **tomadas de força (PTO)** + **kits hidráulicos** pra caminhão. Componentes que vão em frota basculante / Polli-guindaste / plataforma / munck. Desde 2016, posiciona-se como referência nacional.

**NÃO é oficina** — é indústria com linha de produção. Vertical correta no oimpresso: **Modules/Industria/PCP** + **Modules/Sells B2B** (não Modules/OficinaAuto).

## 3. Cadeia comercial (onde Tork se encaixa)

```
[Tork (fábrica PTO Capivari)] → [Martinho Caçambas (revenda + instala em Capivari)] → [Frota basculante terceiro (cliente final)]
```

Martinho (cliente atual biz=164 oimpresso) é provavelmente cliente B2B de Tork — compra PTO/kit hidráulico, vende e instala no caminhão do cliente final. Isso abre **2 caminhos de prospecção**:

1. **Via Martinho (recomendação warm)** — Wagner pode pedir pra Martinho fazer apresentação cruzada (Martinho já está em pacote oimpresso R$ [redacted Tier 0]/mês + add-on WhatsApp [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md))
2. **Direto (cold)** — abordagem via site lp.tork.ind.br ou CNPJ

## 4. Módulos oimpresso aplicáveis

| Módulo | Por quê |
|--------|---------|
| **Modules/Industria** (proposed, sem cliente real ainda) | Tork é o piloto óbvio — sinal qualificado [ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) caso Tork aceitar |
| Modules/PCP (proposed) | Linha de produção PTO + lote/série |
| Modules/Sells | Venda B2B pra revendas (Martinho-like) + cliente final |
| Modules/Compras | Matéria-prima (aço, vedação, fundido) |
| Modules/Fiscal | NFe industrial + ICMS-IPI + bloco K SPED |
| Modules/Financeiro | Fluxo B2B com prazo + boleto |
| Modules/WhatsApp | Atendimento técnico revendas |

## 5. Diferenciais oimpresso pra esse perfil

- Multi-tenant Tier 0 ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md))
- Jana IA com memória persistente — útil pra catálogo PTO cross-ref por modelo caminhão (Scania/Volvo/MB/Ford)
- FSM canônica LIVE prod ([ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) — adaptável pra Bloco K SPED (ordem produção)
- NFe automática + bloco K SPED (relevante pra indústria)
- Add-on WhatsApp R$ [redacted Tier 0]/instância (paridade Martinho [ADR 0171](../../../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md))

## 6. Próximos passos (pendentes Wagner)

- [ ] Wagner decidir abordagem: warm via Martinho OU cold direto
- [ ] Discovery call Tork pra mapear:
  - Volume produção PTO/mês
  - Sistema atual (ERP genérico tipo TOTVS/SAP B1, sistema vertical de indústria, ou planilha?)
  - Maior dor operacional (PCP? venda B2B com prazo? rastreabilidade lote? Bloco K SPED?)
  - Stakeholder (dono/gestor industrial/gerente fiscal?)
- [ ] Avaliar se Modules/Industria sai do "proposed" pra "qualificado" com Tork como piloto (paralelo a OficinaAuto + Martinho)

## 7. Refs

- [ADR 0194 — correção domínio OficinaAuto](../../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) (origem desta descoberta)
- [Perfil Martinho — cliente atual da cadeia](../../clientes-legacy-officeimpresso/05-martinho-cacambas/01-perfil.md)
- WebSearch fontes 2026-05-26:
  - [Tork Tomadas de Força (site oficial)](https://lp.tork.ind.br/)
  - [Tork Tomadas de Forca CNPJ 24758624000130 (cnpj.biz)](https://cnpj.biz/24758624000130)
- [Domínios verticais oimpresso §"Sub-vertical 4"](../../../reference/dominios-verticais-oimpresso.md)
