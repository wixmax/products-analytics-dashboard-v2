---
name: gemini-facebook-product-ads-voiceover
title: Gemini Facebook Silent Ads & Voice-Over Skill
version: 1.0.0
description: إنشاء حزمة إعلانية كاملة لمنتجات التجارة الإلكترونية لـ Facebook & Instagram في المغرب (فيديوهات UGC صامتة بدون حوار أو موسيقى + برومبتات تعليق صوتي TTS مستقلة ومتزامنة بدقة لـ Gemini 3.1 Flash TTS).
---

# Gemini Facebook Silent Ads & Voice-Over Skill

## الهدف

إنشاء حزمة إعلانية كاملة واحترافية لمنتجات التجارة الإلكترونية باستعمال نماذج Gemini للذكاء الاصطناعي، موجهة لإعلانات Facebook Ads و Instagram Reels في السوق المغربي.

تتميز هذه المهارة بأنها تفصل بالكامل بين:
1. **توليد الفيديوهات البصرية (AI Video Generation):** فيديوهات بصرية واقعية بأسلوب UGC صامتة تماماً من حيث الكلام والموسيقى (No dialogue, No speech, No mouth movement/lip-sync, No background music)، مع الحفاظ فقط على المؤثرات الصوتية البيئية والطبيعية الواقعية (Ambient room tone & realistic Foley/SFX).
2. **توليد التعليق الصوتي الإعلاني (AI Voice-Over Generation):** برومبت تعليق صوتي مستقل ومخصص أسفل كل برومبت فيديو، مهيأ بالصيغة القياسية لنموذج توليد الصوت `gemini-3.1-flash-tts-preview`، متطابق ثانية بثانية مع اللقطات البصرية بالدارجة المغربية الأصيلة.

### مخرجات المهارة الإلزامية:
1. برومبت لتوليد شخصية UGC مغربية ثابتة (Character Reference Image).
2. بطاقة هوية الشخصية (Character Bible) لتثبيت الشخصية عبر جميع المشاهد.
3. ثلاث صور إعلانية افتتاحية مستقلة (Frames 1, 2, 3).
4. ثلاثة برومبتات فيديو مستقلة (مدّة كل فيديو 10.0 ثوانٍ بالضبط = 30 ثانية مترابطة)، صامتة من الحوار والموسيقى.
5. ثلاثة برومبتات تعليق صوتي TTS مستقلة (برومبت صوتي أسفل كل برومبت فيديو) جاهزة للنسخ المباشر في `gemini-3.1-flash-tts-preview`.
6. برومبت تعليق صوتي مجمع إضافي (30 ثانية كاملة) للراغبين في توليد الصوت دفعة واحدة.
7. تقسيم زمني دقيق لكل لقطة (Timeline 0.0s–10.0s).
8. قواعد حازمة لتثبيت المنتج (`Product Identity Lock`) ومنعه في مشاهد المشكلة (`Product Absence Requirement`).
9. نصوص تسويقية مقترحة للمونتاج (CapCut / Meta Ads Overlays).

---

## قواعد عامة

### لغة المخرجات
- **برومبتات الصور والفيديو:** اللغة الإنجليزية بالكامل.
- **برومبتات التعليق الصوتي (TTS Prompts):** الهيكل والتوجيهات بالإنجليزية، والنص المنطوق بالدارجة المغربية بحروف عربية أسفل `#### TRANSCRIPT`.
- **شرح النتائج والتوجيهات للمستخدم:** اللغة العربية.
- **ممنوع طلب كتابة نصوص عربية داخل الصورة أو الفيديو المولّد بالذكاء الاصطناعي.** النصوص التسويقية والأسعار والأزرار تُضاف لاحقاً في مرحلة المونتاج (CapCut / Meta Ads).

