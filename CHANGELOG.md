# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Product Transfer**: This module has been transferred to **Topeta**. License activation is now processed via [my.topeta.com](https://my.topeta.com).
- **Licensing Resilience**: The module optimally relies on server-side redirects at `my.jobfew.com` (0 code changes required for existing installations).
- **Support Ecosystem**: Updated support email (`support@topeta.com`) and updated internal store references.

### Added
- **Automated Release Pipeline**: Added a GitHub Actions workflow that automatically builds and attaches a distributable ZIP to every GitHub Release.
- **Release Packaging Improvements (Pro Version)**: Improved build reliability for production releases.
- **Feature Control  (Pro Version)**: Added "Enable Automatic TLD Sync" toggle to module settings.

### Fixed
- **Sync Logic Optimization (Pro Version)**: Automated TLD synchronization now respects the `EnableAutoSync` toggle specifically for background CLI (cron) tasks, while still allowing manual synchronization via the web interface.
- **Namespace Isolation (Pro Version)**: Optimized internal class structures to prevent conflicts with other JobFew modules.
- **TLD Sync Feed Adaptation (Pro Version)**: Updated the pricing synchronizer to support the latest remote pricing feed structure.
- **Critical Runtime Fix (Pro Version)**: Resolved a fatal error during domain searches that affected specialized result lists.
- **Security Fix (Pro Version)**: Enabled TLS verification for live pricing feed requests to prevent man-in-the-middle attacks.
- **Test Suite**: Resolved all test drift issues in `ModuleTest.php`; all 8 core tests now pass cleanly.
- **Cleanup**: Stripped diagnostic logs and finalized the integration with the centralized JobFew Helper framework.

## [2.2.0] - 2026-02-21

### Changed
- **Framework Migration (Pro Version)**: Migrated licensing and bridge logic to the centralized **JobFew Helper** framework for improved stability and management.
- **TLD Sync Fix (Pro Version)**: Improved compatibility for TLD synchronization across different WHMCS versions.
- **Live-Only Pricing Mandate (Pro Version)**: Fully transitioned to a live-relay pricing model, ensuring users always see the most accurate TLD costs.

## [2.1.1] - 2026-02-20

### Added
- **Persistent Database Cache**: Replaced in-memory caching with a database-backed system. Rate-limit protection now persists across sessions and page loads.
- **Auto-Initialization**: Added `spaceship_activate` hook to automatically set up the cache table upon module activation.
- **TLD Pricing Sync Improvement**: Strictly use live cloud feed for latest costs (Pro Version).
- **Enhanced API Logging**: Processed responses now include HTTP status codes and helpful descriptions for empty responses (e.g., 204 No Content).

### Fixed
- **Privacy Toggle (ID Protection)**: Fixed `422 Unprocessable Entity` error by implementing the official `privacyLevel` and `userConsent` spec as per `docs.spaceship.dev`.
- **DNS Record Management**: Fixed synchronization issues by standardizing on `PUT` requests and the required `items` wrapper for batch updates.
- **Contact Details Optimization**: Implemented in-memory deduplication and process-level caching for `GetContactDetails`, reducing redundant API calls by up to 75%.
- **Registrar Lock Toggle**: Major refactor using a dual-check system (Direct Boolean + EPP Status scan) for better reliability.
- **Error Handling**: Added missing `logModuleCall` entries in all setter catch blocks for easier debugging.

### Changed
- **API Headers**: Standardized headers to `X-API-Key` and `X-API-Secret`.
- **User-Agent**: Added customized User-Agent identifying the module and version.

### ⚠️ Upgrade Notes
If upgrading from v2.0.0, you **must re-activate the module** to initialize the database table:
1. Deactivate then Activate the module in **System Settings > Domain Registrars**.
2. This creates the necessary database tables.

## [2.0.0] - 2026-02-19

### Added
- **Smart Rate-Limit Protection**: Initial global in-memory caching engine.
- **Account Balance Display**: Support for viewing wallet balance in WHMCS settings.
- **Advanced Logging**: Full integration with WHMCS `logModuleCall`.
- **Developer Documentation**: Added extensive DocBlocks and technical comments.

### Fixed
- **DNS Management**: Fixed retrieval failure caused by missing `take` and `skip` parameters.
- **Child Nameserver Formatting**: Automatic host-prefix stripping.
- **Contact Deduplication**: Added logic to prevent redundant contact creation.
