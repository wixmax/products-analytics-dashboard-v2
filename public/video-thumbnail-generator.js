// video-thumbnail-generator.js
// Client-side Video Thumbnail Generator & Server Persistence Cache

const generatingThumbnails = new Set();

/**
 * Updates JavaScript in-memory product objects (allProducts & savedProducts)
 * so subsequent re-renders (filtering, sorting) maintain the thumbnail.
 */
function updateProductMemoryImage(productId, productUrl, imageUrl) {
  if (!imageUrl) return;

  const cleanMemoryImage = (p) => {
    let existing = (p.ad_image_urls || "").split(";").filter(Boolean);
    // Strip out base64 data URIs
    existing = existing.filter((url) => !url.startsWith("data:image/") && !url.startsWith("data:"));

    // Add only clean URLs (http, https, or relative path)
    if (imageUrl.startsWith("http://") || imageUrl.startsWith("https://") || imageUrl.startsWith("/")) {
      if (!existing.includes(imageUrl)) {
        existing.unshift(imageUrl);
      }
    }
    p.ad_image_urls = existing.join(";");
  };

  // Update in allProducts (index.js)
  if (typeof allProducts !== "undefined" && Array.isArray(allProducts)) {
    const p = allProducts.find(
      (item) =>
        (productId && String(item.id) === String(productId)) ||
        (productUrl && item.productUrl === productUrl)
    );
    if (p) cleanMemoryImage(p);
  }

  // Update in savedProducts (saved-ads.js)
  if (typeof savedProducts !== "undefined" && Array.isArray(savedProducts)) {
    const p = savedProducts.find(
      (item) =>
        (productId && String(item.id) === String(productId)) ||
        (productUrl && item.productUrl === productUrl)
    );
    if (p) cleanMemoryImage(p);
  }
}

/**
 * Detects whether browser canvas supports AVIF export.
 * Returns 'image/avif' if supported, else 'image/webp' or 'image/jpeg'.
 */
function getPreferredMimeType() {
  try {
    const canvas = document.createElement("canvas");
    canvas.width = 1;
    canvas.height = 1;
    const dataUrl = canvas.toDataURL("image/avif");
    if (dataUrl && dataUrl.indexOf("data:image/avif") === 0) {
      return "image/avif";
    }
  } catch (e) {}

  try {
    const canvas = document.createElement("canvas");
    canvas.width = 1;
    canvas.height = 1;
    const dataUrl = canvas.toDataURL("image/webp");
    if (dataUrl && dataUrl.indexOf("data:image/webp") === 0) {
      return "image/webp";
    }
  } catch (e) {}

  return "image/jpeg";
}

/**
 * Automatically scans container for video placeholders missing a poster/image,
 * generates a thumbnail via HTML5 Video & Canvas (or Media Fragment fallback),
 * updates UI, and persists to server.
 */
