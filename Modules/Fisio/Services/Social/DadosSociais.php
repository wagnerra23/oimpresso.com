<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Social;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\PatientSocial;

/**
 * Dados sociais do paciente (US-FISIO-061). Todos opcionais; onde cabe, "prefiro não informar".
 *
 * Finalidade (LGPD art. 11, dado sensível): mostrar à associação, em números agregados, quem é
 * atendido e se melhora, para editais, emendas e convênios (US-FISIO-062). Nada daqui vai ao
 * portal do paciente nem à exportação da pesquisa: só esta classe e o relatório agregado leem a
 * tabela `fisio_patient_social`.
 *
 * Nascimento, município, UF e bairro são do CONTATO (`contacts.dob`, `city`, `state`,
 * `neighborhood`), o mesmo endereço do cadastro de clientes: aqui só se lê e grava lá.
 *
 * As listas fechadas moram em OPCOES (código → rótulo) e servem de regra de validação, de rótulo
 * na tela e de agrupamento no relatório — uma fonte só.
 */
class DadosSociais
{
    public const LOG = 'fisio';

    public const NAO_INFORMAR = 'prefiro_nao_informar';

    /** Escolhas de lista fechada (coluna de `fisio_patient_social` → código → rótulo). */
    public const OPCOES = [
        'sexo' => [
            'feminino' => 'Feminino', 'masculino' => 'Masculino', 'intersexo' => 'Intersexo',
            self::NAO_INFORMAR => 'Prefiro não informar',
        ],
        'genero' => [
            'mulher' => 'Mulher', 'homem' => 'Homem', 'nao_binario' => 'Não binário', 'outro' => 'Outro',
            self::NAO_INFORMAR => 'Prefiro não informar',
        ],
        // Padrão do IBGE (autodeclaração).
        'raca_cor' => [
            'branca' => 'Branca', 'preta' => 'Preta', 'parda' => 'Parda', 'amarela' => 'Amarela', 'indigena' => 'Indígena',
            self::NAO_INFORMAR => 'Prefiro não informar',
        ],
        // Renda da família por mês, em salários mínimos.
        'renda_familiar' => [
            'ate_1_sm' => 'Até 1 salário mínimo', '1_a_2_sm' => 'De 1 a 2 salários mínimos', '2_a_3_sm' => 'De 2 a 3 salários mínimos',
            '3_a_5_sm' => 'De 3 a 5 salários mínimos', 'acima_5_sm' => 'Acima de 5 salários mínimos',
            self::NAO_INFORMAR => 'Prefiro não informar',
        ],
        'cadunico' => [
            'sim' => 'Sim, tem NIS/CadÚnico', 'nao' => 'Não tem', 'nao_sabe' => 'Não sabe',
            self::NAO_INFORMAR => 'Prefiro não informar',
        ],
        'situacao_trabalho' => [
            'trabalhando' => 'Trabalhando', 'afastado_inss' => 'Afastado pelo INSS', 'aposentado' => 'Aposentado',
            'desempregado' => 'Desempregado', 'outro' => 'Outro',
            self::NAO_INFORMAR => 'Prefiro não informar',
        ],
        'escolaridade' => [
            'sem_escolaridade' => 'Sem escolaridade', 'fundamental_incompleto' => 'Fundamental incompleto',
            'fundamental_completo' => 'Fundamental completo', 'medio_incompleto' => 'Médio incompleto',
            'medio_completo' => 'Médio completo', 'superior_incompleto' => 'Superior incompleto',
            'superior_completo' => 'Superior completo', 'pos_graduacao' => 'Pós-graduação',
            self::NAO_INFORMAR => 'Prefiro não informar',
        ],
        'diagnostico_por' => [
            'reumatologista' => 'Reumatologista', 'clinico_geral' => 'Clínico geral ou médico de família',
            'neurologista' => 'Neurologista', 'outro_medico' => 'Outro médico', 'nao_sabe' => 'Não sabe',
        ],
        'sintomas_ha' => [
            'menos_1_ano' => 'Menos de 1 ano', '1_a_3_anos' => 'De 1 a 3 anos', '3_a_5_anos' => 'De 3 a 5 anos',
            '5_a_10_anos' => 'De 5 a 10 anos', 'mais_10_anos' => 'Mais de 10 anos', 'nao_sabe' => 'Não sabe',
        ],
        'como_chegou' => [
            'ubs_caps' => 'Encaminhado pela UBS ou CAPS', 'indicacao' => 'Indicação de alguém',
            'procura_propria' => 'Procurou por conta própria', 'outro' => 'Outro',
        ],
        // Carteira de Identificação da Pessoa com Fibromialgia (Lei 15.176/2025).
        'cipf' => [
            'tem' => 'Já tem a carteira', 'em_andamento' => 'Pedido em andamento', 'nao_tem' => 'Não tem', 'nao_sabe' => 'Não sabe',
        ],
    ];

