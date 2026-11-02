<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GoogleDriveApiService;
use GuzzleHttp\Psr7\Response as HttpResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * BUG-03: `listFolder` devolvia lista VAZIA em qualquer resposta diferente de
 * 200 (um `break` silencioso) — e o DriveSyncService lê vazio como "apagaram
 * tudo no Drive" e limpa a galeria do cliente. Aqui o contrato é: falha do
 * Google vira exceção, 429/5xx ganham retry curto e o resto falha na hora.
 *
 * O fake sobrescreve `httpGet`/`accessToken`, então nada aqui fala com a rede.
 */
class DriveListFolderRetryTest extends TestCase
{
    public function test_resposta_de_erro_virou_excecao_em_vez_de_lista_vazia(): void
    {
        $api = new FakeHttpDriveApi([[403, '{"error":{"message":"quota"}}']]);

        $this->expectException(RuntimeException::class);
        $api->listFolder(1, 'PASTA');
    }

    public function test_erro_definitivo_nao_gasta_retry(): void
    {
        $api = new FakeHttpDriveApi([[404, '{}']]);

        // Sem $this->fail() dentro do catch: o erro do PHPUnit também é um
        // RuntimeException e seria engolido por ele.
        $erro = null;
        try {
            $api->listFolder(1, 'PASTA');
        } catch (RuntimeException $e) {
            $erro = $e;
        }

        $this->assertNotNull($erro, 'Falha do Google precisa virar exceção.');
        // 404 não melhora com retry: uma tentativa e pronto.
        $this->assertSame(1, $api->calls);
    }

    public function test_429_e_seguido_de_nova_tentativa(): void
    {
        $api = new FakeHttpDriveApi([
            [429, '{"error":{"message":"rate limit"}}'],
            [200, '{"files":[{"id":"A","name":"foto.jpg","mimeType":"image/jpeg"}]}'],
        ]);

        $items = $api->listFolder(1, 'PASTA');

        $this->assertSame(2, $api->calls);
        $this->assertCount(1, $items);
        $this->assertSame('A', $items[0]['id']);
    }

    public function test_5xx_tambem_tem_retry_e_desiste_com_erro(): void
    {
        $api = new FakeHttpDriveApi([[503, '{}'], [503, '{}'], [500, '{}']]);

        $erro = null;
        try {
            $api->listFolder(1, 'PASTA');
        } catch (RuntimeException $e) {
            $erro = $e;
        }

        $this->assertNotNull($erro, 'Instabilidade do Google não pode virar lista vazia.');
        // 3 tentativas no total (2 esperas curtas).
        $this->assertSame(3, $api->calls);
    }

    public function test_pasta_vazia_de_verdade_devolve_lista_vazia(): void
    {
        $api = new FakeHttpDriveApi([[200, '{"files":[]}']]);

        $this->assertSame([], $api->listFolder(1, 'PASTA'));
        $this->assertSame(1, $api->calls);
    }

    public function test_pagina_ate_o_fim(): void
    {
        $api = new FakeHttpDriveApi([
            [200, '{"nextPageToken":"p2","files":[{"id":"A","name":"a","mimeType":"image/jpeg"}]}'],
            [200, '{"files":[{"id":"B","name":"b","mimeType":"image/jpeg"}]}'],
        ]);

        $items = $api->listFolder(1, 'PASTA');

        $this->assertSame(['A', 'B'], array_column($items, 'id'));
    }
}

/** Dublê do serviço: respostas HTTP enlatadas, sem rede e sem banco. */
final class FakeHttpDriveApi extends GoogleDriveApiService
{
    public int $calls = 0;

    /** @param list<array{0:int,1:string}> $responses status + corpo, em ordem */
    public function __construct(private array $responses) {}

    public function accessToken(int $agencyId): string
    {
        return 'token-de-teste';
    }

    protected function httpGet(string $url, array $options): ResponseInterface
    {
        [$status, $body] = $this->responses[$this->calls] ?? [200, '{"files":[]}'];
        $this->calls++;

        return new HttpResponse($status, ['Content-Type' => 'application/json'], $body);
    }
}
