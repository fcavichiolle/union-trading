<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Busca a razão social a partir do CNPJ na BrasilAPI (pública, sem chave).
 *
 * Só CNPJ: **não existe consulta pública de nome por CPF** no Brasil — é
 * dado pessoal protegido. Para CPF o sistema valida os dígitos e o nome é
 * digitado à mão (ver App\Rules\DocumentoValido).
 *
 * É conveniência, nunca dependência: se a API estiver fora do ar, o
 * formulário continua funcionando com o nome digitado. Por isso o método
 * devolve null em qualquer falha, em vez de lançar exceção.
 */
class ConsultaCnpj
{
    /** @return array{nome: string, nome_fantasia: ?string, situacao: ?string, endereco: ?string}|null */
    public function buscar(string $cnpj): ?array
    {
        $digitos = preg_replace('/\D/', '', $cnpj);

        if (strlen($digitos) !== 14) {
            return null;
        }

        // Razão social praticamente não muda: cache longo evita repetir a
        // consulta a cada lançamento do mesmo fornecedor.
        return Cache::remember("cnpj.{$digitos}", now()->addDays(30), function () use ($digitos) {
            try {
                $resposta = Http::timeout(8)
                    ->acceptJson()
                    ->get("https://brasilapi.com.br/api/cnpj/v1/{$digitos}");
            } catch (\Throwable) {
                return null;
            }

            if (! $resposta->successful() || blank($resposta->json('razao_social'))) {
                return null;
            }

            return [
                'nome' => (string) $resposta->json('razao_social'),
                'nome_fantasia' => $resposta->json('nome_fantasia') ?: null,
                'situacao' => $resposta->json('descricao_situacao_cadastral') ?: null,
                'endereco' => $this->montarEndereco($resposta->json()),
            ];
        });
    }

    private function montarEndereco(array $d): ?string
    {
        $partes = array_filter([
            trim(($d['logradouro'] ?? '') . ' ' . ($d['numero'] ?? '')),
            $d['bairro'] ?? null,
            trim(($d['municipio'] ?? '') . ($d['uf'] ?? '' ? ' - ' . $d['uf'] : '')),
        ]);

        return $partes === [] ? null : implode(', ', $partes);
    }
}
