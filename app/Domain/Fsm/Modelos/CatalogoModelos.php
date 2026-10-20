<?php

declare(strict_types=1);

namespace App\Domain\Fsm\Modelos;

use App\Domain\Fsm\SideEffects\ConsumirEstoque;
use Illuminate\Support\Str;

/**
 * Catálogo dos 7 modelos de fluxo por porte × atividade (ADR 0435).
 *
 * Fonte: `prototipo-ui/cowork/Wagner/fluxos-data.jsx` (MODELOS, CASOS, encaixe). Cada modelo
 * monta processos por domínio; cada processo tem etapas na ordem e regras "ao entrar em
 * <etapa> → efeito" ligadas a um UC-FLX-*.
 *
 * Regra = [etapa, efeito, uc, efeito_classe, candidato]:
 * - `efeito_classe` é um side effect que já existe e faz o mesmo que o protótipo pede;
 * - null = planejado: a regra é só dado, não executa nada.
 * - `candidato` anota o side effect parecido que ainda não serve (ex.: ConsumirEstoque baixa a
 *   quantidade reservada; o UC-FLX-14 pede a consumida). Nunca é executado.
 *
 * Hoje nenhuma regra do protótipo tem side effect idêntico no ERP: todas ficam planejadas.
 * Quando se sabe por que uma regra planejada não pode executar, o motivo fica em PENDENCIAS e
 * "Usar modelo" grava junto da regra (AplicarModelo) — a regra diz o que falta, não só "planejado".
 */
final class CatalogoModelos
{
    public const PORTES = ['micro' => 'Micro', 'pequena' => 'Pequena', 'media' => 'Média', 'grande' => 'Grande'];

    public const ATIVIDADES = [
        'cv' => 'Comunicação visual', 'rapida' => 'Gráfica rápida e digital', 'offset' => 'Gráfica offset',
        'brindes' => 'Brindes e sublimação', 'textil' => 'Têxtil e DTF', 'instal' => 'Fachadas e instalação',
        'oficina' => 'Oficina mecânica',
    ];

    public const DOMINIOS = ['venda' => 'Venda', 'producao' => 'Produção', 'compra' => 'Compra', 'entrega' => 'Entrega', 'financeiro' => 'Financeiro'];

    /** Modelo das empresas que usam os processos de antes do catálogo (venda_com_producao e cia.). */
    public const LEGADO = 'legado';

