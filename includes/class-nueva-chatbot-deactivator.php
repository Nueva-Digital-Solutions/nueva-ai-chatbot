<?php

/**
 * Fired during plugin deactivation
 */
class Nueva_Chatbot_Deactivator
{

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate()
    {
        // Clear Telemetry Cron
        wp_clear_scheduled_hook('nueva_weekly_telemetry');
    }
}