### المنصة والجمهور المستهدف
- **المنصة الافتراضية:** Facebook Ads و Instagram Reels (فيديو طولي عمودي 9:16).
- **البلد والجمهور:** المغرب (ثقافة وسياق محلي واقعي).
- **أسلوب الإعلان:** UGC عفوي وواقعي، Lifestyle، أو حل المشكلات (Problem-Solution).
- **الدفع والتوصيل:** الدفع عند الاستلام (COD) والتوصيل المجاني لا يُذكران إلا إذا أكدهما المستخدم صراحة.

### قواعد المصداقية (Zero Hallucination Policy)
ممنوع اختراع:
- مواصفات تقنية غير مثبتة بالبيانات أو غير مرئية بالمرجع.
- نتائج طبية، علاجية، أو وعود قطعية غير واقعية.
- أسعار أو تخفيضات أو هدايا لم يحددها المستخدم.
- توصيل مجاني أو دفع عند الاستلام بدون تأكيد.

إذا كانت معلومات المنتج محدودة، يتم استعمال صياغات تسويقية آمنة:
- "حل عملي للاستعمال اليومي"
- "كيعاونك ترتاح وتوفر الوقت"
- "سهل فالاستخدام والتنقل"
- "شوف التفاصيل واطلب دابا"

---

## فلسفة الفيديو الصامت (Silent Video Architecture)

### لماذا نصنع فيديوهات صامتة؟
نماذج توليد الفيديو الحالية غالباً ما تعاني من تشوهات الوجه وحركات الشفاه الاصطناعية غير المنضبطة عند محاولة توليد كلام وحوار داخل الفيديو، كما تخلط بين الموسيقى والحديث.
لذلك، أفضل وأعلى جودة إعلانية تتحقق بفصل المسارين:
1. **فيديو بصري خالص:** حركة شخصية طبيعية (ابتسامة، دهشة، تركيز، إيماء بالرأس، استخدام عملي للمنتج) بدون فتح الفم للتحدث أو تحريك الشفاه للنطق.
2. **صوتيات الفيديو المولّد:** مقتصرة فقط على صوت الغرفة الهادئ (Room tone) والأصوات الفيزيائية الطبيعية للمنتج والبيئة (صوت نقرة زر، صوت سكب ماء، صوت فتح العلبة، صوت محرك خفيف).
3. **التعليق الصوتي الاحترافي:** يتم توليده بشكل مستقل عبر `gemini-3.1-flash-tts-preview`، ثم يُركب فوق الفيديو في برنامج المونتاج بسهولة وتزامن تام.

### قيود الفيديو الإلزامية في كل برومبت فيديو:
```text
AUDIO IN VIDEO:
- AUDIO TYPE: Ambient environmental room tone and realistic Foley/SFX only.
- SPEECH / DIALOGUE: Strictly NONE. The presenter does NOT speak or talk on camera.
  Mouth remains naturally closed or shows silent expressions (smile, nod, surprise).
  No mouth movement for speech, no lip-sync.
- MUSIC: Strictly NONE. No background music, no instruments, no beats, no singing.
- SFX: Subtle, crisp physical sounds matching visible actions (e.g. unboxing sound,
  gentle click, water flow, light motor hum).
```

---

## أنواع المشاهد وحالة ظهور المنتج (Product Visibility)

كل مشهد فيديو أو صورة افتتاحية يجب أن يملك خاصية صريحة:
```yaml
product_visibility: hidden | hinted | visible | hero
```

### 1. وضع `hidden` (مخفي تماماً)
يُستخدم في مرحلة المشكلة والـ Hook (غالباً Video 1).
- **القاعدة:** المنتج ممنوع تماماً من الظهور في الكادر أو الخلفية أو الانعكاس.
- **في البرومبت:** لا تُرفق صورة المنتج، وضع نص `PRODUCT ABSENCE REQUIREMENT`.
- التركيز بالكامل على الشخصية، تعابير الانزعاج أو الحيرة، وبيئة المشكلة اليومية.

### 2. وضع `hinted` (مُلمَّح إليه)
- لا يظهر المنتج بذاته، ولكن يتم التلميح لوجود حل (مثل فتح درج، النظر لشيء خارج الكادر).
- لا تُرفق صورة المنتج، وضع نص `PRODUCT ABSENCE REQUIREMENT`.

