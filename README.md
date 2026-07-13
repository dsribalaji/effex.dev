# SkillApp

SkillApp is a powerful PHP and MySQL-based chat application designed to act as an intelligent Business Analyst (BA) assistant. It integrates with multiple Large Language Model (LLM) providers (like Anthropic and OpenAI-compatible endpoints) and dynamically fetches role-specific operational skills directly from a GitHub repository to guide its responses.

The application goes beyond simple chat by allowing users to upload context files, interact with specific "skills" (via slash commands), and automatically generate deliverable artifacts (Markdown, PDF, DOCX) directly into a role-specific folder structure.

## Features

- **Multi-Provider LLM Support:** Easily switch between OpenAI-compatible APIs (like Groq) and Anthropic (Claude) APIs. API Keys are securely managed via an admin interface.
- **Dynamic GitHub Skills Integration:** Skills are fetched in real-time from a designated GitHub repository (`dsribalaji/AI-Skills`). These skills act as specialized agents guiding the chat's behavior.
- **Artifact Generation:** When the AI generates a deliverable (like a Stakeholder Register or a Business Requirements Document), it can save it directly as an artifact linked to the conversation. Artifacts retain their intended folder paths (e.g., `01_discovery/stakeholder-register.md`).
- **File Attachments:** Upload files to the chat to be used as context for the AI.
- **Markdown & Export Support:** The chat interface fully renders Markdown. Artifacts can be exported as PDFs or DOCX files.
- **Dark/Light Mode:** Includes an elegant UI with Snow and Carbon themes.

## Prerequisites

- **Web Server:** Apache (XAMPP recommended for local development).
- **PHP:** PHP 8.0 or higher.
- **Database:** MySQL or MariaDB.
- **Extensions:** PDO, cURL, JSON, MBString.

## Installation

1. **Clone the Repository:**
   Clone this project into your web server's document root (e.g., `htdocs` in XAMPP).
   ```bash
   git clone <your-repo-url> skillapp
   cd skillapp
   ```

2. **Database Setup:**
   - Open phpMyAdmin or your preferred MySQL client.
   - Create a new database named `skillapp`.
   - Import the schema by running the SQL found in `sql/schema.sql`.
   ```bash
   mysql -u root -p skillapp < sql/schema.sql
   ```

3. **Configuration:**
   - Copy the example configuration file to create your local configuration:
     ```bash
     cp config/config.example.php config/config.php
     ```
   - Open `config/config.php` and update the database credentials if necessary.
   - You can also set a default fallback LLM API key and update your GitHub Token (highly recommended to avoid API rate limits when fetching skills).

4. **Default Admin User:**
   - You will need an admin user to manage API keys.
   - Register a new user with the username `admin` through the UI (`signup.php`).

## Usage

### Managing API Keys
1. Log in with the `admin` account.
2. Click the **Admin** button in the top navigation.
3. Add a new API Key by selecting the provider (e.g., Anthropic or OpenAI-Compatible), entering the Base URL (e.g., `https://api.groq.com/openai/v1`), the Model (e.g., `llama-3.3-70b-versatile`), and the API Key.
4. Click **Activate** on the key you want the system to use.

### Using Skills
- In the chat interface, type `/` to see a list of available skills fetched from the configured GitHub repository.
- Selecting a skill (e.g., `/stakeholder-discovery`) will activate that agent for the conversation. The AI will immediately adopt the persona and instructions defined in that skill's `SKILL.md` file.

### Generating Artifacts
- Skills are instructed to generate deliverables using the `SAVE_AS: {phase_folder}/{filename}.md` directive.
- When the AI outputs this directive, the system will automatically parse it and save the content as an artifact attached to your conversation, preserving the folder structure.

## Repository Structure

- `/api` - Backend endpoints for chat streaming, artifact management, and admin functions.
- `/assets` - CSS and JavaScript files for the frontend UI.
- `/config` - Configuration files (`config.php`).
- `/lib` - Core logic, including the LLM client, GitHub client, Authentication, and Database handlers.
- `/sql` - Database schema files.

## Security

- Passwords are securely hashed using PHP's native `password_hash`.
- CSRF tokens are implemented for sensitive API endpoints.
- Path traversal protections are in place when saving artifacts.
- API keys are base64 encoded in the database to prevent casual exposure.

## License

This project is open-source and available under the MIT License.
