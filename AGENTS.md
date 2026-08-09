# Overview
This document records the key architectural and design decisions made by the AI agent during development.

# Technology Stack
- **Backend**: PHP 8.3
- **CSS Framework**: Bootstrapped CSS 5.3
- **Database**: sqlite

## Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge, DuckDuckGo)
- ES6+ support required

# Key Principles
- Always create the smallest possible diff to implement a feature.
- Implement exactly what is requested. Do not do additional work. If there is ambiguity, 
first ask for guidance and confirmation.
- Don't keep backwards compatibility when making changes.
- Always test your changes, make sure they work as expected and did not break any previous 
- **Minimal Changes**: When implementing features, make the smallest possible change that achieves the desired outcome
- **Code Deletion**: Actively look for opportunities to delete or streamline existing code during feature implementation
- **Refactoring**: Prefer extracting common patterns into reusable components to reduce duplication
- **Less is Better**: Favor simplicity over complexity - remove unused exports, parameters, and code paths
- **DRY Principle**: Eliminate code duplication through component extraction and parameterization

# Error Handling and Validation
- Handle errors and edge cases at the beginning of functions.
- Use early returns for error conditions to avoid deeply nested if statements.
- Place the happy path last in the function for improved readability.
- Avoid unnecessary else statements; use the if-return pattern instead.
- Use guard clauses to handle preconditions and invalid states early.
- Implement proper error logging and user-friendly error messages.

# General Coding Guidelines
- Regarding imports, group standard library, third-party, and local imports with a blank line between groups.
- Don't keep backwards compatibility unless explicitly told to do so.
- Always test your changes, make sure they work as expected and did not break any previous functionality.

# JavaScript
- Use ES6 features, camelCase for variables and functions.
- Keep all JavaScript in a single file.

# PHP
- Put functions that are used in multiple places into `common.php`.

# CSS Coding Guidelines
- Keep all CSS in a single file.
- Follow BEM (Block Element Modifier) naming convention for CSS.
- Make sure CSS stays tight; remove duplications and redundant styling.

# Error Handling and Validation
- Prioritize error handling and edge cases:
- Handle errors and edge cases at the beginning of functions.
- Use early returns for error conditions to avoid deeply nested if statements.
- Place the happy path last in the function for improved readability.
- Avoid unnecessary else statements; use the if-return pattern instead.
- Use guard clauses to handle preconditions and invalid states early.
- Implement proper error logging and user-friendly error messages.
- Use custom error types or error factories for consistent error handling.

# Key Principles
- Implement exactly what is asked. If there is ambiguity in the request and you'll need to implement something in addition, first ask to confirm.
- Prefer iteration and modularization over code duplication.
- Use descriptive variable names with auxiliary verbs (e.g., is_active, has_permission).
- Use lowercase with underscores for directories and files (e.g., blueprints/user_routes.py).
- Favor named exports for routes and utility functions.
- Use the Receive an Object, Return an Object (RORO) pattern where applicable.


# MEMORY.md

This section tracks high-level decisions, changes, and fixes made to the project.

## Notes
- Keep this section updated with significant architectural decisions
- Include rationale for major changes
- Track performance optimizations and their impact
- Document any breaking changes or migration steps 
- 2026-08-09: The application is hosted at `https://emrahdiril.com/bingeworthy/`. All application URLs must use `APP_BASE_PATH`/`app_url()` in PHP or `window.appUrl()` in JavaScript so links, assets, API calls, and redirects remain inside the subdirectory.

## Change Management Guidelines

### Holistic Changes
- When making changes, consider and implement all necessary side-effects at once
- Think through the entire system impact before making changes
- Examples of side-effects to consider:
  - Build configuration updates
  - Path references in multiple files
  - Development environment setup

### Anti-patterns
- Making a change, then dealing with its fallout one at a time and debugging related issues sequentially
- Not considering the full system impact of a change
- Requiring multiple iterations to fix cascading issues
