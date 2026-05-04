# UI Standard Guidelines

This document outlines the standard guidelines for designing and developing user interfaces, ensuring consistency, accessibility, and compliance with system behaviors across platforms.

## 1. Color & Appearance (Dark Mode)
*   **System Colors:** Always prefer dynamic system colors (e.g., `label`, `systemBlue`, `separator`). These colors automatically adapt to Light Mode, Dark Mode, and increased contrast settings [1, 2].
*   **Color Contrast:** Ensure text, icons, and interactive elements maintain a minimum contrast ratio of **4.5:1** against the background (WCAG AA standard) [3-5]. For custom small text, strive for a **7:1** contrast ratio [4].
*   **Color as Meaning:** **Never rely solely on color to convey information** or indicate interactivity. Always provide alternative visual indicators, such as symbols, text labels, or asterisks (*) for mandatory form fields, to assist users with color blindness [6-8].
*   **Dark Mode Optimization:** When using custom colors, provide both light and dark variants in the asset catalog. Avoid using pure black (`#000000`) for backgrounds or fixed colors like `NSColor.black` for text; use semantic colors instead [9, 10].

## 2. Typography & Dynamic Type
*   **Standard Fonts:** Use the **San Francisco (SF)** typeface family. Specifically, use **SF UI Text** (or SF Pro Text) for sizes 19pt or smaller, and **SF UI Display** (or SF Pro Display) for sizes 20pt or larger [11, 12]. 
*   **Dynamic Type:** Do not hard-code font sizes. Use built-in text styles (like `Body`, `Headline`, `Title 1`) and allow the system to scale text dynamically using functions like `preferredFont(forTextStyle:)` [13, 14].
*   **Scalability & Accessibility:** Interfaces must remain readable and fully functional when text is scaled up to **200%** of its default size, without requiring horizontal scrolling or causing content clipping [15-17].
*   **Avoid Images of Text:** Use real text instead of embedding text inside images to ensure screen readers can access the content and it can scale properly [18, 19].

## 3. Materials & Liquid Glass
*   **Appropriate Use of Liquid Glass:** Use Liquid Glass for structural elements like navigation bars, tab bars, and sidebars. **Do not use Liquid Glass in scrollable content areas** as it creates visual clutter and reduces legibility [20-22].
*   **Avoid Stacking:** Layering multiple Liquid Glass components weakens the visual hierarchy. Apply the material directly to the control, not its inner views [21, 23].
*   **Contrast on Materials:** When placing clear Liquid Glass over bright or visually rich backgrounds, consider adding a dark dimming layer at **35% opacity** to ensure the content remains legible [24].

## 4. Buttons & Modality
*   **Tap Targets:** All buttons and interactive elements must have a minimum hit region of **44x44 pt** to ensure they are easily tappable [25].
*   **Button Shapes & Styles:** Prefer circular or capsule-shaped buttons [26]. Provide an accessible name and descriptive alternative text for image buttons [27].
*   **Alerts & Actions:** Minimize the use of alerts. Use two-button alerts for choices, placing the default/preferred action on the trailing side (right) and the "Cancel" action on the leading side (left) [28-30]. Avoid using "OK" for destructive actions; use clear verbs like "Delete" or "Erase" [30].
*   **Modality (Sheets vs. Full Screen):** Use **Sheets** for short, narrowly scoped tasks (e.g., attaching a file) to maintain context. Use **Full-screen modals** for complex tasks. Always provide an obvious "Cancel" or "Close" button to exit [31-33].

## 5. Form Input & Validation
*   **Labels & Hints:** All text fields must have visible labels that are programmatically associated with the input control. Provide input hints or instructions where formatting is required (e.g., 8-digit phone number) [27, 34].
*   **Minimize Input:** Use selection lists (like `UIPickerView`) or radio buttons instead of text entry wherever possible to reduce user effort [35, 36].
*   **Error Handling:** If an error is detected, the system must clearly identify the error and provide a specific **text-based suggestion** on how to fix it before submission [36].
*   **Error Prevention:** For legal, financial, or critical data, always provide a **Confirmation Page** allowing users to review and change their information before finalizing the submission [37, 38].
*   **Time Limits:** If a form session has a time limit, users must be warned before the time expires and provided with an option to **extend the time limit** [39, 40].

## 6. Layout & Accessibility (a11y)
*   **Meaningful Sequence:** The reading sequence of all pages must flow logically from left to right, and top to bottom [8]. Screens must also have meaningful page titles [41].
*   **Flashing Content:** Ensure no interface element or banner flashes more than **3 times per second** to prevent triggering seizures [42].
*   **Auto-Updating Content:** For auto-refreshing banners or carousels, provide a mechanism for users to pause or stop the updates [43].