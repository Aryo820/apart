<?php

namespace Tests\Feature;

use App\Filament\Resources\ApartmentResource;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component;
use Tests\TestCase;

/**
 * image() pada FileUpload membuka image/* — termasuk image/svg+xml. SVG yang
 * disimpan ke disk public dan dibuka langsung dari URL-nya mengeksekusi
 * <script> di origin aplikasi (stored XSS). Allowlist MIME harus eksplisit:
 * PNG/JPEG/WebP saja, plus batas ukuran per berkas.
 */
class ApartmentUploadHardeningTest extends TestCase
{
    private const ALLOWED = ['image/png', 'image/jpeg', 'image/webp'];

    public function test_gallery_uploads_reject_svg_and_cap_file_size(): void
    {
        // Evaluasi skema butuh konteks Livewire (HasSchemas); komponen anonim
        // cukup untuk introspeksi konfigurasi form resource.
        $livewire = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }
        };

        $schema = ApartmentResource::form(Schema::make($livewire));

        foreach (['main_image', 'images'] as $fieldName) {
            $field = $schema->getComponent(
                fn ($component) => $component instanceof FileUpload && $component->getName() === $fieldName,
            );

            $this->assertInstanceOf(
                FileUpload::class,
                $field,
                "Field upload [{$fieldName}] hilang dari skema resource.",
            );

            /*
             * Konfigurasi inilah yang Filament turunkan menjadi rule
             * server-side mimetypes:/max: saat validasi (BaseFileUpload::
             * acceptedFileTypes mendaftarkan rule mimetypes persis dari
             * daftar ini), jadi mengunci accessor = mengunci rule.
             */
            $this->assertSame(
                self::ALLOWED,
                $field->getAcceptedFileTypes(),
                "Field {$fieldName} harus hanya menerima PNG/JPEG/WebP.",
            );

            $this->assertSame(
                2048,
                $field->getMaxSize(),
                "Field {$fieldName} wajib membatasi ukuran berkas.",
            );
        }
    }
}