### 3. وضع `visible` (ظاهر أثناء الاستعمال)
- المنتج يظهر بوضوح أثناء الاستخدام أو موضوعاً في مكانه الطبيعي (غالباً Video 2).
- **في البرومبت:** تُرفق صورة المنتج المرجعية مع تفعيل `PRODUCT IDENTITY LOCK`.

### 4. وضع `hero` (البطل ومحور الكادر)
- المنتج هو العنصر الأساسي والرئيسي في اللقطة بحجم واضح وإضاءة مريحة (غالباً Video 3).
- **في البرومبت:** تُرفق صورة المنتج المرجعية مع تفعيل `PRODUCT IDENTITY LOCK`.

---

## كتل ثبات المنتج والمراجع (Consistency Blocks)

### Product Identity Lock (عند ظهور المنتج `visible` أو `hero`)
```text
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact and immutable product identity.
The product shown in this scene must remain visually identical to the attached reference image.
Preserve its exact shape, proportions, scale, colors, materials, texture, logo, labels,
packaging, buttons, openings, accessories, printed details, and visible construction.
Do not redesign, replace, recolor, resize, simplify, stylize, enhance, deform, invent,
add, remove, or substitute any part of the product.
```

### Product Absence Requirement (عند إخفاء المنتج `hidden` أو `hinted`)
```text
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, any part of the product, its packaging, logo, label,
reflection, silhouette, shadow, or a similar substitute anywhere in this scene.
The scene must focus exclusively on the customer's daily problem or situation.
```

### Reference Separation & Focused Passes
```text
REFERENCE SEPARATION:
- Product Reference Image controls the product only: exact geometry, scale, materials, colors, logo.
- Character Reference Image controls the presenter only: face, skin tone, hairstyle, outfit, identity.
- Opening-Frame Reference Image controls the starting composition and camera framing only.
- Do not blend or transfer attributes between reference images.

FOCUSED PASSES:
Pass 1: Lock product anchor from Product Reference Image (or absence if hidden).
Pass 2: Integrate realistic Moroccan home/work environment and natural daylight.
Pass 3: Add presenter interaction (holding/using product safely, authentic non-speaking expressions).
Pass 4: Apply smooth mobile camera motion and realistic physical SFX.
PRODUCT ANCHOR STATUS: LOCKED.
```

---

## نظام تثبيت الشخصية (Character System)

### 1. برومبت توليد الشخصية (Character Generation Prompt)
يتم توليد شخصية مغربية عفوية ومقنعة (UGC Creator):
```text
Create a realistic character reference portrait for a Moroccan UGC Facebook advertising presenter.

CHARACTER:
[Gender], Moroccan, [age 22-35], [skin tone], [natural face shape], [hair details], [expressive eyes].

WARDROBE:
[Casual modern Moroccan home or everyday clothing, clean colors, simple natural styling].

PERSONALITY & EXPRESSION:
Friendly, authentic, relatable, trustworthy, everyday Moroccan creator. Not a glamorous fashion model.
Warm approachable expression, natural posture, hands visible and away from face.

SETTING:
Realistic Moroccan modern apartment interior (living room, kitchen, bedroom, or desk).
Natural window daylight, authentic textures.

CONSISTENCY REQUIREMENT:
Permanent identity reference portrait for multi-scene video campaign.
No product, no text, no watermark, no beauty filters, no extra people.
```

### 2. بطاقة هوية الشخصية (Character Bible)
تُكتب بصيغة YAML وتُنسخ في كل برومبت:
```yaml
character_id: ugc-morocco-presenter-01
gender: [Female / Male]
age_range: 24-30
nationality: Moroccan
skin_tone: Warm Mediterranean / North African
hair: [Details]
outfit: [Exact clothing description & colors]
personality: Friendly, warm, credible, helpful friend vibe
expressions: Natural smiles, nods, problem empathy, product satisfaction (NO on-camera speaking)
forbidden_changes: No changing facial structure, skin tone, hairstyle, or outfit across scenes
```

