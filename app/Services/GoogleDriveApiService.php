<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientRepository;
use App\Repositories\GoogleDriveIntegrationRepository;
use GuzzleHttp\Client;
use RuntimeException;

/**
 * Integração REST direta com a Google Drive API (sem google/apiclient).
 *
 * Escopo: drive.file — o app só acessa arquivos/pastas que ele mesmo cria.
 * OAuth: refresh_token guardado por agência; access_token renovado sob demanda.
 * Upload: sessão resumável iniciada aqui (autenticada); o navegador envia os
 * bytes direto pra session URI — o access token nunca vai pro cliente.
 */
class GoogleDriveApiService
{
    private const SCOPES       = 'openid email https://www.googleapis.com/auth/drive.file';
    private const FOLDER_MIME  = 'application/vnd.google-apps.folder';
    private const ROOT_NAME    = 'YVE — Conteúdos de Clientes';

    public function __construct(
        private readonly GoogleDriveIntegrationRepository $integrationRepo,
        private readonly ClientRepository                 $clientRepo,
    ) {}

    // ── Configuração / estado ────────────────────────────────────────────────

    public function getIntegration(int $agencyId): ?array
    {
        $i = $this->integrationRepo->findByAgency($agencyId);
        return ($i && ($i['status'] ?? '') === 'active') ? $i : null;
    }

    public function isConnected(int $agencyId): bool
    {
        return $this->getIntegration($agencyId) !== null;
    }

    // ── OAuth ────────────────────────────────────────────────────────────────

    public function authUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'              => env('GOOGLE_CLIENT_ID', ''),
            'redirect_uri'           => $this->redirectUri(),
            'response_type'          => 'code',
            'scope'                  => self::SCOPES,
            'access_type'            => 'offline',   // garante refresh_token
            'prompt'                 => 'consent',   // força refresh_token mesmo em re-conexão
            'include_granted_scopes' => 'true',
            'state'                  => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    /**
     * Troca o code por tokens, persiste e garante a pasta raiz no Drive.
     * @return array{root_folder_id:string,email:?string}
     */
    public function exchangeCode(int $agencyId, string $code): array
    {
        $resp = (new Client(['timeout' => 30]))->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'code'          => $code,
                'client_id'     => env('GOOGLE_CLIENT_ID', ''),
                'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
                'redirect_uri'  => $this->redirectUri(),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        $token = json_decode((string) $resp->getBody(), true) ?? [];

        if (empty($token['access_token'])) {
            throw new RuntimeException('Falha ao obter token de acesso do Google.');
        }
        if (empty($token['refresh_token'])) {
            throw new RuntimeException('O Google não devolveu refresh_token. Revogue o acesso do app na sua conta Google e tente conectar novamente.');
        }

        $expiresAt = isset($token['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int) $token['expires_in'])
            : null;

        $email = $this->fetchEmail($token['access_token']);

        $this->integrationRepo->upsert($agencyId, [
            'access_token'     => $token['access_token'],
            'refresh_token'    => $token['refresh_token'],
            'token_expires_at' => $expiresAt,
            'connected_email'  => $email,
        ]);

        // Cria (ou reaproveita) a pasta raiz no Drive da agência.
        $rootId = $this->createFolderRaw($token['access_token'], self::ROOT_NAME, null);
        $this->integrationRepo->setRootFolder($agencyId, $rootId);

        return ['root_folder_id' => $rootId, 'email' => $email];
    }

    /** Devolve um access token válido, renovando via refresh_token se expirado. */
    public function accessToken(int $agencyId): string
    {
        $i = $this->getIntegration($agencyId);
        if (!$i) {
            throw new RuntimeException('Google Drive não conectado para esta agência.');
        }

        $expiresAt = $i['token_expires_at'] ?? null;
        $valid = !empty($i['access_token'])
            && $expiresAt !== null
            && strtotime((string) $expiresAt) > (time() + 60);

        if ($valid) {
            return $i['access_token'];
        }

        $resp = (new Client(['timeout' => 30]))->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id'     => env('GOOGLE_CLIENT_ID', ''),
                'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
                'refresh_token' => $i['refresh_token'],
                'grant_type'    => 'refresh_token',
            ],
        ]);

