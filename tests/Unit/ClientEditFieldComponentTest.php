<?php

namespace Tests\Unit;

use PHPUnit\Framework\Assert;
use Tests\TestCase;

class ClientEditFieldComponentTest extends TestCase
{
    public function test_passport_field_does_not_loop_country_options_in_blade(): void
    {
        $blade = $this->viewContents('resources/views/components/client-edit/passport-field.blade.php');

        Assert::assertStringContainsString('data-country-select="priority"', $blade);
        Assert::assertStringContainsString('data-selected=', $blade);
        Assert::assertStringNotContainsString('@foreach($countries as $country)', $blade);
    }

    public function test_qualification_field_does_not_loop_country_options_in_blade(): void
    {
        $blade = $this->viewContents('resources/views/components/client-edit/qualification-field.blade.php');

        Assert::assertStringContainsString('data-country-select="priority"', $blade);
        Assert::assertStringNotContainsString('@foreach($countries as $country)', $blade);
    }

    public function test_travel_and_experience_fields_do_not_loop_country_options_in_blade(): void
    {
        $travel = $this->viewContents('resources/views/components/client-edit/travel-field.blade.php');
        $experience = $this->viewContents('resources/views/components/client-edit/work-experience-field.blade.php');

        Assert::assertStringContainsString('data-country-select="names"', $travel);
        Assert::assertStringNotContainsString('@foreach($countries as $country)', $travel);
        Assert::assertStringContainsString('data-country-select="names"', $experience);
        Assert::assertStringNotContainsString('@foreach($countries as $country)', $experience);
    }

    public function test_visa_field_does_not_loop_visa_types_in_blade(): void
    {
        $blade = $this->viewContents('resources/views/components/client-edit/visa-field.blade.php');

        Assert::assertStringContainsString('data-visa-select', $blade);
        Assert::assertStringNotContainsString('@foreach($visaTypes as $visaType)', $blade);
    }

    public function test_phone_field_uses_lazy_dial_select_instead_of_country_code_partial(): void
    {
        $blade = $this->viewContents('resources/views/components/client-edit/phone-number-field.blade.php');

        Assert::assertStringContainsString('data-phone-dial-select', $blade);
        Assert::assertStringNotContainsString('partials.country-code-select', $blade);
    }

    public function test_edit_client_js_hydrates_selects_from_shared_window_data(): void
    {
        $js = $this->viewContents('public/js/clients/edit-client.js');

        Assert::assertStringContainsString('function hydrateClientEditSelects()', $js);
        Assert::assertStringContainsString('window.visaTypesData', $js);
        Assert::assertStringContainsString('window.countriesData', $js);
        Assert::assertStringContainsString('hydrateClientEditSelects();', $js);
    }

    public function test_client_edit_views_pass_visa_types_json_once(): void
    {
        $edit = $this->viewContents('resources/views/crm/clients/edit.blade.php');
        $company = $this->viewContents('resources/views/crm/clients/company_edit.blade.php');

        Assert::assertStringContainsString('window.visaTypesData', $edit);
        Assert::assertStringContainsString('window.visaTypesData', $company);
        Assert::assertStringNotContainsString('ClientMatter::where', $edit);
        Assert::assertStringNotContainsString('Staff::find', $edit);
        Assert::assertStringNotContainsString('ClientMatter::where', $company);
        Assert::assertStringNotContainsString('Staff::find', $company);
    }

    private function viewContents(string $relative): string
    {
        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $contents = file_get_contents($path);
        Assert::assertNotFalse($contents);

        return $contents;
    }
}
