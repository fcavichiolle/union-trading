@extends('layouts.public')

@section('title', 'Relatório de classificação — Union Trading')

@section('content')
    <div class="card">
        <div class="card__header card__header--dark mesh-texture">
            <h2>Distribuição por padrão e peneira</h2>
        </div>
        <div class="card__body" style="padding:0;">
            @include('dashboard._tabela-classificacao')
        </div>
    </div>

    {{-- O destinatário não vê a quebra por armazém (fica dentro de casa),
         mas precisa saber qual recorte está olhando — senão um total menor
         parece erro em vez de critério. --}}
    <p style="color:var(--muted); font-size:12.5px; margin-top:14px;">
        @if ($filtros['situacao'] === 'definitivo')
            Considera apenas o café com entrada confirmada em armazém.
        @elseif ($filtros['situacao'] === 'aguardando')
            Considera apenas o café comprado com entrada em armazém ainda não confirmada.
        @else
            Considera todo o café comprado, com entrada em armazém confirmada ou não.
        @endif
        Valores em sacas.
    </p>
@endsection
