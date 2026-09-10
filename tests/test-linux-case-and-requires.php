<?php
/**
 * Linux Case-Sensitivity & File Existence Audit.
 */

$root = dirname(__DIR__);

function get_exact_path($path) {
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $current = '';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $current = array_shift($parts); // Drive letter
        $current .= DIRECTORY_SEPARATOR;
    }
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') {
            $current = dirname($current) . DIRECTORY_SEPARATOR;
            continue;
        }
        $entries = scandir($current);
        if ($entries === false) {
            return ['not_found' => true, 'part' => $part, 'in' => $current];
        }
        $found = false;
        foreach ($entries as $e) {
            if (strcasecmp($e, $part) === 0) {
                if ($e !== $part) {
                    return ['mismatch' => true, 'expected' => $e, 'actual' => $part, 'full_expected' => $current . $e];
                }
                $current .= $e . DIRECTORY_SEPARATOR;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return ['not_found' => true, 'part' => $part, 'in' => $current];
        }
    }
    return ['mismatch' => false];
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$case_issues = [];
$missing_issues = [];

foreach ($files as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (strpos($path, 'vendor') !== false || strpos($path, '.git') !== false || strpos($path, 'dist') !== false) continue;
    
    $content = file_get_contents($path);
    if (preg_match_all('/(?:require|require_once|include|include_once)\s*\(?\s*(?:GCA_PLUGIN_DIR\s*\.\s*|__DIR__\s*\.\s*|PLUGIN_DIR\s*\.\s*)?([\'"])([^\'"]+)\1/i', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $rel = $m[2];
            $full = '';
            if (strpos($m[0], 'GCA_PLUGIN_DIR') !== false || strpos($m[0], 'PLUGIN_DIR') !== false) {
                $clean_rel = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel), DIRECTORY_SEPARATOR);
                $full = $root . DIRECTORY_SEPARATOR . $clean_rel;
            } elseif (strpos($m[0], '__DIR__') !== false) {
                $clean_rel = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel), DIRECTORY_SEPARATOR);
                $full = dirname($path) . DIRECTORY_SEPARATOR . $clean_rel;
            } else {
                continue;
            }
            $res = get_exact_path($full);
            if (!empty($res['mismatch'])) {
                $case_issues[] = [
                    'file' => str_replace($root, '', $path),
                    'required' => $rel,
                    'expected' => $res['expected'],
                    'actual' => $res['actual']
                ];
            } elseif (!empty($res['not_found'])) {
                $missing_issues[] = [
                    'file' => str_replace($root, '', $path),
                    'required' => $rel,
                    'part' => $res['part'],
                    'in' => $res['in']
                ];
            }
        }
    }
}

echo "CASE ISSUES (" . count($case_issues) . "):\n" . json_encode($case_issues, JSON_PRETTY_PRINT) . "\n\n";
echo "MISSING ISSUES (" . count($missing_issues) . "):\n" . json_encode($missing_issues, JSON_PRETTY_PRINT) . "\n";
