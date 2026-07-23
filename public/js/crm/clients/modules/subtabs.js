/**
 * Subtabs module - Document/Notes/Form subtab switching (inbox, sent, migrationdocuments, notes, personal, visa, form generation)
 * Extracted from detail-main.js - Phase 3i refactoring.
 * Requires: jQuery, ClientDetailConfig (for getCostAssignmentMigrationAgentDetail in subtab3)
 */
(function($) {
    'use strict';
    if (!$) return;

    $(document).ready(function() {
        // Handle document subtab switching
        $('.subtab-button').click(function() {

        // Remove active class from all document subtab buttons and panes

        $('.subtab-button').removeClass('active');

        $('.subtab-pane').removeClass('active');



        // Add active class to clicked button

        $(this).addClass('active');



        // Show corresponding pane

        const subtabId = $(this).data('subtab');

        $(`#${subtabId}-subtab`).addClass('active');



        if ($('.general_matter_checkbox_client_detail').is(':checked')) {

            var selectedMatter = $('.general_matter_checkbox_client_detail').val();

        } else {

            var selectedMatter = $('#sel_matter_id_client_detail').val();

        }



       



        if( subtabId == 'inbox') {

            if(selectedMatter != "" ) {

                $('#inbox-subtab #email-list').find('.email-card').each(function() {

                    if ($(this).data('matterid') == selectedMatter) {

                        $(this).show();

                    } else {

                        $(this).hide();

                    }

                });

            }  else {

                $(this).hide();

            }

        }



        if( subtabId == 'sent') {

            if(selectedMatter != "" ) {

                $('#sent-subtab #email-list1').find('.email-card').each(function() {

                    if ($(this).data('matterid') == selectedMatter) {

                        $(this).show();

                    } else {

                        $(this).hide();

                    }

                });

            }  else {

                $(this).hide();

            }

        }



        //alert(subtabId);

        if( subtabId == 'migrationdocuments') {

            //var selectedMatter = $('#sel_matter_id_client_detail').val();

            //console.log('selectedMatter&&&&==='+selectedMatter);

            if(selectedMatter != "" ) {

                $('#migrationdocuments-subtab .migdocumnetlist1').find('.drow').each(function() {

                    if ($(this).data('matterid') == selectedMatter) {

                        $(this).show();

                    } else {

                        $(this).hide();

                    }

                });

            }  else {

                $(this).hide();

            }

        }







        // Store the active tab in localStorage when a tab is clicked

        localStorage.setItem('subactiveTab', subtabId);

    });



    // On page load, check localStorage and activate the correct tab

    const subactiveTab = localStorage.getItem('subactiveTab');

    if (subactiveTab) {

        // Find the button corresponding to the stored tabId and trigger its click event

        const $subtargetButton = $(`.subtab-button[data-tab="${subactiveTab}"]`);

        if ($subtargetButton.length) {

            $subtargetButton.click(); // Trigger the click event to reuse the existing logic

        }

        // Clear localStorage to prevent persistence on future loads (optional)

        localStorage.removeItem('subactiveTab');

    }



    //Notes subtab click

    $('.subtab8-button').click(function(e) {

        e.preventDefault();



        // Remove active class from all buttons and panes

        $('.subtab8-button').removeClass('active');

        $('.subtab8-pane').removeClass('active');



        // Add active class to clicked button

        $(this).addClass('active');



        // Show corresponding pane

        const subtabId8 = $(this).data('subtab8'); //alert(subtabId8);

        $(`#${subtabId8}-subtab8`).addClass('active');

    });



    // Document subtab like - Personal (delegated so dynamically added categories work)
    $(document).on('click', '.subtab2-button', function(e) {
        e.preventDefault();

        // Remove active class from all buttons and panes
        $('.subtab2-button').removeClass('active');
        $('.subtab2-pane').removeClass('active');

        // Add active class to clicked button
        $(this).addClass('active');

        // Show corresponding pane
        const subtabId2 = $(this).data('subtab2');
        $(`#${subtabId2}-subtab2`).addClass('active');
    });



    // Document subtab like - Visa (delegated so dynamically added categories work)
    $(document).on('click', '.subtab6-button', function(e) {
        e.preventDefault();

        // Remove active class from all buttons and panes
        $('.subtab6-button').removeClass('active');
        $('.subtab6-pane').removeClass('active');

        // Add active class to clicked button
        $(this).addClass('active');

        // Show corresponding pane
        const subtabId6 = $(this).data('subtab6');
        $(`#${subtabId6}-subtab6`).addClass('active');
    });





    // Note: subtab3 (Form Generation subtabs: Form 956, Cost Assignment, Create Cost
    // Assignment) has been removed. Cost assignment create/amend now uses the modal
    // in Checklists; Form 956 is accessed from Visa Documents.

    }); // end $(document).ready

})(typeof jQuery !== 'undefined' ? jQuery : null);
