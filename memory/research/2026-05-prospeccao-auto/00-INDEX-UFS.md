---
id: research-2026-05-prospeccao-auto-00-index-ufs
---

# Índice prospecção oficinas mecânicas / centro automotivo — Top 10 UFs

**Atualizado:** 2026-05-10
**Pesquisador:** Claude Code — agente prospecção (10 agentes paralelos em background)
**Vertical alvo:** Modules/OficinaAuto ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md))
**Status governança:** ⚠️ backlog feature-wish ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) — sem cliente piloto pagante. Mapeamento existe pra estar pronto quando 1+ piloto reportar dor.

**ICP comum:** mecânica geral / centro automotivo / especialista (CNAE 4520-0/01) · 5–50 funcionários · 1-3 elevadores · R$ [redacted Tier 0]k-500k/mês (estimado por sinais públicos) · validação WebFetch real para Tier 1 · sem invenção (`?` para desconhecido) · sem PII pessoal.

## Cobertura geográfica

10 UFs densas mapeadas (Sudeste + Sul + Centro-Oeste + 2 Nordeste). 17 UFs restantes ficam pra batch futuro se ativar Modules/OficinaAuto.

| UF | Total | Tier 1 | Tier 2 | Tier 3 | Arquivo |
|----|-------|--------|--------|--------|---------|
| SP | 42 | 10 | 17 | 15 | [04-oficinas-sp.md](04-oficinas-sp.md) |
| RJ | 36 | 10 | 14 | 12 | [05-oficinas-rj.md](05-oficinas-rj.md) |
| MG | 32 | 8 | 12 | 12 | [06-oficinas-mg.md](06-oficinas-mg.md) |
| SC | 31 | 15 | 10 | 6 | [09-oficinas-sc.md](09-oficinas-sc.md) |
| PR | 28 | 10 | 11 | 7 | [08-oficinas-pr.md](08-oficinas-pr.md) |
| RS | 27 | 10 | 9 | 8 | [07-oficinas-rs.md](07-oficinas-rs.md) |
| BA | 24 | 8 | 9 | 7 | [10-oficinas-ba.md](10-oficinas-ba.md) |
| GO | 24 | 10 | 8 | 6 | [12-oficinas-go.md](12-oficinas-go.md) |
| DF | 22 | 8 | 8 | 6 | [13-oficinas-df.md](13-oficinas-df.md) |
| PE | 22 | 8 | 8 | 6 | [11-oficinas-pe.md](11-oficinas-pe.md) |
| **Total** | **288** | **97** | **106** | **85** | 10 arquivos |

## Padrões cross-UF

### Diferenciadores vs gráficas (mercado endereçável **5-10x maior** — ver [01-mercado-oficinas-auto-br.md](01-mercado-oficinas-auto-br.md))

1. **Bosch Car Service domina como selo de qualidade** — 5-6 unidades credenciadas no Tier 1+2 de cada UF média/grande. Equivalente "WhatsApp Business" do setor: sinal de operação madura mas sem ERP integrado.
2. **Multi-loja é 3x mais comum que em gráficas** — RJ tem 8/36, GO/MG mostram redes como Tecmídia/Brasil Centro Automotivo (5 unidades). Sweet spot multi-tenant Tier 0 do oimpresso.
3. **B2B frota/seguradora é dor confessada** — 60%+ do Tier 1 atende frotistas (Localiza/Movida/Unidas) ou seguradoras (Mapfre/Porto Seguro). Workflow autorização-seguradora + NFSe/NFe + retenções é gap real.
4. **Especialista câmbio automático** = OS multi-dia de alto ticket — concentrado em ABC (SP), Betim (MG), interior. Sem ERP, planilha+caderno é norma.
5. **Diesel especialista** = compliance CONAMA (DPF/EGR remap) + emissões — gap fiscal/ambiental único. Goiânia tem nicho forte (NS Injeção Diesel).
6. **DF tem mix governo+privado** — frota COGEF-SEEC = empenho/SIAFI. Diferenciador potencial se Modules/OficinaAuto avançar.

### Top 30 candidatos cross-UF (Tier 1 high-fit, multi-loja ou clientes enterprise)

