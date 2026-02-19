# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
