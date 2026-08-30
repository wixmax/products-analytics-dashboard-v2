---
name: gemini-facebook-product-ads
title: Gemini Facebook Product Ads Skill
version: 1.0.0
description: إنشاء حزمة إعلانية كاملة لمنتجات التجارة الإلكترونية باستعمال Gemini Omni Flash، مناسبة لإعلانات Facebook وInstagram في المغرب.
---

# Gemini Facebook Product Ads Skill

## الهدف

إنشاء حزمة إعلانية كاملة لمنتجات التجارة الإلكترونية باستعمال Gemini Omni Flash،
مناسبة لإعلانات Facebook وInstagram في المغرب.

يجب أن ينتج الـSkill:

1. برومبت لتوليد شخصية UGC ثابتة.
2. Character Bible لتثبيت الشخصية عبر جميع المشاهد.
3. ثلاث صور إعلانية مستقلة تعمل كـFrames افتتاحية.
4. ثلاث برومبتات فيديو مستقلة، مدة كل واحد 10 ثوانٍ بالضبط.
5. فيديوهات مترابطة بإجمالي 30 ثانية.
6. تعليقًا صوتيًا مدمجًا داخل كل Video Prompt.
7. تقسيمًا زمنيًا دقيقًا لكل لقطة.
8. تعليقًا بالدارجة المغربية.
9. برومبتات إنجليزية لتوليد الصور والفيديو.
10. قواعد تمنع تغيير المنتج أو الشخصية.
11. قواعد تسمح بإخفاء المنتج في مرحلة المشكلة.

---

## قواعد عامة

### لغة المخرجات

- برومبتات الصور والفيديو: الإنجليزية.
- النص المنطوق: الدارجة المغربية بحروف عربية.
- شرح النتائج للمستخدم: العربية.
- لا تطلب من Gemini كتابة نص عربي داخل الصورة أو الفيديو.
- يمكن إضافة النصوص التسويقية لاحقًا في CapCut أو Meta Ads.

### المنصة والجمهور

الافتراضي:

- المنصة: Facebook Ads وInstagram Reels.
- البلد: المغرب.
- الجهاز: الهاتف.
- أسلوب الإعلان: UGC واقعي، lifestyle، أو problem-solution.
- الدفع: الدفع عند الاستلام فقط إذا أكده المستخدم.
- التوصيل: لا يُذكر إلا إذا كانت تفاصيله مؤكدة.

### المصداقية

ممنوع اختراع:

- مواصفات تقنية غير مؤكدة.
- فوائد طبية أو علاجية.
- نتائج مضمونة.
- سعر أو تخفيض غير مُعطى.
- توصيل مجاني غير مؤكد.
- COD غير مؤكد.
- ندرة أو عرض محدود غير حقيقي.
- استخدامات لا يدعمها المنتج.

إذا لم تكن الفائدة مؤكدة، استعمل لغة آمنة مثل:

- “حل عملي للاستعمال اليومي”
- “مناسب للاستعمال القريب”
- “كيعاونك على الراحة”
- “خفيف وسهل يتنقل”
- “شوف التفاصيل قبل تأكيد الطلب”

---

## أنواع المشاهد

كل مشهد يجب أن يملك خاصية اسمها:

```yaml
product_visibility:
  hidden | hinted | visible | hero
```

### hidden

المنتج ممنوع من الظهور.

يُستخدم عندما نريد إظهار المشكلة فقط قبل كشف الحل.

مثال:
- شخص يعاني من الحرارة.
- شخص يواجه فوضى في المنزل.
- امرأة تتعب من ترتيب الشعر.
- سائق منزعج من الشمس داخل السيارة.

في وضع `hidden`:

- لا تُرفق صورة المنتج مع Prompt الصورة أو الفيديو.
- لا تذكر اسم المنتج بصريًا.
- لا يظهر المنتج في الخلفية أو اليد أو انعكاس المرآة.
- لا يظهر جزء من المنتج.
- يمكن فقط ذكر المشكلة في النص المنطوق.
- يجب أن يركز المشهد على الشخصية والمشكلة.

### hinted

لا يظهر المنتج نفسه، لكن يمكن التلميح إلى وجود حل.

مثال:
- الشخصية تنظر خارج الكادر.
- الشخصية تفتح درجًا أو حقيبة دون أن يظهر المنتج.
- لقطة يد تقترب من مكان سيظهر فيه المنتج لاحقًا.

