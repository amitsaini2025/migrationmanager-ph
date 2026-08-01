<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class PythonConverterService
{
    private $apiUrl;
    private $timeout;

    public function __construct()
    {
        $this->apiUrl = env('PYTHON_CONVERTER_URL', 'http://localhost:5000');
        $this->timeout = env('PYTHON_CONVERTER_TIMEOUT', 120); // 2 minutes timeout
    }

    /**
     * Check if using Unix socket connection
     */
    private function isUnixSocket(): bool
    {
        return strpos($this->apiUrl, 'unix://') === 0;
    }

    /**
     * Extract filesystem path from a unix:// URL (e.g. unix:///var/run/app.sock).
     */
    private function getUnixSocketPath(): string
    {
        $path = substr($this->apiUrl, strlen('unix://'));

        // unix:///path → /path; unix://path → /path
        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Build the HTTP URL for an API path.
     * HTTP configs keep the configured base URL unchanged.
     * Unix sockets use a dummy http host; curl routes via the socket.
     */
    private function buildEndpointUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if ($this->isUnixSocket()) {
            return 'http://localhost' . $path;
        }

        return $this->apiUrl . $path;
    }

    /**
     * Get the appropriate HTTP client for the connection type
     */
    private function getHttpClient()
    {
        if ($this->isUnixSocket()) {
            return Http::timeout($this->timeout)->withOptions([
                'curl' => [
                    CURLOPT_UNIX_SOCKET_PATH => $this->getUnixSocketPath(),
                ],
            ]);
        }

        return Http::timeout($this->timeout);
    }

    /**
     * Convert a DOC/DOCX file to PDF using the Python API
     */
    public function convertToPdf(UploadedFile $file, string $outputPath = null): array
    {
        try {
            // Validate file
            $this->validateFile($file);

            // Check if Python API is available
            if (!$this->isApiAvailable()) {
                throw new \Exception('Python conversion service is not available');
            }

            // Make request to Python API
            $response = $this->getHttpClient()
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($this->buildEndpointUrl('/convert-json'));

            if ($response->successful()) {
                $data = $response->json();

                if ($data['success']) {
                    // Convert base64 data back to binary
                    $pdfData = base64_decode($data['pdf_data']);

                    // Save to output path if provided
                    if ($outputPath) {
                        file_put_contents($outputPath, $pdfData);
                    }

                    return [
                        'success' => true,
                        'pdf_data' => $pdfData,
                        'filename' => $data['filename'],
                        'method' => $data['method'],
                        'message' => $data['message']
                    ];
                } else {
                    throw new \Exception($data['error'] ?? 'Conversion failed');
                }
            } else {
                $errorData = $response->json();
                $errorMessage = $errorData['error'] ?? 'HTTP request failed';
                throw new \Exception("API request failed: {$errorMessage}");
            }

        } catch (\Exception $e) {
            Log::error('Python conversion failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convert file and return PDF as download response
     */
    public function convertAndDownload(UploadedFile $file): \Illuminate\Http\Response
    {
        $result = $this->convertToPdf($file);

        if (!$result['success']) {
            throw new \Exception($result['error']);
        }

        $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.pdf';

        return response($result['pdf_data'])
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Check if the Python API is available
     */
    public function isApiAvailable(): bool
    {
        try {
            $response = $this->getHttpClient()->get($this->buildEndpointUrl('/health'));
            return $response->successful() && $response->json('status') === 'healthy';
        } catch (\Exception $e) {
            Log::warning('Python API health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get API status information
     */
    public function getApiStatus(): array
    {
        try {
            $response = $this->getHttpClient()->get($this->buildEndpointUrl('/health'));

            if ($response->successful()) {
                return [
                    'available' => true,
                    'status' => $response->json()
                ];
            } else {
                return [
                    'available' => false,
                    'error' => 'API responded with error: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            return [
                'available' => false,
                'error' => 'API connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file): void
    {
        $allowedExtensions = ['doc', 'docx'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception("Unsupported file format. Allowed formats: " . implode(', ', $allowedExtensions));
        }

        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($file->getSize() > $maxSize) {
            throw new \Exception("File too large. Maximum size is 50MB.");
        }
    }

    /**
     * Test the conversion service
     */
    public function testConversion(): array
    {
        try {
            // Create a simple test DOCX file
            $testContent = 'Test Document';
            $testFile = $this->createTestDocx($testContent);

            // Convert the test file
            $result = $this->convertToPdf($testFile);

            // Clean up test file
            if (file_exists($testFile->getRealPath())) {
                unlink($testFile->getRealPath());
            }

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Test conversion failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a test DOCX file for testing
     */
    private function createTestDocx(string $content): UploadedFile
    {
        // This is a simplified test - in production you might want to create a proper DOCX
        $tempFile = tempnam(sys_get_temp_dir(), 'test_docx_');
        file_put_contents($tempFile, $content);

        return new UploadedFile(
            $tempFile,
            'test.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }
}
