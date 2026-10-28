<?php

declare(strict_types=1);

namespace App\Services\Documentacao;

/**
 * Lê os pedidos de clientes, as decisões [W] e o contrato (casos de uso com id) de cada tela a
 * partir dos `*.casos.md` versionados — os dados da página "Casos de uso por tela".
 *
 * É a versão PHP de `scripts/casos-backlog/leitor.mjs` + `montarDados()` de `gerar.mjs`. Existe
 * porque a página mora dentro do sistema e lê os arquivos na hora (ADR 0256): o Hostinger não
 * tem `node` no caminho do PHP, e gerar um JSON no deploy deixaria a página parada nos pushes que
 * só mexem em `.md` e vão pelo sync leve (que faz `git reset` mas não publica bundle).
 *
 * DOIS LEITORES, UMA SAÍDA: `scripts/casos-backlog/paridade-php.test.mjs` roda este arquivo
 * (`dados.php`) e o `gerar.mjs --json` sobre o repositório inteiro e exige o MESMO resultado,
 * campo a campo. Mudou o formato num lado só, o teste cai. Por isso a classe não depende do
 * Laravel: o teste a carrega com `php` puro, sem banco.
 *
 * Diferença deliberada: o Node lista os arquivos com `git ls-files`; aqui é um passeio no disco
 * (o PHP de produção não chama `git`). Num checkout limpo as duas listas são a mesma — é o que
 * o teste de paridade confirma no CI.
 */
final class CasosPorTela
{
    public const REPO_URL = 'https://github.com/wagnerra23/oimpresso.com/blob/main/';

    /** Pastas que nunca têm casos.md de tela e custam caro de varrer. */
    private const NAO_VARRER = ['.git', 'node_modules', 'vendor', 'storage', 'public', 'bootstrap', 'prototipo-ui'];

    // O `.` do JavaScript não casa \r nem os separadores U+2028/U+2029; o do PCRE casa. Os
    // padrões abaixo usam esta classe no lugar do ponto para os dois leitores lerem igual.
    private const PONTO = '[^\r\n\x{2028}\x{2029}]';

    public function __construct(private readonly string $raiz) {}

