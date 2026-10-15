<?php

declare(strict_types=1);

namespace App\Services\AppLojas;

use Illuminate\Support\Facades\DB;

/**
 * Ajustes da empresa do app das lojas — fonte única de chave → tipo → padrão.
 *
 * Origem: handoff `propostas-app/AJUSTES-DA-EMPRESA.md` (P1–P9). Cada ajuste é uma chave `app_*` em
 * `business.pos_settings`, no mesmo padrão do `bloquear_venda_preco_zero_app` da venda rápida: sem a
 * chave gravada, vale o padrão daqui. O app lê o bloco `ajustes` de `GET /api/app/inicio`, que vem
 * sempre com TODAS as chaves.
 *
 * Quem lê um ajuste chama `valor($bizId, 'app_…')` ou `paraEmpresa($bizId)`; nunca lê `pos_settings`
 * direto. Chave nova entra só em `CHAVES`. Recusar o que o ajuste proíbe é papel de cada rota de
 * escrita (o app só esconde ou mostra).
 *
 * Tier 0 (ADR 0093): o business_id vem sempre de quem chama (o usuário do token ou da sessão).
 */
final class AjustesDaEmpresa
{
    /**
     * tipo `bool` ou `lista` (lista de strings). `rotulo`/`ajuda` vão para a aba "App das lojas" das
     * configurações; `na_tela` false = a chave existe, mas a aba ainda não a edita.
     *
     * @var array<string, array{tipo: 'bool'|'lista', padrao: bool|list<string>, proposta: string, rotulo: string, ajuda: string, na_tela: bool}>
     */
    public const CHAVES = [
        'app_entrega_exige_assinatura' => [
            'tipo' => 'bool', 'padrao' => false, 'proposta' => 'P3', 'na_tela' => true,
            'rotulo' => 'Entrega exige assinatura de quem recebe',
            'ajuda' => 'Ligado, o app não oferece "Recebido sem assinatura".',
        ],
        'app_entrega_exige_localizacao' => [
            'tipo' => 'bool', 'padrao' => false, 'proposta' => 'P3', 'na_tela' => true,
            'rotulo' => 'Entrega exige a localização do celular',
            'ajuda' => 'Ligado, sem GPS a entrega não é registrada.',
        ],
        'app_acoes_fsm_liberadas' => [
            // Vazio = todas as ações seguras (sem efeito em valor ou estoque). A lista segura e a tela
            // que escolhe dentro dela são do PR da ação do pedido (P1).
            'tipo' => 'lista', 'padrao' => [], 'proposta' => 'P1', 'na_tela' => false,
            'rotulo' => 'Ações do pedido liberadas no app',
            'ajuda' => 'Vazio libera todas as ações seguras.',
        ],
        'app_anexos_camera' => [
            'tipo' => 'bool', 'padrao' => true, 'proposta' => 'P2', 'na_tela' => true,
            'rotulo' => 'Anexar foto tirada na hora',
            'ajuda' => 'Desligado, o anexo da OS só vem da galeria.',
        ],
        'app_apontamento_exige_qr' => [
            'tipo' => 'bool', 'padrao' => false, 'proposta' => 'P8', 'na_tela' => true,
            'rotulo' => 'Apontamento só pelo QR da etiqueta',
            'ajuda' => 'Ligado, o app não deixa digitar o número do pedido.',
        ],
        'app_orcamento_rapido' => [
            'tipo' => 'bool', 'padrao' => false, 'proposta' => 'P4', 'na_tela' => true,
            'rotulo' => 'Orçamento rápido no app',
            'ajuda' => 'Ligado, aparece "+ Novo" em Orçamentos.',
        ],
        'app_atendimento_atalhos' => [
            'tipo' => 'bool', 'padrao' => true, 'proposta' => 'P5', 'na_tela' => true,
            'rotulo' => 'Atalhos na conversa do atendimento',
            'ajuda' => 'Orçamento, status e arte direto na conversa. O consentimento LGPD vale sempre.',
        ],
        'app_comissao_vendedor_ve_propria' => [
            'tipo' => 'bool', 'padrao' => true, 'proposta' => 'P6', 'na_tela' => true,
            'rotulo' => 'Vendedor vê a própria comissão',
            'ajuda' => 'Desligado, só quem tem permissão de ver comissões.',
        ],
        'app_biometria_login' => [
            'tipo' => 'bool', 'padrao' => true, 'proposta' => 'P9', 'na_tela' => true,
            'rotulo' => 'Entrar no app com biometria',
            'ajuda' => 'Desligado, a opção some em Conta. O ponto nunca usa biometria.',
        ],
    ];

    /** @return array<string, bool|list<string>> chave → padrão */
    public static function padroes(): array
    {
        return array_map(fn (array $c) => $c['padrao'], self::CHAVES);
    }

    /**
     * Todos os ajustes da empresa: o gravado em `pos_settings`, normalizado, e o padrão onde falta.
     *
     * @return array<string, bool|list<string>>
     */
    public static function paraEmpresa(int $bizId): array
    {
        $pos = json_decode((string) DB::table('business')->where('id', $bizId)->value('pos_settings'), true);

        return self::completar(is_array($pos) ? $pos : []);
    }

    /** Um ajuste da empresa. Chave fora de `CHAVES` é erro de programação. */
    public static function valor(int $bizId, string $chave): bool|array
    {
        if (! isset(self::CHAVES[$chave])) {
            throw new \InvalidArgumentException("Ajuste do app desconhecido: {$chave}");
        }

        return self::paraEmpresa($bizId)[$chave];
    }

    /**
     * @param  array<string, mixed>  $pos  `pos_settings` decodificado
     * @return array<string, bool|list<string>>
     */
    public static function completar(array $pos): array
    {
        $out = [];
        foreach (self::CHAVES as $chave => $c) {
            $out[$chave] = array_key_exists($chave, $pos) ? self::normalizar($c, $pos[$chave]) : $c['padrao'];
        }

        return $out;
    }

    /**
     * Para o salvar das configurações (substituição total de `pos_settings`): chave `app_*` ausente no
     * envio mantém a gravada; presente é normalizada. Assim a aba que não edita uma chave não a apaga.
     *
     * @param  array<string, mixed>  $novo  o `pos_settings` que chegou
     * @param  array<string, mixed>  $gravado  o `pos_settings` que está no banco
     * @return array<string, mixed>
     */
    public static function aoSalvar(array $novo, array $gravado): array
    {
        foreach (self::CHAVES as $chave => $c) {
            if (array_key_exists($chave, $novo)) {
                $novo[$chave] = self::normalizar($c, $novo[$chave]);
            } elseif (array_key_exists($chave, $gravado)) {
                $novo[$chave] = self::normalizar($c, $gravado[$chave]);
            }
        }

        return $novo;
    }

    /** Valor inválido volta ao padrão, nunca vira outro tipo. */
    private static function normalizar(array $c, mixed $v): bool|array
    {
        if ($c['tipo'] === 'bool') {
            $b = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $b ?? $c['padrao'];
        }

        if (! is_array($v)) {
            return $c['padrao'];
        }
        $itens = array_filter($v, fn ($i) => is_string($i) && preg_match('/^[a-z0-9_]{1,80}$/', $i));

        return array_values(array_unique($itens));
    }
}
