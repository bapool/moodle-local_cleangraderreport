# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Fixed
- Fixed hard-coded language strings in JavaScript - all user-facing strings now use get_string() function for proper localization (addresses Moodle plugin submission requirement)
- Fixed grade display formatting - removed duplicate letter grades from category totals
- Added separate Course total line at bottom of report with highlighted formatting to distinguish from category totals