    /** UC-FLX-* => [título, domínio] — `fluxos-data.jsx` CASOS. */
    public const CASOS = [
        'UC-FLX-01' => ['Orçamento aprovado vira OS sem redigitar', 'venda'],
        'UC-FLX-02' => ['Cliente aprova a arte por link', 'producao'],
        'UC-FLX-03' => ['Orçamento calcula custo, material e lucro sozinho', 'venda'],
        'UC-FLX-04' => ['Termômetro de acesso da proposta', 'venda'],
        'UC-FLX-05' => ['Comissão do vendedor nasce no faturamento', 'venda'],
        'UC-FLX-06' => ['Prazo de entrega calculado pela fila', 'venda'],
        'UC-FLX-07' => ['Etapas de produção vêm do cadastro do produto', 'producao'],
        'UC-FLX-08' => ['Cada etapa tem setor e responsável', 'producao'],
        'UC-FLX-09' => ['Apontamento pelo celular com QR Code', 'producao'],
        'UC-FLX-10' => ['Alocador decide quem e quando começa', 'producao'],
        'UC-FLX-11' => ['Dados da máquina entram sem digitação', 'producao'],
        'UC-FLX-12' => ['Romaneio de impressão peça a peça', 'producao'],
        'UC-FLX-13' => ['Simulador de aproveitamento de mídia', 'producao'],
        'UC-FLX-14' => ['Produção baixa o material consumido', 'producao'],
        'UC-FLX-15' => ['Previsto × realizado por OS', 'producao'],
        'UC-FLX-16' => ['Refugo e retrabalho com motivo', 'producao'],
        'UC-FLX-17' => ['Compra recebida atualiza custo e propõe preço', 'compra'],
        'UC-FLX-18' => ['Estoque fracionado com rastreio da mídia', 'compra'],
        'UC-FLX-19' => ['Estoque mínimo vira requisição de compra', 'compra'],
        'UC-FLX-20' => ['Custeio RKW e ponto de equilíbrio', 'financeiro'],
        'UC-FLX-21' => ['Agenda da equipe de instalação', 'entrega'],
        'UC-FLX-22' => ['Instalação com foto e assinatura', 'entrega'],
        'UC-FLX-23' => ['Protocolo de entrega', 'entrega'],
        'UC-FLX-24' => ['Cliente acompanha o status', 'entrega'],
        'UC-FLX-25' => ['Pesquisa de satisfação depois da entrega', 'entrega'],
        'UC-FLX-26' => ['Boleto pago emite a NF-e', 'financeiro'],
        'UC-FLX-27' => ['Uma OS, duas notas (NF-e + NFS-e)', 'financeiro'],
        'UC-FLX-28' => ['Produção só libera com o sinal pago', 'producao'],
        'UC-FLX-29' => ['Régua de cobrança do título vencido', 'financeiro'],
        'UC-FLX-30' => ['ISS retido ajusta o valor do título', 'financeiro'],
        'UC-FLX-31' => ['Natureza fiscal do item decide a nota', 'financeiro'],
        'UC-FLX-32' => ['NF-e em contingência quando a SEFAZ cai', 'financeiro'],
        'UC-FLX-33' => ['Orçamento aprovado guarda o custo do dia', 'venda'],
        'UC-FLX-34' => ['Orçamento aberto avisa que o custo mudou', 'venda'],
        'UC-FLX-35' => ['Preço negociado abaixo da margem pede aprovação', 'venda'],
        'UC-FLX-36' => ['OS em andamento avisa insumo que mudou de custo', 'producao'],
        'UC-FLX-37' => ['Material consumido leva o custo do dia da baixa', 'producao'],
        'UC-FLX-38' => ['Custo do insumo guarda o histórico por data', 'compra'],
    ];

    /**
     * UC => por que a regra "ao entrar" desse caso não executa no motor.
     *
     * UC-FLX-14 (balcão "Aprovado", brindes "Produção"): os dois são processos de venda sem OS.
     * - A peça vendida já baixa no núcleo quando a venda é finalizada (SellPosController,
     *   `decreaseProductQuantity` com `status = final`).
     * - O material consumido só baixa no apontamento da etapa da OS (ConsumoMaterialService,
     *   US-PCP-021), e estes modelos não têm processo de produção — CriarOsDaVenda não cria OS.
     * - O candidato ConsumirEstoque baixa a quantidade RESERVADA: ligado aqui, somaria uma 2ª baixa
     *   à da venda finalizada. Por isso não vira side effect.
     * O que é "material consumido" numa venda sem OS (a peça? o insumo pela ficha?) é decisão [W].
     */
    public const PENDENCIAS = [
        'UC-FLX-14' => 'Não executa: a peça vendida já baixa quando a venda é finalizada, e o material consumido só baixa no apontamento da OS, que este processo não tem. Ligar a baixa aqui baixaria duas vezes. Falta decidir o que é material consumido na venda sem OS.',
    ];

    private const ORDEM_PORTE = ['micro' => 0, 'pequena' => 1, 'media' => 2, 'grande' => 3];

