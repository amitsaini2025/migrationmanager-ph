<?php

namespace App\Support;

/**
 * Client-detail always-on modals that load as HTML fragments (same IDs as before).
 *
 * Check-in stays in layouts.crm_client_detail — this list is client-detail only.
 */
final class ClientDetailModals
{
    /**
     * Compose / SMS / tags / reassign — CRM template and checklist rows load on modal open.
     *
     * @return list<string>
     */
    public static function shellIds(): array
    {
        return [
            'emailmodal',
            'sendmsgmodal',
            'sendSmsModal',
            'tags_clients',
            'inbox_reassignemail_modal',
            'sent_reassignemail_modal',
            'sent_mail_preview_modal',
        ];
    }

    /**
     * Notes / add-edit / management / appointments / tab-owned modals from addclientmodal.
     *
     * @return list<string>
     */
    public static function extraIds(): array
    {
        return [
            'create_note',
            'create_note_d',
            'view_note',
            'view_matter_note',
            'create_matternote',
            'edit_note',
            'convertLeadToClientModal',
            'create_action_popup',
            'changeMatterAssigneeModal',
            'create_appoint',
            'editMatterOfficeModal',
            'discon_application',
            'decision-received-modal',
            'discontinue-matter-modal',
            'change-workflow-modal',
            'workflow-set-deadline-modal',
            'revert_matter',
            'openeducationdocsmodal',
            'openmigrationdocsmodal',
            'opennominationdocsmodal',
            'signaturePlacementModal',
            'uploadmail',
            'matteremailmodal',
            'uploadAndFetchMailModel',
            'uploadSentAndFetchMailModel',
            'addpersonaldoccatmodel',
            'addvisadoccatmodel',
            'addnominationdoccatmodel',
            'editLedgerModal',
            'editOfficeReceiptModal',
            'costAssignmentCreateFormModel',
            'costAssignmentCreateFormModelLead',
            'form956CreateFormModel',
            'visaAgreementCreateFormModel',
            'agreementModal',
            'edit_datetime_modal',
            'notPickedCallModal',
            'convertActivityToNoteModal',
            'createreceiptmodal',
            'createadjustinvoicereceiptmodal',
            'createclientreceiptmodal',
            'createinvoicereceiptmodal',
            'createofficereceiptmodal',
            'createjournalreceiptmodal',
        ];
    }

    /**
     * Modals that have no id (class selectors in JS).
     *
     * @return list<string>
     */
    public static function extraClassOnly(): array
    {
        return [
            'edit_english_test',
            'edit_other_test',
        ];
    }

    /**
     * Click selectors that write into a pack before .modal('show'). Capture-replay waits for HTML.
     *
     * @return array<string, list<string>>
     */
    public static function packTriggers(): array
    {
        return [
            'shell' => [
                '.clientemail',
                '.send-google-review',
                '.send-sms-btn',
                '.sendmsg',
                '.opentagspopup',
                '.openredtagspopup',
                '.inbox_reassignemail_modal',
                '.sent_reassignemail_modal',
                '.btn-send-checklist',
                '.btn-send-signature-email',
            ],
            'extra' => [
                '.create_note_d',
                '.create_note',
                '.not_picked_call',
                '.convertLeadToClient',
                '.add_education_doc',
                '.add_personal_doc_cat',
                '.add-visa-doc-category',
                '.add-nomination-doc-category',
                '.add_migration_doc',
                '.add_nomination_doc',
                '[data-bs-target="#create_appoint"]',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fragmentRouteNames(): array
    {
        return [
            'shell' => 'clients.detail.shell-modals',
            'extra' => 'clients.detail.extra-modals',
        ];
    }

    /**
     * @return list<array{id: ?string, class: string, pack: string}>
     */
    public static function stubs(): array
    {
        $stubs = [];

        foreach (self::shellIds() as $id) {
            $stubs[] = [
                'id' => $id,
                'class' => 'modal fade custom_modal',
                'pack' => 'shell',
            ];
        }

        $extraClasses = [
            'openeducationdocsmodal' => 'modal fade create_education_docs custom_modal',
            'openmigrationdocsmodal' => 'modal fade create_migration_docs custom_modal',
            'opennominationdocsmodal' => 'modal fade create_nomination_docs custom_modal',
            'addpersonaldoccatmodel' => 'modal fade addpersonaldoccatmodel custom_modal',
            'addvisadoccatmodel' => 'modal fade addvisadoccatmodel custom_modal',
            'addnominationdoccatmodel' => 'modal fade addnominationdoccatmodel custom_modal',
            'create_appoint' => 'modal fade add_appointment custom_modal',
        ];

        foreach (self::extraIds() as $id) {
            $stubs[] = [
                'id' => $id,
                'class' => $extraClasses[$id] ?? 'modal fade custom_modal',
                'pack' => 'extra',
            ];
        }

        foreach (self::extraClassOnly() as $className) {
            $stubs[] = [
                'id' => null,
                'class' => 'modal fade '.$className.' custom_modal',
                'pack' => 'extra',
                'matchClass' => $className,
            ];
        }

        return $stubs;
    }

    public static function packForModalId(string $id): ?string
    {
        if (in_array($id, self::shellIds(), true)) {
            return 'shell';
        }

        if (in_array($id, self::extraIds(), true)) {
            return 'extra';
        }

        return null;
    }
}
