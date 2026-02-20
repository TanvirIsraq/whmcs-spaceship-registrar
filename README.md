# Spaceship.com Registrar Module for WHMCS

An enhanced, high-performance registrar module for WHMCS that integrates with the [Spaceship.com](https://www.spaceship.com/) Public API. This module is designed for stability, speed, and 100% compatibility with WHMCS v8.x and v9.x.

---

## 🚀 Key Features

- **Domain Lifecycle**: High-speed Registration, Renewal, and Transfer automation.
- **Improved Registrar Lock**: Multi-method status detection using both direct API checks and EPP Status fallback.
- **Account Balance Display**: View your Spaceship wallet balance directly on the WHMCS registrar settings page.
- **ID Protection (WHOIS Privacy)**: Manage private registration toggles with instant feedback.
- **Child Nameservers**: Full CRUD support for personal private nameservers (NS1.yourdomain.com).
- **Domain Lookup Provider**: Optimized availability checks tailored for high-volume searches.
- **Transfer Status**: Real-time monitoring of incoming domain transfer progress.
- **Adaptive Syncing**: Advanced status mapping handles `Registered`, `Expired`, `Grace Period`, and `Redemption` automatically.
- **DNS Record Management**: Native support for A, AAAA, CNAME, MX, TXT, and SRV records.
- **EPP Code Handling**: Secure retrieval of transfer authorization codes for clients.
- **Smart Rate-Limit Protection**: Implements **Persistent Database Caching** (`mod_spaceship_cache`) to prevent "429 Too Many Requests" errors, even across multiple admin sessions.
- **Optimized Contact Updates**: Deduplication logic prevents creating multiple identical contact IDs during updates.

---

## 💎 Premium Features (Coming Soon)

- **TLD Pricing Sync**: Automatically import and sync cost pricing for 400+ TLDs directly into WHMCS with a custom profit margin. 
  - *Note: This feature is intended for licensed/premium versions only.*

---

## 🛠️ Installation & Setup

### Prerequisites
- **WHMCS v8.x, v9.x** (Tested on the latest versions)
- **PHP 7.4 or higher** (PHP 8.1+ recommended)
- **Spaceship.com API Credentials**: Obtain your API Key and Secret from the [Spaceship API Manager](https://www.spaceship.com/application/api-manager/).

### Upgrading from v2.0 or earlier
If you are upgrading an existing installation, you **must re-activate the module** to create the necessary database table:
1. Upload the new files, overwriting the old ones.
2. Go to **System Settings > Domain Registrars**.
3. Find **Spaceship**, click **Deactivate**, and then immediately click **Activate**.
4. Verify your API credentials are still present and click **Save Changes**.

### New Installation
1. **Download**: Download the repository as a ZIP or clone it.
2. **Upload**: Upload the `modules/` folder into your **WHMCS root directory**.
3. **Activate**:
   - Log in to your WHMCS Admin Area.
   - Navigate to **System Settings > Domain Registrars**.
   - Find **Spaceship** and click **Activate**.
4. **Configure**:
   - Enter your **API Key** and **API Secret**.
   - Click **Save Changes**.

---

## ⚠️ Important Considerations

### DNS Management
To use the **DNS Management** feature in WHMCS, your domain **must** be using Spaceship's default nameservers. If you are using external nameservers (Cloudflare, etc.), changing DNS records in WHMCS will update the records on Spaceship's side but will have no effect on your live website.

### API Rate Limits
Spaceship enforces a limit of **5 requests per domain every 5 minutes**. This module includes a built-in caching engine to minimize API calls, but excessive bulk domain updates in a short window may still trigger a temporary rate-limit delay.

### Sandbox Mode
As of the current version, Spaceship.com does not provide a public sandbox environment. The **Test Mode** setting is included for future compatibility but will currently return errors if enabled.

---

## ⚡ Troubleshooting
If you encounter any issues:
1. Go to **Utilities > Logs > Module Log**.
2. Enable debug logging if it is not already enabled.
3. Look for `spaceship` entries and review the **Request** and **Response**.

### Common API Errors:
- **404 Not Found**: Usually happens if the domain is not in your Spaceship account or you are trying to GET a sub-resource that only supports PUT.
- **429 Rate Limit Exceeded**: Spaceship allows 5 requests per 5 minutes. Wait a few minutes or reduce bulk updates.
- **400 Invalid Request**: Often means a required field (like DNS `take/skip` or Child Nameserver `host`) is missing or incorrectly formatted.

---

## 📚 Resources & Credits

- **Official API Documentation**: [Spaceship Public API Docs](https://docs.spaceship.dev/)
- **Original Inspiration**: Special thanks to [springmusk026](https://github.com/springmusk026/Spaceship-WHMCS-Registrar-Module) for the foundation.
- **Similar Implementations**: Inspired by the industry-standard [NameSilo WHMCS Module](https://github.com/namesilo/whmcs).

---

## 🤝 Contributing
Found a bug or want to suggest a feature? 
- [Open an issue](https://github.com/TanvirIsraq/whmcs-spaceship-registrar/issues)
- [Submit a Pull Request](https://github.com/TanvirIsraq/whmcs-spaceship-registrar/pulls)

---

## 📝 License
This project is licensed under the MIT License - see the LICENSE file for details.
