<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'skillapp');
define('DB_USER', 'root');
define('DB_PASS', '');

// Optional server-wide fallback LLM (any provider). Users normally bring their
// own key via Settings; leave DEFAULT_API_KEY empty to require that.
define('DEFAULT_PROVIDER', 'openai-compatible'); // or 'anthropic'
define('DEFAULT_API_KEY', '');
define('DEFAULT_API_URL', 'https://openrouter.ai/api/v1'); // any OpenAI-compatible base URL
define('DEFAULT_MODEL', '');

define('GITHUB_TOKEN', '');
define('GITHUB_REPO_OWNER', 'dsribalaji');
define('GITHUB_REPO_NAME', 'AI-Skills');
define('GITHUB_API_BASE', 'https://api.github.com');

define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);
define('MAX_CONTEXT_MESSAGES', 10);

// Optional: local clone of the skills repo for testing/tuning skills without GitHub.
// define('SKILLS_LOCAL_PATH', '/path/to/AI-Skills');
