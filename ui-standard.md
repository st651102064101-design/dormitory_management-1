UI Standard Guidelines
This ui-standard.md file compiles the design and development standards for User Interfaces, heavily referencing digital accessibility principles and system guidelines for various operating systems.
1. Color & Dark Mode
System Colors: You should use system colors to ensure the UI feels at home on the device and automatically adapts to Dark Mode, vibrancy settings, and accessibility preferences
.
Color Contrast: Text and icons must have a color contrast ratio of at least 4.5:1 against the background to ensure readability
.
Color as Meaning: Do not rely solely on color to convey information or instructions. For example, use an asterisk (*) alongside color to indicate mandatory form fields
.
Color Conversion: You can utilize extensions to easily convert HEX strings to UIColor and Color for use within UIKit and SwiftUI projects
.
2. Typography
Standard Fonts: It is recommended to use the San Francisco (SF) typeface. Use SF UI Text for text sizes of 19pt or smaller, and SF UI Display for text sizes of 20pt or larger
.
Large Default Sizes: The recommended default sizes are 17pt for Body and Headline, 15pt for Subhead, 28pt for Title 1, and 22pt for Title 2
.
Dynamic Type Scaling: Avoid hard-coding font values. Instead, use preferredFont(forTextStyle:) or scaledFont(for:) with UIFont.TextStyle constants so that the text can automatically scale based on the user's accessibility settings
.
Avoid Images of Text: Do not use images containing text (e.g., text embedded in an image button); use real text to ensure screen readers can access it
.
Text Resizing: Users must be able to resize or zoom text (up to 200%) without losing functionality or clipping the content
.
3. UI Components & Materials
Liquid Glass & Vibrancy: You can implement Liquid Glass effects for visually rich elements
. When using materials, be mindful of visual hierarchy and background blur
.
Buttons:
Ensure all buttons and interactive elements have a sufficiently large tap target area
.
For corner styles, you can use UIButton.Configuration.CornerStyle values (large, medium, or small). This configuration ignores the background's corner radius and applies the system-defined corner radius instead
.
Sheets: Use Sheets to help users perform scoped tasks or input specific information (e.g., attaching a file) before returning them to the main parent view
.
4. Form Input & Validation
Form Labels: All text fields and controls must be programmatically associated with visible labels so that screen readers can accurately interpret the required input
.
Minimize Input: Where possible, replace text typing with selection lists (like UIPickerView) to reduce the user's input burden
.
Error Handling (Validation): If an error is detected, the system must clearly identify the error and provide a text-based error suggestion to help the user fix it before submission
.
Error Prevention: Always provide a Confirmation page that allows users to review, confirm, or change their data before finalizing the submission to prevent critical errors
.
5. Layout & Accessibility (a11y)
Alternative Text (Alt Text): All non-text content, such as image buttons, must have alternative text. For iOS, you can achieve this by setting isAccessibilityElement = YES and assigning a clear accessibilityLabel
.
Reading Sequence: The logical reading order should typically flow from left to right and top to bottom. You must also provide a clear, meaningful page title so screen reader users immediately understand the page's purpose
.
Flashing Content: To prevent seizures, ensure that no UI element (such as banners) flashes more than 3 times per second
.
Adjustable Time Limits: If a form or task has a time limit, you must provide a warning and allow the user to extend the time limit
.
Auto-updating Content: For lists or banners with user-initiated auto-updating, provide settings that allow the user to easily stop or pause the updates
.