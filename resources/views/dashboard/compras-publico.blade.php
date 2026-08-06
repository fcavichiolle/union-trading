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

    <div class="card">
        <div class="card__header card__header--dark mesh-texture">
            <h2>Distribuição por certificação</h2>
        </div>
        <div class="card__body" style="padding:0;">
            @include('dashboard._tabela-certificacao')
        </div>
    </div>
@endsection