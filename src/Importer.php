<?php

namespace ImportRpps;

if (!defined('ABSPATH')) {
    exit;
}

class Importer
{
    private $database;
    private $batch_size = 1000;
    private $max_execution_time = 300;
    private $memory_limit_mb = 256;

    public function __construct()
    {
        $this->database = new Database();
    }

    public function importFromUrl($url)
    {
        $this->logMessage("Début de l'import depuis URL: {$url}");

        set_time_limit($this->max_execution_time);
        ini_set('memory_limit', $this->memory_limit_mb . 'M');

        $temp_zip = $this->downloadFile($url);
        if (!$temp_zip) {
            return false;
        }

        $result = $this->processZipFile($temp_zip);

        @unlink($temp_zip);

        return $result;
    }

    public function importFromFile($zip_file_path)
    {
        $this->logMessage("Début de l'import depuis fichier: {$zip_file_path}");

        if (!file_exists($zip_file_path)) {
            $this->logMessage("Fichier ZIP introuvable: {$zip_file_path}", 'error');
            return false;
        }

        set_time_limit($this->max_execution_time);
        ini_set('memory_limit', $this->memory_limit_mb . 'M');

        return $this->processZipFile($zip_file_path);
    }

    private function downloadFile($url)
    {
        $this->logMessage("Téléchargement du fichier ZIP...");
        $temp_file = wp_tempnam();
        if (!$temp_file) {
            $this->logMessage("Impossible de créer un fichier temporaire", 'error');
            return false;
        }

        $args = array(
            'timeout' => 300,
            'stream' => true,
            'filename' => $temp_file,
            'sslverify' => false
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            $this->logMessage("Erreur lors du téléchargement: " . $response->get_error_message(), 'error');
            @unlink($temp_file);
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->logMessage("Erreur HTTP lors du téléchargement: Code {$response_code}", 'error');
            @unlink($temp_file);
            return false;
        }

        if (!file_exists($temp_file) || filesize($temp_file) === 0) {
            $this->logMessage("Fichier téléchargé vide ou inexistant", 'error');
            @unlink($temp_file);
            return false;
        }

        $this->logMessage("Fichier téléchargé avec succès: " . $this->formatFileSize(filesize($temp_file)));
        return $temp_file;
    }

    private function processZipFile($zip_file_path)
    {
        $this->logMessage("Traitement du fichier ZIP...");

        $zip = new \ZipArchive();
        $result = $zip->open($zip_file_path);

        if ($result !== TRUE) {
            $this->logMessage("Impossible d'ouvrir le fichier ZIP: Code d'erreur {$result}", 'error');
            return false;
        }

        $temp_dir = sys_get_temp_dir() . '/import_rpps_' . uniqid();
        if (!wp_mkdir_p($temp_dir)) {
            $this->logMessage("Impossible de créer le répertoire temporaire", 'error');
            $zip->close();
            return false;
        }

        $target_file = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, 'PS_LibreAcces_Personne_activite_') === 0) {
                $target_file = $temp_dir . '/' . basename($filename);
                if ($zip->extractTo($temp_dir, $filename)) {
                    $extracted_path = $temp_dir . '/' . $filename;
                    if (file_exists($extracted_path)) {
                        rename($extracted_path, $target_file);
                    }
                }
                break;
            }
        }

        $zip->close();

        if (!$target_file || !file_exists($target_file)) {
            $this->logMessage("Aucun fichier PS_LibreAcces_Personne_activite trouvé dans l'archive", 'error');
            $this->cleanupTempDir($temp_dir);
            return false;
        }

        $this->logMessage("Fichier de données trouvé: " . basename($target_file));

        $result = $this->processDataFile($target_file);

        $this->cleanupTempDir($temp_dir);

        return $result;
    }

    private function processDataFile($file_path)
    {
        $this->logMessage("Traitement du fichier de données...");

        if (!$this->database->truncateTable()) {
            return false;
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            $this->logMessage("Impossible d'ouvrir le fichier de données", 'error');
            return false;
        }

        $header = fgetcsv($handle, 0, '|');
        if (!$header || count($header) < 3) {
            $this->logMessage("En-tête du fichier invalide", 'error');
            fclose($handle);
            return false;
        }

        $this->logMessage("Début de l'import des données (traitement par lots de {$this->batch_size})...");

        $batch = array();
        $total_processed = 0;
        $total_inserted = 0;

        while (($row = fgetcsv($handle, 0, '|')) !== false) {

            if (count($row) < 3) {
                continue;
            }

            $data = array(
                'type_identifiant_pp' => trim($row[0]),
                'identifiant_pp' => trim($row[1]),
                'identification_nationale_pp' => trim($row[2])
            );

            if (empty($data['identification_nationale_pp'])) {
                continue;
            }

            $batch[] = $data;
            $total_processed++;

            if (count($batch) >= $this->batch_size) {
                $inserted = $this->database->insertBatch($batch);
                if ($inserted !== false) {
                    $total_inserted += $inserted;
                }

                $batch = array();

                if ($total_processed % 10000 === 0) {
                    $this->logMessage("Traité: {$total_processed} lignes, Inséré: {$total_inserted} enregistrements");

                    if (memory_get_usage(true) > ($this->memory_limit_mb * 1024 * 1024 * 0.8)) {
                        $this->logMessage("Limite mémoire approchée, libération de la mémoire...");
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                }
            }
        }

        if (!empty($batch)) {
            $inserted = $this->database->insertBatch($batch);
            if ($inserted !== false) {
                $total_inserted += $inserted;
            }
        }

        fclose($handle);

        $this->logMessage("Import terminé: {$total_processed} lignes traitées, {$total_inserted} enregistrements insérés");

        $stats = $this->database->getImportStats();
        $this->logMessage("Statistiques: {$stats['total_records']} enregistrements en base, taille: {$stats['table_size']}");

        return true;
    }

    private function cleanupTempDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dir);
    }

    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
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
            error_log("[Import RPPS] {$level}: {$message}");
        }
    }

    public function validateRppsNumber($rpps_number)
    {
        return $this->database->validateRppsNumber($rpps_number);
    }

    public function getImportStats()
    {
        return $this->database->getImportStats();
    }
}