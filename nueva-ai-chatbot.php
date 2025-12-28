<?php
/**
 * Plugin Name:       Nueva AI Chatbot
 * Plugin URI:        https://github.com/Nueva-Digital-Solutions/nueva-ai-chatbot
 * Description:       An advanced AI chatbot plugin powered by Gemini. Features knowledge base management, flow builder, lead generation, and custom branding.
 * Version:           1.3.0
 * Author:            Nueva Digital
 * Author URI:        https://nuevadigital.com
 * License:           GPL-2.0+
 * Text Domain:       nueva-ai-chatbot
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Current plugin version.
 */
define('NUEVA_AI_CHATBOT_VERSION', '1.3.0');

/**
 * The code that runs during plugin activation.
 */
function activate_nueva_ai_chatbot()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-nueva-chatbot-activator.php';
	Nueva_Chatbot_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_nueva_ai_chatbot()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-nueva-chatbot-deactivator.php';
	Nueva_Chatbot_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_nueva_ai_chatbot');
register_deactivation_hook(__FILE__, 'deactivate_nueva_ai_chatbot');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-nueva-chatbot.php';

/**
 * Begins execution of the plugin.
 */
function run_nueva_ai_chatbot()
{
	$plugin = new Nueva_Chatbot();
	$plugin->run();
}
run_nueva_ai_chatbot();
