# Design System Specification: High-End Editorial Glassmorphism

## 1. Overview & Creative North Star: "The Organic Glass House"
This design system moves away from the sterile, rigid grids of traditional SaaS and toward a "Digital Editorial" experience. The Creative North Star is **The Organic Glass House**: a space where high-tech glassmorphism meets the grounded, tactile warmth of earth-toned materials. 

We achieve a premium aesthetic by breaking the "template" look. This is done through intentional asymmetry, exaggerated corner radii (`24px`), and a rejection of structural lines in favor of tonal depth. The goal is to make the user feel like they are interacting with high-end physical stationery floating over a softly lit, organic environment.

---

## 2. Colors & Surface Philosophy

The palette is rooted in `Deep Earthy Brown` and `Warm Sand`, creating a sophisticated, high-contrast environment that feels authoritative yet approachable.

### The "No-Line" Rule
Traditional 1px solid borders are strictly prohibited for sectioning. Use the **Surface Hierarchy** to define boundaries. A section is "defined" when a `surface-container-low` block sits on a `surface` background. The change in tone is the boundary.

### Surface Hierarchy & Nesting
Treat the UI as a series of physical layers. Use the following tiers to create "nested" depth:
- **Base Layer:** `surface` (#141312)
- **Secondary Sectioning:** `surface-container-low` (#1C1B1A)
- **Active UI Elements/Cards:** `surface-container` (#201F1E)
- **Floating Overlays:** `surface-container-highest` (#363433)

### The "Glass & Gradient" Rule
To achieve the signature "SaaS Premium" look, all primary interactive containers should utilize:
- **Backdrop Blur:** 12px to 20px.
- **Fill:** `surface-variant` at 60% opacity.
- **Stroke:** A "Ghost Border" using `outline-variant` at 20% opacity (0.5px thickness).

### Signature Textures
Avoid flat primary colors for large CTAs. Instead, use a **Linear Gradient**:
- **Direction:** 135deg
- **From:** `primary` (#E7BDB1)
- **To:** `on-primary-container` (#D4ADA1)

---

## 3. Typography: Editorial Authority

We use a duo-font system to balance readability with high-end brand personality.

| Level | Font Family | Size | Character |
| :--- | :--- | :--- | :--- |
| **Display** | Plus Jakarta Sans | 2.25rem - 3.5rem | Bold, expressive, intentional |
| **Headline** | Plus Jakarta Sans | 1.5rem - 2rem | Authoritative editorial tone |
| **Title** | Inter | 1rem - 1.375rem | Clear, functional hierarchy |
| **Body** | Inter | 0.875rem - 1rem | High legibility, 1.6 line-height |
| **Label** | Inter | 0.6875rem - 0.75rem | All-caps or medium weight for metadata |

**Strategy:** Use `Plus Jakarta Sans` for headers to inject a "boutique" feel. Use `Inter` for all functional UI to ensure the tool feels like a high-performance instrument.

---

## 4. Elevation & Depth

### The Layering Principle
Depth is achieved by "stacking" tones. For example:
1. **Background:** `surface`
2. **Content Area:** `surface-container-low` (24px radius)
3. **Card:** `surface-container` (16px radius, nested inside)

### Ambient Shadows
When an element must float (Modals, Popovers), use a **Triple-Layered Shadow**:
- Layer 1: 0px 4px 12px rgba(0,0,0, 0.04)
- Layer 2: 0px 16px 32px rgba(0,0,0, 0.08)
- Layer 3: 0px 32px 64px rgba(0,0,0, 0.12)
*The shadow color should be a tinted version of the background, never pure black.*

### Ghost Borders
For accessibility, if a container needs a stroke, use `outline-variant` (#504441) at **15% opacity**. This creates a "shimmer" effect on the edge of the glass rather than a hard containment line.

---

## 5. Components

### Buttons
- **Primary:** Gradient fill (`primary` to `on-primary-container`), `on-primary` text, 24px radius.
- **Secondary (Glass):** `surface-variant` at 40% opacity, 12px backdrop-blur, 0.5px `outline-variant` border.
- **Tertiary (Ghost):** No background. `primary` text. Underline only on hover.

### Input Fields
Inputs should feel "sunken" or "etched."
- **Background:** `surface-container-lowest`
- **Border:** 0.5px `outline` at 20% opacity.
- **Focus:** Border opacity increases to 100%, 4px soft outer glow using `primary` at 10% opacity.

### Cards & Lists
**Forbid the use of divider lines.** 
To separate list items, use a `3.5rem (10)` vertical gap or a subtle background shift on hover (`surface-container-high`). For cards, use the `24px (xl)` corner radius to create distinct "objects" in the layout.

### Floating Navigation (Signature Component)
A bottom-centered navigation dock using:
- **Fill:** `surface-container-highest` (70% opacity)
- **Blur:** 20px
- **Border:** 0.5px `outline-variant`
- **Radius:** `full` (9999px)

---

## 6. Do's and Don'ts

### Do:
- **Use Asymmetry:** Allow some elements to bleed off the grid or align to unique anchors to create an editorial "magazine" feel.
- **Embrace White Space:** Use the `16 (5.5rem)` spacing token between major sections to let the glass elements "breathe."
- **Layer Glass:** Place glass components over subtle organic shapes or gradients to showcase the `backdrop-blur` effect.

### Don't:
- **Don't use 1px solid borders:** It breaks the high-end glass illusion.
- **Don't use pure black shadows:** It makes the "Organic" brown tones feel muddy. Always tint shadows with the primary or surface color.
- **Don't crowd the corners:** With a `24px` radius, ensure internal padding is at least `32px` to prevent content from being clipped visually by the curve.