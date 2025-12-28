<?php

/**
 * Fired during plugin activation
 */
class Nueva_Chatbot_Activator
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate()
	{
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// 1. Knowledge Base Table
		$table_name_kb = $wpdb->prefix . 'bua_knowledge_base';
		$sql_kb = "CREATE TABLE $table_name_kb (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			type varchar(50) NOT NULL, -- 'url', 'pdf', 'text', 'structured_manual', 'wp_post'
			source_ref varchar(255) DEFAULT '' NOT NULL, -- URL or File Path or Post ID
			content longtext NOT NULL, -- Parsed/Scraped Text
			raw_data longtext DEFAULT '' NOT NULL, -- Original JSON/Structure
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta($sql_kb);

		// 2. Business Profile Table
		$table_name_biz = $wpdb->prefix . 'bua_business_profile';
		$sql_biz = "CREATE TABLE $table_name_biz (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			meta_key varchar(100) NOT NULL UNIQUE,
			meta_value longtext NOT NULL,
			updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta($sql_biz);

		// 3. Chat Flows Table
		$table_name_flows = $wpdb->prefix . 'bua_chat_flows';
		$sql_flows = "CREATE TABLE $table_name_flows (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			flow_json longtext NOT NULL,
			trigger_keywords text DEFAULT '', -- Comma separated
			is_active boolean DEFAULT 1 NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta($sql_flows);

		// 4. Leads Table
		$table_name_leads = $wpdb->prefix . 'bua_leads';
		$sql_leads = "CREATE TABLE $table_name_leads (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			chat_session_id varchar(100) NOT NULL,
			user_data longtext NOT NULL, -- JSON data
			collected_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			is_synced boolean DEFAULT 0 NOT NULL, -- For Google Sheets/Webhook
			PRIMARY KEY  (id)
		) $charset_collate;";
		dbDelta($sql_leads);

		// 5. Chat History Table
		$table_name_history = $wpdb->prefix . 'bua_chat_history';
		$sql_history = "CREATE TABLE $table_name_history (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			session_id varchar(100) NOT NULL,
			sender varchar(50) NOT NULL, -- 'user' or 'bot'
			message longtext NOT NULL,
			meta_data longtext DEFAULT '', -- Token usage, model used, etc.
			timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			INDEX session_idx (session_id)
		) $charset_collate;";
		dbDelta($sql_history);

		// 6. Feedback Table (v1.7.0)
		$table_name_feedback = $wpdb->prefix . 'bua_chat_feedback';
		$sql_feedback = "CREATE TABLE $table_name_feedback (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			session_id varchar(100) NOT NULL,
			rating tinyint(1) NOT NULL, -- 1-5
			reason text DEFAULT '', -- Optional feedback text
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			INDEX session_idx (session_id)
		) $charset_collate;";
		dbDelta($sql_feedback);
	}
}
