<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Analyse un ticket de caisse via l'API OCR.space (fournisseur unique, pas d'abstraction
 * multi-fournisseurs : hors périmètre de la démo jury F3).
 */
final class OcrTicketService
{
    private const ENDPOINT = 'https://api.ocr.space/parse/image';
    private const TIMEOUT = 15.0;
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> MIME réel accepté => code "filetype" attendu par OCR.space */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'image/webp' => 'WEBP',
        'application/pdf' => 'PDF',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TicketParserService $parser,
        private readonly string $apiKey,
    ) {
    }

    /** @return array{montant: ?string, date: ?string, commercant: ?string, texteBrut: string} */
    public function scanTicket(UploadedFile $file): array
    {
        $mimeType = $this->assertValidUpload($file);
        $texteBrut = $this->extractText($file->getPathname(), $mimeType);

        return array_merge($this->parser->parse($texteBrut), ['texteBrut' => $texteBrut]);
    }

    private function assertValidUpload(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new UnprocessableEntityHttpException('Le fichier envoyé est invalide.');
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new UnprocessableEntityHttpException('Le fichier dépasse la taille maximale autorisée (5 Mo).');
        }

        // Sniff du contenu réel via finfo : jamais l'extension ni le Content-Type déclaré
        // par le client, qui sont falsifiables (dossier §3.4.4, OWASP A03/A04).
        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname()) ?: '';

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new UnprocessableEntityHttpException(sprintf('Type de fichier non supporté : %s.', '' !== $mimeType ? $mimeType : 'inconnu'));
        }

        return $mimeType;
    }

    private function extractText(string $filePath, string $mimeType): string
    {
        if ('' === $this->apiKey) {
            throw new ServiceUnavailableHttpException(null, 'Service OCR indisponible : clé OCR_SPACE_API_KEY manquante.');
        }

        $handle = fopen($filePath, 'rb');
        if (false === $handle) {
            throw new HttpException(502, 'Impossible de lire le fichier à analyser.');
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'timeout' => self::TIMEOUT,
                'headers' => ['apikey' => $this->apiKey],
                'body' => [
                    'language' => 'fre',
                    'OCREngine' => '2',
                    'isOverlayRequired' => 'false',
                    'filetype' => self::ALLOWED_MIME_TYPES[$mimeType],
                    'file' => $handle,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new HttpException(502, 'Le service OCR est momentanément indisponible.', $e);
        } finally {
            fclose($handle);
        }

        if ($statusCode >= 300 || true === ($data['IsErroredOnProcessing'] ?? false)) {
            throw new HttpException(502, 'Le service OCR a retourné une erreur de traitement.');
        }

        $parsedResults = $data['ParsedResults'] ?? [];
        $parsedText = $parsedResults[0]['ParsedText'] ?? '';

        return '' !== $parsedText ? trim((string) $parsedText) : '';
    }
}
