<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Nfse;

use InvalidArgumentException;

/**
 * Código de Tributação Nacional da NFS-e (cTribNac) — R-NFSE-006.
 *
 * cTribNac = item (2) + subitem (2) da LC 116/2003 + desdobro nacional (2) — XSD v1.01,
 * TCCServ/cTribNac. A tabela vem do Anexo B oficial (aba LISTA.SERV.NAC.), gerada por
 * `scripts/fiscal/lista-servico-nacional.py` em
 * `Modules/NfeBrasil/Resources/data/nfse/lista-servico-nacional-v1.01.json`.
 *
 * Um subitem pode ter mais de um desdobro (62 dos 201 na v1.01): o 7.10 tem "vias e
 * logradouros públicos" (071001) e "imóveis, chaminés, piscinas" (071002). Nesse caso
 * quem emite escolhe; o sistema não chuta.
 */
final class ListaServicoNacional
{
    public const ARQUIVO = 'Modules/NfeBrasil/Resources/data/nfse/lista-servico-nacional-v1.01.json';

    /** @var array<string, string>|null cTribNac => descrição */
    private ?array $codigos = null;

    /** @return array<string, string> todos os cTribNac => descrição */
    public function todos(): array
    {
        if ($this->codigos === null) {
            $json = json_decode((string) file_get_contents(base_path(self::ARQUIVO)), true);
            $this->codigos = is_array($json['codigos'] ?? null) ? $json['codigos'] : [];
        }

        return $this->codigos;
    }

    /**
     * Desdobros nacionais do subitem do LC 116 ("7.10", "1.05", "17.06").
     *
     * @return array<string, string> cTribNac => descrição (vazio se o subitem não existe)
     */
    public function desdobros(string $lc116): array
    {
        $prefixo = $this->prefixo($lc116);
        if ($prefixo === null) {
            return [];
        }

        return array_filter($this->todos(), fn ($c) => str_starts_with((string) $c, $prefixo), ARRAY_FILTER_USE_KEY);
    }

    /**
     * cTribNac final da nota. Com escolha: confere que existe e é do mesmo subitem.
     * Sem escolha: só resolve se o subitem tiver um desdobro.
     *
     * @throws InvalidArgumentException subitem inexistente, escolha inválida, ou escolha faltando
     */
    public function resolver(string $lc116, ?string $escolhido = null): string
    {
        $opcoes = $this->desdobros($lc116);
        if ($opcoes === []) {
            throw new InvalidArgumentException("Código LC 116 {$lc116} não está na lista de serviços nacional.");
        }

        if ($escolhido !== null && $escolhido !== '') {
            if (! array_key_exists($escolhido, $opcoes)) {
                throw new InvalidArgumentException("O código de tributação nacional {$escolhido} não pertence ao item {$lc116} da LC 116.");
            }

            return $escolhido;
        }

        if (count($opcoes) > 1) {
            throw new InvalidArgumentException(
                "O item {$lc116} da LC 116 tem " . count($opcoes) . ' desdobros nacionais (' . implode(', ', array_keys($opcoes)) . '); escolha um.'
            );
        }

        return (string) array_key_first($opcoes);
    }

    /**
     * Subitens com mais de um desdobro, no formato da tela: "7.10" => [[codigo, descricao]].
     *
     * @return array<string, list<array{codigo: string, descricao: string}>>
     */
    public function subitensComVariosDesdobros(): array
    {
        $grupos = [];
        foreach ($this->todos() as $codigo => $descricao) {
            $chave = ((int) substr((string) $codigo, 0, 2)) . '.' . substr((string) $codigo, 2, 2);
            $grupos[$chave][] = ['codigo' => (string) $codigo, 'descricao' => $descricao];
        }

        return array_filter($grupos, fn (array $g) => count($g) > 1);
    }

    /** "7.10" → "0710"; null se não for item.subitem. */
    private function prefixo(string $lc116): ?string
    {
        if (! preg_match('/^(\d{1,2})\.(\d{1,2})$/', trim($lc116), $m)) {
            return null;
        }

        return str_pad($m[1], 2, '0', STR_PAD_LEFT) . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    }
}
