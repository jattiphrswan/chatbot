<?php
/**
 * Comprehensive Class Resolution, Type Hint & Reflection Audit.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'GCA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'GCA_PLUGIN_URL', 'http://example.com/wp-content/plugins/gemini-chat-assistant/' );
define( 'GCA_PLUGIN_BASENAME', 'gemini-chat-assistant/gemini-chat-assistant.php' );
define( 'GCA_VERSION', '1.0.1' );

// Mock WordPress functions that might be called during require
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/'; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function register_activation_hook( $file, $cb ) {}
function register_deactivation_hook( $file, $cb ) {}
function add_action( $tag, $cb, $pri = 10, $args = 1 ) {}
function add_filter( $tag, $cb, $pri = 10, $args = 1 ) {}

// Mock wpdb
class wpdb {
    public $prefix = 'wp_';
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    public function query($q) { return true; }
    public function prepare($q, ...$args) { return $q; }
}
$GLOBALS['wpdb'] = new wpdb();

// Mock WP_Error & WP_REST_Controller & WP_REST_Request & WP_REST_Response
class WP_Error {
    public function __construct($code = '', $message = '', $data = '') {}
}
class WP_REST_Controller {}
class WP_REST_Request {}
class WP_REST_Response {}

// Load all runtime files as Plugin does
$runtime_files = [
    // Database
    'includes/Database/Migrator.php',
    'includes/Database/ConversationRepository.php',
    'includes/Database/MessageRepository.php',
    'includes/Database/LeadRepository.php',
    'includes/Database/AnalyticsRepository.php',
    'includes/Database/SessionService.php',
    'includes/Database/FaqRepository.php',
    'includes/Database/KnowledgeRepository.php',
    // Knowledge
    'includes/Knowledge/KnowledgeIndexer.php',
    'includes/Knowledge/KnowledgeRetriever.php',
    'includes/Knowledge/KnowledgeContextBuilder.php',
    // Admin
    'includes/Admin/SettingsService.php',
    'includes/Admin/ProfileService.php',
    'includes/Admin/AnalyticsService.php',
    'includes/Admin/AppearanceService.php',
    'includes/Admin/AdminMenu.php',
    // Client
    'includes/class-gemini-client.php',
    // Services
    'includes/class-rate-limiter.php',
    'includes/class-validator.php',
    'includes/class-chat-service.php',
    'includes/class-lead-service.php',
    'includes/class-rest-controller.php',
    'includes/class-assets.php',
    'includes/class-shortcode.php',
    // Integrations
    'includes/Integrations/ActionResult.php',
    'includes/Integrations/ActionInterface.php',
    'includes/Integrations/IntegrationInterface.php',
    'includes/Integrations/ActionValidator.php',
    'includes/Integrations/IntegrationRegistry.php',
    'includes/Integrations/ActionExecutor.php',
    'includes/Integrations/WooCommerce/WooCommerceFormatter.php',
    'includes/Integrations/WooCommerce/SearchProductsAction.php',
    'includes/Integrations/WooCommerce/GetProductAction.php',
    'includes/Integrations/WooCommerce/SearchByCategoryAction.php',
    'includes/Integrations/WooCommerce/WooCommerceIntegration.php',
    // Handoff & Notifications
    'includes/Database/HandoffRepository.php',
    'includes/handoff/class-handoff-service.php',
    'includes/Integrations/Handoff/CreateHandoffAction.php',
    'includes/Integrations/Handoff/SendHandoffNotificationAction.php',
    'includes/Integrations/Handoff/HandoffIntegration.php',
    'includes/notifications/class-notification-service.php',
    // Providers
    'includes/Providers/ProviderInterface.php',
    'includes/Providers/AbstractProvider.php',
    'includes/Providers/ModelRegistry.php',
    'includes/Providers/ProviderResponse.php',
    'includes/Providers/ProviderException.php',
    'includes/Providers/ProviderRegistry.php',
    'includes/Providers/ProviderSelectionService.php',
    'includes/Providers/GeminiProvider.php',
    'includes/Providers/OpenAIClient.php',
    'includes/Providers/OpenAIProvider.php',
    'includes/Providers/ClaudeClient.php',
    'includes/Providers/ClaudeProvider.php',
    // Core
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-plugin.php',
];

echo ">>> Loading runtime files...\n";
foreach ($runtime_files as $rf) {
    $full = GCA_PLUGIN_DIR . $rf;
    if (!file_exists($full)) {
        die("FATAL: File not found: $full\n");
    }
    require_once $full;
}
echo ">>> All " . count($runtime_files) . " runtime files loaded successfully!\n\n";

echo ">>> Reflecting all declared classes in SkyFish\\GeminiChat namespace...\n";
$classes = get_declared_classes();
$interfaces = get_declared_interfaces();
$all_types = array_merge($classes, $interfaces);

$gca_types = array_filter($all_types, function($c) {
    return str_starts_with($c, 'SkyFish\\GeminiChat');
});

$reflection_issues = [];

foreach ($gca_types as $class_name) {
    $ref = new ReflectionClass($class_name);
    
    // Check constructor params
    $ctor = $ref->getConstructor();
    if ($ctor) {
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType) {
                if (!$type->isBuiltin() && !class_exists($type->getName()) && !interface_exists($type->getName())) {
                    $reflection_issues[] = "Class {$class_name} constructor parameter \${$param->getName()} references non-existent type '{$type->getName()}'";
                }
            } elseif ($type instanceof ReflectionUnionType) {
                foreach ($type->getTypes() as $ut) {
                    if (!$ut->isBuiltin() && !class_exists($ut->getName()) && !interface_exists($ut->getName())) {
                        $reflection_issues[] = "Class {$class_name} constructor parameter \${$param->getName()} references non-existent union type '{$ut->getName()}'";
                    }
                }
            }
        }
    }
    
    // Check properties
    foreach ($ref->getProperties() as $prop) {
        $type = $prop->getType();
        if ($type instanceof ReflectionNamedType) {
            if (!$type->isBuiltin() && !class_exists($type->getName()) && !interface_exists($type->getName())) {
                $reflection_issues[] = "Class {$class_name} property \${$prop->getName()} references non-existent type '{$type->getName()}'";
            }
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $ut) {
                if (!$ut->isBuiltin() && !class_exists($ut->getName()) && !interface_exists($ut->getName())) {
                    $reflection_issues[] = "Class {$class_name} property \${$prop->getName()} references non-existent union type '{$ut->getName()}'";
                }
            }
        }
    }
    
    // Check methods return type
    foreach ($ref->getMethods() as $method) {
        $type = $method->getReturnType();
        if ($type instanceof ReflectionNamedType) {
            $tName = $type->getName();
            if (!$type->isBuiltin() && !in_array($tName, ['self', 'static', 'parent'], true) && !class_exists($tName) && !interface_exists($tName)) {
                $reflection_issues[] = "Class {$class_name} method {$method->getName()}() references non-existent return type '{$tName}'";
            }
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $ut) {
                $uName = $ut->getName();
                if (!$ut->isBuiltin() && !in_array($uName, ['self', 'static', 'parent'], true) && !class_exists($uName) && !interface_exists($uName)) {
                    $reflection_issues[] = "Class {$class_name} method {$method->getName()}() references non-existent union return type '{$uName}'";
                }
            }
        }
    }
}

if (empty($reflection_issues)) {
    echo ">>> Reflection check passed! All " . count($gca_types) . " types have fully resolved dependencies, properties, and method types.\n";
} else {
    echo ">>> REFLECTION ISSUES DETECTED (" . count($reflection_issues) . "):\n";
    foreach ($reflection_issues as $iss) {
        echo "  - $iss\n";
    }
}
