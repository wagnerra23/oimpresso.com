---
modulo: producao
tipo: patch-de-indice
gerado: 2026-10-15
base_lido: f7b99399acde (blob do 00-INDICE.md no main) · Producao.casos.md 12ae31508d9b
origem: handoff "Mobile app structure review" (Claude Design) · handoff/producao-celular/00-INDICE.md
dono: "[CD]"
---
# PATCH do 00-INDICE.md e do Producao.casos.md — Produção no celular (2026-10-15)

> **Por que patch e não índice mesclado:** `00-INDICE.md` e `Producao.casos.md` são espelho do Cowork já verificado. Mesclar à mão acende `MEXIDO-DEPOIS` no `cowork-mirror-freshness --unverified --check` (medido neste PR: rc=1 com uma linha anexada ao casos; §5 2026-09-24). A mescla é feita **no Cowork** e desce no próximo retorno. Até lá este arquivo é a extensão: fichas novas em `playbook/`, UC em `Producao-celular.casos.md`.
>
> **Onde está cada coisa citada pelas fichas** (elas foram escritas com caminhos do projeto de origem):
> - `handoff/producao-celular/00-INDICE.md` → este arquivo
> - `handoff/producao-celular/Producao-celular.casos.md` → `cowork-inbox/producao/Producao-celular.casos.md`
> - `DECISOES-W-2026-10-15.md` → `playbook/_DECISOES-W-2026-10-15-celular.md`
> - `_SESSAO-FRIA.md` → `playbook/_SESSAO-FRIA-celular.md` (a `_SESSAO-FRIA.md` da base fica como está)
>
> **Forma alvo:** `Mobile Producao.dc.html`, no projeto Claude Design "Mobile app structure review" — telas A1–A11, I1–I9, V1, T1, E1/E2, L1. Medir junto dos alvos do `A1-alvos.md`: `producao--apontamento`, `producao--instalacao`, `producao--terminal`. O HTML **não** foi copiado: carrega `./support.js` e o bundle `_ds/…` daquele projeto e sozinho renderiza quebrado.

## O que o Cowork aplica na base

1. **`00-INDICE.md`, bloco json:** acrescentar em `decisoes` os 10 itens de `decisoes` abaixo; criar a chave `emendas` (E02, E03, E04, E05, E09, E11 → UC extras nas threads 02, 03, 04, 05, 09, 11); acrescentar em `threads` as threads 12–21. Ordem e bloqueios: §2 e §3 abaixo.
2. **`Producao.casos.md`:** anexar ao fim o corpo de `Producao-celular.casos.md` (UC-PCV-31..50) e acrescentar à tabela de Rastreabilidade as 20 linhas a seguir. Persona fica `—`: os UC do celular não a declaram.

| UC | Caso de uso | Persona | Vista | Prio | Protótipo |
|----|-------------|---------|-------|------|-----------|
| UC-PCV-31 | Dois cronômetros: máquina e pessoa | — | A1 A3 | must | ✅ |
| UC-PCV-32 | Custo real soma máquina, pessoa e terceiro | — | — | must valor | ✅ |
| UC-PCV-33 | Motivos vêm do cadastro do posto | — | A4 A5 | must | ✅ |
| UC-PCV-34 | QR de etapa alheia não aponta | — | A2 | must | ✅ |
| UC-PCV-35 | Deixar a máquina rodando | — | A3 | must | ✅ |
| UC-PCV-36 | Fecha pela saída do ponto | — | A7 | must | ✅ |
| UC-PCV-37 | Etiqueta por modelo da empresa | — | E1 E2 | must | ✅ |
| UC-PCV-38 | Chegada e veículo | — | I2 I7 | must | ✅ |
| UC-PCV-39 | Prova máxima do protocolo | — | I5 I6 | must | ✅ |
| UC-PCV-40 | Critério do piloto | — | — | should | ⬜ |
| UC-PCV-41 | Terminal fixo | — | T1 | must | ✅ |
| UC-PCV-42 | Não foi possível instalar | — | I4 | must | ✅ |
| UC-PCV-43 | Ferramentas por QR | — | I7 | should | ✅ |
| UC-PCV-44 | Visita vira orçamento | — | V1 | should | ✅ |
| UC-PCV-45 | Retalho com baixa própria | — | A11 | should valor | ✅ |
| UC-PCV-46 | Imprimir junto | — | A10 | should | ✅ |
| UC-PCV-47 | Pesquisa | — | I8 | should | ✅ |
| UC-PCV-48 | Terceiro por link | — | I9 | must | ✅ |
| UC-PCV-49 | Mídia pelo RIP | — | A9 | could | ✅ |
| UC-PCV-50 | Alertas no Início do app | — | L1 | should | ✅ |

