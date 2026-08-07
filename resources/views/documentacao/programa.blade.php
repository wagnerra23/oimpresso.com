@extends('documentacao.layout')

@section('titulo', 'Programa de documentação — Trilha D')

{{--
  Toda a matéria desta tela sai do PLANO-MESTRE em runtime (ver DocumentacaoController::programa).
  NÃO escreva aqui a lista de estações, de ondas ou de critérios: isso criaria um segundo dono do
  mesmo fato, que drifaria do plano em silêncio — o oposto do que a § Trilha D exige ("ponteiro >
  cópia"). Se a tela precisar mostrar algo que o plano não diz, o lugar de acrescentar é o plano.
--}}

@section('conteudo')
  <div class="prog">

    <header class="prog-topo">
      <div>
        <h1>Programa de documentação <span class="sep">·</span> <span class="trilha">Trilha D</span></h1>
        <p class="prog-sub">
          Não é escrever documentação — é manter um sistema que mede, traduz, publica, opera,
          detecta drift e aprende.
          @if ($atualizadoEm)
            <span class="prog-quando">Plano atualizado em {{ $atualizadoEm }}.</span>
          @endif
        </p>
      </div>
      <nav class="prog-links" aria-label="Ações da página">
        <a href="{{ route('documentacao') }}">&larr; Documentação</a>
        <a href="{{ $blob }}" rel="noopener noreferrer" target="_blank">Ver plano no git</a>
      </nav>
    </header>

    {{-- Sem JS as quatro vistas ficam todas visíveis e a página continua inteira; as abas são
         conforto de navegação, nunca requisito de leitura. --}}
    <div class="prog-abas" role="tablist" aria-label="Vistas do programa" hidden>
      <button type="button" role="tab" data-vista="ciclo" aria-selected="true">Ciclo</button>
      <button type="button" role="tab" data-vista="ondas" aria-selected="false">Ondas</button>
      <button type="button" role="tab" data-vista="caminhos" aria-selected="false">Caminhos</button>
      <button type="button" role="tab" data-vista="pronto" aria-selected="false">Pronto &amp; batimento</button>
    </div>

    <section class="prog-vista" data-vista="ciclo">
      <h2 class="prog-h2">O ciclo completo</h2>

      <div class="prog-kpis">
        @if ($execucao['onda'])
          <div class="kpi">
            <span class="kpi-rot">Onda atual</span>
            <strong class="kpi-val">{{ $execucao['onda'] }}</strong>
            <span class="kpi-pe">{{ $execucao['onda_nome'] }}</span>
          </div>
        @endif
        @if ($execucao['posicao'])
          <div class="kpi">
            <span class="kpi-rot">Ondas</span>
            <strong class="kpi-val">{{ $execucao['posicao'] }}<span class="kpi-de">/ {{ $execucao['total'] }}</span></strong>
            <span class="kpi-pe">{{ $execucao['onda'] }} em execução</span>
          </div>
        @endif
        <div class="kpi">
          <span class="kpi-rot">Estações do ciclo</span>
          <strong class="kpi-val">{{ count($estacoes) }}</strong>
          <span class="kpi-pe">fecha em aprender &rarr; medir de novo</span>
        </div>
        @if ($execucao['task'])
          <div class="kpi">
            <span class="kpi-rot">Task MCP</span>
            <strong class="kpi-val kpi-task">{{ $execucao['task'] }}</strong>
            <span class="kpi-pe">o estado vivo é dela, não desta tela</span>
          </div>
        @endif
      </div>

      <ol class="prog-estacoes">
        @foreach ($estacoes as $estacao)
          <li>
            <span class="est-n">{{ $estacao['n'] }}</span>
            <strong class="est-tit">{{ $estacao['titulo'] }}</strong>
            <span class="est-corpo">{{ $estacao['corpo'] }}</span>
          </li>
        @endforeach
      </ol>

      <p class="prog-laco">
        estação {{ count($estacoes) }} &rarr; estação 02 · o aprendizado reentra na medição; publicar não encerra
      </p>
    </section>

    <section class="prog-vista" data-vista="ondas">
      <h2 class="prog-h2">As ondas</h2>
      <div class="tabela-scroll">
        <table class="prog-tab">
          <thead>
            <tr><th>Onda</th><th>Escopo</th><th>Saída no dono existente</th><th>Gate de saída</th></tr>
          </thead>
          <tbody>
            @foreach ($ondas as $onda)
              <tr @class(['agora' => $onda['codigo'] === $execucao['onda']])>
                <th scope="row">
                  <span class="onda-cod">{{ $onda['codigo'] ?? '—' }}</span>
                  <span class="onda-nome">{{ $onda['nome'] }}</span>
                </th>
                @foreach ($onda['colunas'] as $celula)
                  <td>{{ $celula }}</td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <section class="prog-vista" data-vista="caminhos">
      <h2 class="prog-h2">Caminho canônico por tipo</h2>
      <div class="tabela-scroll">
        <table class="prog-tab">
          <thead>
            <tr><th>Tipo</th><th>Caminho</th><th>O que a documentação precisa responder</th></tr>
          </thead>
          <tbody>
            @foreach ($caminhos as $caminho)
              <tr>
                <th scope="row">{{ $caminho['rotulo'] }}</th>
                @foreach ($caminho['colunas'] as $celula)
                  <td>{{ $celula }}</td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <section class="prog-vista" data-vista="pronto">
      <h2 class="prog-h2">Quando a trilha termina</h2>
      <ul class="prog-dod">
        @foreach ($dod as $criterio)
          <li>{{ $criterio }}</li>
        @endforeach
      </ul>

      @if (! empty($batimento))
        <h2 class="prog-h2">O batimento que a mantém ativa</h2>
        <div class="tabela-scroll">
          <table class="prog-tab">
            <thead><tr><th>Momento</th><th>Máquina existente</th><th>Efeito</th></tr></thead>
            <tbody>
              @foreach ($batimento as $item)
                <tr>
                  <th scope="row">{{ $item['rotulo'] }}</th>
                  @foreach ($item['colunas'] as $celula)
                    <td>{{ $celula }}</td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>

    <footer class="prog-pe">
      Dono deste texto: <code>{{ $fonte }}</code> — a tela <strong>renderiza</strong> o plano,
      não é cópia commitada. Em que onda a trilha está e o que fechou é estado vivo das tasks MCP
      (<code>parent_plan=programa-ondas</code>), nunca desta página.
    </footer>

  </div>
@endsection

@push('estilo')
<style>
  /* Escopo .prog — cores saem dos tokens do shell (--ink/--rule/--accent/--paper),
     então a página acompanha claro e escuro sem mapa de cor próprio. */
  .prog{display:flex;flex-direction:column;gap:1.75rem}
  .prog-topo{display:flex;flex-wrap:wrap;gap:1rem;justify-content:space-between;align-items:flex-start}
  .prog-topo h1{font-family:var(--sans);font-size:1.35rem;margin:0;letter-spacing:-.01em}
  .prog-topo .sep{opacity:.45}
  .prog-topo .trilha{color:var(--accent)}
  .prog-sub{margin:.4rem 0 0;max-width:62ch;opacity:.78;font-size:.9rem;line-height:1.5}
  .prog-quando{opacity:.7}
  .prog-links{display:flex;gap:1.25rem;font-family:var(--sans);font-size:.85rem;white-space:nowrap}
  .prog-h2{font-family:var(--sans);font-size:1rem;margin:0 0 .85rem;letter-spacing:-.01em}

  .prog-abas{display:flex;gap:.25rem;border-bottom:1px solid var(--rule);flex-wrap:wrap}
  .prog-abas button{appearance:none;background:none;border:0;border-bottom:2px solid transparent;
    color:inherit;opacity:.6;cursor:pointer;font:inherit;font-family:var(--sans);font-size:.875rem;
    padding:.5rem .75rem}
  .prog-abas button[aria-selected="true"]{opacity:1;border-bottom-color:var(--accent);color:var(--accent)}
  .prog-abas button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}

  .prog-kpis{display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(min(100%,15rem),1fr));
    margin-bottom:1.5rem}
  .kpi{border:1px solid var(--rule);border-radius:.5rem;padding:.85rem 1rem;display:flex;
    flex-direction:column;gap:.3rem}
  .kpi-rot{font-family:var(--mono);font-size:.68rem;text-transform:uppercase;letter-spacing:.09em;opacity:.6}
  .kpi-val{font-family:var(--sans);font-size:1.85rem;line-height:1.05;letter-spacing:-.02em}
  .kpi-val.kpi-task{font-family:var(--mono);font-size:1.25rem}
  .kpi-de{font-size:.9rem;opacity:.5;margin-left:.15rem}
  .kpi-pe{font-size:.78rem;opacity:.65;line-height:1.35}

  .prog-estacoes{list-style:none;margin:0;padding:0;display:grid;gap:.6rem;
    grid-template-columns:repeat(auto-fill,minmax(min(100%,15.5rem),1fr))}
  .prog-estacoes li{border:1px solid var(--rule);border-radius:.5rem;padding:.75rem .85rem;
    display:flex;flex-direction:column;gap:.3rem}
  .est-n{font-family:var(--mono);font-size:.7rem;opacity:.5}
  .est-tit{font-family:var(--sans);font-size:.9rem}
  .est-corpo{font-size:.8rem;opacity:.7;line-height:1.4}

  .prog-laco{border:1px dashed var(--rule);border-radius:.5rem;padding:.6rem .85rem;margin:1rem 0 0;
    font-family:var(--mono);font-size:.75rem;opacity:.75}

  .prog-tab{width:100%;border-collapse:collapse;font-size:.82rem}
  .prog-tab th,.prog-tab td{border-bottom:1px solid var(--rule);padding:.55rem .6rem;
    text-align:left;vertical-align:top;line-height:1.45}
  .prog-tab thead th{font-family:var(--mono);font-size:.68rem;text-transform:uppercase;
    letter-spacing:.08em;opacity:.6;white-space:nowrap}
  .prog-tab tbody th{white-space:nowrap}
  .prog-tab tr.agora{background:color-mix(in oklch, var(--accent) 9%, transparent)}
  .onda-cod{font-family:var(--mono);color:var(--accent);margin-right:.4rem}
  .onda-nome{font-weight:400;opacity:.85}

  .prog-dod{margin:0;padding-left:1.1rem;display:flex;flex-direction:column;gap:.4rem;
    font-size:.86rem;line-height:1.5;max-width:78ch}

  .prog-pe{border-top:1px solid var(--rule);padding-top:.9rem;font-size:.8rem;opacity:.7;line-height:1.5}

  @media (prefers-reduced-motion:no-preference){.prog-vista{scroll-margin-top:5rem}}
