<?php

namespace Tests\Unit;

use PHPUnit\Framework\Assert;
use Tests\TestCase;

class MmSelectLoadingTemplateTest extends TestCase
{
    public function test_ajax_loading_markup_does_not_use_generic_spinner_class(): void
    {
        $bridge = file_get_contents($this->projectPath('resources/js/vendor/mm-tomselect-jquery.js'));
        Assert::assertNotFalse($bridge);
        Assert::assertStringContainsString('class="mm-select-loading">Searching...</div>', $bridge);
        Assert::assertStringNotContainsString('class="spinner">Searching...</div>', $bridge);
    }

    public function test_dashboard_overlay_spinner_css_is_scoped_to_the_overlay(): void
    {
        $dashboard = file_get_contents($this->projectPath('resources/views/crm/dashboard-optimized.blade.php'));
        Assert::assertNotFalse($dashboard);
        Assert::assertStringContainsString('.spinner-container .spinner {', $dashboard);
        Assert::assertDoesNotMatchRegularExpression('/^\\.spinner \\{/m', $dashboard);
    }

    public function test_tom_select_compat_styles_the_search_loading_label(): void
    {
        $css = file_get_contents($this->projectPath('public/css/tom-select-layout-compat.css'));
        Assert::assertNotFalse($css);
        Assert::assertStringContainsString('.ts-dropdown .mm-select-loading', $css);
        Assert::assertStringContainsString('white-space: nowrap', $css);
    }

    private function projectPath(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