    /**
     * Os dados que a página desenha, no mesmo formato de `montarDados()` (sem `geradoEm`).
     *
     * @return array{resumo: array<string,int>, areas: list<array>, telas: list<array>, casos: list<array>, regras: list<array>, decisoes: list<array>, contratos: list<array>, proximoPedido: string, problemas: list<string>}
     */
    public function dados(): array
    {
        $mapa = json_decode((string) file_get_contents($this->raiz . '/scripts/casos-backlog/telas.json'), true, 512, JSON_THROW_ON_ERROR);
        $telasMapa = $mapa['telas'];
        $arquivos = $this->arquivosCasos();

        [$pedidos, $decisoes] = $this->lerRepositorio($arquivos);
        $regras = $this->lerRegras();
        $ids = array_flip(array_column($regras, 'id'));
        $problemas = [];
        $vazio = ['tipo' => '', 'texto' => '', 'nota' => ''];

        $casos = [];
        foreach ($pedidos as $p) {
            preg_match_all('/UC-FLX-\d+/', ($p['ancora'] ?? '') . ' ' . ($p['titulo'] ?? ''), $m);
            $regrasDoCaso = array_values(array_unique($m[0]));
            foreach ($regrasDoCaso as $r) {
                if (! isset($ids[$r])) {
                    $problemas[] = "{$p['id']} cita {$r}, que não existe no catálogo";
                }
            }
            $casos[] = [
                'id' => $p['id'], 'n' => (int) substr($p['id'], 2), 'titulo' => $p['titulo'], 'marcas' => $p['marcas'] ?? [],
                'texto' => $p['texto'] ?? '', 'aceite' => $p['aceite'] ?? '', 'fazer' => $p['fazer'] ?? '', 'ancora' => $p['ancora'] ?? '',
                'hoje' => $p['hoje'] ?? $vazio, 'cv' => $p['cv'] ?? $vazio, 'clientes' => $p['clientes'] ?? [], 'tags' => $p['tags'] ?? [],
                'estagio' => $p['estagio'], 'uc' => $p['uc'] ?? '', 'tela' => self::chaveDaTela($p['arquivo']),
                'arquivo' => $p['arquivo'], 'linha' => $p['linha'], 'regras' => $regrasDoCaso,
            ];
        }

        $porId = array_flip(array_column($casos, 'id'));
        $decs = [];
        foreach ($decisoes as $d) {
            foreach ($d['destrava'] ?? [] as $x) {
                if (! isset($porId[$x])) {
                    $problemas[] = "{$d['id']} destrava {$x}, que não está no git";
                }
            }
            $decs[] = [
                'id' => $d['id'], 'pergunta' => $d['pergunta'], 'estado' => ($d['estado'] ?? '') !== '' ? $d['estado'] : 'Em aberto',
                'texto' => $d['texto'] ?? '', 'opcoes' => $d['opcoes'] ?? [],
                'casos' => array_map(static fn (string $x): int => (int) substr($x, 2), $d['destrava'] ?? []),
                'arquivo' => $d['arquivo'], 'linha' => $d['linha'],
            ];
        }

        $telas = [];
        foreach ($casos as $c) {
            if (isset($telas[$c['tela']])) {
                continue;
            }
            [$nome, $area] = $telasMapa[$c['tela']] ?? [$c['tela'], 'outras'];
            $telas[$c['tela']] = ['id' => $c['tela'], 'nome' => $nome, 'area' => $area, 'arquivo' => $c['arquivo'], 'url' => self::REPO_URL . $c['arquivo']];
        }
        $telas = array_values($telas);

        $areas = [];
        foreach ($mapa['areas'] as [$id, $nome]) {
            $daArea = array_values(array_column(array_filter($telas, static fn (array $t): bool => $t['area'] === $id), 'id'));
            if ($daArea) {
                $areas[] = ['id' => $id, 'nome' => $nome, 'telas' => $daArea];
            }
        }

        $contratos = [];
        foreach ($this->lerContratos($arquivos) as $t) {
            $id = self::chaveDaTela($t['arquivo']);
            $modulo = preg_match('#^Modules/([^/]+)#', $t['arquivo'], $mm) || preg_match('#/Pages/([^/]+)#', $t['arquivo'], $mm) ? $mm[1] : 'Outros';
            $contratos[] = [
                'id' => $id, 'nome' => ($telasMapa[$id] ?? [$id])[0], 'modulo' => $modulo, 'arquivo' => $t['arquivo'],
                'url' => self::REPO_URL . $t['arquivo'], 'ucs' => $t['ucs'],
                'pedidos' => count(array_filter($casos, static fn (array $c): bool => $c['tela'] === $id)),
            ];
        }

        $maior = max([(int) $mapa['ultimoReservado'], ...array_column($casos, 'n')]);
        $proximo = 'P-' . str_pad((string) ($maior + 1), 3, '0', STR_PAD_LEFT);

        $resumo = [
            'pedidos' => count($casos),
            'noBacklog' => count(array_filter($casos, static fn ($c) => $c['estagio'] === 'pedido')),
            'viraramUC' => count(array_filter($casos, static fn ($c) => $c['estagio'] !== 'pedido')),
            'testados' => count(array_filter($casos, static fn ($c) => $c['estagio'] === 'testado')),
            'telas' => count($telas),
            'regras' => count($regras),
            'regrasCitadas' => count(array_unique(array_merge([], ...array_column($casos, 'regras')))),
            'decisoes' => count($decs),
            'telasComContrato' => count($contratos),
            'casosDeUso' => array_sum(array_map(static fn ($t) => count($t['ucs']), $contratos)),
            'casosDeUsoVerdes' => array_sum(array_map(static fn ($t) => count(array_filter($t['ucs'], static fn ($u) => $u['veredito'] === 'pass')), $contratos)),
        ];

        return ['resumo' => $resumo, 'areas' => $areas, 'telas' => $telas, 'casos' => $casos, 'regras' => $regras,
            'decisoes' => $decs, 'contratos' => $contratos, 'proximoPedido' => $proximo, 'problemas' => $problemas];
    }

