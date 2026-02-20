# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.1] - 2026-02-20

### Added
- **Persistent Database Cache**: Replaced in-memory caching with a database-backed system (`mod_spaceship_cache` table). Rate-limit protection now persists across sessions and page loads.
- **Auto-Initialization**: Added `spaceship_activate` hook to automatically set up the cache table upon module activation.
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
2. This creates the `mod_spaceship_cache` table.

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