في وضع `hinted`:

- لا تُرفق صورة المنتج.
- لا يظهر المنتج أو جزء منه.
- لا تصف تصميم المنتج.
- لا تكشف الحل بوضوح.
- اجعل نهاية اللقطة مناسبة لانتقال طبيعي نحو ظهوره.

### visible

المنتج يظهر بوضوح، لكنه ليس محور الصورة بالكامل.

في وضع `visible`:

- أرفق صورة المنتج المرجعية.
- طبّق Product Identity Lock.
- اجعل المنتج ظاهرًا أثناء الاستخدام أو في المكان الطبيعي.
- لا تغيّر مواصفاته أو شكله.
- لا تضف ملحقات أو أزرار أو تفاصيل غير موجودة في المرجع.

### hero

المنتج هو محور اللقطة.

في وضع `hero`:

- أرفق صورة المنتج المرجعية.
- طبّق Product Identity Lock بحزم.
- المنتج واضح، كبير نسبيًا، ومقروء بصريًا.
- يمكن استعمال لقطة قريبة أو دوران بسيط أو لقطة استعمال.
- لا تحول المنتج إلى نسخة تسويقية مختلفة عن المرجع.

---

## Product Reference Logic

### قاعدة مهمة

لا تُرفق صورة المنتج تلقائيًا في كل Prompt.

أرفقها فقط إذا كانت قيمة `product_visibility` واحدة من:

```text
visible
hero
```

لا تُرفقها إذا كانت القيمة:

```text
hidden
hinted
```

### Product Identity Lock

عندما يظهر المنتج، يجب إدراج النص التالي في البرومبت:

```text
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact and immutable product
identity. The product shown in this scene must remain visually identical to the
attached reference image.

Preserve its exact shape, proportions, scale, colors, materials, texture,
logo, labels, packaging, buttons, openings, accessories, printed details,
and visible construction.

Do not redesign, replace, recolor, resize, simplify, stylize, enhance, deform,
invent, add, remove, or substitute any part of the product.
```

### Product Identity Absence Requirement

عندما لا يظهر المنتج، يجب إدراج هذا النص:

```text
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, any part of the product, its packaging, logo, label,
reflection, silhouette, shadow, or a similar substitute anywhere in this scene.
The scene must focus exclusively on the customer's problem or daily situation.
```

---

## Character System

### الهدف

قبل إنشاء الإعلان، أنشئ شخصية إعلانية ثابتة واحدة إذا كان الإعلان UGC أو
lifestyle ويحتاج شخصًا ظاهرًا.

سمِّ الصورة الناتجة:

```text
Character Reference Image
```

أرفق هذه الصورة في كل Frame Prompt وكل Video Prompt يظهر فيه نفس الشخص،
سواء كان المنتج ظاهرًا أو مخفيًا.

### Character Image Prompt

```text
Create a realistic character reference portrait for a Moroccan UGC Facebook
advertising presenter.

CHARACTER:
[Gender], Moroccan, [age range], [skin tone], [face shape], [hair color],
[hair length], [hair texture], [hair style], [eye shape], [body type].

WARDROBE:
[Exact clothing colors, fabrics, accessories, makeup or facial-hair details.]

PERSONALITY:
Friendly, expressive, authentic, trustworthy, and naturally conversational.
The person should feel like a real Moroccan social-media creator sharing a
useful tip with close friends, not a fashion model or corporate actor.

POSE:
Upper-body portrait, eye-level smartphone camera, looking toward the camera,
natural relaxed posture, hands visible and away from the face.

SETTING:
Simple realistic Moroccan home interior, bedroom, living room, kitchen, desk,
or office depending on the product category. Natural daylight and realistic
skin texture.

CONSISTENCY REQUIREMENT:
This image will be the permanent identity reference for the same on-screen
presenter across multiple frames and video scenes. Make the face, hairstyle,
outfit, accessories, body type, and overall look distinctive and consistent.

No product, no product packaging, no product logo, no text, no caption,
no watermark, no extra person, no distorted hands, no beauty-filter skin,
no fashion photoshoot styling.
```

### Character Identity Lock

أضف هذا النص في كل مشهد توجد فيه الشخصية:

```text
CHARACTER IDENTITY LOCK:
Use the attached character reference image as the exact permanent identity of
the presenter. Keep the same person in every scene.

Preserve the same face shape, skin tone, age range, hair color, hair length,
hair style, eye shape, eyebrows, body type, outfit, accessories, makeup or
facial hair, and overall appearance.

Do not replace the person. Do not change gender, ethnicity, age, hairstyle,
wardrobe, accessories, face, or body proportions. Do not add another presenter
unless explicitly requested.
```

### Character Bible

بعد توليد الشخصية، أنشئ Character Bible ثابتًا ويُنسخ حرفيًا في جميع البرومبتات:

```yaml
character_id: ugc-morocco-01
gender:
age_range:
nationality: Moroccan
skin_tone:
face_shape:
eyes:
eyebrows:
hair_color:
hair_length:
hair_texture:
hair_style:
body_type:
outfit:
accessories:
makeup_or_facial_hair:
personality:
voice:
shooting_style:
forbidden_changes:
```

---

## هيكل الحملة

أنشئ دائمًا 3 فيديوهات مترابطة:

| الفيديو | المدة | الدور الأساسي | ظهور المنتج الافتراضي |
|---|---:|---|---|
| Video 1 | 0–10 ثوانٍ | Hook والمشكلة | hidden أو hinted |
| Video 2 | 10–20 ثانية | كشف المنتج والاستعمال | visible أو hero |
| Video 3 | 20–30 ثانية | النتيجة، العرض، وCTA | visible أو hero |

### Video 1: المشكلة

الهدف:

- جذب الانتباه في أول ثانيتين.
- عرض مشكلة يومية يفهمها الجمهور.
- عدم كشف المنتج إذا كان الغموض يخدم الإبداع.
- إنهاء المشهد بلحظة انتقال منطقية نحو الحل.

القاعدة الافتراضية:

```yaml
product_visibility: hidden
```

لا يظهر المنتج في Video 1 إلا إذا قرر الـSkill أن المنتج نفسه هو أفضل Hook.

### Video 2: كشف الحل

الهدف:

- الكشف الطبيعي عن المنتج.
- عرضه الحقيقي بوضوح.
- إظهار استخدام واقعي وآمن.
- شرح فائدة واحدة أو اثنتين فقط.
- الحفاظ على شكل المنتج المرجعي.

القاعدة الافتراضية:

```yaml
product_visibility: visible
```

### Video 3: النتيجة وCTA

الهدف:

- تأكيد الفائدة بصريًا.
- إظهار المنتج بوضوح.
- ذكر السعر فقط عند توفره.
- ذكر الدفع عند الاستلام فقط إذا كان مؤكدًا.
- إنهاء الإعلان بـCTA قصير.

القاعدة الافتراضية:

```yaml
product_visibility: hero
```

---

## قواعد الـFrames

أنشئ Frame Image Prompt منفصلًا لكل فيديو.

### Frame 1

إذا كان Video 1 بوضع `hidden` أو `hinted`:

- أرفق فقط Character Reference Image.
- لا ترفق صورة المنتج.
- أضف Product Absence Requirement.
- ركز على تعبير الشخصية والمشكلة.

إذا كان Video 1 بوضع `visible` أو `hero`:

- أرفق Product Reference Image + Character Reference Image.
- أضف Product Identity Lock.

### Frame 2

- أرفق Product Reference Image + Character Reference Image.
- استخدم `visible` أو `hero`.
- يجب أن يكون الـFrame نقطة بداية منطقية بعد نهاية Video 1.

### Frame 3

- أرفق Product Reference Image + Character Reference Image.
- استخدم `hero` غالبًا.
- اجعل المنتج واضحًا والشخصية مرتاحة أو راضية.
- حضّر المشهد لذكر العرض وCTA بالصوت.

---

## قالب Frame Prompt

```text
Create a realistic opening frame for a 10-second Facebook ad video targeting
a Moroccan mobile audience.

SCENE MODE:
product_visibility: [hidden | hinted | visible | hero]

CHARACTER:
Use the attached character reference image as the exact identity of the
presenter. Keep the same face, skin tone, hairstyle, outfit, accessories,
body type, and appearance.

CHARACTER BIBLE:
[Paste the Character Bible exactly.]

[IF product_visibility is hidden OR hinted]
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, its packaging, logo, label, reflection, silhouette,
shadow, or any similar substitute anywhere in the scene.

[IF product_visibility is visible OR hero]
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact product identity.
Preserve the exact shape, colors, proportions, materials, labels, packaging,
and all visible product details. Do not alter, replace, or redesign it.

SCENE:
[Describe the person, exact action, location, mood, object placement, and
what must be visible at the first frame.]

CAMERA:
[Smartphone UGC framing, camera angle, distance, lens feeling, composition.]

LIGHTING:
[Realistic daylight or indoor lighting, natural shadows.]

STYLE:
Authentic Moroccan UGC advertising, believable home environment, realistic
phone-camera image, natural skin texture, not overly polished, mobile-first.

No text, no subtitles, no captions, no watermark, no fake Arabic writing,
no extra presenter, no distorted hands, no impossible object physics.
```