    /** As regras de Fluxos, do catálogo do código — no mesmo formato de `lerRegras()` do Node. */
    private function lerRegras(): array
    {
        $php = (string) file_get_contents($this->raiz . '/app/Domain/Fsm/Modelos/CasosDoPrototipo.php');
        preg_match_all("/\['(UC-FLX-\d+)', '([^']*)', '([a-z_]+)', '([^']*)', '([^']*)', \[[^\]]*\], '([a-z]+)', '([a-z]+)', '([^']*)'\]/", $php, $m, PREG_SET_ORDER);

        return array_map(static fn (array $x): array => [
            'id' => $x[1], 'titulo' => $x[2], 'fase' => $x[3], 'etapa' => $x[4], 'efeito' => $x[5], 'porte' => $x[6], 'status' => $x[7], 'nota' => $x[8],
        ], $m);
    }

    /** Todos os `*.casos.md` fora de `prototipo-ui/`, em caminho relativo e na ordem do `git ls-files`. */
    public function arquivosCasos(): array
    {
        $saida = [];
        $pilha = [''];
        while ($pilha) {
            $rel = array_pop($pilha);
            $dir = $rel === '' ? $this->raiz : $this->raiz . '/' . $rel;
            foreach (scandir($dir) ?: [] as $nome) {
                if ($nome === '.' || $nome === '..' || ($rel === '' && in_array($nome, self::NAO_VARRER, true)) || $nome === 'node_modules') {
                    continue;
                }
                $filho = $rel === '' ? $nome : $rel . '/' . $nome;
                if (is_dir($this->raiz . '/' . $filho)) {
                    $pilha[] = $filho;
                } elseif (str_ends_with($nome, '.casos.md')) {
                    $saida[] = $filho;
                }
            }
        }
        sort($saida, SORT_STRING);

        return $saida;
    }

    /** @return array{0: list<array>, 1: list<array>} */
    private function lerRepositorio(array $arquivos): array
    {
        $pedidos = $decisoes = [];
        foreach ($arquivos as $rel) {
            [$p, $d] = self::lerArquivo((string) file_get_contents($this->raiz . '/' . $rel), $rel);
            array_push($pedidos, ...$p);
            array_push($decisoes, ...$d);
        }
        $vistos = [];
        foreach ([...$pedidos, ...$decisoes] as $x) {
            if (isset($vistos[$x['id']])) {
                throw new \RuntimeException("{$x['id']} aparece em dois lugares: {$vistos[$x['id']]} e {$x['arquivo']}:{$x['linha']}");
            }
            $vistos[$x['id']] = "{$x['arquivo']}:{$x['linha']}";
        }
        $ordem = static fn (array $a, array $b): int => strcmp($a['id'], $b['id']);
        usort($pedidos, $ordem);
        usort($decisoes, $ordem);

        return [$pedidos, $decisoes];
    }