**Sudeste (10):**
1. Ferrino Reparos (Campinas+Paulínia+Americana, 3 unidades, todas seguradoras)
2. Taciro Auto Center (Campinas, 56 anos, check-list digital)
3. Akikar / JR Fabiano (ABC, especialistas câmbio automático)
4. Mecânica HP (Bauru, único Tier 1 polo médio interior)
5. Faria Junior Bosch CS (RJ/Vigário Geral, 30+ anos, clientes Furnas/Vivo/Mapfre/Marinha)
6. Mech Rio (RJ/Recreio, BMW/Audi/Mercedes, ex-concessionária)
7. Mecânica Fusão (Petrópolis/RJ, 2.000m² + cabine pintura)
8. Resolução UV — *correção: gráfica, não auto* — substitui pelo Brasil Centro Automotivo (JF/MG, 5 unidades JF+RJ, Bosch)
9. Strong Car Services (Betim/MG, 3 unidades, especialista câmbio auto)
10. Oficina Barbosa Lima (JF/MG, 50 anos + 2.500m² + 6 elevadores)

**Sul (8):**
11. Lisboa Car (POA/RS, 45 anos, 2 unidades, premium + nacionais)
12. Caprice (POA/RS, 35 anos, seguradoras + frota — dor ERP máxima)
13. Auto Center Fabiano (Santa Maria/RS, 2 unidades multi-tenant real)
14. Stern Premium (POA/RS, BMW/Audi/Porsche/Ferrari)
15. WEJ Centro Automotivo (Curitiba/PR, **16 elevadores publicados** + processo 8 etapas + Porto Seguro)
16. Grid Auto Center (SJP/PR, 3 unidades = único multi-loja PR confirmado)
17. Mecânica Chile (Curitiba/PR, **4 convênios frota simultâneos** — MaxiFrota/Good Card/Prime/Vale Card)
18. Borchers (Jaraguá do Sul/SC, 8 elevadores, 21k carros, 58k atendimentos)
19. Eletrovel (São José/SC, 32 anos, premium câmbio automático/híbridos)
20. Finder Auto Center (Joinville/SC, Bosch Car Service)

**Centro-Oeste (5):**
21. R&R (Aparecida/GO, frota locadora — Unidas/Localiza/Movida/Arval/LeasePlan)
22. Goiânia Auto Center (Setor Coimbra/GO, 22 anos pick-up specialist)
23. NS Injeção Diesel (Pq. Amazônia/GO, especialista diesel + compliance CONAMA)
24. Tecmídia equivalente: **Nippon** (DF, SOF Sul + Asa Norte multi-loja, 50+ anos)
25. JM Auto Centro (DF/Asa Sul, 41 anos, Bosch CS, ADAS)

**Nordeste (5):**
26. AutoService Manutenção (Recife/PE, 3 unidades RMR + funilaria + frota)
27. Maxtroc (Recife/PE, Bosch Car Service desde 1983)
28. New Car Service (Recife/PE, multi-loja + 12 seguradoras)
29. Centro Automotivo Porto (Salvador/BA, 3 lojas — único multi-tenant BA)
30. NB Multimarcas (Feira de Santana/BA, foco PJ)

## Refs

- [01-mercado-oficinas-auto-br.md](01-mercado-oficinas-auto-br.md) — desk research mercado/dimensionamento
- [02-concorrentes-erp-auto-br.md](02-concorrentes-erp-auto-br.md) — Mecânico, Auto Manager, Lokoz, etc
- [03-pricing-erps-auto-br.md](03-pricing-erps-auto-br.md) — pricing comparativo
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — vertical especializado
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente sinal qualificado (esta pesquisa NÃO viola: é mapeamento, não US ativa)
- [ADR 0126](../../decisions/0126-mcp-jira-projects-modulos-verticais.md) — habilita AUTO no canon MCP

## Próximos passos

1. **Não criar US-AUTO-* sem cliente real reportando dor** ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))
2. Quando piloto pagante chegar (Martinho Caçambas a confirmar — pode ser fora ICP SMB se for pesado/frota): converter dossiês UF em pipeline de outbound regional + apender US no SPEC.md
3. Mapear 17 UFs restantes apenas se demanda surgir (não preventivo)