        $token = json_decode((string) $resp->getBody(), true) ?? [];
        if (empty($token['access_token'])) {
            throw new RuntimeException('Falha ao renovar o token do Google Drive. Reconecte a integração.');
        }

        $newExpires = isset($token['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int) $token['expires_in'])
            : null;

        $this->integrationRepo->updateAccessToken($agencyId, $token['access_token'], $newExpires);

        return $token['access_token'];
    }

    // ── Pastas ───────────────────────────────────────────────────────────────

    /**
     * Garante a pasta raiz do cliente (sob a root da agência). Persiste em
     * clients.drive_folder_id. Retorna o ID da pasta no Drive.
     */
    public function ensureClientFolder(array $client, int $agencyId): string
    {
        if (!empty($client['drive_folder_id'])) {
            return $client['drive_folder_id'];
        }

        $integration = $this->getIntegration($agencyId);
        $rootId      = $integration['root_folder_id'] ?? null;
        if (!$rootId) {
            throw new RuntimeException('Pasta raiz do Drive não configurada. Reconecte a integração.');
        }

        $folderId = $this->createFolder($agencyId, (string) ($client['name'] ?? 'Cliente'), $rootId);
        $this->clientRepo->updateById((int) $client['id'], ['drive_folder_id' => $folderId]);

        return $folderId;
    }

    public function createFolder(int $agencyId, string $name, string $parentDriveId): string
    {
        return $this->createFolderRaw($this->accessToken($agencyId), $name, $parentDriveId);
    }

    // ── Upload resumável ─────────────────────────────────────────────────────

    /**
     * Inicia uma sessão de upload resumável e devolve a session URI (Location).
     * O navegador faz o PUT dos bytes direto nessa URI.
     */
    public function initiateResumable(int $agencyId, string $parentDriveId, string $name, string $mime, int $size): string
    {
        $token = $this->accessToken($agencyId);

        $resp = (new Client(['timeout' => 30]))->post(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true',
            [
                'headers' => [
                    'Authorization'           => 'Bearer ' . $token,
                    'Content-Type'            => 'application/json; charset=UTF-8',
                    'X-Upload-Content-Type'   => $mime ?: 'application/octet-stream',
                    'X-Upload-Content-Length' => (string) $size,
                ],
                'body' => json_encode([
                    'name'    => $name,
                    'parents' => [$parentDriveId],
                ]),
            ]
        );

        $location = $resp->getHeaderLine('Location');
        if ($location === '') {
            throw new RuntimeException('Google não devolveu a URI de upload resumável.');
        }

        return $location;
    }

    /** Metadados do arquivo após o upload (thumbnail, link de visualização). */
    public function fileMeta(int $agencyId, string $fileId): array
    {
        $token = $this->accessToken($agencyId);

        $resp = (new Client(['timeout' => 20]))->get(
            "https://www.googleapis.com/drive/v3/files/{$fileId}",
            [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query'   => ['fields' => 'id,name,mimeType,size,thumbnailLink,webViewLink'],
            ]
        );

        return json_decode((string) $resp->getBody(), true) ?? [];
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    private function createFolderRaw(string $accessToken, string $name, ?string $parentDriveId): string
    {
        $body = ['name' => $name, 'mimeType' => self::FOLDER_MIME];
        if ($parentDriveId !== null) {
            $body['parents'] = [$parentDriveId];
        }

        $resp = (new Client(['timeout' => 30]))->post(
            'https://www.googleapis.com/drive/v3/files',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'query' => ['fields' => 'id'],
                'body'  => json_encode($body),
            ]
        );

        $data = json_decode((string) $resp->getBody(), true) ?? [];
        if (empty($data['id'])) {
            throw new RuntimeException('Falha ao criar pasta no Google Drive.');
        }

        return $data['id'];
    }

    private function fetchEmail(string $accessToken): ?string
    {
        try {
            $resp = (new Client(['timeout' => 15]))->get(
                'https://www.googleapis.com/oauth2/v3/userinfo',
                ['headers' => ['Authorization' => 'Bearer ' . $accessToken]]
            );
            $data = json_decode((string) $resp->getBody(), true) ?? [];
            return $data['email'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function redirectUri(): string
    {
        return rtrim(env('APP_URL', ''), '/') . '/integrations/google-drive/oauth/callback';
    }
}
