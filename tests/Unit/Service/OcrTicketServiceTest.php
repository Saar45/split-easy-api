<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\OcrTicketService;
use App\Service\TicketParserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class OcrTicketServiceTest extends TestCase
{
    /** 1x1 pixel PNG, assez pour que finfo la reconnaisse comme image/png réelle. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var string[] */
    private array $filesToCleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->filesToCleanup as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->filesToCleanup = [];
    }

    public function testNominalResponseIsParsedIntoFields(): void
    {
        $ocrPayload = [
            'IsErroredOnProcessing' => false,
            'ParsedResults' => [
                ['ParsedText' => "CARREFOUR CITY\nTOTAL TTC   19.52\n15/07/2026\n"],
            ],
        ];
        $client = new MockHttpClient([new MockResponse(json_encode($ocrPayload, \JSON_THROW_ON_ERROR))]);
        $service = new OcrTicketService($client, new TicketParserService(), 'dummy_key');

        $result = $service->scanTicket($this->buildPngUpload());

        self::assertSame('19.52', $result['montant']);
        self::assertSame('2026-07-15', $result['date']);
        self::assertSame('CARREFOUR CITY', $result['commercant']);
        self::assertStringContainsString('CARREFOUR CITY', $result['texteBrut']);
    }

    public function testMissingApiKeyThrows503(): void
    {
        $service = new OcrTicketService(new MockHttpClient(), new TicketParserService(), '');

        try {
            $service->scanTicket($this->buildPngUpload());
            self::fail('Expected ServiceUnavailableHttpException.');
        } catch (ServiceUnavailableHttpException $e) {
            self::assertSame(503, $e->getStatusCode());
        }
    }

    public function testOcrErrorFlagThrows502(): void
    {
        $ocrPayload = ['IsErroredOnProcessing' => true, 'ParsedResults' => []];
        $client = new MockHttpClient([new MockResponse(json_encode($ocrPayload, \JSON_THROW_ON_ERROR))]);
        $service = new OcrTicketService($client, new TicketParserService(), 'dummy_key');

        try {
            $service->scanTicket($this->buildPngUpload());
            self::fail('Expected 502 HttpException.');
        } catch (HttpException $e) {
            self::assertSame(502, $e->getStatusCode());
        }
    }

    public function testHttpErrorStatusThrows502(): void
    {
        $client = new MockHttpClient([new MockResponse('Internal Server Error', ['http_code' => 500])]);
        $service = new OcrTicketService($client, new TicketParserService(), 'dummy_key');

        try {
            $service->scanTicket($this->buildPngUpload());
            self::fail('Expected 502 HttpException.');
        } catch (HttpException $e) {
            self::assertSame(502, $e->getStatusCode());
        }
    }

    public function testTransportFailureThrows502(): void
    {
        $client = new MockHttpClient([new MockResponse('', ['error' => 'Connection timed out'])]);
        $service = new OcrTicketService($client, new TicketParserService(), 'dummy_key');

        try {
            $service->scanTicket($this->buildPngUpload());
            self::fail('Expected 502 HttpException.');
        } catch (HttpException $e) {
            self::assertSame(502, $e->getStatusCode());
        }
    }

    public function testEmptyParsedResultsReturnsNullFields(): void
    {
        $ocrPayload = ['IsErroredOnProcessing' => false, 'ParsedResults' => []];
        $client = new MockHttpClient([new MockResponse(json_encode($ocrPayload, \JSON_THROW_ON_ERROR))]);
        $service = new OcrTicketService($client, new TicketParserService(), 'dummy_key');

        $result = $service->scanTicket($this->buildPngUpload());

        self::assertNull($result['montant']);
        self::assertNull($result['date']);
        self::assertNull($result['commercant']);
        self::assertSame('', $result['texteBrut']);
    }

    public function testWrongMimeTypeThrows422(): void
    {
        $client = new MockHttpClient();
        $service = new OcrTicketService($client, new TicketParserService(), 'dummy_key');

        $path = tempnam(sys_get_temp_dir(), 'ticket_txt_');
        self::assertIsString($path);
        file_put_contents($path, 'ceci nest pas une image');
        $this->filesToCleanup[] = $path;

        $upload = new UploadedFile($path, 'note.txt', 'text/plain', null, true);

        $this->expectException(UnprocessableEntityHttpException::class);
        $service->scanTicket($upload);
    }

    public function testOversizedFileThrows422(): void
    {
        $client = new MockHttpClient();
        $service = new OcrTicketService($client, new TicketParserService(), 'dummy_key');

        $path = tempnam(sys_get_temp_dir(), 'ticket_big_');
        self::assertIsString($path);
        file_put_contents($path, str_repeat('a', 6 * 1024 * 1024));
        $this->filesToCleanup[] = $path;

        $upload = new UploadedFile($path, 'big.jpg', 'image/jpeg', null, true);

        $this->expectException(UnprocessableEntityHttpException::class);
        $service->scanTicket($upload);
    }

    private function buildPngUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ticket_png_');
        self::assertIsString($path);
        file_put_contents($path, base64_decode(self::PNG_BASE64, true));
        $this->filesToCleanup[] = $path;

        return new UploadedFile($path, 'ticket.png', 'image/png', null, true);
    }
}