</style>
@endpush

@push('script')
<script>
  // Abas: progressive enhancement. O HTML nasce com as quatro vistas visíveis e a barra
  // `hidden`; só quando o JS roda ela aparece e passa a esconder as não-selecionadas.
  // Sem JS (ou se isto quebrar), a página segue completa — nada fica inalcançável.
  (function () {
    var barra = document.querySelector('.prog-abas');
    var vistas = Array.prototype.slice.call(document.querySelectorAll('.prog-vista'));
    if (!barra || vistas.length < 2) return;

    var abas = Array.prototype.slice.call(barra.querySelectorAll('[data-vista]'));
    var validas = vistas.map(function (v) { return v.dataset.vista; });

    function mostrar(nome, empurrarUrl) {
      if (validas.indexOf(nome) === -1) nome = validas[0];

      vistas.forEach(function (v) { v.hidden = v.dataset.vista !== nome; });
      abas.forEach(function (a) { a.setAttribute('aria-selected', String(a.dataset.vista === nome)); });

      if (empurrarUrl) {
        var url = new URL(window.location.href);
        url.searchParams.set('vista', nome);
        history.replaceState(null, '', url);
      }
    }

    barra.hidden = false;
    abas.forEach(function (a) {
      a.addEventListener('click', function () { mostrar(a.dataset.vista, true); });
    });

    // A vista vive na URL, então um link compartilhado abre onde o autor parou.
    mostrar(new URL(window.location.href).searchParams.get('vista') || validas[0], false);
  })();
</script>
@endpush