    /** @return array{0: list<array>, 1: list<array>} pedidos e decisões de um arquivo. */
    public static function lerArquivo(string $md, string $rel): array
    {
        $P = self::PONTO;
        $L = explode("\n", $md);
        $n = count($L);
        $pedidos = $decisoes = [];
        $uc = null;
        for ($i = 0; $i < $n; $i++) {
            $l = $L[$i];
            if (preg_match("/^#{2,3} (UC-[A-Z0-9-]+)\s*·\s*({$P}+)$/u", $l, $h)) {
                $uc = ['id' => $h[1], 'titulo' => self::limpar(preg_replace('/`[^`]*`/u', '', $h[2])), 'linha' => $i + 1];

                continue;
            }
            if (preg_match('/^#{1,3} /', $l)) {
                $uc = null;

                continue;
            }
            if ($uc && preg_match('/^- \*\*Pedido de origem:\*\* (P-\d{3})/u', $l, $o)) {
                $testado = false;
                for ($j = $i + 1; $j < $n && ! preg_match('/^#{1,3} /', $L[$j]); $j++) {
                    if (preg_match('/^- \*\*Status:?\*\*:? *✅/u', $L[$j])) {
                        $testado = true;
                    }
                }
                $pedidos[] = ['id' => $o[1], 'arquivo' => $rel, 'linha' => $uc['linha'], 'titulo' => $uc['titulo'], 'uc' => $uc['id'], 'estagio' => $testado ? 'testado' : 'uc', 'marcas' => []];

                continue;
            }
            if (! preg_match('/^- \S/u', $l)) {
                continue;
            }
            $corpo = [];
            for ($j = $i + 1; $j < $n && preg_match('/^\s{2,}\S/u', $L[$j]); $j++) {
                $corpo[] = $L[$j];
            }
            $subs = [];
            foreach ($corpo as $x) {
                if ($s = self::sub($x)) {
                    $subs[] = $s;
                }
            }
            if (preg_match("/^- \*\*\[DECISÃO\] (D-\d{2}) · ({$P}+?)\*\*\s*$/u", $l, $dec)) {
                $decisoes[] = ['id' => $dec[1], 'pergunta' => $dec[2], 'arquivo' => $rel, 'linha' => $i + 1, ...self::campos($subs)];

                continue;
            }
            $pid = null;
            foreach ($corpo as $x) {
                if (preg_match('/^\s{2,}- Pedido: (P-\d{3})\s*$/u', $x, $pm)) {
                    $pid = $pm[1];
                    break;
                }
            }
            if ($pid === null) {
                continue;
            }
            $corte = mb_strpos($l, ' — ');
            $resto = $corte > 0 ? mb_substr($l, $corte + 3) : '';
            $prosa = [$resto];
            foreach ($corpo as $x) {
                if (! self::sub($x)) {
                    $prosa[] = self::trimJs($x);
                }
            }
            $pedidos[] = ['id' => $pid, 'arquivo' => $rel, 'linha' => $i + 1, 'titulo' => self::tituloDoBullet($l), 'marcas' => self::marcasDe($l),
                'texto' => self::trimJs(implode(' ', $prosa)), 'estagio' => 'pedido', ...self::campos($subs)];
        }

        return [$pedidos, $decisoes];
    }

    /** @return array{0:string,1:string}|null subitem `Chave: valor`. */
    private static function sub(string $x): ?array
    {
        $P = self::PONTO;

        return preg_match("/^\s{2,}- ([A-ZÀ-Úa-zà-ú ]+): ?({$P}*)$/u", $x, $m) ? [self::trimJs($m[1]), $m[2]] : null;
    }

    public static function tituloDoBullet(string $linha): string
    {
        $s = (string) preg_replace('/^\s*- /u', '', $linha);
        $marcador = '\[BACKLOG(?:[^[\]]|\[[^\]]*\])*\]';
        $s = (string) preg_replace('/^\*\*' . $marcador . '\*\*\s*/u', '', $s, 1);
        $s = (string) preg_replace('/^\*\*' . $marcador . '\s*/u', '**', $s, 1);
        $s = (string) preg_replace('/^`\[BACKLOG\]`\s*/u', '', $s, 1);
        $s = (string) preg_replace('/^' . $marcador . '\s*/u', '', $s, 1);
        $corte = mb_strpos($s, ' — ');
        if ($corte > 0) {
            $s = mb_substr($s, 0, $corte);
        }
        $s = str_replace('**', '', (string) preg_replace('/`\[(V0|E0|T0)\]`/u', '', $s));
        $s = (string) preg_replace('/\((pedido|comparação)[^)]*\)/iu', '', $s);

        return self::limpar($s);
    }

    public static function marcasDe(string $linha): array
    {
        preg_match_all('/\[(V0|E0|T0)\]/', $linha, $m);

        return $m[1];
    }

    /** Campos do documento a partir dos subitens `Chave: valor`. */
    private static function campos(array $subs): array
    {
        $c = [];
        $selecao = static function (string $v): array {
            $partes = explode(' | ', $v);

            return ['tipo' => $partes[0] ?? '', 'texto' => $partes[1] ?? '', 'nota' => implode(' | ', array_slice($partes, 2))];
        };
        $lista = static fn (string $v): array => $v === '' ? [] : array_values(array_filter(array_map([self::class, 'trimJs'], explode(',', $v)), static fn ($x) => $x !== ''));
        foreach ($subs as [$k, $v]) {
            match ($k) {
                'Hoje' => $c['hoje'] = $selecao($v),
                'Comunicação visual' => $c['cv'] = $selecao($v),
                'Clientes' => $c['clientes'] = $lista($v),
                'Etiquetas' => $c['tags'] = $lista($v),
                'Âncora' => $c['ancora'] = $v,
                'Fazer' => $c['fazer'] = $v,
                'Aceite' => $c['aceite'] = $v,
                'Destrava' => $c['destrava'] = $lista($v),
                'Estado' => $c['estado'] = $v,
                'Texto' => $c['texto'] = $v,
                'Opção' => $c['opcoes'][] = ($i = mb_strpos($v, ' — ')) > 0
                    ? ['nome' => mb_substr($v, 0, $i), 'texto' => mb_substr($v, $i + 3)]
                    : ['nome' => $v, 'texto' => ''],
                default => null,
            };
        }

        return $c;
    }

