<?php

if (!function_exists("array_key_last")) {
    function array_key_last($array)
    {
        if (!is_array($array) || empty($array)) {
            return NULL;
        }
        return array_keys($array)[count($array) - 1];
    }
}

if (!function_exists("assets")) {
    function assets($files) {
        $base = '/assets'; // или URL CDN
        $html = '';

        foreach ((array) $files as $file) {
            $ext = pathinfo($file, PATHINFO_EXTENSION);
            $url = $base . '/' . ltrim($file, '/');

            if ($ext === 'css') {
                $html .= "<link rel=\"stylesheet\" href=\"{$url}\">\n";
            } elseif ($ext === 'js') {
                $html .= "<script src=\"{$url}\" defer></script>\n";
            }
        }

        return $html;
    }
}