<?php

namespace Tests\Unit;

use App\Support\ClientDetailModals;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientDetailModalsTest extends TestCase
{
    #[Test]
    public function shell_ids_keep_compose_sms_tags_and_reassign(): void
    {
        Assert::assertSame([
            'emailmodal',
            'sendmsgmodal',
            'sendSmsModal',
            'tags_clients',
            'inbox_reassignemail_modal',
            'sent_reassignemail_modal',
            'sent_mail_preview_modal',
        ], ClientDetailModals::shellIds());
    }

    #[Test]
    public function extra_ids_keep_notes_convert_lead_and_appointment(): void
    {
        Assert::assertContains('create_note_d', ClientDetailModals::extraIds());
        Assert::assertContains('convertLeadToClientModal', ClientDetailModals::extraIds());
        Assert::assertContains('create_appoint', ClientDetailModals::extraIds());
        Assert::assertContains('matteremailmodal', ClientDetailModals::extraIds());
        Assert::assertContains('createreceiptmodal', ClientDetailModals::extraIds());
        Assert::assertContains('editLedgerModal', ClientDetailModals::extraIds());
        Assert::assertContains('form956CreateFormModel', ClientDetailModals::extraIds());
        Assert::assertContains('visaAgreementCreateFormModel', ClientDetailModals::extraIds());
    }

    #[Test]
    public function fragment_routes_and_pack_lookup_stay_stable(): void
    {
        Assert::assertSame([
            'shell' => 'clients.detail.shell-modals',
            'extra' => 'clients.detail.extra-modals',
        ], ClientDetailModals::fragmentRouteNames());
        Assert::assertSame('shell', ClientDetailModals::packForModalId('emailmodal'));
        Assert::assertSame('extra', ClientDetailModals::packForModalId('create_note_d'));
        Assert::assertNull(ClientDetailModals::packForModalId('checkinmodal'));
    }

    #[Test]
    public function stubs_cover_shell_and_extra_ids(): void
    {
        $ids = array_values(array_filter(array_column(ClientDetailModals::stubs(), 'id')));
        foreach (ClientDetailModals::shellIds() as $id) {
            Assert::assertContains($id, $ids);
        }
        foreach (ClientDetailModals::extraIds() as $id) {
            Assert::assertContains($id, $ids);
        }
    }

    #[Test]
    public function client_detail_sms_modal_handlers_are_delegated_for_lazy_shell_pack(): void
    {
        $blade = (string) file_get_contents(resource_path('views/crm/clients/detail.blade.php'));

        Assert::assertStringContainsString("$(document).on('change.clientSendSms', '#sms_template'", $blade);
        Assert::assertStringContainsString("$(document).on('input.clientSendSms', '#sms_message'", $blade);
        Assert::assertStringContainsString("$(document).on('submit.clientSendSms', '#sendSmsForm'", $blade);
        Assert::assertDoesNotMatchRegularExpression("/\\\$\\('#sms_template'\\)\\.on\\('change'/", $blade);
        Assert::assertDoesNotMatchRegularExpression("/\\\$\\('#sms_message'\\)\\.on\\('input'/", $blade);
        Assert::assertDoesNotMatchRegularExpression("/\\\$\\('#sendSmsForm'\\)\\.on\\('submit'/", $blade);
        Assert::assertMatchesRegularExpression("/\\\$\\('\\.send-sms-btn'\\)\\.on\\('click'/", $blade);
    }

    #[Test]
    public function send_sms_message_textarea_avoids_form_control_height_lock(): void
    {
        $shell = (string) file_get_contents(resource_path('views/crm/clients/modals/shell_modals.blade.php'));
        $customCss = (string) file_get_contents(public_path('css/custom.css'));

        Assert::assertStringContainsString('id="sms_message"', $shell);
        Assert::assertStringContainsString('class="sms-compose-message"', $shell);
        Assert::assertDoesNotMatchRegularExpression('/id="sms_message"[^>]*class="form-control"/', $shell);
        Assert::assertDoesNotMatchRegularExpression('/class="form-control"[^>]*id="sms_message"/', $shell);
        Assert::assertStringContainsString('textarea.sms-compose-message', $customCss);
        Assert::assertStringContainsString('resize: vertical', $customCss);
    }
}