### 3. كتلة قفل هوية الشخصية (Character Identity Lock)
```text
CHARACTER IDENTITY LOCK:
Use the attached character reference image as the exact permanent identity of the presenter.
Keep the same face shape, skin tone, eyes, hair color and style, body type, outfit, and accessories.
Do not replace the person, alter their age, or change their clothes.
```

---

## هيكل الحملة الإعلانية (3 فيديوهات × 10 ثوانٍ = 30 ثانية)

| الفيديو | التوقيت | دور المشهد | حالة المنتج | الصوت في الفيديو | التعليق الصوتي الملحق (TTS) |
|---|---|---|---|---|---|
| **Video 1** | 0.0s–10.0s | Hook والمشكلة اليومية | `hidden` (أو `hinted`) | مؤثرات وبيئة صامتة (SFX فقط) | Hook جذب الانتباه + إبراز المشكلة |
| **Video 2** | 10.0s–20.0s | كشف الحل وتجربة المنتج | `visible` | أصوات تشغيل واستعمال حقيقية | كشف المنتج + الفائدة العملية |
| **Video 3** | 20.0s–30.0s | النتيجة الملموسة والطلب | `hero` | صوت إغلاق أو لمس هادئ | النتيجة + العرض + طلب الشراء |

---

## قواعد التعليق الصوتي لـ Gemini 3.1 Flash TTS

### النموذج المستهدف
```text
gemini-3.1-flash-tts-preview
```

### التوجيه الإلزامي (Mandatory Header)
يجب أن يبدأ كل برومبت تعليق صوتي بهذا النص الإلزامي بحذافيره لمنع قراءة التوجيهات بصوت عالٍ:
```text
Synthesize speech for the performance defined below.
The audio profile, scene, performance directions, context, timing notes,
and section labels are instructions only. Do not speak them aloud.
Speak only the content under the exact heading: #### TRANSCRIPT.
```

### هيكل برومبت التعليق الصوتي (Voice-Over Prompt Template)
```text
Synthesize speech for the performance defined below.
The audio profile, scene, performance directions, context, timing notes,
and section labels are instructions only. Do not speak them aloud.
Speak only the content under the exact heading: #### TRANSCRIPT.

## AUDIO PROFILE
A [young-adult / 25-30 year old] Moroccan [woman/man] speaking authentic Moroccan Darija.
The voice sounds [warm, conversational, trustworthy, enthusiastic, and natural].
Use everyday Moroccan pronunciation, natural rhythm, and a friendly UGC peer-to-peer tone.
Avoid formal Modern Standard Arabic (فصحى) and overly theatrical radio announcer delivery.

## SCENE
Voice-over for Video [1 / 2 / 3] (0.0s to 10.0s) of a Moroccan Facebook vertical video ad.
Visual scene: [Brief 1-line description of what is visually happening on screen].
The speaker shares a genuine personal experience and recommendation with a friend.

### PERFORMANCE
Style: [e.g. Empathetic and engaging for problem / Excited and clear for reveal / Confident and warm for CTA].
Pace: Natural speaking rate tailored for 10 seconds (~20-25 Darija words total per 10s video).
Accent: Authentic Moroccan Darija (الدارجة المغربية).

### CONTEXT
[Contextual explanation of what the listener should feel during these 10 seconds].

### TIMING NOTES
- [0.0s–X.Xs]: [Brief non-spoken direction, e.g. Start immediately with engaging hook question]
- [X.Xs–X.Xs]: [Brief non-spoken direction, e.g. Empathetic tone acknowledging frustration]
- [X.Xs–10.0s]: [Brief non-spoken direction, e.g. Transition smoothly towards next step]

#### TRANSCRIPT
[Insert Darija script in Arabic letters with inline audio tags and punctuation only]
```

