<?php

namespace EAD_XML_Importer;

class Logger
{
    public static function log_message($message)
    {
        $log_dir = EAD_PLUGIN_DIR . 'logs/';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . 'log_' . date('Y-m-d') . '.log';
        $timestamp = date('[Y-m-d H:i:s]');
        file_put_contents($log_file, $timestamp . ' ' . $message . PHP_EOL, FILE_APPEND);
    }

    public static function get_log_files()
    {
        $log_dir = EAD_PLUGIN_DIR . 'logs/';
        if (!is_dir($log_dir)) {
            return [];
        }

        $files = array_diff(scandir($log_dir), ['.', '..']);
        $log_files = [];

        // Remove file beginning with a dot
        $files = array_filter($files, function ($file) {
            return strpos($file, '.') !== 0;
        });


        foreach ($files as $file) {
            $file_path = $log_dir . $file;
            if (is_file($file_path)) {
                $log_files[] = [
                    'name' => $file,
                    'size' => filesize($file_path),
                ];
            }
        }
        return $log_files;
    }

    public static function get_log_file_content($file_name)
    {
        $log_dir = EAD_PLUGIN_DIR . 'logs/';
        $file_path = $log_dir . $file_name;

        if (file_exists($file_path) && is_readable($file_path)) {
            return file_get_contents($file_path);
        } else {
            return __('Invalid log file specified.', 'ead-xml-importer');
        }
    }

    public static function clear_log_files()
    {
        $log_dir = EAD_PLUGIN_DIR . 'logs/';
        $files = array_diff(scandir($log_dir), ['.', '..']);

        foreach ($files as $file) {
            $file_path = $log_dir . $file;
            if (is_file($file_path)) {
                unlink($file_path);
            }
        }
        echo '<div class="updated"><p>' . __('Log files cleared successfully.', 'ead-xml-importer') . '</p></div>';
    }

    public static function get_last_log()
    {
        $log_dir = EAD_PLUGIN_DIR . 'logs/';
        $files = array_diff(scandir($log_dir), ['.', '..']);
        $last_log = '';

        foreach ($files as $file) {
            $file_path = $log_dir . $file;
            if (is_file($file_path)) {
                $last_log = $file;
            }
        }

        return $last_log;
    }

    public static function download_log_file($file_name)
    {
        $log_dir = EAD_PLUGIN_DIR . 'logs/';
        $file_path = $log_dir . $file_name;

        if (file_exists($file_path) && is_readable($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            wp_die(__('Invalid log file specified.', 'ead-xml-importer'), 'Error', ['back_link' => true]);
        }
    }

    public static function delete_log_file($file_name)
    {
        $log_dir = EAD_PLUGIN_DIR . 'logs/';
        $file_path = $log_dir . $file_name;

        if (file_exists($file_path) && is_writable($file_path)) {
            unlink($file_path);
            echo '<div class="updated"><p>' . __('Log file deleted successfully.', 'ead-xml-importer') . '</p></div>';
        } else {
            echo '<div class="error"><p>' . __('Failed to delete the log file.', 'ead-xml-importer') . '</p></div>';
        }
    }
}
