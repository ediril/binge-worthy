
- Make channel name a link and different color in the overlay


- When there are no more new videos available, fetch older videos from the same channel and 
  display them with a note "No more new videos in <channel-name>. Care to watch one of these?"
  and present 3 older videos. This time no countdown!


- Find good channels to include in the catalog
    - Periodically scour reddit, X, social media, etc to find them


- "Catalog" page that lists all names, topics etc that are in the listed videos
    - Clicking them should show associated channels/videos
    - this page should be searchable


- Search feature for channels/videos
    - Main site
    - Admin page


- We need stubs for channels so we can access them without using chan-id for SEO


- Allow to create accounts:
    - To follow channels
    - mark videos watched
    - show new videos in followed channels



- Redesign:

Hello Claude Code,

I need your help to completely redesign the front-end of my website, bingeworthyshows.tv. The current UI/UX is failing, and I want to transform it into something modern, elegant, and intuitive, without breaking any of the existing backend functionality.

Your task is to act as a world-class product designer, channeling the spirit of Jonathan Ive at Apple. The goal is a complete visual and structural overhaul of the front-end (HTML, CSS, and necessary JS for UI interactions).

Core Mandate: Do Not Break Functionality
This is the most critical rule. All existing JavaScript logic for fetching data, handling user interactions (like marking a show as watched, searching, etc.), and communicating with the backend must remain intact. You are to refactor the HTML structure and completely rewrite the CSS to serve a new design, ensuring all existing event listeners and dynamic elements are correctly re-applied to the new structure.

Design Vision & Principles (Inspired by Jony Ive / Apple):
Minimalism & Clarity: The design should be clean, uncluttered, and focus entirely on the content—the TV shows. Remove any unnecessary visual elements. Every element on the page must have a clear purpose.
Modern Card Structures: This is a key request. Redesign the show listings to use extremely modern, elegant cards.
Spacing: Use generous whitespace around cards and within them.
Appearance: They should have subtle rounded corners, soft, diffused drop shadows to create a sense of depth and hierarchy (not harsh, solid shadows).
Hover Effects: Implement a subtle, delightful hover effect, such as a gentle lift (transform: translateY(-4px)) and a slightly more pronounced shadow.

A Cohesive Design System: Create a consistent design system for the entire site.

Typography: Implement a clean, legible typography hierarchy. Use a system font stack that defaults to San Francisco on Apple devices and a similar modern sans-serif font (like Roboto or Segoe UI) on other platforms. For example: font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;. Headings should be bold and well-spaced; body text should be highly readable.

Color Palette: Create a sophisticated and restrained color palette with a single, vibrant accent color for interactive elements (buttons, links, selected states).

Iconography: If there are icons, replace them with a lightweight, modern, and consistent icon set (similar to Apple's SF Symbols in style—clean, outlined, and simple).

Light & Dark Mode Support:

Light Mode: Should feel airy and light. Use a white or very light gray background (#F5F5F7) with dark gray text (#1D1D1F) for high contrast and readability.

Dark Mode: Should be truly dark and easy on the eyes, not just an inversion. Use a deep, dark gray (#1C1C1E) or near-black for the background with off-white text. The accent color should remain vibrant in both modes. Implement this using CSS variables for easy toggling.

Materiality & Depth (Glassmorphism): Where appropriate (e.g., for modal pop-ups, search bars, or header navigation), consider using a "frosted glass" or "glassmorphism" effect (using backdrop-filter: blur(...)) to create a sense of depth and layer information contextually, a hallmark of modern Apple design.

Intuitive Layout: Re-evaluate the overall layout for simplicity and intuitive navigation. The user journey to find, view details of, and track a show should be seamless and obvious.

Deliverables:
Please provide the complete, rewritten, and production-ready code for:

The main HTML file(s) with a new, cleaner semantic structure.

A single CSS file that implements the entire design system, including the card structures, typography, light/dark modes, and all other visual elements.

If necessary, any modifications to the JavaScript file(s) that are required only to connect the existing logic to the new HTML elements (e.g., updating selectors with document.querySelector).

Thank you for your help in bringing this vision to life.