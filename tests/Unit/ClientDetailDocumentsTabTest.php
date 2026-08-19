<?php

namespace Tests\Unit;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Document tab fragments: one query per tab, staff eager-loaded, no per-row Staff/Admin finds.
 */
class ClientDetailDocumentsTabTest extends TestCase
{
    #[Test]
    public function document_tab_blades_do_not_query_staff_or_admin_per_row(): void
    {
        foreach ([
            'resources/views/crm/clients/tabs/personal_documents.blade.php',
            'resources/views/crm/clients/tabs/visa_documents.blade.php',
            'resources/views/crm/clients/tabs/not_used_documents.blade.php',
        ] as $relativePath) {
            $contents = file_get_contents($this->projectPath($relativePath));
            Assert::assertNotFalse($contents);
            Assert::assertStringNotContainsString('Staff::find', $contents, $relativePath);
            Assert::assertStringNotContainsString("Staff::where('id'", $contents, $relativePath);
            Assert::assertStringNotContainsString('Admin::find', $contents, $relativePath);
            Assert::assertStringNotContainsString("Admin::where('id'", $contents, $relativePath);
            Assert::assertStringNotContainsString('\App\Models\Document::', $contents, $relativePath);
            Assert::assertStringContainsString('ClientDetailDocumentsTab', $contents, $relativePath);
            Assert::assertStringContainsString('->staff', $contents, $relativePath);
        }
    }

    #[Test]
    public function document_helpers_eager_load_staff_and_group_by_folder(): void
    {
        $helper = file_get_contents($this->projectPath('app/Support/ClientDetailDocumentsTab.php'));
        Assert::assertNotFalse($helper);
        Assert::assertStringContainsString("->with('staff')", $helper);
        Assert::assertStringContainsString("->with(['staff', 'signers'])", $helper);
        Assert::assertStringContainsString('function personalDocumentsByFolder', $helper);
        Assert::assertStringContainsString('function visaDocumentsByFolder', $helper);
        Assert::assertStringContainsString('function notUsedDocuments', $helper);
        Assert::assertStringContainsString('groupBy', $helper);
        Assert::assertStringNotContainsString('Staff::find', $helper);
        Assert::assertStringNotContainsString('Admin::find', $helper);
    }

    private function projectPath(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