    /**
     * @return array<string, array{nome:string, para:string, portes:list<string>, atividades:list<string>,
     *     processos:array<string, array{etapas:list<string>, regras:list<array{0:string,1:string,2:string,3:?string,4:?string}>}>,
     *     casos:list<string>, insp:list<string>, compra:array<string, string>}>
     */
    public static function modelos(): array
    {
        $baixa = ConsumirEstoque::class;

        $modelos = [
            'balcao' => [
                'nome' => 'Balcão enxuto', 'para' => 'Quem orça, imprime e entrega no mesmo dia, com poucas pessoas.',
                'portes' => ['micro'], 'atividades' => ['cv', 'rapida', 'brindes', 'textil'],
                'processos' => [
                    'venda' => ['etapas' => ['Orçamento', 'Aprovado', 'Pronto', 'Entregue', 'Pago'], 'regras' => [
                        ['Aprovado', 'Baixa o material do estoque', 'UC-FLX-14', null, $baixa],
                        ['Pago', 'Emite a NF-e sozinho', 'UC-FLX-26', null, null],
                    ]],
                    'compra' => ['etapas' => ['Pedido', 'Recebido', 'Pago'], 'regras' => [
                        ['Recebido', 'Custo = último preço; preço de venda não muda', 'UC-FLX-17', null, null],
                    ]],
                ],
                'casos' => ['UC-FLX-01', 'UC-FLX-03', 'UC-FLX-14', 'UC-FLX-17', 'UC-FLX-19', 'UC-FLX-23', 'UC-FLX-26', 'UC-FLX-33', 'UC-FLX-38'],
            ],
            'cv-pequena' => [
                'nome' => 'Comunicação visual · pequena', 'para' => 'Produção por OS com arte, aprovação do cliente e instalação própria.',
                'portes' => ['pequena'], 'atividades' => ['cv', 'instal'],
                'processos' => [
                    'venda' => ['etapas' => ['Orçamento', 'Aprovado', 'Faturado', 'Entregue', 'Pago'], 'regras' => [
                        ['Aprovado', 'Cria a OS com itens e arquivos', 'UC-FLX-01', null, null],
                        ['Faturado', 'NF-e da mercadoria + NFS-e do serviço', 'UC-FLX-27', null, null],
                    ]],
                    'producao' => ['etapas' => ['Arte', 'Aprovação do cliente', 'Impressão', 'Acabamento', 'Instalação', 'Entregue'], 'regras' => [
                        ['Aprovação do cliente', 'Link de aprovação; recusa volta para Arte', 'UC-FLX-02', null, null],
                        ['Impressão', 'Apontamento pelo celular', 'UC-FLX-09', null, null],
                        ['Entregue', 'Protocolo + pesquisa em 2 dias', 'UC-FLX-23', null, null],
                    ]],
                    'compra' => ['etapas' => ['Pedido', 'Em trânsito', 'Recebido', 'Conferido', 'Pago'], 'regras' => [
                        ['Recebido', 'Custo médio; propõe preço, aprova no recebimento', 'UC-FLX-17', null, null],
                        // O protótipo não liga esta regra a um caso de uso.
                        ['Conferido', 'Gera conta a pagar', '', null, null],
                    ]],
                ],
                'casos' => ['UC-FLX-01', 'UC-FLX-02', 'UC-FLX-03', 'UC-FLX-08', 'UC-FLX-09', 'UC-FLX-14', 'UC-FLX-15', 'UC-FLX-17', 'UC-FLX-21', 'UC-FLX-23', 'UC-FLX-25', 'UC-FLX-26', 'UC-FLX-27', 'UC-FLX-33', 'UC-FLX-34', 'UC-FLX-35', 'UC-FLX-37', 'UC-FLX-38'],
            ],
            'cv-pcp' => [
                'nome' => 'Comunicação visual · média com PCP', 'para' => 'Vários setores e turnos; o gargalo é decidir o que entra na máquina.',
                'portes' => ['media', 'grande'], 'atividades' => ['cv', 'instal', 'offset'],
                'processos' => [
                    'venda' => ['etapas' => ['Orçamento', 'Enviado', 'Aprovado', 'Faturado', 'Entregue', 'Pago'], 'regras' => [
                        ['Enviado', 'Termômetro de acesso + retorno em 48 h', 'UC-FLX-04', null, null],
                        ['Aprovado', 'Prazo calculado pela fila', 'UC-FLX-06', null, null],
                        ['Faturado', 'Comissão do vendedor', 'UC-FLX-05', null, null],
                    ]],
                    'producao' => ['etapas' => ['Pré-impressão', 'Aprovação do cliente', 'Liberada', 'Impressão', 'Acabamento', 'Expedição'], 'regras' => [
                        ['Pré-impressão', 'Aproveitamento de mídia', 'UC-FLX-13', null, null],
                        ['Liberada', 'Alocador escolhe quem e quando', 'UC-FLX-10', null, null],
                        ['Impressão', 'Romaneio peça a peça', 'UC-FLX-12', null, null],
                        ['Expedição', 'Previsto × realizado', 'UC-FLX-15', null, null],
                    ]],
                    'compra' => ['etapas' => ['Requisição', 'Pedido', 'Em trânsito', 'Recebido', 'Conferido', 'Pago'], 'regras' => [
                        ['Requisição', 'Nasce do estoque mínimo', 'UC-FLX-19', null, null],
                        ['Conferido', 'Custo médio; propostas vão à fila da Fabricação', 'UC-FLX-17', null, null],
                    ]],
                ],
                'casos' => ['UC-FLX-01', 'UC-FLX-02', 'UC-FLX-04', 'UC-FLX-05', 'UC-FLX-06', 'UC-FLX-07', 'UC-FLX-08', 'UC-FLX-09', 'UC-FLX-10', 'UC-FLX-12', 'UC-FLX-13', 'UC-FLX-15', 'UC-FLX-16', 'UC-FLX-17', 'UC-FLX-19', 'UC-FLX-21', 'UC-FLX-22', 'UC-FLX-24', 'UC-FLX-27', 'UC-FLX-33', 'UC-FLX-34', 'UC-FLX-35', 'UC-FLX-36', 'UC-FLX-37', 'UC-FLX-38'],
            ],
            'grafica' => [
                'nome' => 'Gráfica digital e offset', 'para' => 'Produção seriada; papel e tinta mudam de preço com frequência.',
                'portes' => ['pequena', 'media', 'grande'], 'atividades' => ['offset', 'rapida'],
                'processos' => [
                    'venda' => ['etapas' => ['Orçamento', 'Aprovado', 'Faturado', 'Entregue', 'Pago'], 'regras' => [
                        ['Aprovado', 'Cria a OP com o roteiro do produto', 'UC-FLX-07', null, null],
                    ]],
                    'producao' => ['etapas' => ['Pré-impressão', 'Prova', 'Impressão', 'Acabamento', 'Expedição'], 'regras' => [
                        ['Prova', 'Aprovação online da prova', 'UC-FLX-02', null, null],
                        ['Impressão', 'Dados da máquina sem digitação', 'UC-FLX-11', null, null],
                        ['Acabamento', 'Refugo com motivo', 'UC-FLX-16', null, null],
                    ]],
                    'compra' => ['etapas' => ['Pedido', 'Recebido', 'Conferido', 'Pago'], 'regras' => [
                        ['Recebido', 'Custo médio; preço acompanha o papel automaticamente', 'UC-FLX-17', null, null],
                    ]],
                    'financeiro' => ['etapas' => ['Emitido', 'Conferido', 'Conciliado', 'Liquidado'], 'regras' => [
                        ['Liquidado', 'Custeio RKW no fechamento do mês', 'UC-FLX-20', null, null],
                    ]],
                ],
                'casos' => ['UC-FLX-01', 'UC-FLX-02', 'UC-FLX-07', 'UC-FLX-11', 'UC-FLX-13', 'UC-FLX-15', 'UC-FLX-16', 'UC-FLX-17', 'UC-FLX-20', 'UC-FLX-33', 'UC-FLX-36', 'UC-FLX-37', 'UC-FLX-38'],
            ],
            'brindes' => [
                'nome' => 'Brindes, sublimação e DTF', 'para' => 'Catálogo com variação de cor e tamanho; compra por grade.',
                'portes' => ['micro', 'pequena'], 'atividades' => ['brindes', 'textil'],
                'processos' => [
                    'venda' => ['etapas' => ['Orçamento', 'Aprovado', 'Produção', 'Entregue', 'Pago'], 'regras' => [
                        ['Produção', 'Baixa a peça e o insumo pela grade', 'UC-FLX-14', null, $baixa],
                        ['Entregue', 'Cliente avisado por mensagem', 'UC-FLX-24', null, null],
                    ]],
                    'compra' => ['etapas' => ['Pedido', 'Recebido', 'Pago'], 'regras' => [
                        ['Recebido', 'Último preço; aplica o preço sozinho', 'UC-FLX-17', null, null],
                    ]],
                ],
                'casos' => ['UC-FLX-01', 'UC-FLX-03', 'UC-FLX-14', 'UC-FLX-17', 'UC-FLX-19', 'UC-FLX-24', 'UC-FLX-26', 'UC-FLX-33', 'UC-FLX-38'],
            ],
            'instalacao' => [
                'nome' => 'Fachadas e instalação sob encomenda', 'para' => 'Cada job é um projeto: visita, projeto, produção e equipe em campo.',
                'portes' => ['pequena', 'media'], 'atividades' => ['instal', 'cv'],
                'processos' => [
                    'venda' => ['etapas' => ['Visita técnica', 'Projeto', 'Orçamento', 'Aprovado', 'Faturado', 'Pago'], 'regras' => [
                        ['Aprovado', 'Prazo pela fila de produção', 'UC-FLX-06', null, null],
                        ['Faturado', 'NF-e + NFS-e', 'UC-FLX-27', null, null],
                    ]],
                    'entrega' => ['etapas' => ['Pronto', 'Agendado', 'Em campo', 'Instalado', 'Entregue'], 'regras' => [
                        ['Agendado', 'Agenda equipe e veículo', 'UC-FLX-21', null, null],
                        ['Instalado', 'Exige foto e assinatura', 'UC-FLX-22', null, null],
                        ['Entregue', 'Pesquisa de satisfação', 'UC-FLX-25', null, null],
                    ]],
                    'compra' => ['etapas' => ['Pedido', 'Recebido', 'Conferido', 'Pago'], 'regras' => [
                        ['Recebido', 'Custo médio; propõe preço, aprova no recebimento', 'UC-FLX-17', null, null],
                    ]],
                ],
                'casos' => ['UC-FLX-02', 'UC-FLX-06', 'UC-FLX-15', 'UC-FLX-17', 'UC-FLX-21', 'UC-FLX-22', 'UC-FLX-23', 'UC-FLX-25', 'UC-FLX-27', 'UC-FLX-33', 'UC-FLX-34', 'UC-FLX-35', 'UC-FLX-37', 'UC-FLX-38'],
            ],
            'oficina' => [
                'nome' => 'Oficina mecânica', 'para' => 'OS de veículo; a peça comprada destrava o serviço.',
                'portes' => ['micro', 'pequena', 'media'], 'atividades' => ['oficina'],
                'processos' => [
                    'venda' => ['etapas' => ['Diagnóstico', 'Aguardando peça', 'Em serviço', 'Pronto', 'Entregue'], 'regras' => [
                        ['Pronto', 'Cliente avisado', 'UC-FLX-24', null, null],
                        ['Entregue', 'Fotos do serviço', 'UC-FLX-22', null, null],
                    ]],
                    'compra' => ['etapas' => ['Pedido', 'Recebido', 'Pago'], 'regras' => [
                        ['Recebido', 'Libera as OS em Aguardando peça; custo médio', 'UC-FLX-17', null, null],
                    ]],
                ],
                'casos' => ['UC-FLX-17', 'UC-FLX-22', 'UC-FLX-24', 'UC-FLX-27'],
            ],
        ];

        // Inspirado em e regra de custo da compra (fluxos-data.jsx), exportados em CasosDoPrototipo.
        foreach (CasosDoPrototipo::MODELOS as $id => [$insp, $compra]) {
            $modelos[$id] += ['insp' => $insp, 'compra' => $compra];
        }

        return $modelos;
    }

