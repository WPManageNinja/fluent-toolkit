<?php

/**
 * HubServer reads the Fluent plugins' MCP servers back from the adapter and
 * serves them through two router tools per product. This checks the routing
 * rules against stub adapter classes:
 *
 * - read-only actions go on {slug}-read, everything else on {slug}-write;
 * - an action works by its short name and by the product's full tool name;
 * - an action called on the wrong router tool names the right one;
 * - the product tool's own permission check runs before it executes;
 * - help returns the product's instructions and only the asked-for actions;
 * - a product whose transport gate the user fails is hidden from tools/list;
 * - a product left out on the MCP page is not served.
 */

namespace {
    $root = dirname(__DIR__);

    if (!defined('ABSPATH')) {
        define('ABSPATH', $root . '/');
    }

    define('WP_MCP_VERSION', '0.7.1');
    define('FLUENT_TOOLKIT_VERSION', '9.9.9');

    $GLOBALS['options'] = [];
    $GLOBALS['created_servers'] = [];
    $GLOBALS['user_caps'] = ['read' => true];

    class WP_Error
    {
        private $code;
        private $message;

        public function __construct($code = '', $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
        }
    }

    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }

    function wp_register_ability($name, $args = [])
    {
        return true;
    }

    function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        return true;
    }

    function add_filter($hook, $callback, $priority = 10, $acceptedArgs = 1)
    {
        return true;
    }

    function apply_filters($hook, $value, ...$args)
    {
        $GLOBALS['applied_filters'][] = [$hook, isset($args[0]) ? $args[0] : null, isset($args[1]) ? $args[1] : null];

        if (isset($GLOBALS['filter_overrides'][$hook])) {
            return call_user_func($GLOBALS['filter_overrides'][$hook], $value, ...$args);
        }

        return $value;
    }

    function get_option($key, $default = false)
    {
        return array_key_exists($key, $GLOBALS['options']) ? $GLOBALS['options'][$key] : $default;
    }

    function update_option($key, $value)
    {
        $GLOBALS['options'][$key] = $value;
        return true;
    }

    function sanitize_key($key)
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }

    function wp_json_encode($data, $flags = 0)
    {
        return json_encode($data, $flags);
    }

    function current_user_can($cap)
    {
        return !empty($GLOBALS['user_caps'][$cap]);
    }

    function get_rest_url($blogId = null, $path = '')
    {
        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }

    function trailingslashit($value)
    {
        return rtrim($value, '/') . '/';
    }

    function fail($message)
    {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

namespace WP\MCP\Core {

    class McpAdapter
    {
        const VERSION = '0.7.1';

        public $servers = [];

        public function get_server($id)
        {
            return isset($this->servers[$id]) ? $this->servers[$id] : null;
        }

        public function create_server($id, $namespace, $route, $name, $description, $version, $transports, $errorHandler, $observability, $tools = [], $resources = [], $prompts = [], $permission = null)
        {
            $GLOBALS['created_servers'][$id] = compact('id', 'namespace', 'route', 'name', 'description', 'tools', 'prompts', 'permission');
        }
    }

    class StubSchemas
    {
        public function forVersion($version)
        {
            return $version;
        }
    }

    class StubServer
    {
        public $id;
        public $tools = [];
        public $prompts = [];
        public $gate = null;

        public function __construct($id, $tools, $gate = null)
        {
            $this->id = $id;
            $this->tools = $tools;
            $this->gate = $gate;
        }

        public function get_server_id()
        {
            return $this->id;
        }

        public function get_server_description()
        {
            return 'Instructions for ' . $this->id;
        }

        public function get_schemas()
        {
            return new StubSchemas();
        }

        public function get_tools($schema)
        {
            $records = [];
            foreach ($this->tools as $name => $tool) {
                $records[$name] = $tool->record;
            }

            return $records;
        }

        public function get_prompts($schema)
        {
            return [];
        }

        public function get_mcp_tool($name)
        {
            return isset($this->tools[$name]) ? $this->tools[$name] : null;
        }

        public function get_mcp_prompt($name)
        {
            return null;
        }

        public function get_transport_permission_callback()
        {
            return $this->gate;
        }
    }
}

namespace WP\MCP\Domain\Tools {

    class McpTool
    {
        public $record;
        public $ability;
        public $allowed = true;
        public $calls = [];
        public $config;

        public static function source($ability, $readOnly)
        {
            $tool = new self();
            $tool->ability = $ability;
            $tool->record = [
                'name'        => str_replace('/', '-', $ability),
                'description' => 'Runs ' . $ability,
                'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                'annotations' => ['readOnlyHint' => $readOnly],
            ];

            return $tool;
        }

        public static function fromArray($config)
        {
            $tool = new self();
            $tool->config = $config;

            return $tool;
        }

        public function get_adapter_meta()
        {
            return ['ability' => $this->ability];
        }

        public function check_permission($args)
        {
            return $this->allowed ? true : new \WP_Error('forbidden', 'You cannot run ' . $this->ability);
        }

        public function execute($args)
        {
            $this->calls[] = $args;

            return ['ran' => $this->ability, 'args' => $args];
        }
    }
}

namespace {
    require_once $root . '/includes/Mcp/AdapterBootstrap.php';
    require_once $root . '/includes/Mcp/HubServer.php';

    use FluentToolkit\Mcp\HubServer;
    use WP\MCP\Core\StubServer;
    use WP\MCP\Domain\Tools\McpTool;

    function build_adapter()
    {
        $adapter = new \WP\MCP\Core\McpAdapter();

        $crmTools = [];
        foreach (['fluent-crm/get-crm-context' => true, 'fluent-crm/list-tags' => true, 'fluent-crm/delete-contact' => false] as $ability => $readOnly) {
            $tool = McpTool::source($ability, $readOnly);
            $crmTools[$tool->record['name']] = $tool;
        }

        $supportTools = [];
        $tool = McpTool::source('fluent-support/list-tickets', true);
        $supportTools[$tool->record['name']] = $tool;

        $adapter->servers['fluent-crm'] = new StubServer('fluent-crm', $crmTools);
        $adapter->servers['fluent-support'] = new StubServer('fluent-support', $supportTools, function () {
            return !empty($GLOBALS['user_caps']['support_agent']);
        });
        // Not a Fluent product: the hub must ignore it.
        $adapter->servers['other-plugin'] = new StubServer('other-plugin', []);

        return $adapter;
    }

    function router_tools()
    {
        $tools = [];
        foreach ($GLOBALS['created_servers']['fluent-hub']['tools'] as $tool) {
            $tools[$tool->config['name']] = $tool->config;
        }

        return $tools;
    }

    function call_router($tools, $name, $args)
    {
        return call_user_func($tools[$name]['handler'], $args, null);
    }

    if (!HubServer::supported()) {
        fail('Expected HubServer::supported() with adapter 0.7.1.');
    }

    $adapter = build_adapter();
    HubServer::createServer($adapter);

    if (empty($GLOBALS['created_servers']['fluent-hub'])) {
        fail('Expected the fluent-hub server to be created.');
    }

    $server = $GLOBALS['created_servers']['fluent-hub'];
    if ($server['namespace'] !== 'fluent-hub' || $server['route'] !== 'mcp') {
        fail('Expected the hub at /fluent-hub/mcp.');
    }

    $tools = router_tools();
    $names = array_keys($tools);
    sort($names);

    // fluent-support has no write action, so it gets no write tool.
    if ($names !== ['fluent-crm-read', 'fluent-crm-write', 'fluent-support-read']) {
        fail('Unexpected router tools: ' . implode(', ', $names));
    }

    if (empty($tools['fluent-crm-read']['annotations']['readOnlyHint']) || !empty($tools['fluent-crm-write']['annotations']['readOnlyHint'])) {
        fail('Expected the read tool to be read-only and the write tool not.');
    }

    if (false === strpos($tools['fluent-crm-read']['description'], 'get-crm-context, list-tags')
        || false !== strpos($tools['fluent-crm-read']['description'], 'delete-contact')) {
        fail('Expected the read tool to list only the read-only actions.');
    }

    // Short name and full tool name both reach the same product tool.
    $short = call_router($tools, 'fluent-crm-read', ['action' => 'list-tags', 'arguments' => ['id' => 4]]);
    $full = call_router($tools, 'fluent-crm-read', ['action' => 'fluent-crm-list-tags', 'arguments' => ['id' => 4]]);

    if ($short !== ['ran' => 'fluent-crm/list-tags', 'args' => ['id' => 4]] || $full !== $short) {
        fail('Expected list-tags to run by its short and full names.');
    }

    $wrong = call_router($tools, 'fluent-crm-read', ['action' => 'delete-contact', 'arguments' => ['id' => 1]]);
    if (!is_wp_error($wrong) || false === strpos($wrong->get_error_message(), 'fluent-crm-write')) {
        fail('Expected a write action on the read tool to point at fluent-crm-write.');
    }

    $crmDelete = $adapter->servers['fluent-crm']->tools['fluent-crm-delete-contact'];
    if ($crmDelete->calls) {
        fail('The wrong-tool call must not execute the action.');
    }

    $unknown = call_router($tools, 'fluent-crm-write', ['action' => 'nope']);
    if (!is_wp_error($unknown) || $unknown->get_error_code() !== 'fluent_hub_unknown_action') {
        fail('Expected an unknown action to fail.');
    }

    // The product tool's own permission check runs first and stops the call.
    $crmDelete->allowed = false;
    $denied = call_router($tools, 'fluent-crm-write', ['action' => 'delete-contact', 'arguments' => ['id' => 1]]);
    if (!is_wp_error($denied) || $denied->get_error_message() !== 'You cannot run fluent-crm/delete-contact' || $crmDelete->calls) {
        fail('Expected the product permission check to refuse before execute.');
    }

    $help = call_router($tools, 'fluent-crm-write', ['action' => 'help', 'arguments' => ['actions' => ['delete-contact']]]);
    if ($help['instructions'] !== 'Instructions for fluent-crm' || count($help['actions']) !== 1) {
        fail('Expected help to return the product instructions and one action.');
    }

    $action = $help['actions'][0];
    if ($action['action'] !== 'delete-contact' || $action['tool'] !== 'fluent-crm-write' || $action['name'] !== 'fluent-crm-delete-contact' || empty($action['inputSchema'])) {
        fail('Unexpected help entry: ' . json_encode($action));
    }

    $fullHelp = call_router($tools, 'fluent-crm-read', ['action' => 'help']);
    if (count($fullHelp['actions']) !== 3) {
        fail('Expected help without a filter to list every CRM action.');
    }

    // A subscriber passes the CRM default ('read') but not the Support gate.
    $listed = HubServer::filterToolsList(
        [['name' => 'fluent-crm-read'], ['name' => 'fluent-crm-write'], ['name' => 'fluent-support-read']],
        new StubServer('fluent-hub', [])
    );
    if (array_column($listed, 'name') !== ['fluent-crm-read', 'fluent-crm-write']) {
        fail('Expected the Support tools to be hidden from a user who fails its gate.');
    }

    if (true !== HubServer::transportPermission(null)) {
        fail('Expected the hub transport to let in a user who passes one product gate.');
    }

    $GLOBALS['user_caps'] = [];
    if (false !== HubServer::transportPermission(null)) {
        fail('Expected the hub transport to refuse a user who passes no product gate.');
    }

    // Another server's tools/list must pass through untouched.
    $other = [['name' => 'x']];
    if (HubServer::filterToolsList($other, new StubServer('fluent-crm', [])) !== $other) {
        fail('Expected filterToolsList to leave other servers alone.');
    }

    // A product left out on the MCP page is not served.
    HubServer::setExcludedProducts(['fluent-support', 'not-a-product']);
    if (HubServer::excludedProducts() !== ['fluent-support']) {
        fail('Expected only known products in the exclude list.');
    }

    $GLOBALS['created_servers'] = [];
    HubServer::createServer(build_adapter());
    $names = array_keys(router_tools());
    sort($names);
    if ($names !== ['fluent-crm-read', 'fluent-crm-write']) {
        fail('Expected an excluded product to drop out of the hub: ' . implode(', ', $names));
    }

    // The server is off until the admin turns it on. The first time, sign-in
    // comes on with it; after that the admin's own choice for sign-in stays.
    if (HubServer::enabled()) {
        fail('Expected the FluentHub MCP server to be off by default.');
    }

    HubServer::setEnabled(true);
    if (!HubServer::enabled() || 'yes' !== $GLOBALS['options']['_fluent_kit_settings']['mcp_oauth']) {
        fail('Expected turning the server on to turn sign-in on with it.');
    }

    $GLOBALS['options']['_fluent_kit_settings']['mcp_oauth'] = 'no';
    HubServer::setEnabled(false);
    HubServer::setEnabled(true);
    if ('no' !== $GLOBALS['options']['_fluent_kit_settings']['mcp_oauth']) {
        fail('Expected the admin\'s choice to keep sign-in off to stay.');
    }

    // The MCP page changes one product per request. A product whose plugin is
    // not active keeps its state: it is neither excluded nor included by it.
    HubServer::setExcludedProducts([]);
    HubServer::setProductIncluded('fluent-crm', false);
    if (HubServer::excludedProducts() !== ['fluent-crm']) {
        fail('Expected only the unticked product in the exclude list: ' . implode(', ', HubServer::excludedProducts()));
    }

    HubServer::setProductIncluded('fluent-support', false);
    HubServer::setProductIncluded('fluent-crm', true);
    if (HubServer::excludedProducts() !== ['fluent-support']) {
        fail('Expected ticking a product to leave the other exclusions alone.');
    }
    HubServer::setExcludedProducts([]);

    // The adapter's tool-call filters run for the product's tool, by its name
    // and with its server, so a site rule on that tool also holds on the hub.
    $GLOBALS['created_servers'] = [];
    $GLOBALS['user_caps'] = ['read' => true];
    $adapter = build_adapter();
    HubServer::createServer($adapter);
    $tools = router_tools();

    $GLOBALS['applied_filters'] = [];
    call_router($tools, 'fluent-crm-write', ['action' => 'delete-contact', 'arguments' => ['id' => 7]]);
    $seen = [];
    foreach ($GLOBALS['applied_filters'] as $applied) {
        if (0 === strpos($applied[0], 'mcp_adapter_')) {
            $seen[$applied[0]] = true;
        }
    }
    if (empty($seen['mcp_adapter_pre_tool_call']) || empty($seen['mcp_adapter_tool_call_result'])) {
        fail('Expected the adapter tool-call filters to run for the product tool.');
    }

    $deleteTool = $adapter->servers['fluent-crm']->tools['fluent-crm-delete-contact'];
    $callsBefore = count($deleteTool->calls);
    $GLOBALS['filter_overrides']['mcp_adapter_pre_tool_call'] = function ($args, $toolName) {
        return 'fluent-crm-delete-contact' === $toolName ? new \WP_Error('blocked', 'Deletes are blocked on this site.') : $args;
    };
    $blocked = call_router($tools, 'fluent-crm-write', ['action' => 'delete-contact', 'arguments' => ['id' => 7]]);
    if (!is_wp_error($blocked) || 'blocked' !== $blocked->get_error_code() || count($deleteTool->calls) !== $callsBefore) {
        fail('Expected a pre-tool-call filter on the product tool name to stop the call.');
    }
    unset($GLOBALS['filter_overrides']['mcp_adapter_pre_tool_call']);

    $GLOBALS['filter_overrides']['mcp_adapter_tool_call_result'] = function ($result) {
        return ['redacted' => true];
    };
    if (call_router($tools, 'fluent-crm-read', ['action' => 'list-tags']) !== ['redacted' => true]) {
        fail('Expected a tool-call-result filter to change the result.');
    }
    unset($GLOBALS['filter_overrides']['mcp_adapter_tool_call_result']);

    // prompts/get: a prompt of another server passes; nothing here is gated
    // when no product owns the name.
    $hub = new StubServer('fluent-hub', []);
    if (HubServer::gatePrompt(['a' => 1], 'unknown-prompt', null, $hub) !== ['a' => 1]
        || HubServer::gatePrompt(['a' => 1], 'unknown-prompt', null, new StubServer('fluent-crm', [])) !== ['a' => 1]
    ) {
        fail('Expected gatePrompt to pass prompts it does not own.');
    }

    echo 'MCP hub server test passed.' . PHP_EOL;
}
