---
name: nano-banana-pro-consistent-ads
title: Nano Banana Pro Image-to-Image Ad Generator with Web Color System
version: 3.2.0
description: Generates high-converting visual prompts, strictly locked products, and explicit CSS/HEX color systems for web integration.
---

# Nano Banana Pro Ad & Web Color Pipeline

## Core Instructions for Nano Banana Pro
When processing input image `{{ask_user_product_image}}`:
1. **Color Extraction & Web System Design:** 
   - Analyze the product tones and generate a complete **Web Design Color System** with exact HEX values to be used both inside the ad imagery and on the web landing page (CSS/Tailwind).
2. **Zero-Modification Rule:** Treat the uploaded product as an immutable asset. Maintain exact shapes, materials, tool alignments, colors, and branding badges.
3. **Context-Only Editing:** Only modify the surrounding environment, lighting reflections, human models, and graphic overlays.
4. **Typography Engine:** Render all text strings strictly in `{{LANGUAGE}}` with crisp vector-like edges, high contrast, and correct reading alignment. Do NOT translate to English under any circumstances. Never render the technical tag "RTL" as visible text.

---

## 1. Global Brand & Web Color Palette Output

Before generating the sections, the model must output this structured color block:

```markdown
## 🎨 Web & Brand Color System (CSS Variables)

| Role | Color Name | HEX Code | Usage on Web / Landing Page |
| :--- | :--- | :--- | :--- |
| **Primary** | Terracotta Deep | `#A45A3E` | Main Headlines, Primary CTA Buttons, Key Badges |
| **Secondary** | Muted Clay | `#D28C70` | Subheadings, Icons, Secondary Highlights |
| **Accent / Action** | Gold / Warm Amber | `#E5A842` | Star Ratings, Limited-time Offer Tags, Badges |
| **Background Light** | Warm Cream / Beige | `#F7F2EB` | Main Page Sections, Cards Background |
| **Background Dark** | Espresso / Deep Slate | `#2B1D18` | Dark Mode Sections, High-contrast Trust Banners |
| **Surface / Card** | Pure Neutral White | `#FFFFFF` | Review Cards, Feature Containers, Form Fields |
| **Text Dark** | Deep Charcoal | `#1F1A18` | Main Body Text, Paragraphs, FAQ Answers |
| **Text Light** | Off-White | `#FDFBF7` | Text over Primary / Dark CTA buttons |
```

```css
/* CSS Custom Properties for Direct-Response Landing Page */
:root {
  --color-primary: #A45A3E;
  --color-secondary: #D28C70;
  --color-accent: #E5A842;
  --color-bg-main: #F7F2EB;
  --color-surface: #FFFFFF;
  --color-text-main: #1F1A18;
  --color-text-muted: #6E625D;
}
```

---

## 2. Section Prompt Format

For each section, generate the prompt in this structure:

```markdown
### 🏷️ [Section Name] ([slug_id])
- **Language & Direction:** [Language] / [Right-aligned or Left-aligned]
- **Aspect Ratio:** [1:1 or 4:5]
- **Section Dominant Colors:** [e.g., Primary `#A45A3E` + Accent `#E5A842`]

**Nano Banana Pro Prompt:**
```text
[IMAGE-TO-IMAGE REFERENCE LOCK]
Keep the exact product from the uploaded input image completely unaltered: maintain the exact geometry, rounded-square matte terracotta case, silicone handle, golden square "MANICURE" logo, and the specific dual-tone tool layout. Do NOT redesign or replace the case with generic leather pouches.

[SCENE COMPOSITION & COLOR HARMONY]
Place this exact product seamlessly onto a [describe luxury/clean surface, e.g., beige travertine stone `#F7F2EB` with soft morning shadows and subtle spa elements]. Ensure overall lighting and reflections harmonize with the palette (#A45A3E, #E5A842).

[IN-IMAGE TYPOGRAPHY - {{LANGUAGE}} ONLY - DO NOT TRANSLATE]
Render all visible text overlays strictly in {{LANGUAGE}} using clear modern typography ([right-aligned layout / left-aligned layout]):
- Top Trust Badges (Accent `#E5A842`): "[Badge 1]" | "[Badge 2]"
- Main Headline (Bold, Primary `#A45A3E`): "[Localized Headline]"
- Features List: "• [Point 1] • [Point 2] • [Point 3]"
- Offer / Price Tag (Highlight Box): "[Localized Price Box]"

[OUTPUT QUALITY]
Flawless photorealistic advertising poster, commercial catalog photography, sharp vector-like text rendering, 8k resolution.
```
```

---

## 3. Sections Breakdown

1. **Hero Offer (`hero_offer`)**: Complete open + closed case lock on a premium backdrop + localized trust bar & 4 bullet points.
2. **Before / After (`before_after`)**: Split layout with identical locked product styling and localized problem/solution labels.
3. **Authority / Social Validation (`authority_social_validation`)**: Locked product in focus foreground with soft background salon/expert + star rating badge.
4. **Tools Breakdown (`ingredients_mechanism`)**: Exploded / organized view of the exact tools from the case with pointing arrows and localized labels.
5. **Customer Reviews (`customer_reviews`)**: Local target audience user holding the exact reference kit + review card overlay.
6. **FAQ Section (`faq_section`)**: Minimal clean background with closed reference case + 3 localized Q&A blocks.
7. **Social Feed Creative (`social_ad_creative`)**: Dynamic feed ad (4:5) featuring hand using the exact clipper from the kit + promo badge.