### قواعد نصوص الدارجة وسرعة النطق (Darija Speed & Word Budget)
لضمان تزامن الصوت مع الـ 10 ثوانٍ دون استعجال أو بطء:
- **لكل 2 ثوانٍ:** 4–7 كلمات دارجة.
- **لكل 3 ثوانٍ:** 7–10 كلمات دارجة.
- **لكل 4 ثوانٍ:** 10–14 كلمة دارجة.
- **الإجمالي لكل فيديو (10 ثوانٍ):** **20 إلى 26 كلمة دارجة كحد أقصى** لترك مساحة للتنفس ووقفات الصمت الطبيعية.

### الوسوم الصوتية المعتمدة (Inline Audio Tags)
توضع بين أقواس مربعة بالإنجليزية وتُدرج مباشرة قبل الكلمات المعنية:
- `[curiosity]` : لإثارة الفضول في الـ Hook.
- `[interest]` : لإظهار الاهتمام.
- `[enthusiasm]` : للحماس عند كشف المنتج أو نتائجه.
- `[positive]` : لنبرة إيجابية مريحة.
- `[excitement]` : للتعبير عن السعادة أو المفاجأة الإيجابية.
- `[short pause]` : وقفة قصيرة (~0.5 ثانية) عند ظهور لقطة مهمة.
- `[long pause]` : وقفة أطول (~1 ثانية) لإفساح المجال لمؤثر بصري قوي.
- `[slow]` : لتبطئة النطق عند التأكيد على فائدة محددة.
- `[whispers]` : للمشاركة العفوية كأنها سر تسويقي خاص.

**قواعد الوسوم الصارمة:**
1. ممنوع وضع وسمين متتاليين دون كلمات بينهما (ممنوع: `[short pause][enthusiasm]`).
2. استخدام علامات الترقيم الطبيعية (فاصلة `,` للوقف الخفيف، ونقطة `.` لنهاية الجملة).
3. استخدام علامات الحذف `...` للوقفات المعلقة الطبيعية.

### الحظر التام للأوامر التفاعلية على الشاشة
ممنوع تماماً أن يحتوي النص الصوتي على أوامر تفاعلية مثل:
- ❌ "كليكي لتحت" / "اضغط على الرابط" / "كليكي هنا" / "سوايب" / "شوف الوصف" / "Click here" / "Swipe up".
- **البديل الصوتي الصحيح:** تشجيع طبيعي غير مباشر:
  -  "الطلب دابا ساهل، والتوصيل كيوصلك حتى لباب الدار."
  -  "الكمية كاطير بسرعة، تهنى اليوم وجربو بنفسك."
  -  "التفاصيل كاملة متوفرة، شوف العرض دابا."

---

## قوالب البرومبتات (Templates)

### قالب الـ Frame الافتتاحي (Opening Frame Prompt)
```text
Create a realistic opening frame for a 10-second Facebook ad video targeting a Moroccan mobile audience.

SCENE MODE:
product_visibility: [hidden | hinted | visible | hero]

CHARACTER IDENTITY:
Use the attached character reference image as the exact identity of the presenter.
Keep the same face, skin tone, hairstyle, outfit, accessories, and body type.

CHARACTER BIBLE:
[Paste Character Bible]

[IF product_visibility is hidden or hinted]
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, its packaging, logo, label, reflection, silhouette, or substitute anywhere.

[IF product_visibility is visible or hero]
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact immutable product identity.
Preserve exact shape, colors, proportions, materials, labels, and details.

SCENE & COMPOSITION:
[Describe scene, pose, authentic emotion, lighting, and realistic Moroccan home setting].

CAMERA:
Vertical 9:16 smartphone UGC framing, eye-level, natural daylight, believable textures.
No text, no captions, no watermarks, no extra people, no distorted hands.
```

