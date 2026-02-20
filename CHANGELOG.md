# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Persistent Database Cache**: Replaced in-memory caching with a database-backed system (`mod_spaceship_cache` table). Rate-limit protection now persists across multiple page loads and different administrative sessions.
- **Auto-Initialization**: Added `spaceship_activate` hook to automatically set up required database tables upon module activation.

### ⚠️ Upgrade Notes
If you are upgrading from a previous version, you **must** trigger the database table creation:
1. Go to **System Settings > Domain Registrars**.
2. Find **Spaceship** and click **Deactivate**.
3. Immediately click **Activate** again.
4. Verify your API credentials are still saved.
This will create the necessary `mod_spaceship_cache` table in your WHMCS database.

## [2.0.0] - 2026-02-19

### Added
- **Smart Rate-Limit Protection**: Implemented a global in-memory caching engine to minimize API calls (prevents 429 Too Many Requests errors).
- **Account Balance Display**: Support for viewing Spaceship wallet balance in WHMCS settings.
- **Improved Domain Sync**: Support for granular status mapping (`Registered`, `Expired`, `Grace Period`, `Redemption`).
- **Advanced Logging**: Full integration with WHMCS `logModuleCall` with automatic credential masking.
- **MIT License**: Formally added the project license.
- **Developer Documentation**: Added extensive DocBlocks and technical comments for contributors.

### Fixed
- **Registrar Lock Toggle**: Major refactor to fix 404 errors using a dual-check system (Direct Boolean + EPP Status scan).
- **DNS Management**: Fixed retrieval failure caused by missing mandatory `take` and `skip` parameters.
- **Child Nameserver Formatting**: Automatic host-prefix stripping for compatibility with Spaceship API requirements.
- **Contact Deduplication**: Added logic to prevent redundant contact creation during WHOIS updates.
- **DNS Error Handling**: Improved error messages to detect when a domain is using external nameservers.

### Changed
- **README**: Completely rewritten for clarity, including installation and troubleshooting guides.
- **Repository Structure**: Moved original fork code to `legacy` branch and modernized the `main` branch.

### Compatibility
- Verified support for **WHMCS v9.x**.
- Verified support for **PHP 8.1+**.
