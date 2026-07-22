# Índice prospecção lojas vestuário — Top 10 UFs

**Atualizado:** 2026-05-10
**Pesquisador:** Claude Code — agente prospecção (10 agentes paralelos em background)
**Vertical alvo:** Modules/Vestuario ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md))
**Status governança:** ✅ em produção (cliente piloto **ROTA LIVRE biz=4** Termas do Gravatal/SC, 2+ anos). Mapeamento sustenta expansão da base de clientes.

**ICP comum:** lojas vestuário (CNAE 4781-4/00) — moda feminina/masculina/infantil/jeans/plus size · 5–50 funcionários · R$ [redacted Tier 0]k-1M/m · multi-loja é sweet spot · validação WebFetch real para Tier 1 · sem invenção (`?` para desconhecido) · sem PII pessoal.

**Excluídos:** redes nacionais (Renner, CEA, Riachuelo, Marisa), atacado/confecção puro (Brás-Norte CE, Toritama PE), boutiques luxo, marketplaces puros, franquias internacionais.

## Cobertura geográfica

10 UFs densas mapeadas. 17 UFs restantes ficam pra batch futuro conforme expansão.

| UF | Total | Tier 1 | Tier 2 | Tier 3 | Arquivo |
|----|-------|--------|--------|--------|---------|
| SC | 33 | 10 | 14 | 9 | [02-vestuario-sc.md](02-vestuario-sc.md) |
| SP | 33 | 10 | 12 | 11 | [01-vestuario-sp.md](01-vestuario-sp.md) |
| RS | 30 | 8 | 12 | 10 | [05-vestuario-rs.md](05-vestuario-rs.md) |
| RJ | 29 | 8 | 21 | 8 | [04-vestuario-rj.md](04-vestuario-rj.md) |
| PR | 28 | 10 | 11 | 7 | [06-vestuario-pr.md](06-vestuario-pr.md) |
| MG | 27 | 10 | 11 | 6 | [03-vestuario-mg.md](03-vestuario-mg.md) |
| CE | 26 | 8 | 10 | 8 | [07-vestuario-ce.md](07-vestuario-ce.md) |
| BA | 24 | 10 | 8 | 6 | [09-vestuario-ba.md](09-vestuario-ba.md) |
| PE | 22 | 8 | 9 | 5 | [08-vestuario-pe.md](08-vestuario-pe.md) |
| GO | 22 | 10 | 7 | 5 | [10-vestuario-go.md](10-vestuario-go.md) |
| **Total** | **274** | **92** | **115** | **75** | 10 arquivos |

## Padrões cross-UF

### Diferenciadores vs gráficas/oficinas

1. **IG > site (oposto gráficas)** — moda SMB BR opera social-commerce-first. Annie Store/Recife (62k IG), Morena Mel/Petrolina (226k IG), Cor de Pimenta/Maringá (251k IG). Outbound precisa enfatizar integração WhatsApp/IG DM, não "ecommerce".
2. **Multi-loja física + ecommerce próprio** é norma SMB Tier 1 — Mamô (SP, 11 lojas), Cor de Rosa (BC+7 cidades SC/PR), Pole Modas (Caxias, 4 lojas), Priori (6 lojas SC+PR), Soul Dila (BA, 10+ pontos), Roupa Mágica (BH, 6 lojas RM). Sweet spot multi-tenant Tier 0.
3. **SaaS de prateleira domina ecommerce** — Mitienda Nube > Tray > Loja Integrada > JetNeo > VTEX. Nenhum tem ERP integrado real → gap claro pra oimpresso entrar como camada de gestão sob o ecommerce já existente.
4. **Polos têxteis distorcem mapa** — Toritama/SCC (PE), Brás-Norte (CE), Brusque (SC), Muriaé (MG) têm forte indústria/atacado que fica fora do ICP varejo. Filtragem rigorosa fabricante vs varejista é Tier 0 da pesquisa.
5. **Verticais nicho (plus size + gestante + infantil + moda praia)** têm SKU explosion + grade complexa = dor ERP forte. Pandora Rio (plus size 3 lojas), Lione (BH infantil), Picorrucho (Caxias infantil), Q Onda/Cor de Jambo (Cabo Frio moda praia).
6. **Cluster Costa do Descobrimento (BA)** + **Cabo Frio (RJ)** + **Balneário Camboriú (SC)** = sazonalidade turística idêntica ao ROTA LIVRE em Termas do Gravatal/SC. Case ROTA LIVRE vende direto pra esse perfil.
7. **Região do piloto Larissa concentra 8 prospects** — Tubarão/Gravatal/Laguna em SC tem 4 Tier 1+2 (LE ROSE, Mais Fashion, Centrão das Fábricas + Imbituba/Origem) + 4 long-tail. Outbound de proximidade geográfica é alavanca natural.

### Top 30 candidatos cross-UF (multi-loja real ou enterprise)

