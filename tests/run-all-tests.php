<?php
/**
 * Master Test Runner for Gemini Chat Assistant.
 */

$php = PHP_BINARY;
$test_dir = __DIR__;

$tests = [
    'test-linux-case-and-requires.php',
    'test-class-resolution-audit.php',
    'test-runtime-bootstrap.php',
    'test-provider-model-selection.php',
    'test-openai-integration.php',
    'test-claude-integration.php',
    'test-provider-credentials.php',
    'test-ai-profiles.php',
    'test-analytics.php',
    'test-appearance.php',
    'test-assets.php',
    'test-chat-service.php',
    'test-chat-ux.php',
    'test-conversation-memory.php',
    'test-conversations-page.php',
    'test-dashboard.php',
    'test-database.php',
    'test-direct-contact-channels.php',
    'test-email-notifications.php',
    'test-faqs.php',
    'test-gemini-client.php',
    'test-handoff-service.php',
    'test-integrations-framework.php',
    'test-knowledge-indexer.php',
    'test-knowledge-retriever.php',
    'test-leads.php',
    'test-prechat.php',
    'test-public-ui.php',
    'test-rag-context.php',
    'test-rate-limiter.php',
    'test-rest-controller.php',
    'test-settings.php',
    'test-shortcode.php',
    'test-validator.php',
    'test-woocommerce-integration.php',
];

$total_suites = count($tests);
$passed_suites = 0;
$failed_suites = [];

echo "=======================================================\n";
echo "MASTER TEST RUNNER - " . count($tests) . " SUITES\n";
echo "=======================================================\n\n";

foreach ($tests as $t) {
    $path = $test_dir . '/' . $t;
    if (!file_exists($path)) {
        echo "[SKIP] $t (file not found)\n";
        continue;
    }
    
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    exec($cmd, $output, $return_code);
    
    if ($return_code === 0) {
        echo "[PASS] $t\n";
        $passed_suites++;
    } else {
        echo "[FAIL] $t (code $return_code)\n";
        echo "  Output:\n  " . implode("\n  ", array_slice($output, -10)) . "\n";
        $failed_suites[] = $t;
    }
}

echo "\n=======================================================\n";
printf("TOTAL: %d | PASSED: %d | FAILED: %d\n", $total_suites, $passed_suites, count($failed_suites));
echo "=======================================================\n";

if (!empty($failed_suites)) {
    echo "Failed Suites:\n";
    foreach ($failed_suites as $fs) {
        echo " - $fs\n";
    }
    exit(1);
}

exit(0);
