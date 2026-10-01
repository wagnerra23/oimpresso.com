---
id: requisitos-ponto-legal-rep-p
doc: LEGAL-REP-P
modulo: Ponto
status: rascunho-para-revisao-E
data: "2026-10-01"
autor: "sessão LEGAL REP-P [CL]"
revisora: "Eliana [E] — pendente"
decisor: "Wagner [W]"
related_adrs:
  - 0383-ponto-interno-nao-coleta-biometria
  - 0419-ponto-rep-p-escopo-ratificado-w10
  - 0413-ponto-fechamento-competencia-conformidade-relatorios-legais
  - 0420-ponto-aej-le-apuracao-e-falha-fechada
---

# REP-P do oimpresso: o que a Portaria MTP 671/2021 exige e o que falta

> **Não é parecer jurídico.** É um levantamento técnico para a Eliana [E] validar e o Wagner [W]
> decidir. Cada exigência traz o artigo e a fonte. Onde a fonte não é o texto oficial, isso
> está dito na linha.

## 0. Resposta curta

A lembrança estava certa: o REP-P precisa de **registro do programa no INPI** (art. 91) e o
desenvolvedor precisa entregar a cada empregador um **Atestado Técnico e Termo de
Responsabilidade** (art. 89, modelo do Anexo VII). Há uma terceira obrigação que não estava na
lembrança: o desenvolvedor assina o AFD com **certificado ICP-Brasil próprio** (art. 88), e o
comprovante entregue ao trabalhador é um **PDF assinado em PAdES** (art. 79-80).

O REP-P **não tem homologação nem certificação no Ministério do Trabalho**. O FAQ oficial diz
que ele "precisa apenas" do registro no INPI (pergunta 12). Logo, nenhuma descrição de loja pode
dizer "homologado" ou "certificado pelo MTE", nem depois que tudo estiver pronto.

Hoje o oimpresso **não cumpre** quatro exigências: INPI, atestado, assinatura ICP do AFD/AEJ e
comprovante ao trabalhador. O código já está preparado para a primeira: sem o número do INPI, o
AFD e o AEJ **recusam** a geração em vez de inventar um número.

## 1. Fontes

