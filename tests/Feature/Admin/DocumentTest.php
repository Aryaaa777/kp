<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Unggah & unduh dokumen (FR-070/071): validasi berkas, storage privat, otorisasi unduh.
 */
class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('koperasi.document_disk', 'local'));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @return array{0: Cooperative, 1: CooperativeLicense}
     */
    private function license(): array
    {
        $cooperative = Cooperative::factory()->create();
        $license = CooperativeLicense::factory()->create(['cooperative_id' => $cooperative->id]);

        return [$cooperative, $license];
    }

    private function pdf(string $name = 'berkas.pdf', int $sizeKb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $sizeKb, 'application/pdf');
    }

    public function test_admin_can_upload_document(): void
    {
        [$cooperative, $license] = $this->license();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->pdf(),
            ])
            ->assertRedirect(route('admin.koperasi.izin.show', [$cooperative, $license]));

        $document = Document::firstOrFail();

        $this->assertSame($cooperative->id, $document->cooperative_id);
        $this->assertSame($license->getMorphClass(), $document->documentable_type);
        $this->assertSame($license->id, $document->documentable_id);
        $this->assertSame('berkas.pdf', $document->original_name);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame(1, $document->version);
        $this->assertSame($admin->id, $document->uploaded_by);
        $this->assertNotNull($document->checksum);

        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_upload_rejects_disallowed_extension(): void
    {
        [$cooperative, $license] = $this->license();

        // AC-DOC-01
        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => UploadedFile::fake()->create('malware.exe', 10),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_upload_rejects_oversized_file(): void
    {
        [$cooperative, $license] = $this->license();

        // AC-DOC-01 (di atas batas 5 MB)
        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->pdf('besar.pdf', 6000),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_upload_requires_file_and_category(): void
    {
        [$cooperative, $license] = $this->license();

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [])
            ->assertSessionHasErrors(['category', 'file']);
    }

    public function test_uploaded_file_is_stored_privately(): void
    {
        [$cooperative, $license] = $this->license();

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->pdf(),
            ]);

        $document = Document::firstOrFail();

        $this->assertStringStartsWith("documents/{$cooperative->id}/", $document->file_path);
        $this->assertFalse(Storage::disk('public')->exists($document->file_path));

        // AC-DOC-02: URL langsung ke berkas privat gagal (bukan 200).
        $direct = $this->get('/storage/'.$document->file_path);
        $this->assertContains($direct->status(), [403, 404]);
    }

    public function test_guest_cannot_download_document(): void
    {
        [$cooperative, $license] = $this->license();
        $document = Document::factory()->forLicense($license)->create();

        $this->get(route('dokumen.unduh', $document))->assertRedirect(route('login'));
    }

    public function test_admin_can_download_document(): void
    {
        [$cooperative, $license] = $this->license();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
            'category' => 'izin_usaha',
            'file' => $this->pdf('legalitas.pdf'),
        ]);

        $document = Document::firstOrFail();

        $this->actingAs($admin)
            ->get(route('dokumen.unduh', $document))
            ->assertOk()
            ->assertDownload('legalitas.pdf');
    }

    public function test_pengelola_can_download_own_document(): void
    {
        [$cooperative, $license] = $this->license();
        $document = $this->storedDocument($license, 'milik-saya.pdf');

        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)
            ->get(route('dokumen.unduh', $document))
            ->assertOk()
            ->assertDownload('milik-saya.pdf');
    }

    public function test_pengelola_cannot_download_other_cooperative_document(): void
    {
        [$otherCoop, $otherLicense] = $this->license();
        $document = $this->storedDocument($otherLicense, 'milik-orang-lain.pdf');

        $own = Cooperative::factory()->create();
        $user = User::factory()->pengelola($own->id)->create();

        // AC-DOC-03
        $this->actingAs($user)
            ->get(route('dokumen.unduh', $document))
            ->assertForbidden();
    }

    public function test_download_missing_file_returns_not_found(): void
    {
        [$cooperative, $license] = $this->license();
        $document = Document::factory()->forLicense($license)->create([
            'file_path' => 'documents/hilang/tidak-ada.pdf',
        ]);

        $this->actingAs($this->admin())
            ->get(route('dokumen.unduh', $document))
            ->assertNotFound();
    }

    public function test_admin_can_archive_document(): void
    {
        [$cooperative, $license] = $this->license();
        $document = Document::factory()->forLicense($license)->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.dokumen.destroy', $document))
            ->assertRedirect();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_archived_document_cannot_be_downloaded(): void
    {
        [$cooperative, $license] = $this->license();
        $document = $this->storedDocument($license, 'terarsip.pdf');
        $document->delete();

        $this->actingAs($this->admin())
            ->get(route('dokumen.unduh', $document))
            ->assertNotFound();
    }

    public function test_mass_assignment_on_upload_is_ignored(): void
    {
        [$cooperative, $license] = $this->license();
        $admin = $this->admin();
        $other = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->pdf(),
                'cooperative_id' => $other->id,
                'uploaded_by' => 999999,
                'file_path' => '../../evil.pdf',
                'documentable_id' => 999999,
                'documentable_type' => 'App\\Models\\Cooperative',
                'version' => 99,
                'size_bytes' => 1,
            ])
            ->assertSessionHasNoErrors();

        $document = Document::firstOrFail();

        $this->assertSame($cooperative->id, $document->cooperative_id);
        $this->assertSame($admin->id, $document->uploaded_by);
        $this->assertSame($license->getMorphClass(), $document->documentable_type);
        $this->assertSame($license->id, $document->documentable_id);
        $this->assertStringStartsWith("documents/{$cooperative->id}/", $document->file_path);
        $this->assertSame(1, $document->version);
    }

    public function test_upload_rejects_spoofed_client_mime_type(): void
    {
        [$cooperative, $license] = $this->license();

        // Isi berkas adalah teks biasa, tetapi klien mengaku `application/pdf` dan menamainya .pdf.
        // NFR-SEC-008: jenis berkas dinilai dari KONTEN berkas, bukan dari Content-Type klien.
        $spoofed = $this->realFile('dokumen-palsu.pdf', "Ini bukan PDF, hanya teks.\n", 'application/pdf');

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $spoofed,
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_upload_rejects_disallowed_content_even_with_allowed_name(): void
    {
        [$cooperative, $license] = $this->license();

        // Berkonten executable tetapi bernama .pdf → tetap ditolak.
        $executable = $this->realFile('jahat.pdf', "MZ\x90\x00".str_repeat("\x00", 64));

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $executable,
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_upload_type_is_derived_from_content_not_client_extension(): void
    {
        [$cooperative, $license] = $this->license();

        // JPEG asli yang dinamai .pdf dan mengaku application/pdf: jenis ditentukan dari isi berkas.
        $jpeg = $this->realFile('menyamar.pdf', $this->jpegBytes(), 'application/pdf');

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $jpeg,
            ])
            ->assertSessionHasNoErrors();

        $document = Document::firstOrFail();

        $this->assertSame('image/jpeg', $document->mime_type);
        $this->assertSame('menyamar.pdf', $document->original_name);
        $this->assertMatchesRegularExpression(
            '~^documents/'.$cooperative->id.'/[A-Za-z0-9]+\.(jpe?g)$~',
            $document->file_path,
        );
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_upload_accepts_file_exactly_at_size_limit_and_rejects_over(): void
    {
        [$cooperative, $license] = $this->license();
        $admin = $this->admin();
        $maxKb = (int) config('koperasi.document_max_size_kb');

        // Tepat pada batas (5120 KB) → diterima.
        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->realFile('tepat-batas.pdf', $this->pdfBytes($maxKb * 1024)),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($maxKb * 1024, Document::firstOrFail()->size_bytes);

        // Melewati batas → ditolak.
        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->realFile('lewat-batas.pdf', $this->pdfBytes($maxKb * 1024 + 1024)),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, Document::count());
    }

    public function test_upload_sanitizes_original_file_name(): void
    {
        [$cooperative, $license] = $this->license();
        $admin = $this->admin();

        // Nama dengan komponen path tidak boleh bocor ke metadata maupun ke path penyimpanan.
        $traversal = $this->realFile('..\\..\\..\\jahat.pdf', $this->pdfBytes(2048));

        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $traversal,
            ])
            ->assertSessionHasNoErrors();

        $first = Document::firstOrFail();

        $this->assertSame('jahat.pdf', $first->original_name);
        $this->assertStringNotContainsString('/', $first->original_name);
        $this->assertStringNotContainsString('\\', $first->original_name);
        $this->assertMatchesRegularExpression(
            '~^documents/'.$cooperative->id.'/[A-Za-z0-9]+\.pdf$~',
            $first->file_path,
        );

        // Nama sangat panjang dipotong agar muat kolom (255) dan tetap aman.
        $longName = str_repeat('nama-berkas-sangat-panjang-', 20).'.pdf';

        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->realFile($longName, $this->pdfBytes(2048)),
            ])
            ->assertSessionHasNoErrors();

        $second = Document::latest('id')->firstOrFail();

        $this->assertLessThanOrEqual(255, mb_strlen($second->original_name));
        $this->assertMatchesRegularExpression(
            '~^documents/'.$cooperative->id.'/[A-Za-z0-9]+\.pdf$~',
            $second->file_path,
        );
    }

    public function test_download_returns_exact_content_with_attachment_headers(): void
    {
        [$cooperative, $license] = $this->license();
        $admin = $this->admin();
        $bytes = $this->pdfBytes(4096);

        $this->actingAs($admin)->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
            'category' => 'izin_usaha',
            'file' => $this->realFile('legalitas.pdf', $bytes),
        ]);

        $document = Document::firstOrFail();

        // AC-DOC-02: unduhan mengembalikan isi berkas yang benar (bukan sekadar status 2xx).
        $response = $this->actingAs($admin)->get(route('dokumen.unduh', $document));

        $response->assertOk()->assertDownload('legalitas.pdf');
        $this->assertSame($bytes, $response->streamedContent());

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
    }

    public function test_download_file_name_cannot_inject_headers(): void
    {
        [, $license] = $this->license();

        // Nama bermuatan karakter sensitif header (kutip, titik-koma, persen) tidak boleh
        // menghasilkan header ganda/baris baru. Nama seperti ini dimungkinkan pada data lama.
        $document = $this->storedDocument($license, 'laporan "penting"; v=1%.pdf');

        $response = $this->actingAs($this->admin())->get(route('dokumen.unduh', $document));

        $response->assertOk();

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
    }

    public function test_cannot_upload_document_to_archived_license(): void
    {
        [$cooperative, $license] = $this->license();
        $license->delete();

        $this->actingAs($this->admin())
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->pdf(),
            ])
            ->assertNotFound();

        $this->assertSame(0, Document::count());
    }

    /**
     * Berkas "nyata": isi berkas benar-benar ditulis ke disk sehingga validasi MIME/ekstensi
     * diuji dari **konten** (finfo), bukan dari laporan MIME sintetis `UploadedFile::fake()`.
     */
    private function realFile(string $name, string $bytes, ?string $clientMime = null): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'kp-f3-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $clientMime, null, true);
    }

    /**
     * Isi berkas dengan magic `%PDF-` pada ukuran byte tertentu (dipakai untuk uji batas ukuran).
     */
    private function pdfBytes(int $size): string
    {
        $head = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n";
        $tail = "\ntrailer\n<</Root 1 0 R>>\n%%EOF\n";

        return $head.str_repeat('A', max(0, $size - strlen($head) - strlen($tail))).$tail;
    }

    private function jpegBytes(): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            .str_repeat("\x00", 200)."\xFF\xD9";
    }

    /**
     * Unggah berkas nyata ke storage (fake) lalu kembalikan modelnya.
     */
    private function storedDocument(CooperativeLicense $license, string $originalName): Document
    {
        $path = "documents/{$license->cooperative_id}/".uniqid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 demo');

        return Document::factory()->forLicense($license)->create([
            'original_name' => $originalName,
            'file_path' => $path,
        ]);
    }

    public function test_admin_can_upload_new_document_version_and_old_version_is_retained(): void
    {
        [$cooperative, $license] = $this->license();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.store', [$cooperative, $license]), [
                'category' => 'izin_usaha',
                'file' => $this->pdf('versi-1.pdf'),
            ])
            ->assertRedirect();

        $original = Document::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.versi', [$cooperative, $license, $original]), [
                'file' => $this->pdf('versi-2.pdf'),
            ])
            ->assertRedirect(route('admin.koperasi.izin.show', [$cooperative, $license]));

        $original->refresh();
        $replacement = Document::query()->where('previous_version_id', $original->id)->firstOrFail();

        $this->assertNotNull($original->superseded_at);
        $this->assertSame(2, $replacement->version);
        $this->assertSame($original->id, $replacement->previous_version_id);
        $this->assertSame('versi-2.pdf', $replacement->original_name);
        $this->assertSame('izin_usaha', $replacement->category);
        $this->assertSame(2, Document::count());

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'document_version_created',
            'subject_id' => $replacement->id,
        ]);
    }

    public function test_superseded_document_cannot_be_versioned_again(): void
    {
        [$cooperative, $license] = $this->license();
        $admin = $this->admin();
        $document = $this->storedDocument($license, 'lama.pdf');
        $document->forceFill(['superseded_at' => now()])->save();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.izin.dokumen.versi', [$cooperative, $license, $document]), [
                'file' => $this->pdf(),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(1, Document::count());
    }
}
