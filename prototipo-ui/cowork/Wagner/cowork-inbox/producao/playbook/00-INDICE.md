---
sessao: "00"
titulo: Produção (PCP) — índice
autor: "[CC]"
criado: 2026-10-15
base: wagnerra23/oimpresso.com@main 0c29a8505b8b (lido 2026-10-15)
---
# Produção (PCP) — playbook

```json
{
  "modulo": "Producao",
  "sha": "0c29a8505b8b",
  "gerado": "2026-10-15",
  "variaveis": {
    "MOD": "Modules/Producao",
    "MPAGES": "Modules/Producao/Resources/js/Pages",
    "ALVOS": "governance/design/targets",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {
      "id": "D-PCP",
      "pergunta": "PCP sai do 'parado' da ADR 0152?",
      "respondida": true,
      "resposta": "sim, autorizado fazer",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D1",
      "pergunta": "Nome e pasta do módulo",
      "respondida": true,
      "resposta": "Modules/Producao",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D2",
      "pergunta": "QR: PWA ou app",
      "respondida": true,
      "resposta": "PWA",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D3",
      "pergunta": "Cronômetro",
      "respondida": true,
      "resposta": "híbrido, padrão automático",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D4",
      "pergunta": "Capacidade",
      "respondida": true,
      "resposta": "configurável por empresa, com valores sugeridos",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D5",
      "pergunta": "Gargalo",
      "respondida": true,
      "resposta": "regra simples na V1",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-PERM",
      "pergunta": "Permissões",
      "respondida": true,
      "resposta": "producao.etapa.mover · producao.desempenho.ver",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-RELACAO",
      "pergunta": "Relação com CV/Repair/Oficina/Fabricação",
      "respondida": true,
      "resposta": "camada única de PCP; os módulos seguem donos da OS",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-PILOTO",
      "pergunta": "Quem estreia",
      "respondida": true,
      "resposta": "biz=1, depois cliente de CV",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-TURNO",
      "pergunta": "Turno",
      "respondida": true,
      "resposta": "configurável, padrão 08h–18h, fecha o cronômetro",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-AVISO",
      "pergunta": "Aviso ao próximo",
      "respondida": true,
      "resposta": "notificação no app; WhatsApp só ao cliente",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-EMPENHO",
      "pergunta": "Reserva de material",
      "respondida": true,
      "resposta": "sim, no orçamento aprovado; baixa pelo apontamento",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-PROVA",
      "pergunta": "Link de arte",
      "respondida": true,
      "resposta": "bloco no link com código do Repair",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-ENCAIXE",
      "pergunta": "Encaixe de mídia",
      "respondida": true,
      "resposta": "versão 2",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-FASES",
      "pergunta": "Ordem",
      "respondida": true,
      "resposta": "ROADMAP do Pcp",
      "fonte": "_DECISOES-W-2026-10-15.md"
    },
    {
      "id": "D-AGENDA",
      "pergunta": "Agenda de instalação: da Produção ou da Comunicação Visual?",
      "respondida": true,
      "resposta": "uma agenda só, a da ComVis (#9721); a thread 09 fica com checklist, fotos, assinatura e PDF (UC-PCV-25)",
      "fonte": "decisão [W] 2026-10-21 (conferência dos PRs da Produção)"
    },
    {
      "id": "D-ARTE-PAGINA",
      "pergunta": "Aprovação de arte: página própria /arte/{token} (#9645) ou bloco em /os/{codigo}?",
      "respondida": true,
      "resposta": "mantém a /arte/{token} do #9645; a thread 10 fica com a trava da arte (UC-PCV-11) e o link a partir de /os/{codigo} depois da Repair 06",
      "fonte": "decisão [W] 2026-10-21 (conferência dos PRs da Produção)"
    }
  ],
  "threads": [
    {
      "id": "A1",
      "titulo": "ALVO: medir as 7 vistas do protótipo (pcv, pcv-quadro, pcv-linha, pcv-custo, pcv-instalacao, pcv-apontamento, pcv-roteiros) + a ficha",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "A1-alvos.md",
      "prefixo": [
        "${ALVOS}/producao--*"
      ],
      "nao_toca": [
        "${MOD}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/producao--painel--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/producao--quadro--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/producao--linha--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/producao--custo--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/producao--instalacao--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/producao--apontamento--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/producao--roteiros--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/producao--ficha--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "ADR: PCP sai do estado de desejo (supersede 0152 no PCP), ADR-PROPOSAL → accepted, Modules/Producao nasce, IProduction sai",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-adr.md",
      "depende_threads": [],
      "depende_decisoes": [
        "D-PCP",
        "D1"
      ],
      "prefixo": [
        "memory/decisions/",
        "memory/requisitos/Pcp/",
        "Modules/Producao/",
        "Modules/IProduction/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Producao/module.json"
        },
        {
          "tipo": "ausente",
          "path": "Modules/IProduction/module.json"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md · sem Page"
    },
    {
      "id": "02",
      "titulo": "Fundo: postos, operações, apontamentos (append-only), programação, eventos (append-only), turno e custo-hora por empresa",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "02-fundo.md",
      "depende_threads": [
        "01"
      ],
      "depende_decisoes": [
        "D1",
        "D3",
        "D4",
        "D-TURNO"
      ],
      "prefixo": [
        "${MOD}/Database/",
        "${MOD}/Entities/",
        "${MOD}/Services/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoFundoContratoTest.php",
          "padrao": "UC-PCV-28"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md"
    },
    {
      "id": "03",
      "titulo": "Cadastros: postos e máquinas, roteiros por família (2 trilhas, opcionais pelo orçamento)",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "03-cadastros.md",
      "depende_threads": [
        "02",
        "A1"
      ],
      "depende_decisoes": [
        "D1",
        "D4"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/",
        "${MPAGES}/Producao/Roteiros/",
        "${MPAGES}/Producao/Postos/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoRoteirosContratoTest.php",
          "padrao": "UC-PCV-13"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md"
    },
    {
      "id": "04",
      "titulo": "Celular do operador (PWA): minhas etapas, QR, cronômetro, fechamento no fim do turno, fila offline",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "04-apontamento.md",
      "depende_threads": [
        "02",
        "A1"
      ],
      "depende_decisoes": [
        "D2",
        "D3",
        "D-TURNO",
        "D-AVISO"
      ],
      "prefixo": [
        "${MPAGES}/Producao/Apontamento/",
        "${MOD}/Http/Controllers/",
        "${MOD}/Tests/",
        "public/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoApontamentoContratoTest.php",
          "padrao": "UC-PCV-19"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoApontamentoContratoTest.php",
          "padrao": "UC-PCV-20"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoApontamentoContratoTest.php",
          "padrao": "UC-PCV-21"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoApontamentoContratoTest.php",
          "padrao": "UC-PCV-22"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoApontamentoContratoTest.php",
          "padrao": "UC-PCV-23"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md"
    },
    {
      "id": "05",
      "titulo": "QR na etiqueta e no recibo da OS (CV, Assistência, Oficina)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "05-qr-etiqueta.md",
      "depende_threads": [
        "04"
      ],
      "depende_decisoes": [
        "D-PCP",
        "D1"
      ],
      "prefixo": [
        "${MOD}/",
        "Modules/Repair/Resources/views/job_sheet/print_label.blade.php"
      ],
      "nao_toca": [
        "Modules/Repair/Http/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoApontamentoContratoTest.php",
          "padrao": "UC-PCV-20"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md"
    },
    {
      "id": "06",
      "titulo": "Painel + Quadro: indicadores-filtro, fila de arte, quadro por setor/máquina, filtro, mover etapa, aviso no app",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "06-painel-quadro.md",
      "depende_threads": [
        "02",
        "04",
        "A1"
      ],
      "depende_decisoes": [
        "D-RELACAO",
        "D-AVISO",
        "D-PERM"
      ],
      "prefixo": [
        "${MPAGES}/Producao/Painel/",
        "${MPAGES}/Producao/Quadro/",
        "${MOD}/Http/Controllers/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoQuadroContratoTest.php",
          "padrao": "UC-PCV-01"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoQuadroContratoTest.php",
          "padrao": "UC-PCV-02"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoQuadroContratoTest.php",
          "padrao": "UC-PCV-03"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoQuadroContratoTest.php",
          "padrao": "UC-PCV-04"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoQuadroContratoTest.php",
          "padrao": "UC-PCV-05"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoQuadroContratoTest.php",
          "padrao": "UC-PCV-06"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoQuadroContratoTest.php",
          "padrao": "UC-PCV-07"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoQuadroContratoTest.php",
          "padrao": "UC-PCV-29"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md"
    },
    {
      "id": "07",
      "titulo": "Linha do tempo por máquina (arrastar e Alt+setas) + gargalo por regra com sugestão aplicável",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "07-linha.md",
      "depende_threads": [
        "06"
      ],
      "depende_decisoes": [
        "D5",
        "D-TURNO"
      ],
      "prefixo": [
        "${MPAGES}/Producao/Linha/",
        "${MOD}/Services/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoLinhaContratoTest.php",
          "padrao": "UC-PCV-08"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoLinhaContratoTest.php",
          "padrao": "UC-PCV-09"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoLinhaContratoTest.php",
          "padrao": "UC-PCV-30"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md"
    },
    {
      "id": "08",
      "titulo": "Ficha da OS + custo real × esperado + materiais (reserva/baixa) + refugo e perdas",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "08-ficha-custo.md",
      "depende_threads": [
        "06"
      ],
      "depende_decisoes": [
        "D-EMPENHO",
        "D-PERM"
      ],
      "prefixo": [
        "${MPAGES}/Producao/Os/",
        "${MPAGES}/Producao/Custo/",
        "${MOD}/Services/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-10"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-11"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-12"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-14"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-15"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-16"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-17"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-18"
        },
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoCustoContratoTest.php",
          "padrao": "UC-PCV-27"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md · regra mestre de valor: dupla prova; fórmula de custo nova = aprovação [W]"
    },
    {
      "id": "09",
      "titulo": "Instalação: checklist com foto antes/depois, assinatura e protocolo em PDF (a agenda é a da ComVis)",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "09-instalacao.md",
      "depende_threads": [
        "06"
      ],
      "depende_decisoes": [
        "D-PCP",
        "D1",
        "D-AGENDA"
      ],
      "prefixo": [
        "${MPAGES}/Producao/Instalacao/",
        "${MOD}/Http/Controllers/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoInstalacaoContratoTest.php",
          "padrao": "UC-PCV-25"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md · UC-PCV-24 (agenda) é atendido pela /comunicacao-visual/agenda (#9721, D-AGENDA), lida sem escrever em Modules/ComunicacaoVisual"
    },
    {
      "id": "10",
      "titulo": "Trava da arte aprovada (UC-PCV-11) e link para a prova a partir de /os/{codigo}",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "10-arte-link.md",
      "depende_threads": [
        "06"
      ],
      "depende_decisoes": [
        "D-PROVA",
        "D-ARTE-PAGINA"
      ],
      "prefixo": [
        "${MOD}/Services/",
        "${MOD}/Http/Controllers/",
        "${MOD}/Tests/",
        "Modules/ConsultaOs/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoAprovacaoArteContratoTest.php",
          "padrao": "UC-PCV-11"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md · a página /arte/{token} já existe (#9645, D-ARTE-PAGINA) · a trava não depende de nada; o link em /os/{codigo} depende da thread Repair 06 · UC-PCV-04 (fila de arte) foi entregue no Painel da thread 06"
    },
    {
      "id": "11",
      "titulo": "Virada: biz=1 (WR2) primeiro, depois um cliente de CV; canary e rota de fuga",
      "dono": "CL",
      "vaga": 5,
      "prs": 1,
      "arquivo": "11-virada.md",
      "depende_threads": [
        "04",
        "06",
        "07",
        "08",
        "09"
      ],
      "depende_decisoes": [
        "D-PILOTO"
      ],
      "prefixo": [
        "config/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoFundoContratoTest.php",
          "padrao": "UC-PCV-28"
        }
      ],
      "nota_provas": "casos em cowork-inbox/producao/Producao.casos.md · sem Page; recibo com smoke discriminante"
    }
  ]
}
```

