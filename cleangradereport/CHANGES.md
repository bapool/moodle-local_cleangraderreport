# Changelog

All notable changes to this project will be documented in this file.

## [1.2.1] - 2025-12-10

### Fixed
- Improved table column widths to prevent premature text wrapping
  - Item name column increased from 40% to 55% width
  - Grade columns adjusted to provide better spacing and readability
  - Long item names like "Ch 3 - PSU removal and replacement (with PSU Issues)" now display on single line

## [1.2] - 2025-12-10

### Fixed
- Improved report formatting and indentation for better readability
  - Category totals now align with their parent category headers (no indentation)
  - Subcategories are now properly indented 2 spaces per level
  - Grade items within categories are indented 2 spaces per level
  - Multi-level category hierarchies now display with proper visual hierarchy

## [1.1] - 2025-12-04

### Fixed
- Fixed hard-coded language strings in JavaScript - all user-facing strings now use get_string() function for proper localization (addresses Moodle plugin submission requirement)
- Fixed grade display formatting - removed duplicate letter grades from category totals
- Added separate Course total line at bottom of report with highlighted formatting to distinguish from category totals
