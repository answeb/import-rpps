<?php

namespace ImportRpps;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    private $importer;
    private $database;

    public function __construct()
    {
        $this->importer = new Importer();
        $this->database = new Database();

        add_action('admin_menu', array($this, 'addAdminMenu'));
        add_action('admin_init', array($this, 'registerSettings'));
        add_action('admin_init', array($this, 'handleManualImportPost'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueScripts'));
        add_action('wp_ajax_import_rpps_clear_logs', array($this, 'handleClearLogs'));
        add_action('wp_ajax_import_rpps_validate_number', array($this, 'handleValidateNumber'));
    }

    public function addAdminMenu()
    {
        add_management_page(
            __('Import RPPS', 'import-rpps'),
            __('Import RPPS', 'import-rpps'),
            'manage_options',
            'import-rpps',
            array($this, 'renderAdminPage')
        );
    }

    public function registerSettings()
    {
        register_setting('import_rpps_settings', 'import_rpps_url', array(
            'type' => 'string',
            'default' => 'https://service.annuaire.sante.fr/annuaire-sante-webservices/V300/services/extraction/PS_LibreAcces',
            'sanitize_callback' => 'sanitize_url'
        ));

        register_setting('import_rpps_settings', 'import_rpps_file_path', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ));

	    register_setting('import_rpps_settings', 'import_rpps_time', array(
		    'type' => 'string',
		    'default' => '02:00',
		    'sanitize_callback' => array($this, 'sanitizeTime')
	    ));

        register_setting('import_rpps_settings', 'import_rpps_frequency', array(
            'type' => 'string',
            'default' => 'weekly',
            'sanitize_callback' => array($this, 'sanitizeFrequencyAndSchedule')
        ));


        // Ajouter les sections et champs
        add_settings_section(
            'import_rpps_main_section',
            __('Configuration de l\'import RPPS', 'import-rpps'),
            array($this, 'settingsSectionCallback'),
            'import_rpps_settings'
        );

        add_settings_field(
            'import_rpps_url',
            __('URL du fichier d\'import', 'import-rpps'),
            array($this, 'urlFieldCallback'),
            'import_rpps_settings',
            'import_rpps_main_section'
        );

        add_settings_field(
            'import_rpps_file_path',
            __('Fichier local (alternatif)', 'import-rpps'),
            array($this, 'filePathFieldCallback'),
            'import_rpps_settings',
            'import_rpps_main_section'
        );

        add_settings_field(
            'import_rpps_time',
            __('Heure d\'import', 'import-rpps'),
            array($this, 'timeFieldCallback'),
            'import_rpps_settings',
            'import_rpps_main_section'
        );

        add_settings_field(
            'import_rpps_frequency',
            __('Fréquence d\'import', 'import-rpps'),
            array($this, 'frequencyFieldCallback'),
            'import_rpps_settings',
            'import_rpps_main_section'
        );

    }

    public function enqueueScripts($hook)
    {
        if ($hook !== 'tools_page_import-rpps') {
            return;
        }

        wp_enqueue_script(
            'import-rpps-admin',
            IMPORT_RPPS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            IMPORT_RPPS_VERSION,
            true
        );

        wp_enqueue_style(
            'import-rpps-admin',
            IMPORT_RPPS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            IMPORT_RPPS_VERSION
        );

        wp_localize_script('import-rpps-admin', 'importRppsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('import_rpps_nonce'),
            'strings' => array(
                'importing' => __('Import en cours...', 'import-rpps'),
                'success' => __('Import terminé avec succès', 'import-rpps'),
                'error' => __('Erreur lors de l\'import', 'import-rpps'),
                'confirm_import' => __('Êtes-vous sûr de vouloir lancer l\'import ? Cette opération peut prendre plusieurs minutes.', 'import-rpps'),
                'validating' => __('Validation en cours...', 'import-rpps'),
                'valid' => __('Numéro RPPS valide', 'import-rpps'),
                'invalid' => __('Numéro RPPS invalide', 'import-rpps')
            )
        ));
    }

    public function renderAdminPage()
    {
        $stats = $this->database->getImportStats();
        $logs = get_option('import_rpps_logs', array());
        $logs = array_reverse(array_slice($logs, -20));

        include IMPORT_RPPS_PLUGIN_DIR . 'templates/admin-page.php';
    }


    public function handleClearLogs()
    {
        check_ajax_referer('import_rpps_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'import-rpps'));
        }

        delete_option('import_rpps_logs');
        wp_send_json_success(__('Logs effacés', 'import-rpps'));
    }

    public function handleValidateNumber()
    {
        check_ajax_referer('import_rpps_nonce', 'nonce');

        $number = sanitize_text_field($_POST['number']);

        if (empty($number)) {
            wp_send_json_error(__('Numéro requis', 'import-rpps'));
        }

        $is_valid = $this->importer->validateRppsNumber($number);

        wp_send_json_success(array(
            'valid' => $is_valid,
            'message' => $is_valid ? __('Numéro RPPS valide', 'import-rpps') : __('Numéro RPPS invalide', 'import-rpps')
        ));
    }

    public function handleManualImportPost()
    {
        if (!isset($_POST['import_rpps_manual_submit']) || !is_admin()) {
            return;
        }

        check_admin_referer('import_rpps_manual_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Permissions insuffisantes', 'import-rpps'));
        }

        $url = get_option('import_rpps_url');
        $file_path = get_option('import_rpps_file_path');

        $result = false;

        if (!empty($url)) {
            $result = $this->importer->importFromUrl($url);
        } elseif (!empty($file_path) && file_exists($file_path)) {
            $result = $this->importer->importFromFile($file_path);
        }

        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Import terminé avec succès', 'import-rpps') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Erreur lors de l\'import', 'import-rpps') . '</p></div>';
            });
        }

        wp_redirect(admin_url('tools.php?page=import-rpps'));
        exit;
    }

    public function sanitizeFrequency($value)
    {
        $allowed = array('never', 'daily', 'weekly', 'monthly');
        return in_array($value, $allowed) ? $value : 'never';
    }

    public function sanitizeFrequencyAndSchedule($value)
    {
        $frequency = $this->sanitizeFrequency($value);

        // Reconfigurer le cron avec la nouvelle fréquence
        $time = get_option('import_rpps_time', '02:00');
        wp_clear_scheduled_hook('import_rpps_cron_hook');

        if ($frequency !== 'never') {
            $timestamp = strtotime("today {$time}");
            if ($timestamp < time()) {
                $timestamp = strtotime("tomorrow {$time}");
            }

            wp_schedule_event($timestamp, $frequency, 'import_rpps_cron_hook');
        }

        return $frequency;
    }

    public function settingsSectionCallback()
    {
        echo '<p>' . __('Configurez les paramètres d\'import des données RPPS.', 'import-rpps') . '</p>';
    }

    public function urlFieldCallback()
    {
        $value = get_option('import_rpps_url', 'https://service.annuaire.sante.fr/annuaire-sante-webservices/V300/services/extraction/PS_LibreAcces');
        echo '<input name="import_rpps_url" type="url" id="import_rpps_url" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('URL de téléchargement du fichier ZIP contenant les données RPPS.', 'import-rpps') . '</p>';
    }

    public function filePathFieldCallback()
    {
        $value = get_option('import_rpps_file_path', '');
        echo '<input name="import_rpps_file_path" type="text" id="import_rpps_file_path" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Chemin vers un fichier ZIP local (utilisé si l\'URL ne fonctionne pas).', 'import-rpps') . '</p>';
    }

    public function frequencyFieldCallback()
    {
        $value = get_option('import_rpps_frequency', 'never');
        echo '<select name="import_rpps_frequency" id="import_rpps_frequency">';
        echo '<option value="never"' . selected($value, 'never', false) . '>' . __('Jamais (manuel uniquement)', 'import-rpps') . '</option>';
        //echo '<option value="daily"' . selected($value, 'daily', false) . '>' . __('Quotidien', 'import-rpps') . '</option>';
	    echo '<option value="weekly"' . selected($value, 'weekly', false) . '>' . __('Hebdomadaire', 'import-rpps') . '</option>';
        echo '<option value="monthly"' . selected($value, 'monthly', false) . '>' . __('Mensuel', 'import-rpps') . '</option>';
        echo '</select>';

        $scheduler = new Scheduler();
        if ($scheduler->isScheduled()) {
            echo '<p class="description">' .
                 sprintf(__('Prochain import programmé le : %s', 'import-rpps'), $scheduler->getNextScheduledTime()) .
                 '</p>';
        }
    }

    public function timeFieldCallback()
    {
        $value = get_option('import_rpps_time', '02:00');
        echo '<input name="import_rpps_time" type="time" id="import_rpps_time" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . __('Heure à laquelle l\'import automatique sera exécuté.', 'import-rpps') . '</p>';
    }


    public function sanitizeTime($value)
    {
        if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
            return $value;
        }
        return '02:00';
    }

}