    /** Comorbidades mais comuns na fibromialgia (lista fechada; o resto vai em `comorbidades_outras`). */
    public const COMORBIDADES = [
        'depressao' => 'Depressão', 'ansiedade' => 'Ansiedade', 'disturbio_sono' => 'Distúrbio do sono',
        'enxaqueca' => 'Enxaqueca', 'intestino_irritavel' => 'Intestino irritável', 'hipertensao' => 'Hipertensão',
        'diabetes' => 'Diabetes', 'obesidade' => 'Obesidade', 'hipotireoidismo' => 'Hipotireoidismo',
    ];

    public const UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR',
        'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    /** Campos que moram no contato (`fisio` → coluna de `contacts`). */
    private const DO_CONTATO = ['nascimento' => 'dob', 'municipio' => 'city', 'uf' => 'state', 'bairro' => 'neighborhood'];

    /** @return array<string, mixed> regras do formulário; nenhum campo é obrigatório */
    public function regras(): array
    {
        $regras = [
            'nascimento' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'municipio' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', Rule::in(self::UFS)],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cns' => ['nullable', 'string', 'max:20', function (string $campo, mixed $valor, \Closure $falha) {
                if (! self::cnsValido((string) $valor)) {
                    $falha('Cartão SUS inválido: confira os 15 números.');
                }
            }],
            'diagnostico_cid' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z][0-9]{2}(\.?[0-9A-Za-z]{1,2})?$/'],
            'diagnostico_em' => ['nullable', 'date', 'before_or_equal:today'],
            'comorbidades' => ['nullable', 'array'],
            'comorbidades.*' => ['string', Rule::in(array_keys(self::COMORBIDADES))],
            'comorbidades_outras' => ['nullable', 'string', 'max:255'],
        ];
        foreach (self::OPCOES as $campo => $opcoes) {
            $regras[$campo] = ['nullable', 'string', Rule::in(array_keys($opcoes))];
        }

