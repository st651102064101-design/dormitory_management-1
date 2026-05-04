UI Standard Guidelines
This document outlines the standard guidelines for designing and developing user interfaces, ensuring they are accessible, consistent, and compliant with best practices.
1. Color, Dark Mode & Materials
System Colors: Use system colors (e.g., label, secondaryLabel, systemBlue) so the UI automatically adapts to Dark Mode, vibrancy, and accessibility settings
.
Color Contrast: Text and icons must maintain a minimum color contrast ratio of 4.5:1 against their background
.
Color as Meaning: Do not rely solely on color to convey information or instructions. For example, use an asterisk (*) along with color to indicate mandatory form fields
.
Vibrancy and Materials: When using visual effects like Liquid Glass or Materials to create a sense of depth, avoid using quaternary vibrancy on thin and ultraThin materials as the contrast will be too low
.
2. Typography
Standard Fonts: Use the San Francisco (SF) typeface. Specifically, use SF UI Text for text sizes 19pt or smaller, and SF UI Display for text sizes 20pt or larger
.
Default Sizes: The recommended large default sizes are 17pt for Body and Headline, and 28pt for Title 1
.
Dynamic Type Scaling: Do not hard-code font sizes. Use preferredFont(forTextStyle:) or scaledFont(for:) to allow text to scale according to the user's system preferences
.
Text Resizing: The UI must remain readable and functional when text is scaled up to 200% of its initial size
.
Avoid Images of Text: Use real text instead of images of text so that the content is fully accessible and scalable
.
3. UI Components & Interaction
Alerts: Use alerts sparingly. They should only be used to deliver critical information, warn users about destructive actions, or confirm important purchases
.
Sheets & Modality: Use Sheets to help users perform narrowly scoped tasks (e.g., attaching a file or choosing a location) without losing their previous context. Modality prevents interaction with the parent view until the task is explicitly dismissed or completed
.
Buttons & Gestures: Ensure all buttons have a sufficient tap target size. Use simple gestures (e.g., a simple tap) for primary interactions
. For iOS buttons, utilize configurations like UIButton.Configuration.CornerStyle to maintain system-standard corner radii
.
4. Form Input & Validation
Labels: All text fields and form controls must be programmatically associated with visible labels
.
Error Handling: If an input error is detected, the system must clearly identify the error and provide a text-based error suggestion to help the user fix it
.
Error Prevention: Always provide a Confirmation Page that allows users to review, confirm, or modify their information before finalizing a submission
.
Adjustable Time Limits: If a form or a task has a time limit, users must be provided with a warning and a function to extend the time limit
.
5. Accessibility (a11y)
Alternative Text: All non-text content (like images and image buttons) must have descriptive alternative text. For example, set isAccessibilityElement = YES and provide an accessibilityLabel in iOS, or use setContentDescription in Android
.
Meaningful Sequence: The logical reading order should flow from left to right, and top to bottom. If necessary, manually adjust the accessibility traversal order (e.g., using AccessibilityTraversalAfter in Android or ordering accessibilityElements in iOS)
.
Flashing Content: To prevent seizures, ensure that UI elements (such as banners) flash fewer than 3 times per second
.
Auto-updating Content: For lists or banners with user-initiated auto-updating, you must provide a setting to let users easily stop or pause the updates