---

## Extensão recebida (texto do handoff, sem edição)

# Produção no celular — playbook de PRs (extensão)

```json
{
  "modulo": "Producao",
  "extensao_de": "cowork-inbox/producao/playbook/00-INDICE.md",
  "gerado": "2026-10-15",
  "forma": "Mobile Producao.dc.html (projeto Mobile app structure review)",
  "variaveis": {
    "MOD": "Modules/Producao",
    "MPAGES": "Modules/Producao/Resources/js/Pages",
    "APP": "wagnerra23/oimpresso-app"
  },
  "decisoes": [
    {
      "id": "D-CUSTO",
      "resposta": "máquina e veículo custam por hora da máquina; resto por hora da pessoa",
      "respondida": true
    },
    {
      "id": "D-ETIQUETA",
      "resposta": "configurável: térmica 100×50 QR por etapa | A4 QR por OS",
      "respondida": true
    },
    {
      "id": "D-TERCEIRO",
      "resposta": "instalador terceirizado por link com código, sem conta",
      "respondida": true
    },
    {
      "id": "D-FOTO",
      "resposta": "guardar sem prazo; comprimir 1600px, original 90 dias",
      "respondida": true
    },
    {
      "id": "D-ASSINATURA",
      "resposta": "prova máxima: traço + OTP WhatsApp + geo/hora/aparelho + hash PDF",
      "respondida": true
    },
    {
      "id": "D-PONTO",
      "resposta": "sugestão: ponto nunca alterado; apontado pessoa ≤ presença; saída fecha cronômetro",
      "respondida": false,
      "bloqueia": [
        "E04"
      ]
    },
    {
      "id": "D-APARELHO",
      "resposta": "sugestão: terminal padrão nas máquinas + celular pessoal opcional",
      "respondida": false,
      "bloqueia": [
        "12"
      ]
    },
    {
      "id": "D-PILOTO-METRICA",
      "resposta": "sugestão: ≥90% etapas apontadas · ≤5% fechadas pelo turno · ±20% previsto×real · 100% protocolo",
      "respondida": false,
      "bloqueia": [
        "E11"
      ]
    },
    {
      "id": "D-TAXA-VISITA",
      "pergunta": "cliente ausente vira taxa de nova visita?",
      "respondida": false,
      "bloqueia": [
        "13"
      ]
    },
    {
      "id": "D-RIP",
      "pergunta": "qual RIP integrar primeiro (VersaWorks, Onyx, Caldera)?",
      "respondida": false,
      "bloqueia": [
        "20"
      ]
    }
  ],
  "emendas": [
    {
      "id": "E02",
      "emenda_thread": "02",
      "titulo": "Fundo: tipo_custo do posto (maquina|pessoa), apontamento de máquina separado do de pessoa, veículo como posto-máquina, chave de idempotência",
      "arquivo": "E02-fundo-custo.md",
      "ucs": [
        "UC-PCV-31",
        "UC-PCV-32"
      ]
    },
    {
      "id": "E03",
      "emenda_thread": "03",
      "titulo": "Cadastros: motivos de pausa e de refugo por posto, custo-hora da pessoa, veículos",
      "arquivo": "E03-cadastros-motivos.md",
      "ucs": [
        "UC-PCV-33"
      ]
    },
    {
      "id": "E04",
      "emenda_thread": "04",
      "titulo": "Celular: dois cronômetros, deixar rodando, pausa com motivo, QR recusa etapa alheia, fecha pela saída do ponto",
      "arquivo": "E04-apontamento.md",
      "ucs": [
        "UC-PCV-31",
        "UC-PCV-34",
        "UC-PCV-35",
        "UC-PCV-36"
      ]
    },
    {
      "id": "E05",
      "emenda_thread": "05",
      "titulo": "Etiqueta configurável E1 (QR por etapa) / E2 (QR por OS)",
      "arquivo": "E05-etiqueta.md",
      "ucs": [
        "UC-PCV-37"
      ]
    },
    {
      "id": "E09",
      "emenda_thread": "09",
      "titulo": "Instalação: chegada com local, fotos obrigatórias comprimidas, assinatura com OTP e hash, custo do veículo",
      "arquivo": "E09-instalacao-prova.md",
      "ucs": [
        "UC-PCV-38",
        "UC-PCV-39"
      ]
    },
    {
      "id": "E11",
      "emenda_thread": "11",
      "titulo": "Virada: critério de saída do piloto biz=1",
      "arquivo": "E11-piloto-metrica.md",
      "ucs": [
        "UC-PCV-40"
      ]
    }
  ],
  "threads": [
    {
      "id": "12",
      "titulo": "Modo terminal da PWA: preso à máquina, romaneio do dia, troca de operador por crachá QR ou PIN",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "12-terminal.md",
      "depende_threads": [
        "04"
      ],
      "depende_decisoes": [
        "D-APARELHO",
        "D-CUSTO"
      ],
      "prefixo": [
        "${MPAGES}/Producao/Terminal/",
        "${MOD}/Http/Controllers/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoTerminalContratoTest.php",
          "padrao": "UC-PCV-41"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md"
    },
    {
      "id": "13",
      "titulo": "Não foi possível instalar: motivo, foto obrigatória, volta à agenda e aviso ao balcão",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "13-nao-instalou.md",
      "depende_threads": [
        "09"
      ],
      "depende_decisoes": [
        "D-TAXA-VISITA"
      ],
      "prefixo": [
        "${MPAGES}/Producao/Instalacao/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoInstalacaoContratoTest.php",
          "padrao": "UC-PCV-42"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md"
    },
    {
      "id": "14",
      "titulo": "Ferramentas e veículo por QR: separar, sair, devolver",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "14-ferramentas.md",
      "depende_threads": [
        "09"
      ],
      "depende_decisoes": [
        "D-CUSTO"
      ],
      "prefixo": [
        "${MOD}/Database/",
        "${MPAGES}/Producao/Instalacao/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoFerramentasContratoTest.php",
          "padrao": "UC-PCV-43"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md"
    },
    {
      "id": "15",
      "titulo": "Visita técnica: fotos, medidas, superfície e acesso, envio para orçamento",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "15-visita.md",
      "depende_threads": [
        "03"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "${MPAGES}/Producao/Visita/",
        "${MOD}/Services/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoVisitaContratoTest.php",
          "padrao": "UC-PCV-44"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md · orçamento só por Service público do ComunicacaoVisual"
    },
    {
      "id": "16",
      "titulo": "Retalho de mídia com etiqueta QR e baixa pelo retalho",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "16-retalho.md",
      "depende_threads": [
        "08"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "${MOD}/Services/",
        "${MPAGES}/Producao/Apontamento/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoRetalhoContratoTest.php",
          "padrao": "UC-PCV-45"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md · estoque fracionado: reusar Service do Estoque"
    },
    {
      "id": "17",
      "titulo": "Imprimir junto: sugestão por mesma mídia e máquina, aceita pelo operador",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "17-imprimir-junto.md",
      "depende_threads": [
        "07"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "${MOD}/Services/",
        "${MPAGES}/Producao/Apontamento/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoLinhaContratoTest.php",
          "padrao": "UC-PCV-46"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md"
    },
    {
      "id": "18",
      "titulo": "Pesquisa de satisfação no link com código, alerta de nota baixa",
      "dono": "CL",
      "vaga": 5,
      "prs": 1,
      "arquivo": "18-pesquisa.md",
      "depende_threads": [
        "09",
        "10"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "Modules/ConsultaOs/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoPesquisaContratoTest.php",
          "padrao": "UC-PCV-47"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md"
    },
    {
      "id": "19",
      "titulo": "Instalador terceirizado por link: aceitar, executar I3–I5, gerar conta a pagar",
      "dono": "CL",
      "vaga": 5,
      "prs": 1,
      "arquivo": "19-terceiro.md",
      "depende_threads": [
        "09",
        "10"
      ],
      "depende_decisoes": [
        "D-TERCEIRO"
      ],
      "prefixo": [
        "Modules/ConsultaOs/",
        "${MOD}/Services/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoTerceiroContratoTest.php",
          "padrao": "UC-PCV-48"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md · conta a pagar só por Service público do Financeiro"
    },
    {
      "id": "20",
      "titulo": "Mídia confirmada pelo RIP (spike + 1 conector)",
      "dono": "CL",
      "vaga": 6,
      "prs": 1,
      "arquivo": "20-rip.md",
      "depende_threads": [
        "04"
      ],
      "depende_decisoes": [
        "D-RIP"
      ],
      "prefixo": [
        "${MOD}/Services/Rip/",
        "${MOD}/Tests/"
      ],
      "nao_toca": [
        "Modules/Repair/Http/",
        "Modules/ComunicacaoVisual/",
        "Modules/OficinaAuto/",
        "Modules/Ponto/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoRipContratoTest.php",
          "padrao": "UC-PCV-49"
        }
      ],
      "nota_provas": "casos em handoff/producao-celular/Producao-celular.casos.md · bloqueada até D-RIP"
    },
    {
      "id": "21",
      "titulo": "App das lojas: alertas de produção no Início",
      "dono": "CL",
      "vaga": 4,
      "prs": 2,
      "arquivo": "21-app-inicio.md",
      "depende_threads": [
        "06"
      ],
      "depende_decisoes": [],
      "prefixo": [
        "Modules/Producao/Http/Controllers/Api/",
        "${APP}:src/telas/Inicio.tsx"
      ],
      "nao_toca": [
        "${APP}:src/telas/Ponto*"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Tests/Feature/ProducaoResumoApiContratoTest.php",
          "padrao": "UC-PCV-50"
        }
      ],
      "nota_provas": "1 PR no ERP (rota) + 1 PR no oimpresso-app (tela 01)"
    }
  ]
}
```

