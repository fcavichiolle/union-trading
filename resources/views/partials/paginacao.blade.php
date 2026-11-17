{{-- Paginador do sistema. Substitui o padrão do Laravel, que é escrito para
     Tailwind: sem aquele CSS (o projeto não tem build step) as setas em SVG
     cresciam até ocupar a tela inteira. Aqui é só texto e as classes do
     próprio sistema. Registrado como padrão no AppServiceProvider, então
     toda lista com `->links()` usa este. --}}
@if ($paginator->hasPages())
    <nav class="paginacao" role="navigation" aria-label="Paginação">
        <span class="paginacao__resumo">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </span>

        <div class="paginacao__paginas">
            @if ($paginator->onFirstPage())
                <span class="paginacao__item is-disabled" aria-disabled="true">{!! __('pagination.previous') !!}</span>
            @else
                <a class="paginacao__item" href="{{ $paginator->previousPageUrl() }}" rel="prev">{!! __('pagination.previous') !!}</a>
            @endif

            @foreach ($elements as $element)
                {{-- Reticências entre blocos de páginas --}}
                @if (is_string($element))
                    <span class="paginacao__item is-disabled" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $pagina => $url)
                        @if ($pagina == $paginator->currentPage())
                            <span class="paginacao__item is-atual" aria-current="page">{{ $pagina }}</span>
                        @else
                            <a class="paginacao__item" href="{{ $url }}" aria-label="Página {{ $pagina }}">{{ $pagina }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="paginacao__item" href="{{ $paginator->nextPageUrl() }}" rel="next">{!! __('pagination.next') !!}</a>
            @else
                <span class="paginacao__item is-disabled" aria-disabled="true">{!! __('pagination.next') !!}</span>
            @endif
        </div>
    </nav>
@endif
