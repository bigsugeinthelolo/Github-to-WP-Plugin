# GitHub to WP Deployer & Auto-Updater

A secure, premium WordPress plugin that automates the installation, synchronization, and updates of plugins and themes directly from public and private GitHub repositories using secure Webhooks.

---

## 🚀 Key Features

*   **Public & Private Repositories**: Install and update plugins or themes from public repositories or private ones (via encrypted GitHub Personal Access Tokens).
*   **Automatic Updates**: Keeps your site updated instantly when code is pushed to your configured branch.
*   **Secure Webhooks**: Synchronizations are secured using **HMAC SHA-256 signatures** to prevent unauthorized trigger attempts.
*   **Encrypted Token Storage**: Sensitive tokens (GitHub PATs) are encrypted using site-specific keys (AES-256-CBC) derived from your WordPress salts.
*   **Rollback & Backup Protection**: Before updating, the plugin creates a temporary backup of the old version. If the extraction or update fails, it auto-restores the last working state to prevent downtime.
*   **Interactive Dashboard**: A beautiful, modern settings panel designed using the Outfit Google font, complete with real-time feedback and modal interfaces.
*   **Audit Logging & Timeline**: A complete history of updates, logging the status, author, commit message, and commit SHA of each synchronisation.

---

## 🛠 Requirements

*   **WordPress**: 5.8 or higher
*   **PHP**: 7.4 or higher (with `openssl` extension enabled for token encryption)

---

## 📥 Installation

1.  Download the repository as a ZIP file.
2.  Go to your WordPress Admin dashboard -> **Plugins** -> **Add New** -> **Upload Plugin**.
3.  Choose the downloaded ZIP file and click **Install Now**.
4.  **Activate** the plugin.

---

## ⚙️ Configuration & Setup

### 1. Add a Monitored Repository
After activation, navigate to **GitHub Sync** from your WordPress admin sidebar:
1. Click **Add Repository**.
2. Fill out the configuration fields:
    *   **GitHub Repo Path**: The format should be `owner/repo` (e.g., `your-username/my-plugin-repo`).
    *   **Type**: Select whether it is a **Plugin** or a **Theme**.
    *   **Target Branch**: The branch to monitor (e.g., `main` or `production`).
    *   **Local Directory Slug**: The folder name under `wp-content/plugins/` or `wp-content/themes/` where it should reside.
    *   **GitHub PAT**: Required for private repositories. Enter a GitHub classic token or fine-grained token with repository contents read access. Leave blank for public repositories.
3. Click **Save Repository**.

### 2. Configure GitHub Webhooks
To set up automatic updates:
1. Hover or click on the saved repository card to copy the **Webhook URL** and **Webhook Secret**.
2. Head to your GitHub Repository -> **Settings** -> **Webhooks** -> **Add webhook**.
3. Paste the **Webhook URL** into the **Payload URL** field.
4. Set **Content type** to `application/json`.
5. Paste the **Webhook Secret** into the **Secret** field.
6. Select **Just the push event** under triggers.
7. Click **Add webhook**.

*Note: GitHub will send a test `ping` event. You will see a successful handshake response code `200` once the webhook is correctly connected.*

---

## 🔄 Synchronisation Options

### ⚡ Automatic Updates
Whenever a new commit is pushed to your monitored branch, GitHub sends a webhook payload. The plugin verifies the signature, verifies the branch, downloads the latest zipball, and performs a clean replacement.

### 🖱 Manual Sync
If you need to manually trigger a sync from GitHub without waiting for a commit:
1. Open the **GitHub Sync** dashboard.
2. Click the **Sync Now** button on the repository card.
3. The plugin will query the GitHub API, fetch the latest commit message and author, and run the synchronisation engine immediately.

---

## 🔒 Security Architecture

*   **AES-256-CBC Encryption**: Your Personal Access Tokens are never stored in plain text. They are encrypted using `openssl` with a key built from the site's unique `SECURE_AUTH_KEY` and `AUTH_SALT` defined in your `wp-config.php`.
*   **Signature Verification**: Webhooks verify the `X-Hub-Signature-256` header sent by GitHub using the secret token shared between WordPress and your GitHub repository settings.
*   **Directory Traversal Guard**: Slugs are sanitized and protected against path traversal attacks (e.g., preventing directory paths containing `../` or arbitrary system paths).

---

## 📂 Code Layout & Structure

```text
github-to-wp-updater/
├── assets/
│   ├── css/
│   │   └── admin-style.css       # Custom visual stylesheet (Outfit font, layout, timeline)
│   └── js/
│       └── admin-script.js       # Admin panel AJAX controllers & UI modal scripts
├── includes/
│   ├── class-engine.php          # Handles downloading, unzipping, backups, and directory swaps
│   ├── class-helper.php          # Encryption helpers & WP_Filesystem initializer
│   ├── class-logger.php          # DB-backed logging for update audits
│   ├── class-settings.php        # WordPress settings pages and AJAX action routers
│   └── class-webhook.php         # REST API endpoint endpoints, ping and push verification logic
└── github-to-wp-updater.php      # Core plugin bootstrapper
```

---

## 📄 License

This project is licensed under the GPLv2 or later. Feel free to modify and adapt it to your development workflows.