## 1 · Como encaixa no playbook base
- **Emendas (E02–E11)** não abrem PR próprio: entram **na mesma thread base** (02, 03, 04, 05, 09, 11), que ganha os UC listados. Se a thread base já fechou, a emenda vira um PR de continuação com o mesmo prefixo.
- **Threads novas (12–21)** seguem o formato do base: uma por sessão limpa, `/onda Producao --thread NN`, fecham com `_saida-NN.md`.
- Copiar esta pasta para `cowork-inbox/producao/playbook/` (fichas) e os UC para o fim de `cowork-inbox/producao/Producao.casos.md`.

## 2 · Ordem
Base A1 → 01 → 02+E02 → 03+E03 → 04+E04 → 05+E05 → 06 → 07 → **17** · **12** · **21** → 08 → **16** · 09+E09 → **13** · **14** · **15** → 10 → **18** · **19** → 11+E11. **20** só depois de D-RIP.

## 3 · Bloqueios
| thread | espera |
|---|---|
| E04 (só a regra do ponto) | D-PONTO — sem resposta, fecha só pelo turno (UC-PCV-22 original) |
| 12 | D-APARELHO |
| 13 (só a taxa) | D-TAXA-VISITA — o resto da 13 anda |
| 20 | D-RIP |
| E11 | D-PILOTO-METRICA |

## 4 · Regras que valem para todas
- Inertia + React no ERP, mesma PWA (D2). Toque ≥ 48px, botão principal 56px, terminal 88px.
- Apontamento append-only; correção = lançamento novo com justificativa.
- `business_id` em toda leitura e escrita; tenant 98 × 99 nos testes; discriminante e controle negativo no recibo.
- **Nunca** escrever em `Modules/Ponto` — só ler a marcação de saída por Service público.
- Outros módulos (ComunicacaoVisual, Estoque, Financeiro) só por Service público; se não existir, pedir no `_saida-NN.md`.
