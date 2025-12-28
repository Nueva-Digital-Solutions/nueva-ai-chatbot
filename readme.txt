=== Nueva AI Chatbot ===
Contributors: nuevadigital
Tags: ai, chatbot, gemini, google gemini, customer support, lead generation
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.7.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

An advanced AI chatbot plugin powered by Gemini. Features knowledge base management, flow builder, lead generation, and custom branding.

== Description ==

Nueva AI Chatbot is a powerful conversational agent for WordPress, driven by Google's Gemini API. It allows business owners to train their AI using a custom Knowledge Base (URLs, PDFs, Manual Entries, or Site Content) and capture leads directly through the chat interface.

**Key Features:**

*   **AI Core**: Powered by Google Gemini 2.5 Flash/Pro & 3.0 Preview for human-like understanding.
*   **Multimodal**: Users can upload Images and PDFs for the AI to analyze and discuss.
*   **Knowledge Base**: Train the AI with your Website Content (Site Scan), External URLs, PDFs, or Manual Data.
*   **Lead Generation**: Capture Name, Email, Phone, and Custom Fields (e.g. Order ID) with strict validation.
*   **Smart Automation**: Auto-suggestions, Inactivity Auto-end, and "Smart End" detection (e.g. "Bye").
*   **WooCommerce**: Guests can check Order Status using their Phone Number or Email.
*   **Feedback System**: Built-in 5-star Emoji rating system to gather user sentiment.
*   **Analytics Dashboard**: Visual charts for Chat Volume, Satisfaction Ratings, and Lead conversions.
*   **Visual Flows**: Create static decision trees for guided support workflows.
*   **Customization**: Match your brand's Colors, Fonts, Tone, and Position.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/nueva-ai-chatbot` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to "Nueva AI Chat" in the admin sidebar.
4.  Enter your Gemini API key in the General Settings.
5.  Configure your Knowledge Base and Appearance settings.

== Frequently Asked Questions ==

= Do I need a Gemini API Key? =
Yes, you must obtain an API key from Google AI Studio to use the AI features.

= Can I customize the chatbot colors? =
Yes, you can fully customize the primary and secondary colors, as well as the font family and size, from the Appearance settings.

== Screenshots ==

1.  **General Settings**: Configure API keys and agent identity.
2.  **Knowledge Base**: Manage your training data sources.
3.  **Frontend Widget**: The beautiful, responsive chat interface.

== Changelog ==

= 1.7.0 =
*   **New**: Customer Feedback System (1-5 Stars, Emoji UI).
*   **New**: Admin Analytics Dashboard (Visual Charts for Activity & Sentiment).
*   **New**: Smart End-Chat (Auto-ends on Inactivity or "Bye" intents).
*   **New**: Feedback Management Tab.
*   **Database**: Added table for feedback tracking.

= 1.6.0 =
* Added strict email/phone validation in AI logic.
* Added support for file attachments (images/PDF) in chat.
* Expanded lead collection fields configuration.
* UI refinements and bug fixes.

= 1.5.0 =
*   **New**: "Before Chat" Lead Gate mode.
*   **New**: AI Smart Suggestions for follow-up questions.
*   **New**: Guest Order Lookup by Phone Number.
*   **New**: "Load Template" for Flows.
*   **Improved**: Lead Management (CSV Export, IP, Date).
*   **Improved**: De-activated AI Auto-Flow Generator.

= 1.4.0 =
*   Fixed path issue in flow generator.

= 1.3.0 =
*   Added AI Auto-Generate Flows.
*   Added Smart Actions (Link, Phone).
*   Added Conditional Logic for Flows.

= 1.2.0 =
*   Added GitHub Auto-Updater.
*   Added Guest Order Status (Email + ID).
*   Added Link Sharing Controls.

= 1.0.0 =
*   Initial release.
