# Nueva AI Chatbot

An advanced AI chatbot plugin for WordPress powered by Google Gemini. Developed by [Nueva Digital Solutions](https://nuevadigital.co.in).

![Detailed Banner Placeholder](https://via.placeholder.com/1200x400?text=Nueva+AI+Chatbot)

## 🚀 Features

- **Google Gemini Integration**: Utilizes the latest Gemini 2.5 Pro and Flash models for intelligent, context-aware responses.
- **Multimodal capabilities**: AI can analyze uploaded Images and PDFs.
- **Dynamic Knowledge Base**:
    - **Site Scan**: Auto-ingest all your WordPress Posts and Pages.
    - **URL Scraping**: Add external links for context.
    - **Manual Entry**: Structured data editor for specific FAQs or business rules.
- **Lead Generation**: 
    - **Conversational**: Capture details naturally during chat.
    - **Leads Gate**: Force lead capture before starting a chat.
    - **Export**: Download leads as CSV.
    - **Custom Fields**: Configure exact fields to collect (Name, City, Order #) with strict validation.
- **Smart AI Actions**:
    - **Suggestions**: Auto-suggested follow-up questions.
    - **Order Lookup**: Guests can check order status via Email or Phone.
- **Customizable Appearance**:
    - Match your brand's Primary/Secondary colors.
    - Choose fonts (Roboto, Inter, Open Sans).
    - Custom Positioning (Left/Right) for Desktop and Mobile.
- **Visual Flow Builder**: Create static conversation trees for guided support.
- **Privacy & Control**: Admin dashboard to manage all chat logs and leads.

## 🛠 Installation

1. Clone this repository into your `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/Nueva-Digital-Solutions/nueva-ai-chatbot.git
   ```
2. Activate the plugin in the WordPress Admin Dashboard.
3. Go to **Nueva AI Chat > Settings**.
4. Enter your **Gemini API Key**.
5. Build your **Knowledge Base** and customize the widget.

## 📋 Requirements

- WordPress 5.8+
- PHP 7.4+
- A Google Cloud Project with Gemini API enabled.

## 📜 Changelog

### 1.6.0
* Added strict email/phone validation in AI logic.
* Added support for file attachments (images/PDF) in chat.
* Expanded lead collection fields configuration.
* UI refinements and bug fixes.
* Removed Legacy Gemini 1.5 models.

### 1.5.0
*   **New**: "Before Chat" Lead Gate mode.
*   **New**: AI Smart Suggestions for follow-up questions.
*   **New**: Guest Order Lookup by Phone Number.
*   **New**: "Load Template" for Flows.
*   **Improved**: Lead Management (CSV Export, IP, Date).

## 🤝 Contributing

This is a proprietary plugin developed by Nueva Digital Solutions.

## 📄 License

GPL-2.0+

---
**Powered by [Nueva Digital Solutions](https://nuevadigital.co.in)**
