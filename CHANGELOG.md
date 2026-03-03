# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **Product Transfer**: This module has been transferred to **Topeta**. License activation is now processed via [my.topeta.com](https://my.topeta.com).
- **Licensing Resilience**: The module optimally relies on server-side redirects at `my.jobfew.com` (0 code changes required for existing installations).
- **Support Ecosystem**: Updated support email (`support@topeta.com`) and updated internal store references.

## [2.2.2] - 2026-03-03

### Fixed
- **API Endpoint Accuracy**: Reverted the domain registration endpoint back to the explicitly required `/v1/domains/{domain}` to comply with Spaceship proxy architecture.
- **Contact Data Formatting**: Sanitized all outbound contact details (trimmed spaces, filtered missing optional fields) to stop `422 Unprocessable Entity` formatting errors, and implemented rigorous fallbacks for required missing fields (like phone numbers).
- **Privacy Protection (WHOIS) Spec**: Fixed a missing data error by actively dispatching the explicitly required `privacyProtection.level` (evaluating to 'high' or 'public') and `privacyProtection.userConsent` boolean configurations.
- **Improved HTTP Methods**: Altered the Contact Create API method from `POST` to the strictly enforced `PUT` endpoint.

## [2.2.1] - 2026-02-23

### Fixed
- **PHP 8.4 & Namespace Compatibility**: Resolved critical "Class Not Found" errors by standardizing on fully qualified `\WHMCS\Database\Capsule`.
- **Surgical Obfuscation**: Implemented a "Safe-Logic" boundary in the release pipeline. Interface files (`spaceship.php`, `Config.php`) are now excluded from scrambling to ensure WHMCS hooks function correctly.
- **PRO Badge & Status UI**: Restored missing licensing UI components in the module settings page.
- **TLD Sync Resilience**: Fixed variable scrambling that prevented the pricing feed from being correctly indexed.

### Added
- **Production-Build Standards**: Integrated comprehensive release scrubbing to remove development artifacts from the distributed ZIP.
- **Framework Hardening**: Full compatibility verified with JobFew Helper v1.0.0.

## [2.2.0] - 2026-02-21

### Changed
- **Framework Migration (Pro Version)**: Migrated licensing and bridge logic to the centralized **JobFew Helper** framework for improved stability and management.
- **TLD Sync Fix (Pro Version)**: Improved compatibility for TLD synchronization across different WHMCS versions.
- **Live-Only Pricing Mandate (Pro Version)**: Fully transitioned to a live-relay pricing model, ensuring users always see the most accurate TLD costs.

## [2.1.1] - 2026-02-20

### Added
- **Persistent Database Cache**: Replaced in-memory caching with a database-backed system.
- **Auto-Initialization**: Added `spaceship_activate` hook for cache table setup.

### Fixed
- **Privacy Toggle (ID Protection)**: Implemented official `privacyLevel` and `userConsent` spec.
- **DNS Record Management**: Standardized on `PUT` requests with `items` wrapper.

## [2.0.0] - 2026-02-19

### Added
- **Smart Rate-Limit Protection**: Initial global in-memory caching engine.
- **Account Balance Display**: Support for viewing wallet balance in WHMCS settings.
