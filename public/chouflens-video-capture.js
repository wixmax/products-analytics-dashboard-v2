/**
 * chouflens-video-capture.js
 * High-performance Video Frame Extraction & Moroccan Wholesale Sourcing (ChoufLens + Mediabunny)
 *
 * Allows users to extract high-resolution frames from any paused video (or timestamp)
 * using HTML5 Canvas and Mediabunny (WebCodecs API), then instantly searches Moroccan wholesale
 * suppliers (Choufliya) for matching products, prices, WhatsApp contacts, and profit margins.
 */

const ChoufLensVideoCapture = (function () {
  let mediabunnyInstance = null;

  /**
   * Format seconds to human readable mm:ss.ms
   */
  function formatTimestamp(seconds) {
    if (isNaN(seconds) || seconds < 0) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    const ms = Math.floor((seconds % 1) * 10);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}.${ms}`;
  }

  /**
   * Dynamically loads Mediabunny via ESM CDN when needed
   */
  async function loadMediabunny() {
    if (mediabunnyInstance) return mediabunnyInstance;
    try {
      mediabunnyInstance = await import('https://esm.sh/mediabunny@latest');
      return mediabunnyInstance;
    } catch (e) {
      console.warn('Mediabunny CDN load failed, using native Canvas fallback:', e);
      return null;
    }
  }

  /**
   * Extracts frame from an active HTML5 <video> element via Canvas
   */
  function captureCanvasFrame(videoEl) {
    return new Promise((resolve, reject) => {
      try {
        if (!videoEl || videoEl.readyState < 2) {
          return reject(new Error('الفيديو غير جاهز أو لم يتم تحميل الإطارات بعد.'));
        }

        const width = videoEl.videoWidth || videoEl.clientWidth || 640;
        const height = videoEl.videoHeight || videoEl.clientHeight || 360;
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');
        if (!ctx) return reject(new Error('تعذر تهيئة سياق Canvas.'));

        ctx.drawImage(videoEl, 0, 0, width, height);

        canvas.toBlob(
          (blob) => {
            if (!blob) {
              return reject(new Error('فشل استخراج الإطار (قد يكون الفيديو محمي بسياسة CORS).'));
            }
            const timestamp = videoEl.currentTime || 0;
            const fileName = `chouflens_frame_${Math.floor(timestamp)}s_${Date.now()}.jpg`;
            const file = new File([blob], fileName, { type: 'image/jpeg' });
            resolve({
              blob,
              file,
              dataUrl: canvas.toDataURL('image/jpeg', 0.95),
              timestamp,
              width,
              height,
            });
          },
          'image/jpeg',
          0.95
        );
      } catch (err) {
        reject(err);
      }
    });
  }

  /**
   * Extracts frame at exact microsecond timestamp using Mediabunny (WebCodecs)
   */
  async function captureMediabunnyFrame(source, timestampSeconds) {
    const mb = await loadMediabunny();
    if (!mb) {
      throw new Error('مكتبة Mediabunny غير متوفرة، جاري الاعتماد على مشغل المتصفح.');
    }

    const { Input, UrlSource, BlobSource, VideoSampleSink, ALL_FORMATS } = mb;
    let inputSource;

    if (source instanceof Blob) {
      inputSource = new BlobSource(source);
    } else if (typeof source === 'string') {
      inputSource = new UrlSource(source);
    } else {
      throw new Error('مصدر الفيديو غير مدعوم في Mediabunny.');
    }

    const input = new Input({
      source: inputSource,
      formats: ALL_FORMATS,
    });

    const videoTrack = await input.getPrimaryVideoTrack();
    const sink = new VideoSampleSink(videoTrack);
    const sample = await sink.getSample(timestampSeconds);

    const canvas = document.createElement('canvas');
    canvas.width = sample.displayWidth;
    canvas.height = sample.displayHeight;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
      if (typeof sample.close === 'function') sample.close();
      throw new Error('فشل إنشاء سياق رسم Canvas.');
    }

    sample.draw(ctx, 0, 0);

    // Free WebCodecs VideoFrame resources immediately
    if (typeof sample.close === 'function') {
      sample.close();
    }

    return new Promise((resolve, reject) => {
      canvas.toBlob(
        (blob) => {
          if (!blob) return reject(new Error('فشل تصدير الإطار من Mediabunny.'));
          const fileName = `mediabunny_frame_${Math.floor(timestampSeconds)}s_${Date.now()}.jpg`;
          const file = new File([blob], fileName, { type: 'image/jpeg' });
          resolve({
            blob,
            file,
            dataUrl: canvas.toDataURL('image/jpeg', 0.95),
            timestamp: timestampSeconds,
            width: sample.displayWidth,
            height: sample.displayHeight,
          });
        },
        'image/jpeg',
        0.95
      );
    });
  }

  /**
   * Captures frame by loading video through local proxy with crossOrigin="anonymous"
   */
  function captureViaProxiedVideo(proxiedUrl, timestampSeconds) {
    return new Promise((resolve, reject) => {
      const v = document.createElement('video');
      v.crossOrigin = 'anonymous';
      v.muted = true;
      v.playsInline = true;
      v.preload = 'auto';

      let done = false;
      const timeout = setTimeout(() => {
        if (!done) {
          done = true;
          cleanup();
          reject(new Error('استغرق استخراج الإطار وقتاً طويلاً.'));
        }
      }, 8000);

      function cleanup() {
        v.onloadedmetadata = null;
        v.onseeked = null;
        v.onerror = null;
        v.pause();
        v.removeAttribute('src');
        v.load();
      }

      v.onloadedmetadata = () => {
        v.currentTime = Math.max(0, Math.min(v.duration || timestampSeconds, timestampSeconds));
      };

      v.onseeked = () => {
        if (done) return;
        done = true;
        clearTimeout(timeout);

        try {
          const canvas = document.createElement('canvas');
          canvas.width = v.videoWidth || 640;
          canvas.height = v.videoHeight || 360;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(v, 0, 0, canvas.width, canvas.height);

          canvas.toBlob((blob) => {
            cleanup();
            if (!blob) return reject(new Error('فشل تصدير صورة الإطار.'));
            const fileName = `chouflens_frame_${Math.floor(timestampSeconds)}s_${Date.now()}.jpg`;
            const file = new File([blob], fileName, { type: 'image/jpeg' });
            resolve({
              blob,
              file,
              dataUrl: canvas.toDataURL('image/jpeg', 0.95),
              timestamp: timestampSeconds,
              width: canvas.width,
              height: canvas.height,
            });
          }, 'image/jpeg', 0.95);
        } catch (err) {
          cleanup();
          reject(err);
        }
      };

      v.onerror = () => {
        if (done) return;
        done = true;
        clearTimeout(timeout);
        cleanup();
        reject(new Error('تعذر تحميل الفيديو عبر البروكسي.'));
      };

      v.src = proxiedUrl;
    });
  }

  /**
   * Main Action: Captures frame from current paused video and triggers ChoufLens modal search
   */
  async function searchFromVideo(videoEl, productContext = null) {
    if (!videoEl) return;
    videoEl.pause();

    const currentTime = videoEl.currentTime || 0;
    if (typeof showToast === 'function') {
      showToast(`📸 جاري التقاط الإطار عند ${formatTimestamp(currentTime)}...`, 'info');
    }

    try {
      let result;
      try {
        result = await captureCanvasFrame(videoEl);
      } catch (canvasErr) {
        console.info('Direct canvas capture hit CORS taint, resolving via same-origin stream:', canvasErr.message);
        const rawSrc = videoEl.currentSrc || videoEl.src;
        if (rawSrc && rawSrc.startsWith('http')) {
          const proxiedUrl = `/api/choufliya/proxy-image?url=${encodeURIComponent(rawSrc)}`;
          try {
            // Tier 2: Try Proxied HTML5 video (same-origin CORS)
            result = await captureViaProxiedVideo(proxiedUrl, currentTime);
          } catch (proxiedErr) {
            console.warn('Proxied canvas failed, falling back to Mediabunny WebCodecs:', proxiedErr);
            // Tier 3: Try Mediabunny WebCodecs
            try {
              result = await captureMediabunnyFrame(proxiedUrl, currentTime);
            } catch (mbErr) {
              throw new Error('تعذر استخراج الفريم مباشرة بسبب حماية الفيديو. يمكنك أخذ لقطة شاشة ولصقها بـ Ctrl+V داخل نافذة ChoufLens.');
            }
          }
        } else {
          throw canvasErr;
        }
      }

      // If productContext is not provided, try resolving from active product or DOM
      if (!productContext && typeof activeProduct !== 'undefined') {
        productContext = activeProduct;
      }

      // Open Choufliya modal with the captured frame
      if (typeof window.openChoufliyaModalWithFile === 'function') {
        window.openChoufliyaModalWithFile(result.file, productContext, currentTime);
      } else if (typeof window.handleChoufliyaFileSelected === 'function') {
        const modal = document.getElementById('choufliya-modal');
        if (modal) {
          modal.style.display = 'flex';
          document.body.style.overflow = 'hidden';
        }
        window.handleChoufliyaFileSelected([result.file]);
      } else {
        console.error('Choufliya modal handler not found.');
      }
    } catch (err) {
      console.error('Frame search error:', err);
      if (typeof showToast === 'function') {
        showToast(err.message, 'warning');
      } else {
        alert(err.message);
      }
    }
  }

  /**
   * Attaches a floating ChoufLens capture button to a video element or container
   */
  function attachButton(container, videoEl, productContext = null) {
    if (!container || container.querySelector('.btn-chouflens-video-capture')) return;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-chouflens-video-capture';
    btn.title = 'البحث بالجملة عن السلعة الظاهرة في هذا التوقيت من الفيديو (ChoufLens)';
    btn.innerHTML = `<span>📸 فريم الجملة</span>`;

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      e.preventDefault();
      searchFromVideo(videoEl, productContext);
    });

    if (getComputedStyle(container).position === 'static') {
      container.style.position = 'relative';
    }
    container.appendChild(btn);
  }

  /**
   * Scans document and attaches buttons to all active videos
   */
  function scanAndAttach(scope = document) {
    // 1. Details Modal Media Items
    scope.querySelectorAll('.details-media-item video').forEach((v) => {
      const parent = v.closest('.details-media-item');
      if (parent && !parent.querySelector('.btn-chouflens-video-capture')) {
        attachButton(parent, v, window.currentDetailsProduct || null);
      }
    });

    // 2. Video elements inside catalog and saved-ads placeholders
    scope.querySelectorAll('.vid-placeholder video').forEach((v) => {
      const parent = v.closest('.vid-placeholder');
      if (parent && !parent.querySelector('.btn-chouflens-video-capture')) {
        const prodId = parent.getAttribute('data-product-id');
        let prod = null;
        if (typeof catalogProducts !== 'undefined' && Array.isArray(catalogProducts)) {
          prod = catalogProducts.find((p) => String(p.id) === String(prodId));
        } else if (typeof allProducts !== 'undefined' && Array.isArray(allProducts)) {
          prod = allProducts.find((p) => String(p.id) === String(prodId));
        } else if (typeof savedProducts !== 'undefined' && Array.isArray(savedProducts)) {
          prod = savedProducts.find((p) => String(p.id) === String(prodId));
        }
        attachButton(parent, v, prod);
      }
    });
  }

  return {
    formatTimestamp,
    loadMediabunny,
    captureCanvasFrame,
    captureMediabunnyFrame,
    searchFromVideo,
    attachButton,
    scanAndAttach,
    captureFromElement: function (btn) {
      const parent =
        btn.closest('.details-media-item') ||
        btn.closest('.vid-placeholder') ||
        btn.parentElement;
      const videoEl = parent ? parent.querySelector('video') : null;
      if (videoEl) {
        searchFromVideo(videoEl, window.currentDetailsProduct || null);
      }
    },
  };
})();

// Auto-run scanner on DOM load and mutations
if (typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', () => {
    ChoufLensVideoCapture.scanAndAttach();

    // Observe DOM mutations to attach buttons to dynamically loaded videos
    const obs = new MutationObserver(() => {
      ChoufLensVideoCapture.scanAndAttach();
    });
    obs.observe(document.body, { childList: true, subtree: true });
  });
}