function ensureVideoThumbnails(scope = document) {
  const placeholders = scope.querySelectorAll(".vid-placeholder[data-vid-src]");
  placeholders.forEach((el) => {
    const videoSrc = el.getAttribute("data-vid-src");
    const poster = el.getAttribute("data-vid-poster");
    const productId = el.getAttribute("data-product-id");
    const productUrl = el.getAttribute("data-product-url");
    const imgEl = el.querySelector(".vid-placeholder-img");
    const videoPreviewEl = el.querySelector(".vid-placeholder-preview-video");
    const bgEl = el.querySelector(".vid-placeholder-bg");

    // Skip if poster, image, or video preview already exists
    if (poster && poster.trim().length > 0 && !poster.includes("undefined")) return;
    if (imgEl && imgEl.getAttribute("src") && imgEl.getAttribute("src").trim().length > 0) return;
    if (videoPreviewEl) return;
    if (!videoSrc) return;

    // Unique key per element / product ID to avoid cross-product bleed
    const elementKey = productId ? `pid_${productId}` : `vsrc_${videoSrc}`;
    
    // If element was re-rendered and lost its image DOM node, allow re-generating
    if (bgEl && !imgEl && !videoPreviewEl) {
      generatingThumbnails.delete(elementKey);
    } else if (generatingThumbnails.has(elementKey)) {
      return;
    }

    generatingThumbnails.add(elementKey);

    generateVideoThumbnail(videoSrc)
      .then((dataUrl) => {
        if (!dataUrl) return;

        // Update JavaScript memory objects
        updateProductMemoryImage(productId, productUrl, dataUrl);

        // Render AVIF/WebP/JPEG image in UI
        const currentBg = el.querySelector(".vid-placeholder-bg");
        const newImg = document.createElement("img");
        newImg.src = dataUrl;
        newImg.alt = "";
        newImg.className = "vid-placeholder-img";

        if (currentBg) {
          currentBg.replaceWith(newImg);
        } else if (!el.querySelector(".vid-placeholder-img")) {
          el.prepend(newImg);
        }

        // Save to server asynchronously tied specifically to productId/productUrl
        if (productId || productUrl) {
          fetch("/api/products/save-thumbnail", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              product_id: productId,
              product_url: productUrl,
              image_data: dataUrl,
            }),
          })
            .then((res) => res.json())
            .then((data) => {
              if (data && data.thumbnail_url) {
                el.setAttribute("data-vid-poster", data.thumbnail_url);
                updateProductMemoryImage(productId, productUrl, data.thumbnail_url);
              }
            })
            .catch((err) => console.warn("Failed to persist thumbnail:", err));
        }
      })
      .catch((err) => {
        // Fallback for CORS or canvas errors: Use HTML5 video frame preview (#t=0.5)
        // Browsers natively render the frame at 0.5s without CORS restrictions
        const currentBg = el.querySelector(".vid-placeholder-bg");
        const previewVid = document.createElement("video");
        previewVid.src = `${videoSrc}#t=0.5`;
        previewVid.preload = "metadata";
        previewVid.muted = true;
        previewVid.playsInline = true;
        previewVid.className = "vid-placeholder-img vid-placeholder-preview-video";
        previewVid.style.objectFit = "cover";
        previewVid.style.width = "100%";
        previewVid.style.height = "100%";
        previewVid.style.pointerEvents = "none";

        if (currentBg) {
          currentBg.replaceWith(previewVid);
        } else if (!el.querySelector(".vid-placeholder-img") && !el.querySelector(".vid-placeholder-preview-video")) {
          el.prepend(previewVid);
        }
      });
  });
}

/**
 * Bulk Video Thumbnail Generator (Admin Action)
 * Processes all video cards missing a thumbnail, generates canvas/frame, and saves to DB.
 */