**Sudeste (10):**
1. **Mamô** (SP, **11 lojas** SP/Santos/Guarulhos/Mogi)
2. **All Side Store** (SP/Itaim+Pinheiros, 4 lojas, perfil quase-gêmeo do ROTA LIVRE)
3. **Tatuapé Conceito** (SP/flagship 400m², 30+ func)
4. **Ronald's Fashion** (SP Z. Leste, 2 lojas, 25+ anos)
5. **Mega São José** (SP/Brás+25 março, 5 lojas, lingerie/plus, 1978)
6. **Pandora Rio** (RJ/Tijuca+Méier+Caxias, 3 lojas plus size LTDA)
7. **Atitude Feminina** (Volta Redonda/RJ, 3 lojas LTDA fundada 2014)
8. **Roupa Mágica BH** (MG/RM, 6 lojas infantil)
9. **Ousada Moda Uberlândia** (MG, 4 lojas)
10. **LN Store** (Juiz de Fora/MG, 3 lojas + ecommerce)

**Sul (8):**
11. **Cor de Rosa** (BC/SC + 7 cidades SC/PR, 22 anos, fábrica+ecommerce+multimarca)
12. **Priori** (SC+PR, 6 lojas, 39 anos, marca própria)
13. **Divina Store** (Joinville/SC, 4 lojas, IG 70k)
14. **Averzzy** (SC, 3 cidades, dual varejo+atacado+B2B)
15. **LE ROSE** (Tubarão/SC, **proximidade geográfica do piloto ROTA LIVRE**)
16. **Pole Modas** (Caxias/RS, 4 lojas + ecommerce)
17. **Tauth** (Pelotas/RS, multi-canal Mitienda + envio BR)
18. **Loja Salem** (POA/RS, 60 anos + private label)

**Centro-Oeste (2):**
19. **Pó de Canella** (Goiânia, 2 lojas, 22 anos, "departamentalização" declarada)
20. **RamalZ** (Catalão/GO, 35 anos, multimarca M+F)

**Nordeste (10):**
21. **Soul Dila** (Salvador/BA, **10+ pontos**, ecommerce+offline)
22. **Kombikini** (Costa do Descobrimento/BA, 6 lojas turismo — perfil ROTA LIVRE em sazonalidade)
23. **Lojas Cida** (sertão BA, 65k IG)
24. **DWZ** (Recife/PE, Boa Viagem)
25. **Morena Mel** (Petrolina/PE, **226k IG** + 5 categorias, hub regional PE+BA+PI+CE)
26. **Império do Jeans** (Petrolina/PE, rede multi-cidade rara em PE varejo)
27. **Chérie Cereja** (Caruaru/PE, único Tier 1 varejo limpo no agreste)
28. **BÚHO** (Fortaleza/CE, 3 lojas + ecommerce, 14 anos jeans)
29. **Joiola** (Fortaleza/CE, 2 endereços Aldeota+Meireles + ecommerce verão)
30. **Modaria Sobral** (CE, único Tier 1 fora capital com ecommerce próprio)

## Conexões com cliente piloto ROTA LIVRE

ROTA LIVRE = **LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA** · biz=4 · Termas do Gravatal/SC · ~3 lojas físicas + WhatsApp + monitor 1280px · faturamento R$ [redacted Tier 0]k/m. Casos análogos:

- **Geografia:** LE ROSE (Tubarão), Mais Fashion (Tubarão), Centrão das Fábricas (Tubarão), Origem Modas (Imbituba) — vizinhança direta da Larissa
- **Sazonalidade turística:** Kombikini/BA, Cor de Jambo/RJ, Q Onda/RJ, Costa do Descobrimento (Porto Seguro/Arraial)
- **Multi-loja regional 2-5 lojas com ERP em planilha:** Pó de Canella (GO), Mais Fashion (SC), All Side Store (SP), Ronald's (SP), Atitude Feminina (RJ)
- **WhatsApp/IG-first sem ERP integrado:** universalmente todas (oportunidade pitch "primeira camada digital de verdade")

Case study ROTA LIVRE em [memory/clientes/rota-livre/operacao.md](../../clientes/rota-livre/operacao.md) é a prova social mais relevante pra outbound nesses 30.

## Refs

- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — vertical especializado
- [ADR 0126](../../decisions/0126-mcp-jira-projects-modulos-verticais.md) — habilita VEST no canon MCP
- [memory/clientes/rota-livre/operacao.md](../../clientes/rota-livre/operacao.md) — case piloto

## Próximos passos sugeridos

1. **Outbound regional Tubarão/Gravatal/Laguna** primeiro (proximidade geográfica + word-of-mouth com Larissa) — 4 prospects high-touch
2. Top 30 cross-UF como pipeline Q3 — Cold #1+#2+#3 seguindo template do plano [outbound-comvis-q2/00-PLAN.md](../../sales/2026-05/outbound-comvis-q2/00-PLAN.md) adaptado pra vertical vestuário (case ROTA LIVRE como prova social central)
3. **Não mapear 17 UFs restantes** preventivamente — ativar quando taxa de resposta dos Top 30 indicar ICP-fit
