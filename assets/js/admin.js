jQuery(document).ready(function($) {
    'use strict';

    const ImportRppsAdmin = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#import-rpps-manual-import').on('click', this.handleManualImport);
            $('#import-rpps-clear-logs').on('click', this.handleClearLogs);
            $('#import-rpps-validate-form').on('submit', this.handleValidateNumber);
            $('#import-rpps-validate-number').on('input', this.resetValidationResult);
        },

        handleManualImport: function(e) {
            if (!confirm(importRppsAjax.strings.confirm_import)) {
                e.preventDefault();
                return;
            }

            const $button = $(this);
            $button.text('Import en cours, ne rechargez pas la page');
            return true; // Continue with the form submission
        },

        handleClearLogs: function(e) {
            e.preventDefault();

            if (!confirm('Êtes-vous sûr de vouloir effacer tous les logs ?')) {
                return;
            }

            const $button = $(this);
            const originalText = $button.text();

            $button.prop('disabled', true).text('Effacement...');

            $.ajax({
                url: importRppsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'import_rpps_clear_logs',
                    nonce: importRppsAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.import-rpps-log-entry').remove();
                        ImportRppsAdmin.showNotice('Logs effacés avec succès', 'success');
                    } else {
                        ImportRppsAdmin.showNotice('Erreur lors de l\'effacement des logs', 'error');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        handleValidateNumber: function(e) {
            e.preventDefault();

            const $form = $(this);
            const $input = $('#import-rpps-validate-number');
            const $button = $form.find('button[type="submit"]');
            const $result = $('.import-rpps-validator-result');
            const number = $input.val().trim();

            if (!number) {
                ImportRppsAdmin.showValidationResult(false, 'Veuillez saisir un numéro RPPS');
                return;
            }

            const originalText = $button.text();
            $button.prop('disabled', true).text(importRppsAjax.strings.validating);
            $result.hide();

            $.ajax({
                url: importRppsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'import_rpps_validate_number',
                    nonce: importRppsAjax.nonce,
                    number: number
                },
                success: function(response) {
                    if (response.success) {
                        ImportRppsAdmin.showValidationResult(response.data.valid, response.data.message);
                    } else {
                        ImportRppsAdmin.showValidationResult(false, response.data || 'Erreur de validation');
                    }
                },
                error: function() {
                    ImportRppsAdmin.showValidationResult(false, 'Erreur de communication');
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        resetValidationResult: function() {
            $('.import-rpps-validator-result').hide();
        },

        showValidationResult: function(isValid, message) {
            const $result = $('.import-rpps-validator-result');
            $result.removeClass('valid invalid')
                   .addClass(isValid ? 'valid' : 'invalid')
                   .text(message)
                   .show();
        },

        updateStats: function(stats) {
            if (stats.total_records !== undefined) {
                $('.import-rpps-stat-value').first().text(stats.total_records.toLocaleString());
            }
            if (stats.last_import) {
                $('.import-rpps-stat-value').eq(1).text(new Date(stats.last_import).toLocaleString());
            }
            if (stats.table_size) {
                $('.import-rpps-stat-value').eq(2).text(stats.table_size);
            }
        },

        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.import-rpps-admin').prepend($notice);

            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    ImportRppsAdmin.init();
});