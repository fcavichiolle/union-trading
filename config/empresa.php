<?php

/**
 * Dados de contato que saem no cabeçalho dos PDFs (contrato, carta de
 * fixação, programação de estufagem).
 *
 * Telefone e e-mail ficam no `.env`, não no código: o repositório é
 * público e endereço de e-mail de pessoa em código público vira alvo de
 * phishing. Sem valor no `.env`, a linha simplesmente não sai no PDF.
 */
return [
    'telefone' => env('EMPRESA_TELEFONE'),
    'email' => env('EMPRESA_EMAIL'),
];
