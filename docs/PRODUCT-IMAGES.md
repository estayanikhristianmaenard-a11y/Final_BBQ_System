# Product images

The six menu images are AI-generated, realistic 3D-style renders based on the owner's supplied BBQ reference. They are illustrative catalog images, not photographs of the shop's actual servings or interactive 3D models. Generated with the built-in imagegen tool (no CLI/API fallback).

## Saved assets

All final assets live in `../bulkbites-bbq/assets/images/products/`:

- `bbq-render.webp`
- `isaw-manok-render.webp`
- `bituka-baboy-render.webp`
- `tenga-baboy-render.webp`
- `balunan-render.webp`
- `hotdog-render.webp`

Images use 640 × 640 WebP at quality 85, about 35–48 KB each (about 253 KiB combined). Resizing and encoding use PHP GD only; no compositional edits. Original generated PNGs remain in Codex's generated-images directory. Existing SVGs remain available as legacy assets. Database image paths are shared by the menu, cart, checkout, and manager product previews. Existing custom uploads were preserved. `database/current.sql` and seed `database/schema.sql` include the new paths.

To replace a render with an actual photo, use Manager → Products → Edit → Image. Upload a JPG, PNG, WebP, or GIF up to 2 MB.

## Final prompts

Each asset was generated with the attached customer image as subject reference, using the following common prompt followed by its subject specification.

Use case: product-mockup. Create ONE standalone square catalog asset for a Filipino BBQ ordering website, a realistic 3D-style food render with natural food textures. Attached image is SUBJECT REFERENCE ONLY for the grilled food shape, red-orange glaze and char. Show exactly one bamboo skewer centered diagonally from lower left to upper right, fully visible with generous 12% margins, food filling most of frame. Warm off-white (#FBFBF8) seamless background, soft studio light and subtle contact shadow, appetizing glossy caramelized glaze with authentic blackened grill patches. No plate, garnish, flames, typography, prices, labels, watermark, collage or other food varieties. Subject: 

### bbq

Pork barbecue: irregular thin folded slices of pork threaded tightly along a bamboo stick, reddish mahogany glaze, visible meat fibers and small rendered fat edges; reference leftmost skewer.

### isaw-manok

Isaw manok: slender chicken intestine threaded into many closely packed small U-shaped loops along a bamboo stick, distinctly coiled ruffled silhouette, glossy burnt-orange glaze; reference second skewer.

### bituka-baboy

Bituka ng baboy: thicker pork intestine folded into chunky short irregular tubular pieces threaded along a bamboo stick, reddish brown glaze, plump lobed folds; reference third skewer.

### tenga-baboy

Tenga ng baboy: broad irregular folded pieces of grilled pork ear with thin golden yellow cartilage edges and orange brown skin, layered on a bamboo stick, distinctly broad flat angular folds; reference fourth skewer.

### balunan

Balunan: chicken gizzard skewer, four compact oval muscular lobed pieces, deeply browned glossy red-orange exterior and charred edges; reference fifth skewer.

### hotdog

One Filipino bright red hotdog on a bamboo skewer, elongated smooth red sausage with a few shallow diagonal grill cuts and subtle char marks; reference rightmost skewer. No other sausage or food.
## Interactive grill banner

Saved asset: `../bulkbites-bbq/assets/images/grill-render.webp` (640 × 640, transparent WebP, quality 85; 84,746 bytes). Generated with the built-in imagegen tool, then resized and encoded with PHP GD, preserving alpha. The original generated PNG stays in Codex's generated-images directory. No image references were supplied for this separate, new render.

The menu now renders a real, stylized **3D scene** with modeled grill, skewers, brush, coals and calamansi using locally hosted Three.js. The earlier raster-and-SVG overlay is superseded. This WebP remains the no-JavaScript/WebGL fallback and the Preparing status image. The 3D banner supports drag/arrow-key rotation and a 3.2-second basting pass with surface gloss. Reduced-motion visitors start still and can choose Play. See [UI review](UI-REVIEW.md) for validation and the latest moving preview.

Final prompt:

Use case: product-mockup. Create one premium realistic 3D-style render for a Filipino BBQ ordering website hero, isolated on a genuinely transparent background. Subject: a small low rectangular charcoal tabletop grill seen at a three-quarter elevated isometric angle, with FOUR bamboo skewers arranged parallel across its metal grate, three skewers with irregular grilled pork pieces in glossy red-orange caramelized marinade and authentic char patches, one skewer of coiled chicken isaw. Visible bamboo handles extend over front edge. A small green calamansi half with pale yellow-green radial flesh rests safely on a little metal corner ledge next to one whole calamansi. Compact matte charcoal grill body, warm ember-orange coals subtly visible under grate, realistic appetizing food textures and lighting, soft dimensional highlights. Entire grill and all skewers fully visible, centered with generous margins, roughly landscape silhouette in a square canvas. Color palette charcoal #242824, warm ember #BC4015, golden bamboo #F4AC3D, natural green citrus. Transparent background with no floor, no plate, no labels, no words, no logo, no hands, no people, no smoke, no cartoon styling, no exaggerated flames. Single cohesive object, not a collage. This is illustrative food imagery, not actual store product photography.
