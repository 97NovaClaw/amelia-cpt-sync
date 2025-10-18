# Changelog

All notable changes to the Amelia to CPT Sync plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-10-16

### Added
- Initial release of Amelia to CPT Sync plugin
- Real-time, event-driven synchronization from AmeliaWP to JetEngine CPT
- Admin settings page with two tabs: Setup and Field Mapping
- Post type selection dropdown for destination CPT
- Taxonomy selection dropdown for service categories
- Dynamic taxonomy loading based on selected post type
- Field mapping configuration for:
  - Service price (with JetEngine Number field support)
  - Service duration (with 4 format options: seconds, minutes, HH:MM, readable text)
  - Service gallery (with automatic Media Library import)
  - Service extras (with JetEngine Repeater field support)
- Automatic locked mappings for:
  - Service name → Post title
  - Service description → Post content
  - Primary photo → Featured image
  - Category → Selected taxonomy
- Image sideloading functionality to import images to WordPress Media Library
- Automatic category/term creation and assignment
- Support for Amelia action hooks:
  - `amelia_after_service_added` for creating CPT posts
  - `amelia_after_service_updated` for updating CPT posts
  - `amelia_before_service_deleted` for deleting CPT posts
- JSON-based settings storage in wp_options
- AJAX-powered settings save without page reload
- Admin notices for sync operations success/failure
- Debug logging for troubleshooting (when WP_DEBUG enabled)
- Comprehensive error handling and validation
- Modular, object-oriented architecture:
  - `Amelia_CPT_Sync_Admin_Settings` class for admin UI
  - `Amelia_CPT_Sync_CPT_Manager` class for CPT operations
  - `Amelia_CPT_Sync_Handler` class for sync logic
- Responsive admin interface
- Plugin activation/deactivation hooks
- Settings preservation across activations

### Developer Features
- Clean, documented code following WordPress coding standards
- Modular file structure for easy maintenance
- Extensible class-based architecture
- Action hooks for potential future extensions
- Security measures:
  - Nonce verification for all AJAX requests
  - Capability checks for admin operations
  - Input sanitization and output escaping
  - Direct file access prevention

### Documentation
- Comprehensive README.md with usage instructions
- Detailed INSTALLATION.md guide
- Inline code documentation
- Troubleshooting section
- FAQ section
- Best practices guide

## [Unreleased]

### Planned Features
- Manual full sync functionality (sync all existing Amelia services at once)
- Sync history log to track all synchronization operations
- Custom field type auto-detection from JetEngine
- Support for additional Amelia data fields (capacity, min/max bookings, etc.)
- Bulk actions for synced posts
- Sync preview before execution
- Export/import settings functionality
- Multi-site support
- Integration with JetEngine Booking functionality
- Custom sync filters for developers
- Webhooks for external system notifications
- Sync statistics dashboard widget

### Potential Improvements
- Performance optimization for large service catalogs
- Background processing for image imports
- Image optimization during sideload
- Selective field sync (choose which fields to sync)
- Field transformation rules (e.g., price formatting)
- Conflict resolution options
- Rollback functionality for sync operations
- WP-CLI commands for management
- REST API endpoints for external integrations

## Version History

### Version Numbering

This plugin uses semantic versioning:
- **MAJOR** version when making incompatible API changes
- **MINOR** version when adding functionality in a backwards compatible manner
- **PATCH** version when making backwards compatible bug fixes

### Support Policy

- Latest version receives active development and support
- Previous minor versions receive security updates for 6 months
- Major versions supported for 1 year after new major release

## Upgrade Notes

### Upgrading to 1.0.0
- Initial release - no upgrade necessary

## Breaking Changes

### Version 1.0.0
- None (initial release)

## Contributors

- Lead Developer: [Your Name]
- Documentation: [Your Name]
- Testing: [Your Team]

## License

This plugin is released under GPL v2 or later.

---

For more information about changes, visit the [GitHub repository](https://github.com/yourusername/amelia-cpt-sync) or contact support.

