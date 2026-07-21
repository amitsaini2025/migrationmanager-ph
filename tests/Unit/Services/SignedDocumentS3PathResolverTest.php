<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Services\SignedDocumentS3PathResolver;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignedDocumentS3PathResolverTest extends TestCase
{
    public function test_locates_local_signed_pdf_from_storage_url(): void
    {
        Storage::fake('public');

        $relativePath = 'signed/42_signed.pdf';
        Storage::disk('public')->put($relativePath, '%PDF-1.4 fake');

        $document = new Document([
            'signed_doc_link' => 'https://example.com/storage/signed/42_signed.pdf',
        ]);
        $document->id = 42;

        $location = SignedDocumentS3PathResolver::locateSignedPdfFile($document);

        $this->assertNotNull($location);
        $this->assertSame('local', $location['disk']);
        $this->assertTrue(is_file($location['path']));
    }

    public function test_locates_s3_signed_pdf_from_virtual_hosted_url(): void
    {
        Storage::fake('s3');
        Config::set('filesystems.disks.s3.bucket', 'my-bucket');

        $s3Key = 'DON123/agreement/signed/77_signed.pdf';
        Storage::disk('s3')->put($s3Key, '%PDF-1.4 fake');

        $document = new Document([
            'signed_doc_link' => 'https://my-bucket.s3.amazonaws.com/DON123/agreement/signed/77_signed.pdf',
        ]);
        $document->id = 77;

        $location = SignedDocumentS3PathResolver::locateSignedPdfFile($document);

        $this->assertNotNull($location);
        $this->assertSame('s3', $location['disk']);
        $this->assertSame($s3Key, $location['key']);
    }

    public function test_falls_back_to_canonical_s3_key_when_local_link_is_stale(): void
    {
        Storage::fake('public');
        Storage::fake('s3');

        $s3Key = '5/agreement/signed/99_signed.pdf';
        Storage::disk('s3')->put($s3Key, '%PDF-1.4 fake');

        $document = new Document([
            'user_id' => 5,
            'doc_type' => 'agreement',
            'signed_doc_link' => 'https://example.com/storage/signed/99_signed.pdf',
        ]);
        $document->id = 99;

        $location = SignedDocumentS3PathResolver::locateSignedPdfFile($document);

        $this->assertNotNull($location);
        $this->assertSame('s3', $location['disk']);
        $this->assertSame($s3Key, $location['key']);
    }

    public function test_strips_bucket_prefix_from_path_style_s3_url(): void
    {
        Storage::fake('s3');
        Config::set('filesystems.disks.s3.bucket', 'my-bucket');

        $s3Key = 'DON123/agreement/signed/88_signed.pdf';
        Storage::disk('s3')->put($s3Key, '%PDF-1.4 fake');

        $document = new Document([
            'signed_doc_link' => 'https://s3.amazonaws.com/my-bucket/DON123/agreement/signed/88_signed.pdf',
        ]);
        $document->id = 88;

        $location = SignedDocumentS3PathResolver::locateSignedPdfFile($document);

        $this->assertNotNull($location);
        $this->assertSame('s3', $location['disk']);
        $this->assertSame($s3Key, $location['key']);
    }
}