    public static function modelo(string $id): ?array
    {
        return self::modelos()[$id] ?? null;
    }

    /** Encaixe 0–4 do protótipo: porte igual vale 2, porte vizinho 1, atividade igual 2. */
    public static function encaixe(string $modeloId, string $porte, string $atividade): int
    {
        $m = self::modelo($modeloId);
        if ($m === null || ! isset(self::ORDEM_PORTE[$porte])) {
            return 0;
        }
        $s = 0;
        if (in_array($porte, $m['portes'], true)) {
            $s += 2;
        } else {
            foreach ($m['portes'] as $p) {
                if (abs(self::ORDEM_PORTE[$p] - self::ORDEM_PORTE[$porte]) === 1) {
                    $s += 1;
                    break;
                }
            }
        }
        if (in_array($atividade, $m['atividades'], true)) {
            $s += 2;
        }

        return $s;
    }

    /** 4 = Recomendado, 3 = Serve, 1–2 = Parcial, 0 = não serve (rótulos do protótipo). */
    public static function rotuloEncaixe(int $encaixe): ?string
    {
        return match (true) {
            $encaixe >= 4 => 'Recomendado',
            $encaixe === 3 => 'Serve',
            $encaixe >= 1 => 'Parcial',
            default => null,
        };
    }

    /**
     * Modelos ordenados pelo encaixe (maior primeiro; empate mantém a ordem do catálogo).
     *
     * @return list<array{id:string, encaixe:int, rotulo:?string}>
     */
    public static function recomendar(string $porte, string $atividade): array
    {
        $lista = [];
        foreach (array_keys(self::modelos()) as $i => $id) {
            $lista[] = ['id' => $id, 'encaixe' => self::encaixe($id, $porte, $atividade), 'ordem' => $i];
        }
        usort($lista, fn ($a, $b) => [$b['encaixe'], $a['ordem']] <=> [$a['encaixe'], $b['ordem']]);

        return array_map(fn ($l) => ['id' => $l['id'], 'encaixe' => $l['encaixe'], 'rotulo' => self::rotuloEncaixe($l['encaixe'])], $lista);
    }

