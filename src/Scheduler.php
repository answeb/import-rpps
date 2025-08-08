<?php

namespace ImportRpps;

if (!defined('ABSPATH')) {
    exit;
}

class Scheduler
{
    private $importer;
    
    public function __construct()
    {
        $this->importer = new Importer();
        
        add_action('import_rpps_cron_hook', array($this, 'executeScheduledImport'));
        add_filter('cron_schedules', array($this, 'addCustomSchedules'));
    }
    
    public function addCustomSchedules($schedules)
    {
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Une fois par semaine', 'import-rpps')
            );
        }
        
        if (!isset($schedules['monthly'])) {
            $schedules['monthly'] = array(
                'interval' => MONTH_IN_SECONDS,
                'display' => __('Une fois par mois', 'import-rpps')
            );
        }
        
        return $schedules;
    }
    
    public function executeScheduledImport()
    {
        $this->logMessage("Démarrage de l'import programmé");
        
        ignore_user_abort(true);
        set_time_limit(0);
        
        $url = get_option('import_rpps_url');
        $file_path = get_option('import_rpps_file_path');
        
        $result = false;
        
        if (!empty($url)) {
            $this->logMessage("Import depuis URL: {$url}");
            $result = $this->importer->importFromUrl($url);
        } elseif (!empty($file_path) && file_exists($file_path)) {
            $this->logMessage("Import depuis fichier: {$file_path}");
            $result = $this->importer->importFromFile($file_path);
        } else {
            $this->logMessage("Aucune source d'import configurée", 'error');
            return;
        }
        
        if ($result) {
            $stats = $this->importer->getImportStats();
            $this->logMessage("Import programmé terminé avec succès. Statistiques: {$stats['total_records']} enregistrements");
            
            $this->sendNotificationEmail(true, $stats);
        } else {
            $this->logMessage("Échec de l'import programmé", 'error');
            $this->sendNotificationEmail(false);
        }
    }
    
    private function sendNotificationEmail($success, $stats = null)
    {
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }
        
        $site_name = get_bloginfo('name');
        
        if ($success) {
            $subject = sprintf(__('[%s] Import RPPS - Succès', 'import-rpps'), $site_name);
            $message = sprintf(
                __("L'import RPPS programmé s'est déroulé avec succès.\n\nStatistiques:\n- Nombre d'enregistrements: %s\n- Dernière mise à jour: %s\n- Taille de la base: %s\n\nCet email a été envoyé automatiquement par le plugin Import RPPS.", 'import-rpps'),
                $stats['total_records'],
                $stats['last_import'],
                $stats['table_size']
            );
        } else {
            $subject = sprintf(__('[%s] Import RPPS - Échec', 'import-rpps'), $site_name);
            $message = sprintf(
                __("L'import RPPS programmé a échoué.\n\nVeuillez vérifier les logs dans l'interface d'administration pour plus de détails.\n\nCet email a été envoyé automatiquement par le plugin Import RPPS.", 'import-rpps')
            );
        }
        
        wp_mail($admin_email, $subject, $message);
    }
    
    public function getNextScheduledTime()
    {
        $timestamp = wp_next_scheduled('import_rpps_cron_hook');
        return $timestamp ? date_i18n('Y-m-d H:i:s', $timestamp) : __('Aucun import programmé', 'import-rpps');
    }
    
    public function isScheduled()
    {
        return wp_next_scheduled('import_rpps_cron_hook') !== false;
    }
    
    public function reschedule($frequency, $time)
    {
        wp_clear_scheduled_hook('import_rpps_cron_hook');
        
        if (empty($frequency) || $frequency === 'never') {
            $this->logMessage("Planification désactivée");
            return true;
        }
        
        $timestamp = strtotime("today {$time}");
        if ($timestamp < time()) {
            $timestamp = strtotime("tomorrow {$time}");
        }
        
        $result = wp_schedule_event($timestamp, $frequency, 'import_rpps_cron_hook');
        
        if ($result) {
            $this->logMessage("Import reprogrammé: {$frequency} à {$time}");
        } else {
            $this->logMessage("Erreur lors de la reprogrammation", 'error');
        }
        
        return $result;
    }
    
    public function unschedule()
    {
        wp_clear_scheduled_hook('import_rpps_cron_hook');
        $this->logMessage("Planification supprimée");
    }
    
    private function logMessage($message, $level = 'info')
    {
        $logs = get_option('import_rpps_logs', array());
        
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => $message
        );
        
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('import_rpps_logs', $logs);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Import RPPS Scheduler] {$level}: {$message}");
        }
    }
}