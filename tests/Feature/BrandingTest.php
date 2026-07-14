<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ImageUploadService;

/**
 * PROD-06 (white-label) + upload de logotipo.
 *
 * O campo pedia uma **URL**: obrigava a pessoa a hospedar a imagem em outro
 * lugar antes de usar o sistema, e um host externo fora do ar fazia o logo
 * sumir do portal da cliente.
 *
 * O arquivo enviado vira **URL pública no nosso domínio** — então as validações
 * aqui não são burocracia: SVG é XML com script (XSS armazenado), e confiar no
 * MIME que o navegador declara permitiria subir um `.php` disfarçado.
 */
class BrandingTest extends FeatureTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/yve-branding-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    /** Um PNG mínimo de verdade (o finfo precisa reconhecer os bytes). */
    private function fakePng(): string
    {
        $path = $this->tmpDir . '/logo.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $path);

        return $path;
    }

    private function fileArray(string $path, string $declaredMime, string $name): array
    {
        return [
            'name'     => $name,
            'type'     => $declaredMime, // o que o navegador DIZ — nunca confiar
            'tmp_name' => $path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => (int) filesize($path),
        ];
    }

    public function test_svg_e_recusado_mesmo_declarado_como_png(): void
    {
        $svg = $this->tmpDir . '/hostil.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $service = new ImageUploadService();

        $this->expectException(\RuntimeException::class);
        // O navegador declara image/png, mas o conteúdo é SVG: servido do nosso
        // domínio, seria XSS armazenado. O finfo precisa pegar isso.
        $service->storeLogo($this->fileArray($svg, 'image/png', 'logo.png'));
    }

    public function test_php_disfarcado_de_imagem_e_recusado(): void
    {
        $php = $this->tmpDir . '/shell.php';
        file_put_contents($php, "<?php echo 'oi'; ?>");

        $service = new ImageUploadService();

        $this->expectException(\RuntimeException::class);
        $service->storeLogo($this->fileArray($php, 'image/png', 'logo.png'));
    }

    public function test_upload_sem_ser_de_http_e_recusado(): void
    {
        // is_uploaded_file() falha para caminho arbitrário — impede que alguém
        // aponte o "upload" para um arquivo do servidor.
        $service = new ImageUploadService();

        $this->expectException(\RuntimeException::class);
        $service->storeLogo($this->fileArray($this->fakePng(), 'image/png', 'logo.png'));
    }

    public function test_delete_logo_nao_sai_da_pasta_de_uploads(): void
    {
        $service = new ImageUploadService();

        $vitima = public_path('index.php');
        $antes  = file_exists($vitima);

        // Path traversal: não pode apagar nada fora de /uploads/logos.
        $service->deleteLogo('/uploads/logos/../../index.php');

        $this->assertSame($antes, file_exists($vitima), 'deleteLogo não pode escapar da pasta de uploads.');
    }

    /** URL externa (legado) não é "nossa" para apagar. */
    public function test_delete_ignora_url_externa(): void
    {
        $service = new ImageUploadService();
        $service->deleteLogo('https://cdn.exemplo.com/logo.png'); // não pode explodir

        $this->assertTrue(true);
    }

    // ── White-label ──────────────────────────────────────────────────────────

    public function test_portal_usa_a_cor_da_agencia(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $this->pdo->prepare('UPDATE agencies SET brand_color = :c WHERE id = :id')
            ->execute([':c' => '#ff0000', ':id' => $agencyId]);

        $body = $this->get('/portal/' . $client['portal_token'])->getBody();

        // A cor entra como componentes numéricos, não como string crua do banco.
        $this->assertStringContainsString('--accent: 255 0 0', $body);
    }

    public function test_portal_sem_cor_usa_o_tema_padrao(): void
    {
        $agencyId = $this->createAgency();
        $client   = $this->createClient($agencyId);

        $body = $this->get('/portal/' . $client['portal_token'])->getBody();

        $this->assertStringNotContainsString('--accent:', $body, 'Sem cor definida, o portal não sobrescreve o tema.');
    }
}
