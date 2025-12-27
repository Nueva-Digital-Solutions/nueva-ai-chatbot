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
        // Nothing to clean up for now.
        // We do NOT drop tables on deactivation to preserve user data.
    }
}