---

## قالب Video Prompt

اكتب كل فيديو في Prompt واحد كامل، بالإنجليزية، مع النص المنطوق بالدارجة داخل
كل لقطة.

```text
Create exactly one 10-second realistic vertical UGC product advertising video
for Facebook Ads in Morocco.

SCENE MODE:
product_visibility: [hidden | hinted | visible | hero]

REFERENCE IMAGES:
[Describe only the images that must be attached.]
- Character reference image: attached.
- Product reference image: attached only if product_visibility is visible or hero.
- Opening-frame reference image: attached when available.

CHARACTER IDENTITY LOCK:
Use the attached character reference image as the same permanent presenter.
Preserve the same face, skin tone, age range, hairstyle, outfit, accessories,
body type, and mannerisms exactly throughout the video.

CHARACTER BIBLE:
[Paste the Character Bible exactly.]

[IF product_visibility is hidden OR hinted]
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, any part of it, its packaging, logo, label,
reflection, silhouette, shadow, or a similar substitute anywhere in this video.

[IF product_visibility is visible OR hero]
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact and immutable product.
Preserve its exact shape, size, colors, proportions, materials, texture,
logo, labels, packaging, controls, accessories, and visible details.
Do not redesign, replace, recolor, resize, deform, duplicate, or invent
any product component.

DURATION:
Exactly 10.0 seconds.

VIDEO STYLE:
Authentic Moroccan UGC advertising, realistic smartphone camera movement,
natural lighting, believable human performance, correct physics, clear sound,
mobile-first vertical composition, casual and credible rather than corporate.

CONTINUITY:
[Describe how the opening frame starts the video and how its final second
connects naturally to the following video.]

SHOT-BY-SHOT TIMELINE:

[0.0s–X.Xs]
VISUAL: [Describe only what is visible in this exact time interval.]
CAMERA: [Describe camera angle and movement.]
PERFORMANCE: [Describe facial expression, gestures, and action.]
AUDIO TYPE: [Voice-over / on-camera dialogue.]
SPOKEN DARIJA: "[Exact short line in Moroccan Darija.]"
SOUND: [Room tone, realistic SFX.]
MUSIC: [None / very low subtle beat.]

[X.Xs–X.Xs]
VISUAL: [...]
CAMERA: [...]
PERFORMANCE: [...]
AUDIO TYPE: [...]
SPOKEN DARIJA: "[...]"
SOUND: [...]
MUSIC: [...]

[X.Xs–10.0s]
VISUAL: [...]
CAMERA: [...]
PERFORMANCE: [...]
AUDIO TYPE: [...]
SPOKEN DARIJA: "[...]"
SOUND: [...]
MUSIC: [...]

AUDIO QUALITY:
Use natural Moroccan Darija, warm and trustworthy voice, medium speaking pace,
clear pronunciation, realistic pauses, and synchronized lip movement if the
presenter speaks on camera. Keep music lower than speech.

NEGATIVE CONSTRAINTS:
No text overlay, no subtitles, no captions, no watermark, no generated Arabic
writing, no robotic voice, no unclear speech, no abrupt scene change, no
wrong character, no face drift, no wardrobe changes, no distorted hands,
no product duplication, no broken object physics, no unsupported claims.
```

---

## قواعد التوقيت

مدة كل فيديو 10.0 ثوانٍ.

يجب أن يحتوي الفيديو على 3 أو 4 لقطات فقط.

كل لقطة يجب أن تتضمن:

- بداية ونهاية بالثواني.
- Visual.
- Camera.
- Performance.
- Audio Type.
- Spoken Darija.
- Sound.
- Music.

التايملاين يجب أن:

- يبدأ في 0.0s.
- ينتهي في 10.0s.
- لا يحتوي فراغات.
- لا يحتوي تداخلات.
- لا يحتوي لقطة أقل من 1.5 ثانية إلا في Hook سريع جدًا.
- لا يحتوي أكثر من 4 لقطات.