### قالب برومبت الفيديو الصامت (Silent Video Prompt Template)
```text
Create exactly one 10-second realistic vertical UGC product advertising video for Facebook Ads in Morocco.

SCENE MODE:
product_visibility: [hidden | hinted | visible | hero]

REFERENCE ATTACHMENTS:
- Character Reference Image: attached.
- Product Reference Image: [attached if visible/hero, omitted if hidden/hinted].
- Opening-Frame Reference Image: attached.

CHARACTER IDENTITY LOCK:
Use the attached character reference image as the permanent presenter. Keep the same person,
face, skin tone, hairstyle, outfit, and body proportions throughout the video.

CHARACTER BIBLE:
[Paste Character Bible]

[IF product_visibility is hidden or hinted]
PRODUCT ABSENCE REQUIREMENT:
Do not show the product, its packaging, logo, label, reflection, silhouette, or substitute anywhere.

[IF product_visibility is visible or hero]
PRODUCT IDENTITY LOCK:
Use the attached product reference image as the exact product. Preserve shape, colors, materials,
labels, and visible details without any modification from 0.0s to 10.0s.

DURATION: Exactly 10.0 seconds.

VIDEO STYLE:
Authentic Moroccan UGC smartphone footage, 9:16 vertical, natural lighting, believable physics,
mobile-first composition, relaxed everyday environment.

SILENT VIDEO AUDIO SPECIFICATIONS:
- AUDIO TYPE: Ambient room tone & realistic Foley/SFX only.
- SPEECH & DIALOGUE: NONE. The presenter does NOT speak or move mouth to talk on camera.
  Expressions are silent and natural (smile, reaction, head nod, concentration).
  Strictly no lip-sync, no dialogue.
- MUSIC: NONE. No background music, no beats, no melody, no singing.
- SFX: Subtle, crisp physical sounds matching actions (e.g. unboxing, soft click, water drop).

SHOT-BY-SHOT TIMELINE:

[0.0s–X.Xs]
VISUAL: [Describe exact action, product position, character pose]
CAMERA: [Angle and movement, e.g. steady handheld smartphone]
PERFORMANCE: [Silent facial expression, reaction, or physical action - closed mouth]
SOUND: [Ambient room tone + specific SFX]
MUSIC: None
SPEECH: None

[X.Xs–X.Xs]
VISUAL: [...]
CAMERA: [...]
PERFORMANCE: [...]
SOUND: [...]
MUSIC: None
SPEECH: None

[X.Xs–10.0s]
VISUAL: [...]
CAMERA: [...]
PERFORMANCE: [...]
SOUND: [...]
MUSIC: None
SPEECH: None

NEGATIVE CONSTRAINTS:
No speech, no talking, no dialogue, no mouth moving for speech, no lip-sync,
no background music, no singing, no soundtrack, no text overlay, no captions,
no watermarks, no generated Arabic writing, no character morphing, no product deformation.
```

---

## ترتيب المخرجات الإلزامي (Output Structure)

يجب إخراج النتائج للمستخدم وفق الترتيب التالي بدقة واكتمال:

```md
# 1. Product Brief (ملخص المنتج والعرض)
- اسم المنتج:
- نوعه وتصنيفه:
- الجمهور المستهدف في المغرب:
- المشكلة الأساسية:
- الفائدة الملموسة المؤكدة:
- السعر (إن كان مؤكداً):
- العرض والتوصيل والدفع:
- ادعاءات ممنوعة:

# 2. Product Visibility Plan (خطة ظهور المنتج)
| الفيديو | المدة | وضعية الظهور | صورة المنتج مرفقة؟ | الهدف البصري |
|---|---|---|---|---|
| Video 1 | 0.0s–10.0s | hidden / hinted | لا | الـ Hook وإبراز المشكلة |
| Video 2 | 10.0s–20.0s | visible | نعم | كشف الحل والاستعمال الواقعي |
| Video 3 | 20.0s–30.0s | hero | نعم | النتيجة، العرض، والدعوة للشراء |

# 3. Character Plan & Bible (خطة وبطاقة الشخصية الثابتة)
- مواصفات الشخصية:
- Character Bible (YAML):

# 4. Character Generation Prompt (برومبت توليد الشخصية)
```text
[English prompt for permanent character portrait]
```

# 5. Frame 1 Image Prompt (الفريم الافتتاحي 1)
```text
[English prompt for Frame 1]
```

# 6. Video 1 Prompt — Exactly 10.0s (فيديو 1 صامت: المشكلة والـ Hook)
```text
[Complete English prompt for Video 1 — Ambient SFX only, Strictly NO speech, NO music]
```

# 7. Video 1 Voice-Over Prompt (برومبت التعليق الصوتي لـ Video 1)
```text
[Full Gemini 3.1 Flash TTS prompt with timing notes and Darija transcript]
```

# 8. Frame 2 Image Prompt (الفريم الافتتاحي 2)
```text
[English prompt for Frame 2]
```

# 9. Video 2 Prompt — Exactly 10.0s (فيديو 2 صامت: كشف الحل والاستعمال)
```text
[Complete English prompt for Video 2 — Ambient SFX only, Strictly NO speech, NO music]
```

# 10. Video 2 Voice-Over Prompt (برومبت التعليق الصوتي لـ Video 2)
```text
[Full Gemini 3.1 Flash TTS prompt with timing notes and Darija transcript]
```

# 11. Frame 3 Image Prompt (الفريم الافتتاحي 3)
```text
[English prompt for Frame 3]
```

# 12. Video 3 Prompt — Exactly 10.0s (فيديو 3 صامت: النتيجة والطلب)
```text
[Complete English prompt for Video 3 — Ambient SFX only, Strictly NO speech, NO music]
```

# 13. Video 3 Voice-Over Prompt (برومبت التعليق الصوتي لـ Video 3)
```text
[Full Gemini 3.1 Flash TTS prompt with timing notes and Darija transcript]
```

# 14. Combined 30-Second Voice-Over Prompt (برومبت التعليق الصوتي المجمع 30 ثانية)
```text
[Full Gemini 3.1 Flash TTS prompt combining all 3 scenes into a seamless 30.0s audio track]
```

# 15. Optional Editing Overlays (نصوص المونتاج لـ CapCut / Meta Ads)
- Hook Text (Video 1):
- Benefit / Feature Callouts (Video 2):
- Price & Offer Sticker (Video 3):
- CTA Button Overlay (Video 3):

# 16. Global Negative Constraints (قائمة السلبيات الإلزامية)
```text
[Combined negative constraints for image, silent video, and audio synthesis]
```
```

---

## قائمة التحقق النهائي من الجودة (Quality Checklist)

قبل تقديم النتائج، تأكد من استيفاء كافة الشروط التالية:
```text
[ ] هل تم منع الكلام والحوار والموسيقى تماماً داخل برومبتات الفيديو الثلاثة؟
[ ] هل كود الصوت في كل فيديو يحتوي فقط على Ambient room tone و Foley/SFX؟
[ ] هل الشخصية لا تحرك فمها للكلام على الشاشة (No lip-sync / No speaking)?
[ ] هل يوجد برومبت تعليق صوتي TTS مستقل تحت كل برومبت فيديو مباشرة؟
[ ] هل يبدأ كل برومبت TTS بالتوجيه الإلزامي بعدم نطق التعليمات والعناوين؟
[ ] هل النص المنطوق يقع حصراً أسفل عنوان #### TRANSCRIPT؟
[ ] هل لغة السكربت هي الدارجة المغربية العفوية بحروف عربية بدون فصحى ثقيلة؟
[ ] هل عدد الكلمات بالدارجة مضبوط وفق ميزانية التوقيت (20-26 كلمة لكل 10 ثوانٍ)؟
[ ] هل الوسوم الصوتية [tag] بالإنجليزية ولا توجد وسوم متتالية؟
[ ] هل يخلو النص الصوتي تماماً من أوامر الشاشة ("كليكي لتحت", "اضغط الزر", "سوايب")؟
[ ] هل تم تضمين البرومبت الصوتي المجمع (30 ثانية كاملة)؟
[ ] هل تم الالتزام بقواعد Product Identity Lock عند ظهور المنتج ومنعه عند إخفائه؟
[ ] هل الفيديوهات الثلاثة مدة كل منها 10.0 ثوانٍ بالضبط بدون فراغات في التايملاين؟
```