| Fonte | Tipo | Como foi lida |
|---|---|---|
| Portaria MTP nº 671/2021, arts. 74 a 101 | norma | compilação de texto integral em normaslegais.com.br. O DOU (in.gov.br) não respondeu em 2026-10-01. **Conferir no DOU antes de citar em contrato.** |
| [Perguntas e Respostas — Portaria 671/2021](https://www.gov.br/trabalho-e-emprego/pt-br/assuntos/inspecao-do-trabalho/fiscalizacao-do-trabalho/Perguntas%20e%20Respostas%20REP) | MTE, oficial | lida em 2026-10-01. Perguntas 7, 12, 17, 30, 33, 34, 40, 45, 46, 52 e 53 transcritas |
| [Guia básico de programa de computador — INPI](https://www.gov.br/inpi/pt-br/servicos/programas-de-computador/guia-basico) | INPI, oficial | lido em 2026-10-01 |
| Anexo IX da Portaria (requisitos do REP-P) | norma | **só por fonte secundária** (blogs de fornecedores de ponto). O texto literal não foi obtido. Itens marcados "(Anexo IX, a conferir)" |

Alterações posteriores: a compilação lida não traz nota de "redação dada por" nos arts. 74 a 101.
Isso **não prova** que não houve alteração. A Eliana deve conferir a versão compilada vigente.

## 2. O que a Portaria exige do REP-P

| Artigo | Exigência (resumo) | Fonte |
|---|---|---|
| 74 | O registro deve ser fiel. É vedado restringir horário de marcação, marcar automaticamente, exigir autorização prévia para hora extra e alterar o que o trabalhador registrou | compilação |
| 78 | REP-P é programa executado em servidor dedicado ou em nuvem, só para registro de jornada | compilação |
| 79 | O comprovante tem: título "Comprovante de Registro de Ponto do Trabalhador", NSR, empregador (nome e CNPJ/CPF), local de prestação, trabalhador (nome e CPF), data e hora, nº de registro no INPI, **hash SHA-256** (só no REP-P) e assinatura eletrônica | compilação |
| 80 | Comprovante pode ser eletrônico: PDF assinado, acesso pelo trabalhador após cada marcação | compilação + FAQ 40 |
| 81 | Gera o AFD (Anexo V), entregue ao auditor quando solicitado | compilação |
| 82-83 | O programa de tratamento gera o AEJ (Anexo VI) e o Espelho de Ponto | compilação |
| 84 | Espelho com identificação, horário contratual, marcações e jornadas; acesso eletrônico do trabalhador ao menos mensal | compilação |
| 85 | Empregador entrega os arquivos ao auditor em, no mínimo, 2 dias da solicitação | compilação |
| 88 | REP-P e programa de tratamento usam **certificado digital ICP-Brasil válido** | compilação |
| 89 + Anexo VII | **Atestado Técnico e Termo de Responsabilidade**, emitido pelo desenvolvedor, assinado pelo responsável técnico e pelo responsável legal, ambos **pessoas físicas**, com assinatura eletrônica qualificada | compilação + FAQ 7, 45, 46 |
| 91 | REP-P deve ter **certificado de registro de programa de computador no INPI** e atender ao Anexo IX | compilação + FAQ 12 |
| 101 | Observar a LGPD | compilação |
| Anexo IX | Armazenamento de Registro de Ponto (ARP) com redundância e alta disponibilidade; guardar inclusões, alterações, ajustes de relógio e eventos sensíveis sem apagar pelo prazo legal; relógio sincronizado com a Hora Legal Brasileira do Observatório Nacional, com variação de até 30 s | **secundária, a conferir** |

Esclarecimentos do FAQ oficial que mudam o desenho:

- **FAQ 33:** "O arquivo AFD gerado pelo REP-P ou REP-A deve ser assinado pelo fabricante/desenvolvedor do REP." O certificado é **do desenvolvedor**, não do empregador cliente.
- **FAQ 34:** o AEJ é assinado pelo desenvolvedor do Programa de Tratamento.
- **FAQ 30:** comprovante em PDF assinado em **PAdES**.
- **FAQ 40:** não precisa emitir na hora se o trabalhador tiver acesso eletrônico após cada marcação, com extração das **últimas 48 horas, no mínimo**.
- **FAQ 17:** no atestado, a versão pode ser escrita como "≥ 1.2" ou "a partir de <data>". Não é preciso um atestado por versão.
- **FAQ 46:** certificado A1 ou A3 serve para o atestado.
- **FAQ 52:** marcação offline **não** é obrigatória para o REP-P.
- **FAQ 53:** campos de data e hora levam o fuso (`AAAA-MM-ddThh:mm:00ZZZZZ`).

## 3. Exigência × estado do oimpresso

Estado medido no `main` em 2026-10-01 (commit `4399254d9`).

| # | Exigência | Cumpre? | Evidência | O que falta | Quem faz |
|---|---|---|---|---|---|
| 1 | Registro no INPI (art. 91) | ❌ | `config/ponto_afd.php` lê `PONTO_REP_P_INPI` sem default; sem ele o AFD e o AEJ recusam (`ReportService`, `AejService`). O `.env` de produção não foi conferido | Pedido no INPI (§4.1) e depois gravar o número no `.env` | [W] titular e assinatura · [E] revisa a Declaração de Veracidade · dev grava o `.env` |
| 2 | Atestado Técnico e Termo de Responsabilidade (art. 89, Anexo VII) | ❌ | não existe no repo | Redigir no modelo do Anexo VII, assinar e entregar a cada empresa cliente (§4.2) | [E] redige · [W] assina como responsável legal · responsável técnico assina |
| 3 | AFD assinado em CAdES `.p7s` pelo desenvolvedor (art. 88 + FAQ 33) | ❌ | `ReportService` escreve a linha reservada `ASSINATURA_DIGITAL_EM_ARQUIVO_P7S` e **não** gera o `.p7s`. A ADR 0413 D2 deixou a assinatura ICP fora e exige ADR nova | Certificado ICP-Brasil da empresa (e-CNPJ A1) + ADR nova + implementação | [W] compra o certificado e decide a ADR · dev implementa · segredo no Vaultwarden |
| 4 | AEJ assinado pelo desenvolvedor do programa de tratamento (FAQ 34) | ❌ | `AejService` gera o leiaute 002 e a mesma linha de assinatura, sem `.p7s` | O mesmo certificado do item 3 resolve | idem 3 |
| 5 | Comprovante ao trabalhador em PDF PAdES com hash SHA-256 e nº INPI (arts. 79-80, FAQ 30/40) | ❌ | US-PONTO-010 está `_pendente_`. A tela `/ponto/mobile` mostra o NSR devolvido, não um comprovante | Gerar o PDF por marcação, assinar em PAdES, deixar acessível no app (mínimo 48 h) | dev · [E] confere o conteúdo contra o art. 79 · depende dos itens 1 e 3 |
| 6 | Registro fiel, sem alteração (art. 74) | 🟡 | marcação append-only com NSR e hash encadeado (`MarcacaoService`); correção só por intercorrência | **Questão para [E]:** o app **recusa** marcação com GPS acima de 500 m ou relógio fora de 30 s (ADR 0419, decisão W5). É preciso avaliar se recusar a marcação conflita com o caput do art. 74 ("registrar fielmente"). Não é restrição de horário, mas impede o registro | [E] avalia · [W] decide |
| 7 | Hora legal ±30 s (Anexo IX, a conferir) | 🟡 | o horário gravado é o do servidor (`'momento' => now()` em `MobileMarcacaoService`). O sincronismo NTP do servidor com o Observatório Nacional **não foi medido** | Medir o relógio do servidor de produção e documentar a fonte de tempo | dev (infra) |
| 8 | ARP redundante, alta disponibilidade, trilha de ajustes de relógio e eventos sensíveis (Anexo IX, a conferir) | 🟡 | imutabilidade por trigger MySQL e override no Model. Backup/redundância do banco de produção e trilha de "ajuste de relógio" **não verificados** | Conferir o texto do Anexo IX e mapear item a item | dev · [E] |
| 9 | AFD (art. 81, Anexo V) | 🟡 | gerador por colaborador existe (#8224), recusa sem INPI e CNPJ | Depende dos itens 1 e 3 | — |
| 10 | AEJ + Espelho (arts. 82-84) | 🟡 | AEJ leiaute 002 existe (#8242, ADR 0420), recusa sem identidade do PTRP. "Meu espelho" no app atende o acesso mensal | Configurar `PONTO_PTRP_*` e assinar (item 4) | [W] fornece os dados · dev configura |
| 11 | Entrega ao auditor em 2 dias (art. 85) | ➖ | obrigação do **empregador**, não do programa | Orientar o cliente no onboarding | [E] texto · suporte |
| 12 | Sem biometria (LGPD art. 5º II e 11) | ✅ | ADR 0383 | — | — |
| 13 | LGPD (art. 101) — localização e dados do trabalhador | 🟡 | sem biometria. Coleta GPS no momento da marcação | Política de privacidade do app (exigida pelas duas lojas) e aviso de coleta de localização | [E] redige |
| 14 | Marcação offline | ✅ não obrigatória | FAQ 52 | — | — |

Divergência de canon encontrada, **não corrigida aqui**: a US-PONTO-001 do `SPEC.md` lista
"Comprovante PDF gerado com QR Code" como critério e está marcada `done`, enquanto a US-PONTO-010
diz que o comprovante está pendente. O código confirma a US-PONTO-010. E o Non-Goal "REP-P
certificado terceiros" do SPEC sugere que o REP-P seria de terceiro, o que a ADR 0419 desmente.
Os dois ajustes ficam para um PR do SPEC.

## 4. Passo a passo do que falta

### 4.1 Registro do programa no INPI ([W] + [E])

Fonte: guia oficial do INPI. O registro sai em até 10 dias após o pedido e vale 50 anos a partir
da criação do programa.

1. **Decidir o titular.** É o CNPJ da empresa desenvolvedora do oimpresso. Esse CNPJ é o mesmo que
   vai em `PONTO_REP_P_DESENVOLVEDOR_CNPJ`, no AFD e no atestado. Decisão de [W].
2. **Decidir o escopo do registro.** O art. 78 define o REP-P como o programa no servidor; o app
   da loja é só a interface. Sugestão a validar com [E]: registrar como "oimpresso Ponto — REP-P"
   abrangendo `Modules/Ponto` (servidor) e a tela do colaborador. Listar como autores as pessoas
   físicas que escreveram o código.
3. **Certificado digital.** O e-Software só aceita assinatura qualificada ICP-Brasil. Gov.br e
   ACOAB **não** servem. Use o mesmo e-CNPJ do item 4.3, ou o e-CPF de quem assina.
4. **Cadastro no e-INPI** com os dados do titular.
5. **Emitir e pagar a GRU, código 730** (pedido de registro de programa de computador). O valor
   está na tabela vigente do INPI. Há desconto para ME, EPP e MEI. Guarde o número da GRU.
6. **Baixar a Declaração de Veracidade (DV)** no sistema da GRU, conferir com [E] e assinar
   digitalmente.
7. **Gerar o resumo hash do código-fonte.** O código **não** é enviado ao INPI, só o hash.
   Procedimento sugerido:
   ```bash
   git archive --format=tar <tag-da-versao> Modules/Ponto > rep-p-fonte.tar
   ```
   Depois, calcular o hash do arquivo com o algoritmo que o formulário do e-Software pedir. Guardar
   o `.tar`, a tag e o hash em local seguro: é a prova de qual código foi registrado.
8. **Preencher o e-Software** com título, data de criação, linguagens, autores, titular, hash e DV.
9. **Acompanhar a publicação** na Revista da Propriedade Industrial, seção Programas de Computador.
   Baixar o certificado no portal do INPI.
10. **Gravar o número** em `PONTO_REP_P_INPI` (até 17 caracteres) no `.env` de produção. Ver
    `config/ponto_afd.php`.

Pergunta para [E]: o guia oficial não diz se cada versão nova exige novo registro. O FAQ 17 do MTE
aceita "versão ≥ X" no atestado. A leitura provável é um registro só para o programa, mas cabe
confirmar.

### 4.2 Atestado Técnico e Termo de Responsabilidade ([E] + [W])

1. Obter o **modelo do Anexo VII** no texto oficial da Portaria. Ele não foi transcrito aqui.
2. Preencher com: desenvolvedor (razão social, CNPJ), programa (nome, nº INPI, versão no formato
   "≥ 1.0" ou "a partir de <data>", conforme FAQ 17), declaração de conformidade com a Seção.
3. Se o modelo também cobrir o **programa de tratamento** (AEJ e espelho), declarar os dois. Se não
   cobrir, emitir um atestado para cada. [E] decide pela leitura do Anexo VII.
4. Assinar em PDF com **assinatura eletrônica qualificada de pessoa física**, A1 ou A3:
   responsável técnico **e** responsável legal ([W]). Não usar assinatura do e-CNPJ: o art. 89 §2º
   exige pessoa física (FAQ 45).
5. Entregar o PDF assinado a **cada empresa cliente** que usar o REP-P. Sugestão: disponibilizar
   para download na tela de Configurações do Ponto, sempre na versão vigente.
6. Reemitir quando mudar o responsável técnico ou legal, ou quando a declaração de versão deixar de
   cobrir o programa.

### 4.3 Certificado ICP-Brasil para assinar o AFD e o AEJ ([W] + dev)

1. [W] compra um **e-CNPJ A1** da empresa desenvolvedora. O A1 é arquivo e pode ficar no servidor;
   o A3 exige token físico e não serve para assinatura automática.
2. Guardar o certificado e a senha no **Vaultwarden**, nunca no git. Ver
   `memory/_INDEX-SECRETS.md`.
3. Abrir **ADR nova**, porque a ADR 0413 D2 deixou a assinatura ICP fora e disse que isso exige
   decisão própria.
4. Dev implementa a assinatura CAdES destacada (`.p7s`) do AFD e do AEJ e a PAdES do comprovante.
5. Renovar o certificado antes de vencer. A1 vale 1 ano.

### 4.4 Comprovante ao trabalhador (dev, depois de 4.1 e 4.3)

Fechar a US-PONTO-010: PDF por marcação com todos os campos do art. 79, assinado em PAdES, acessível
no app sem precisar pedir, com no mínimo as últimas 48 horas. CPF ausente deve recusar, como no AEJ
(ADR 0420: dado legal ausente é recusa).

## 5. O que pode e o que não pode na descrição das lojas

Vale para Google Play e App Store, na descrição, nas capturas e no texto de permissão.

**Não pode, em nenhum momento:**

- "homologado pelo Ministério do Trabalho", "certificado pelo MTE", "aprovado pelo governo". O
  REP-P não passa por homologação (FAQ 12). A frase é falsa mesmo depois do INPI.
- "certificado INMETRO". Isso é do REP-C.
- "100% conforme a Portaria 671" ou "garante segurança jurídica". Conformidade depende também do
  uso pelo empregador (art. 85, configuração, escala).
- "evita processos trabalhistas" ou "prova incontestável em juízo".
- "reconhecimento facial", "biometria", "selfie". Não existem (ADR 0383).

**Não pode enquanto os itens 1 a 5 da §3 estiverem abertos:**

- "REP-P registrado no INPI", "em conformidade com a Portaria 671/2021", "comprovante de ponto
  assinado digitalmente".

**Pode, já hoje:**

- "Registre entrada, saída e intervalos pelo celular."
- "A marcação usa a localização do aparelho no momento do registro. Sinal de GPS fraco impede a
  marcação."
- "Consulte o seu espelho do mês e envie justificativas ao gestor."
- "As marcações não podem ser apagadas nem alteradas. Correções são pedidas ao gestor."
- "Não coletamos foto nem biometria."
- "Uso exclusivo de colaboradores de empresas clientes do oimpresso." O app depende de login da
  empresa.

**Pode, depois de fechar os itens 1 a 5:**

- "Registrador Eletrônico de Ponto via Programa (REP-P), programa registrado no INPI sob o nº
  <número>, desenvolvido conforme a Portaria MTP nº 671/2021."

**Obrigatório pelas lojas:** URL da política de privacidade explicando a coleta de localização
(quando, para quê, por quanto tempo, quem vê) e o texto de permissão de localização no sistema do
celular. Coletar localização **só no momento da marcação**, nunca em segundo plano: localização em
segundo plano exige justificativa extra nas duas lojas e não é necessária aqui. [E] redige a
política.

## 6. Decisões pendentes

| # | Pergunta | Quem |
|---|---|---|
| L1 | Qual CNPJ é o titular do registro e o desenvolvedor no AFD? | [W] |
| L2 | Escopo do registro no INPI (só servidor, ou servidor e app) e lista de autores | [W] + [E] |
| L3 | Recusar marcação por GPS ou relógio conflita com o art. 74? | [E] avalia, [W] decide |
| L4 | Um atestado cobre REP-P e programa de tratamento, ou dois? (leitura do Anexo VII) | [E] |
| L5 | Comprar e-CNPJ A1 e abrir a ADR da assinatura ICP (emenda à 0413 D2) | [W] |
| L6 | Conferir no DOU o texto vigente dos arts. 74-101 e o Anexo IX, e se houve alteração | [E] |

Até L1, L5 e o registro no INPI, o app pode ir às lojas só com o texto da coluna "pode, já hoje".