        return $regras;
    }

    /** @return array<string, mixed> o que a ficha mostra; nulo = não informado */
    public function doPaciente(Patient $paciente): array
    {
        $contato = DB::table('contacts')->where('business_id', $paciente->business_id)->where('id', $paciente->contact_id)
            ->first(['dob', 'city', 'state', 'neighborhood']);
        $social = PatientSocial::query()->where('patient_id', $paciente->id)->first();

        $dados = [
            'nascimento' => $contato?->dob ? substr((string) $contato->dob, 0, 10) : null,
            'municipio' => $contato?->city,
            'uf' => $contato?->state,
            'bairro' => $contato?->neighborhood,
        ];
        foreach ([...array_keys(self::OPCOES), 'cns', 'diagnostico_cid', 'comorbidades_outras'] as $campo) {
            $dados[$campo] = $social?->{$campo};
        }
        $dados['diagnostico_em'] = $social?->diagnostico_em?->toDateString();
        $dados['comorbidades'] = array_values((array) ($social?->comorbidades ?? []));

        return $dados;
    }

    /**
     * Grava só o que veio no corpo. Os do contato vão para `contacts` (filtrado pela empresa do
     * paciente); o resto para `fisio_patient_social`. O `activity_log` guarda quem, quando e QUAIS
     * campos mudaram, nunca os valores (dado sensível).
     *
     * @param  array<string, mixed>  $dados  já validado por `regras()`
     */
    public function gravar(Patient $paciente, array $dados, int $userId): void
    {
        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['dados_sociais' => 'Paciente bloqueado: os dados sociais não aceitam alteração.']);
        }

        $contato = [];
        foreach (self::DO_CONTATO as $campo => $coluna) {
            if (array_key_exists($campo, $dados)) {
                $contato[$coluna] = self::vazioParaNulo($dados[$campo]);
            }
        }
        $social = [];
        foreach (array_keys($this->regras()) as $campo) {
            if (isset(self::DO_CONTATO[$campo]) || str_contains($campo, '.') || ! array_key_exists($campo, $dados)) {
                continue;
            }
            $social[$campo] = match ($campo) {
                'cns' => self::vazioParaNulo(preg_replace('/\D/', '', (string) $dados['cns'])),
                'diagnostico_cid' => self::vazioParaNulo(strtoupper(trim((string) $dados['diagnostico_cid']))),
                'comorbidades' => array_values(array_unique((array) ($dados['comorbidades'] ?? []))),
                default => self::vazioParaNulo($dados[$campo]),
            };
        }

        DB::transaction(function () use ($paciente, $contato, $social, $userId) {
            if ($contato !== []) {
                DB::table('contacts')->where('business_id', $paciente->business_id)->where('id', $paciente->contact_id)
                    ->update([...$contato, 'updated_at' => now()]);
            }
            // Tudo vazio e ainda sem linha: não cria linha vazia (ninguém respondeu nada).
            $temResposta = array_filter($social, fn ($v) => $v !== null && $v !== []) !== [];
            if ($social !== [] && ($temResposta || PatientSocial::query()->where('patient_id', $paciente->id)->exists())) {
                PatientSocial::query()->updateOrCreate(
                    ['patient_id' => $paciente->id],
                    [...$social, 'business_id' => $paciente->business_id, 'updated_by' => $userId],
                );
            }
        });

        activity(self::LOG)
            ->performedOn($paciente)
            ->causedBy(User::query()->find($userId))
            ->event('dados_sociais_atualizados')
            ->withProperties(['campos' => array_values(array_keys(array_intersect_key($dados, [...self::DO_CONTATO, ...$social])))])
            ->log('dados_sociais_atualizados');
    }

    /**
     * Cartão Nacional de Saúde: 15 dígitos. Definitivo (começa com 1 ou 2) deriva do PIS com
     * resto 11; provisório (7, 8 ou 9) fecha a soma ponderada (15..1) em múltiplo de 11.
     * Pontuação e espaço são ignorados. Vazio é válido (o campo é opcional).
     */
    public static function cnsValido(string $valor): bool
    {
        $cns = preg_replace('/\D/', '', $valor) ?? '';
        if ($cns === '') {
            return true;
        }
        if (strlen($cns) !== 15) {
            return false;
        }

        $soma = 0;
        for ($i = 0; $i < 15; $i++) {
            $soma += (int) $cns[$i] * (15 - $i);
        }

        if (in_array($cns[0], ['1', '2'], true)) {
            $pis = substr($cns, 0, 11);
            $parcial = 0;
            for ($i = 0; $i < 11; $i++) {
                $parcial += (int) $pis[$i] * (15 - $i);
            }
            $dv = 11 - ($parcial % 11);
            if ($dv === 11) {
                $dv = 0;
            }
            $esperado = $dv === 10
                ? $pis.'001'.(string) ((11 - (($parcial + 2) % 11)) % 11)
                : $pis.'000'.$dv;

            return $cns === $esperado;
        }

        return in_array($cns[0], ['7', '8', '9'], true) && $soma % 11 === 0;
    }

    private static function vazioParaNulo(mixed $v): mixed
    {
        return $v === '' || $v === null ? null : $v;
    }
}