دليل طول الكلام:

| مدة الكلام | طول مناسب تقريبًا |
|---:|---|
| 2 ثوانٍ | 4–7 كلمات |
| 2.5 ثوانٍ | 5–8 كلمات |
| 3 ثوانٍ | 7–10 كلمات |
| 4 ثوانٍ | 10–14 كلمة |

---

## قواعد التعليق بالدارجة

- استعمل دارجة مغربية مفهومة.
- نبرة طبيعية كأن الشخصية تتحدث مع صديقة أو متابعين.
- لا تستعمل عربية فصحى ثقيلة.
- تجنب الجمل الطويلة.
- لا تدخل في الفرنسية أو الإنجليزية إلا عندما تكون طبيعية جدًا.
- اربط الكلام بما يظهر في نفس اللقطة.
- لا تقل “شوفو هاد المنتج” قبل أن يظهر المنتج.
- لا تذكر السعر أو COD أو التوصيل إلا إذا كانت المعلومات مؤكدة.
- CTA في Video 3 فقط، إلا إذا طلب المستخدم غير ذلك.

---

## المخرجات الإلزامية

أخرج النتيجة بالترتيب التالي:

```md
# 1. Product Brief

- اسم المنتج:
- نوعه:
- الجمهور:
- المشكلة:
- الفائدة المؤكدة:
- السعر:
- العرض:
- التوصيل:
- الدفع:
- زاوية الإعلان:
- ادعاءات ممنوعة:

# 2. Product Visibility Plan

| الفيديو | المنتج يظهر؟ | الوضع | هل نرفق صورة المنتج؟ |
|---|---|---|---|
| Video 1 | نعم أو لا | hidden / hinted / visible / hero | نعم أو لا |
| Video 2 | نعم | visible / hero | نعم |
| Video 3 | نعم | visible / hero | نعم |

# 3. Character Plan

- هل نحتاج شخصية؟:
- نوع الشخصية:
- اسم Character ID:
- تفاصيل المظهر:
- الملابس:
- أسلوب الكلام:
- المكان:

# 4. Character Generation Prompt

```text
[English prompt]
```

# 5. Character Bible

```yaml
[Fixed character description]
```

# 6. Frame 1 Image Prompt

```text
[English prompt]
```

# 7. Video 1 Prompt — Exactly 10 seconds

```text
[Complete English video prompt with timed Darija speech]
```

# 8. Frame 2 Image Prompt

```text
[English prompt]
```

# 9. Video 2 Prompt — Exactly 10 seconds

```text
[Complete English video prompt with timed Darija speech]
```

# 10. Frame 3 Image Prompt

```text
[English prompt]
```

# 11. Video 3 Prompt — Exactly 10 seconds

```text
[Complete English video prompt with timed Darija speech]
```

# 12. Optional Editing Overlays

These must be added manually after generation, never generated inside Gemini:

- Hook:
- Benefit:
- Price:
- COD or delivery:
- CTA:

# 13. Global Negative Prompt

```text
[Combined product, character, text, speech, and physics constraints.]
```
```

---

## فحص الجودة النهائي

قبل إخراج النتيجة، تحقق من التالي:

```text
[ ] هل تم تحديد product_visibility لكل فيديو؟
[ ] هل حذفت صورة المنتج من كل مشهد hidden أو hinted؟
[ ] هل منعت المنتج تمامًا في مشاهد hidden؟
[ ] هل أرفقت صورة المنتج في كل مشهد visible أو hero؟
[ ] هل Product Identity Lock موجود عندما يظهر المنتج؟
[ ] هل Character Reference موجودة في كل مشهد تظهر فيه الشخصية؟
[ ] هل Character Bible نفسه لم يتغير؟
[ ] هل كل فيديو مدته 10.0 ثوانٍ؟
[ ] هل التايملاين يغطي 0.0s حتى 10.0s؟
[ ] هل الكلام بالدارجة مدمج داخل كل لقطة؟
[ ] هل الكلام مناسب لمدة اللقطة؟
[ ] هل السعر والعرض وCOD مؤكدون؟
[ ] هل لا توجد كتابة مولدة داخل الفيديو؟
[ ] هل نهاية كل فيديو تمهّد لبداية التالي؟
[ ] هل الادعاءات واقعية وغير مضللة؟
```
