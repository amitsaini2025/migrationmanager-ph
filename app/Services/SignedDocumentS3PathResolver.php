<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves S3 storage paths for signed documents.
 *
 * Client-linked docs:  {admins.client_id}/{doc_type}/signed/{id}_signed.pdf
 * Ad-hoc docs (null client_id): {staff_admin_id}/{doc_type}/signed/{id}_signed.pdf
 *   — prefix parsed from myfile URL or fallback to documents.user_id
 */
class SignedDocumentS3PathResolver
{
    public static function resolvePrefix(Document $document): int|string|null
    {
        if ($document->client_id) {
            $admin = DB::table('admins')
                ->select('client_id')
                ->where('id', '=', $document->client_id)
                ->first();

            if ($admin && $admin->client_id) {
                return $admin->client_id;
            }

            return $document->client_id;
        }

        $fromMyfile = self::parseMyfileS3Segments($document->myfile);
        if ($fromMyfile !== null) {
            return $fromMyfile['prefix'];
        }

        if ($document->user_id) {
            return $document->user_id;
        }

        return null;
    }

    public static function resolveDocType(Document $document): string
    {
        $docType = trim((string) ($document->doc_type ?? ''));
        if ($docType !== '') {
            return $docType;
        }

        $fromMyfile = self::parseMyfileS3Segments($document->myfile);
        if ($fromMyfile !== null && $fromMyfile['doc_type'] !== '') {
            return $fromMyfile['doc_type'];
        }

        return 'ad_hoc_documents';
    }

    public static function resolveSignedPdfS3Key(Document $document): ?string
    {
        $prefix = self::resolvePrefix($document);
        if ($prefix === null || $prefix === '') {
            return null;
        }

        return $prefix . '/' . self::resolveDocType($document) . '/signed/' . $document->id . '_signed.pdf';
    }

    public static function resolveSignaturePngS3Key(Document $document, string $filename): ?string
    {
        $prefix = self::resolvePrefix($document);
        if ($prefix === null || $prefix === '') {
            return null;
        }

        $basename = basename(ltrim($filename, '/'));

        return $prefix . '/' . self::resolveDocType($document) . '/signatures/' . $basename;
    }

    /**
     * Parse {prefix}/{doc_type}/... from an S3 URL or key stored in myfile.
     *
     * @return array{prefix: string, doc_type: string}|null
     */
    public static function parseMyfileS3Segments(?string $myfile): ?array
    {
        if ($myfile === null || trim($myfile) === '') {
            return null;
        }

        $path = trim($myfile);
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($path);
            $path = ltrim((string) ($parsed['path'] ?? ''), '/');
        } else {
            $path = ltrim($path, '/');
        }

        if ($path === '' || str_starts_with($path, 'storage/') || str_starts_with($path, 'signed/')) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($segment) => $segment !== ''));
        if (count($segments) < 2 || $segments[1] === 'signed') {
            return null;
        }

        return [
            'prefix' => $segments[0],
            'doc_type' => $segments[1],
        ];
    }

    /**
     * Locate a signed PDF on the public disk or S3.
     *
     * Tries the stored signed_doc_link first, then falls back to the canonical S3 key
     * when the link is stale (e.g. local /storage/ URL after migration to S3).
     *
     * @return array{disk: 'local', path: string}|array{disk: 's3', key: string}|null
     */
    public static function locateSignedPdfFile(Document $document, ?string $fileUrl = null): ?array
    {
        $fileUrl = trim((string) ($fileUrl ?? $document->signed_doc_link ?? ''));
        if ($fileUrl === '') {
            return self::locateOnS3ByCanonicalKey($document);
        }

        $localPath = self::resolveLocalPublicPath($fileUrl);
        if ($localPath !== null) {
            return ['disk' => 'local', 'path' => $localPath];
        }

        return self::locateOnS3($document, $fileUrl);
    }

    private static function resolveLocalPublicPath(string $fileUrl): ?string
    {
        $relativePath = self::extractLocalPublicRelativePath($fileUrl);
        if ($relativePath === null || ! Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        try {
            $size = Storage::disk('public')->size($relativePath);
        } catch (\Throwable) {
            return null;
        }

        if ($size <= 0) {
            return null;
        }

        return Storage::disk('public')->path($relativePath);
    }

    private static function extractLocalPublicRelativePath(string $fileUrl): ?string
    {
        $parsed = parse_url($fileUrl);
        $urlPath = (string) ($parsed['path'] ?? '');

        if ($urlPath !== '' && str_contains($urlPath, '/storage/')) {
            $parts = explode('/storage/', $urlPath);
            $relative = end($parts);

            return $relative !== '' ? $relative : null;
        }

        if (! filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            $relative = ltrim($fileUrl, '/');
            if (str_starts_with($relative, 'signed/')) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * @return array{disk: 's3', key: string}|null
     */
    private static function locateOnS3ByCanonicalKey(Document $document): ?array
    {
        $key = self::resolveSignedPdfS3Key($document);
        if ($key === null || ! Storage::disk('s3')->exists($key)) {
            return null;
        }

        return ['disk' => 's3', 'key' => $key];
    }

    /**
     * @return array{disk: 's3', key: string}|null
     */
    private static function locateOnS3(Document $document, string $fileUrl): ?array
    {
        $disk = Storage::disk('s3');

        foreach (self::candidateSignedPdfS3Keys($document, $fileUrl) as $key) {
            if ($disk->exists($key)) {
                return ['disk' => 's3', 'key' => $key];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function candidateSignedPdfS3Keys(Document $document, string $fileUrl): array
    {
        $keys = [];

        $parsed = parse_url($fileUrl);
        $urlPath = (string) ($parsed['path'] ?? '');
        if ($urlPath !== '') {
            $keys[] = self::normalizeS3ObjectKey(ltrim(urldecode($urlPath), '/'));
        }

        if (! filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            $keys[] = self::normalizeS3ObjectKey(ltrim($fileUrl, '/'));
        }

        $canonical = self::resolveSignedPdfS3Key($document);
        if ($canonical !== null) {
            $keys[] = $canonical;
        }

        $myfile = trim((string) ($document->myfile ?? ''));
        if ($myfile !== '') {
            if (filter_var($myfile, FILTER_VALIDATE_URL)) {
                $myParsed = parse_url($myfile);
                $myPath = (string) ($myParsed['path'] ?? '');
                if ($myPath !== '') {
                    $keys[] = self::normalizeS3ObjectKey(ltrim(urldecode($myPath), '/'));
                }
            } elseif (! str_starts_with($myfile, 'signed/') && ! str_starts_with($myfile, 'storage/')) {
                $keys[] = self::normalizeS3ObjectKey(ltrim($myfile, '/'));
            }
        }

        return array_values(array_unique(array_filter($keys, static fn ($key) => $key !== '')));
    }

    private static function normalizeS3ObjectKey(string $key): string
    {
        $bucket = (string) config('filesystems.disks.s3.bucket', '');
        if ($bucket !== '' && str_starts_with($key, $bucket . '/')) {
            return substr($key, strlen($bucket) + 1);
        }

        return $key;
    }
}