    /** O contrato de cada tela: os UC com id, e o veredito do manifesto do casos-gate. */
    private function lerContratos(array $arquivos): array
    {
        $manifesto = [];
        $bruto = @file_get_contents($this->raiz . '/scripts/casos-test-results.json');
        if ($bruto !== false) {
            $manifesto = json_decode($bruto, true)['ucs'] ?? [];
        }
        $telas = [];
        foreach ($arquivos as $rel) {
            $tsx = (string) preg_replace('/\.casos\.md$/', '.tsx', $rel);
            if (! self::ehTela($tsx) || ! is_file($this->raiz . '/' . $tsx)) {
                continue;
            }
            $md = (string) file_get_contents($this->raiz . '/' . $rel);
            $ucs = [];
            $desde = 0;
            // Mesmo parser de bloco do casos-gate (scripts/lib/uc-regex.mjs → ucBlocksInCasos).
            $blocos = preg_split('/^##\s+/m', $md);
            foreach (array_slice($blocos, 1) as $bloco) {
                if (! preg_match('/^(UC-(?:[A-Z][A-Z0-9]{0,5})?-?\d{1,3}[a-zA-Z]?)\b/', $bloco, $m)) {
                    continue;
                }
                $id = strtoupper($m[1]);
                $cabeca = explode("\n", $bloco)[0];
                $pos = strpos($md, $cabeca, $desde);
                $desde = $pos + strlen($cabeca);
                $titulo = (string) preg_replace('/^\S+/u', '', $cabeca);
                $titulo = (string) preg_replace('/\s+/u', ' ', (string) preg_replace('/`[^`]*`/u', '', $titulo));
                $titulo = self::trimJs((string) preg_replace(['/^[\s·:—-]+/u', '/[\s·:—-]+$/u'], '', $titulo));
                $ucs[] = ['id' => $id, 'titulo' => $titulo, 'linha' => substr_count(substr($md, 0, $pos), "\n") + 1, 'veredito' => $manifesto[$id]['verdict'] ?? 'sem-prova'];
            }
            if ($ucs) {
                $telas[] = ['arquivo' => $rel, 'ucs' => $ucs];
            }
        }

        return $telas;
    }

    /** `isPageScreenPath` de scripts/qa/page-path.mjs. */
    private static function ehTela(string $path): bool
    {
        $path = (string) preg_replace('#^(?:Modules/[^/]+/)?[Rr]esources/js/Pages/#', '', $path);
        if (! str_ends_with($path, '.tsx') || str_ends_with($path, '.charter.tsx') || str_contains($path, '.test.')) {
            return false;
        }
        $partes = explode('/', $path);
        array_pop($partes);
        if (! $partes) {
            return false;
        }
        foreach ($partes as $p) {
            if (preg_match('/^(?:_.*|components?|partials?|hooks?|utils?|lib|types?|constants?|schemas?|stores?|contexts?)$/i', $p)) {
                return false;
            }
        }

        return true;
    }

    private static function chaveDaTela(string $arquivo): string
    {
        return (string) preg_replace(['#^.*/Pages/#', '/\.casos\.md$/'], '', $arquivo);
    }

    /** `replace(/\s+/g, ' ').trim()` do JavaScript. */
    private static function limpar(string $s): string
    {
        return self::trimJs((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** `String.prototype.trim` do JavaScript (tira também espaço Unicode). */
    public static function trimJs(string $s): string
    {
        return (string) preg_replace('/^[\s\x{a0}\x{feff}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}]+|[\s\x{a0}\x{feff}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}]+$/u', '', $s);
    }
}