async function generateAllVideoThumbnails() {
  const placeholders = Array.from(
    document.querySelectorAll(".vid-placeholder[data-vid-src]")
  ).filter((el) => {
    const poster = el.getAttribute("data-vid-poster");
    const imgEl = el.querySelector(".vid-placeholder-img");
    const hasPoster = poster && poster.trim().length > 0 && !poster.includes("undefined");
    const hasImg = imgEl && imgEl.getAttribute("src") && imgEl.getAttribute("src").trim().length > 0;
    return !hasPoster && !hasImg;
  });

  if (placeholders.length === 0) {
    if (typeof showToast === "function") {
      showToast("✨ جميع بطاقات الفيديو تحتوي بالفعل على صور مصغرة!", "info");
    } else {
      alert("جميع بطاقات الفيديو تحتوي بالفعل على صور مصغرة!");
    }
    return;
  }

  const total = placeholders.length;
  let successCount = 0;
  let fallbackCount = 0;

  if (typeof showToast === "function") {
    showToast(`🎬 جاري توليد وتخزين ${total} صورة فيديو...`, "info");
  }

  for (let i = 0; i < placeholders.length; i++) {
    const el = placeholders[i];
    const videoSrc = el.getAttribute("data-vid-src");
    const productId = el.getAttribute("data-product-id");
    const productUrl = el.getAttribute("data-product-url");

    try {
      const dataUrl = await generateVideoThumbnail(videoSrc);
      if (dataUrl) {
        updateProductMemoryImage(productId, productUrl, dataUrl);

        // Update UI
        const bgEl = el.querySelector(".vid-placeholder-bg");
        const newImg = document.createElement("img");
        newImg.src = dataUrl;
        newImg.className = "vid-placeholder-img";
        if (bgEl) {
          bgEl.replaceWith(newImg);
        } else if (!el.querySelector(".vid-placeholder-img")) {
          el.prepend(newImg);
        }

        // Save to server
        const res = await fetch("/api/products/save-thumbnail", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            product_id: productId,
            product_url: productUrl,
            image_data: dataUrl,
          }),
        });

        const json = await res.json();
        if (json && json.thumbnail_url) {
          el.setAttribute("data-vid-poster", json.thumbnail_url);
          updateProductMemoryImage(productId, productUrl, json.thumbnail_url);
        }
        successCount++;
      }
    } catch (err) {
      // Fallback to video frame preview
      const bgEl = el.querySelector(".vid-placeholder-bg");
      const previewVid = document.createElement("video");
      previewVid.src = `${videoSrc}#t=0.5`;
      previewVid.preload = "metadata";
      previewVid.muted = true;
      previewVid.playsInline = true;
      previewVid.className = "vid-placeholder-img vid-placeholder-preview-video";
      previewVid.style.objectFit = "cover";
      previewVid.style.width = "100%";
      previewVid.style.height = "100%";
      previewVid.style.pointerEvents = "none";

      if (bgEl) {
        bgEl.replaceWith(previewVid);
      } else if (!el.querySelector(".vid-placeholder-img") && !el.querySelector(".vid-placeholder-preview-video")) {
        el.prepend(previewVid);
      }
      fallbackCount++;
    }
  }

  const msg = `✅ تم الانتهاء! تمت معالجة ${total} فيديو (تخزين ثابت: ${successCount}، معاينات مباشرة: ${fallbackCount})`;
  if (typeof showToast === "function") {
    showToast(msg, "success");
  } else {
    alert(msg);
  }
}

/**
 * Extracts a frame from a video URL at second 0.5 using HTML5 Video + Canvas
 */
function generateVideoThumbnail(videoUrl) {
  return new Promise((resolve, reject) => {
    const video = document.createElement("video");
    video.crossOrigin = "anonymous";
    video.src = videoUrl;
    video.muted = true;
    video.playsInline = true;
    video.preload = "metadata";

    let resolved = false;
    const timeout = setTimeout(() => {
      if (!resolved) {
        resolved = true;
        cleanup();
        reject("Timeout loading video metadata");
      }
    }, 6000);

    function cleanup() {
      video.removeEventListener("loadeddata", onLoaded);
      video.removeEventListener("seeked", onSeeked);
      video.removeEventListener("error", onError);
      video.pause();
      video.removeAttribute("src");
      video.load();
    }

    function onLoaded() {
      const seekTime = Math.min(0.5, (video.duration || 2) / 2);
      video.currentTime = seekTime;
    }

    function onSeeked() {
      if (resolved) return;
      resolved = true;
      clearTimeout(timeout);

      try {
        const canvas = document.createElement("canvas");
        canvas.width = video.videoWidth || 480;
        canvas.height = video.videoHeight || 270;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const mimeType = getPreferredMimeType();
        const dataUrl = canvas.toDataURL(mimeType, 0.8);

        cleanup();
        resolve(dataUrl);
      } catch (err) {
        cleanup();
        reject(err);
      }
    }

    function onError(e) {
      if (resolved) return;
      resolved = true;
      clearTimeout(timeout);
      cleanup();
      reject(e);
    }

    video.addEventListener("loadeddata", onLoaded);
    video.addEventListener("seeked", onSeeked);
    video.addEventListener("error", onError);

    video.load();
  });
}
