<?php

/**
 * Define the internationalization functionality
 */
class Nueva_Chatbot_i18n
{

    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'nueva-ai-chatbot',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}