    /**
     * Casos de uso na ordem do protótipo, com tudo que a aba Casos de uso mostra.
     *
     * @return list<array{id:string, titulo:string, processo:string, etapa:string, regra:string,
     *     fontes:list<string>, porte:string, estado:string, us:string}>
     */
    public static function casos(): array
    {
        return array_map(fn ($c) => [
            'id' => $c[0], 'titulo' => $c[1], 'processo' => $c[2], 'etapa' => $c[3], 'regra' => $c[4],
            'fontes' => $c[5], 'porte' => $c[6], 'estado' => $c[7], 'us' => $c[8],
        ], CasosDoPrototipo::CASOS);
    }

    /** @return array<string, array{nome:string, url:string}> sistema pesquisado => nome e endereço */
    public static function fontes(): array
    {
        return array_map(fn ($f) => ['nome' => $f[0], 'url' => $f[1]], CasosDoPrototipo::FONTES);
    }

    /** @return array<string, array{rotulo:string, descricao:string}> porte => "Pequena", "6 a 20 pessoas" */
    public static function portes(): array
    {
        return array_map(fn ($p) => ['rotulo' => $p[0], 'descricao' => $p[1]], CasosDoPrototipo::PORTES);
    }

    /** @return array<string, array{rotulo:string, tom:string}> estado do caso no oimpresso => rótulo e tom */
    public static function estadosCaso(): array
    {
        return array_map(fn ($e) => ['rotulo' => $e[0], 'tom' => $e[1]], CasosDoPrototipo::ESTADOS);
    }

    /** Chave da etapa no motor: "Aprovação do cliente" → "aprovacao_do_cliente". */
    public static function chaveEtapa(string $etapa): string
    {
        return Str::slug($etapa, '_');
    }

    /** Chave do processo no motor: modelo `cv-pequena` + domínio `venda` → `flx_cv_pequena_venda`. */
    public static function chaveProcesso(string $modeloId, string $dominio): string
    {
        return 'flx_' . str_replace('-', '_', $modeloId) . '_' . $dominio;
    }
}