## 1 · De onde vem
- **Forma:** protótipo `producao-cv-*.jsx` + `producao-cv.css` (rotas `pcv`, `pcv-*`). Nada no `main` ainda: tela nova, sem par 1:1.
- **Comportamento:** `cowork-inbox/producao/Producao.casos.md` (UC-PCV-01..28, com "como provar").
- **Domínio no `main`:** `memory/requisitos/Pcp/{SPEC,ADR-PROPOSAL,ROADMAP,MATRIZ-ROI}.md` · `memory/research/2026-05-prospeccao/33-grade-producao-concorrentes.md`.
- **Decisões:** todas respondidas por [W] em 2026-10-15 (`_DECISOES-W-2026-10-15.md`). Nada espera [W] para começar.

## 2 · Ordem (ROADMAP do Pcp, D-FASES)
A1 e 01 primeiro · 02 → 03 · 04 → 05 · 06 → 07 · 08, 09 e 10 depois da 06 · 11 por último. Uma thread por sessão limpa, `/onda Producao --thread NN`, fecha com `_saida-NN.md`.

## 3 · Âncoras de implementação (irmãs golden no `main`, a reler no turno)
- Page Inertia de módulo: `Modules/Crm/Resources/js/Pages/Crm/Pedidos/Index.tsx` + `CrmPedidosContratoTest.php` (molde de teste 98×99).
- Quadro existente para reaproveitar o padrão (não o código do domínio): `Modules/Repair/Http/Controllers/ProducaoOficinaController.php` + `KanbanProductionService.php`.
- Etiqueta de OS: `Modules/Repair/Resources/views/job_sheet/print_label.blade.php`.

## 4 · O que este índice NÃO resolve
- Encaixe de mídia por algoritmo (D-ENCAIXE: versão 2).
- Análise de gargalo por IA (D5: depois).
- App nativo (D2: só se a PWA falhar em mais de 2 aparelhos).
