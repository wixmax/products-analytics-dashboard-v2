/**
 * Shared helper functions for Marketing Strategy Analysis & Timeline Visualizations
 */

function getMonthNameAr(monthIdx) {
  const arMonths = [
    "يناير",
    "فبراير",
    "مارس",
    "أبريل",
    "مايو",
    "يونيو",
    "يوليو",
    "أغسطس",
    "سبتمبر",
    "أكتوبر",
    "نوفمبر",
    "ديسمبر",
  ];
  return arMonths[monthIdx];
}

function formatArDateString(d) {
  return `${getMonthNameAr(d.getMonth())} ${d.getDate()}, ${d.getFullYear()}`;
}

function formatMetricRange(min, max) {
  const formatNum = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
    if (num >= 1000) return (num / 1000).toFixed(1) + "k";
    return num.toFixed(0);
  };
  return `${formatNum(min)} - ${formatNum(max)}`;
}

function generateAdAnalysis(
  adCount,
  reactCount,
  avgCreatives,
  product,
  columnData,
  minDate,
  maxDate,
) {
  // --- استخراج إشارات من بيانات حقيقية ---
  const isActive =
    product?.active_ads === true ||
    product?.active_ads === 1 ||
    product?.active_ads === "t";
  const startDate = minDate || new Date();
  const endDate = maxDate || new Date();
  const lifeSpanDays = Math.round(
    (endDate - startDate) / (1000 * 60 * 60 * 24),
  );
  const lifeSpanText =
    lifeSpanDays >= 30
      ? `${Math.floor(lifeSpanDays / 30)} شهر`
      : `${lifeSpanDays} يوم`;

  // --- تحليل شكل المنحنى من columnData ---
  let trend = "unknown";
  let peakCol = 0;
  let peakIdx = 0;

  if (columnData && columnData.length >= 3) {
    const counts = columnData.map((c) => c.activeCount);
    peakCol = Math.max(...counts);
    peakIdx = counts.indexOf(peakCol);

    const firstHalf = counts.slice(0, Math.floor(counts.length / 2));
    const secondHalf = counts.slice(Math.floor(counts.length / 2));
    const firstAvg = firstHalf.reduce((a, b) => a + b, 0) / firstHalf.length;
    const secondAvg = secondHalf.reduce((a, b) => a + b, 0) / secondHalf.length;
    const lastVal = counts[counts.length - 1];
    const nonZero = counts.filter((c) => c > 0).length;

    if (nonZero <= 2) {
      trend = "testing"; // اختبار محدود
    } else if (peakIdx <= 2 && secondAvg < firstAvg * 0.5) {
      trend = "peak_then_drop"; // ذروة مبكرة ثم انخفاض
    } else if (secondAvg > firstAvg * 1.3) {
      trend = "scaling"; // تصاعد = scaling
    } else if (lastVal === 0 && !isActive) {
      trend = "stopped"; // توقف كامل
    } else if (reactCount > 0) {
      trend = "reactivated"; // إعادة تنشيط
    } else if (Math.abs(secondAvg - firstAvg) / Math.max(firstAvg, 1) < 0.3) {
      trend = "stable"; // استقرار
    } else {
      trend = "fluctuating"; // تذبذب
    }
  }

  // --- بناء التحليل بناءً على الإشارات الحقيقية ---
  let badge = "";
  let text = "";

  switch (trend) {
    case "scaling":
      badge = `<span class="strategy-badge" style="background:rgba(16,185,129,0.15);color:var(--color-success)">🚀 توسع نشط (Scaling)</span>`;
      text = `يشهد هذا الإعلان <b>تصاعداً ملحوظاً</b> في عدد الكرياتيف النشطة مع مرور الوقت، وهي علامة واضحة على <b>Scaling</b> — أي أن المعلن يضخ ميزانيات متزايدة لأن الأداء مربح. المنتج نشط منذ <b>${lifeSpanText}</b> بمعدل <b>${avgCreatives} كرياتيف</b>، وهذا مستوى ثقة عالٍ.`;
      break;
    case "peak_then_drop":
      badge = `<span class="strategy-badge" style="background:rgba(245,158,11,0.15);color:var(--color-warning)">⚡ ذروة ثم تصفية (Testing → Filtering)</span>`;
      text = `بدأ المعلن بـ<b>اختبار مكثف (High Peak)</b> في مرحلة الإطلاق ثم أوقف الإعلانات الخاسرة تدريجياً. الحالة الحالية <b>${isActive ? "🟢 لا يزال نشطاً" : "🔴 متوقف حالياً"}</b>. إذا كانت الإعلانات المتبقية لا تزال تعمل فهذا يعني أن المعلن وصل لـ<b>Winning Creatives</b>.`;
      break;
    case "testing":
      badge = `<span class="strategy-badge" style="background:rgba(99,102,241,0.15);color:#6366f1">🔬 مرحلة اختبار (Testing Phase)</span>`;
      text = `يبدو أن المعلن لا يزال في <b>مرحلة اختبار السوق</b> — عدد الإعلانات محدود (<b>${adCount} إعلان</b>) ومدة النشاط <b>${lifeSpanText}</b>. لم تصل الحملة بعد لمرحلة الـ Scaling، لكن وجودها يعني أن المعلن يدرس الاستجابة.`;
      break;
    case "stopped":
      badge = `<span class="strategy-badge" style="background:rgba(239,68,68,0.15);color:var(--color-error)">⏸️ متوقف (Paused)</span>`;
      text = `<b>الإعلانات متوقفة حالياً</b> بعد فترة نشاط امتدت <b>${lifeSpanText}</b>. السبب المحتمل: <b>نفاد المخزون</b>، إعادة هيكلة الحملة، أو انتهاء الموسم. المنتج كان يُعلَن عنه بـ<b>${adCount} إعلان</b> — إذا عاد النشاط فهو إشارة شراء قوية.`;
      break;
    case "reactivated":
      badge = `<span class="strategy-badge" style="background:rgba(16,185,129,0.2);color:var(--color-success)">🔄 إعادة تنشيط (${reactCount}x)</span>`;
      text = `<b>🔥 الإشارة الذهبية!</b> رُصدت <b>${reactCount} أحداث إعادة تنشيط</b> بعد فترات خمول — وهذا الدليل الأقوى على ربحية المنتج. المعلن أوقف الحملة مؤقتاً (غالباً بسبب <b>نفاد المخزون</b>) ثم أعادها بمجرد توفر البضاعة. مدة الحياة الكلية للإعلان: <b>${lifeSpanText}</b>.`;
      break;
    case "stable":
      badge = `<span class="strategy-badge" style="background:rgba(59,130,246,0.15);color:#3b82f6">📊 استقرار (Steady State)</span>`;
      text = `يُظهر هذا الإعلان <b>استقراراً إعلانياً منتظماً</b> على مدار <b>${lifeSpanText}</b> بمعدل <b>${avgCreatives} كرياتيف</b>. الاستقرار = ربحية متواصلة، لأن المعلنين لا يستمرون في الدفع لإعلانات خاسرة.`;
      break;
    case "fluctuating":
      badge = `<span class="strategy-badge" style="background:rgba(245,158,11,0.15);color:var(--color-warning)">📈 متذبذب (A/B Testing)</span>`;
      text = `يتذبذب نشاط الإعلان بشكل غير منتظم على مدار <b>${lifeSpanText}</b>، مما يشير إلى <b>اختبارات A/B مستمرة</b> — المعلن يجرب مواد إبداعية مختلفة لإيجاد أفضل صيغة. <b>${adCount} إعلان</b> بمتوسط <b>${avgCreatives} كرياتيف</b>.`;
      break;
    default:
      badge = `<span class="strategy-badge">📋 بيانات غير كافية</span>`;
      text = `البيانات المتاحة تشمل <b>${adCount} إعلان</b> بمتوسط <b>${avgCreatives} كرياتيف</b> ومدة <b>${lifeSpanText}</b>. <b>الحالة: ${isActive ? "🟢 نشط" : "🔴 متوقف"}</b>. لتحليل أعمق يُفضَّل الضغط على "🔄 تحديث النشاط" لجلب البيانات الحية.`;
  }

  return `${badge}<p style="margin-top:10px">${text}</p>`;
}

function renderTimelineAndMetrics(product, entries) {
  entries.sort((a, b) => new Date(a.ad_start_date) - new Date(b.ad_start_date));

  const dates = [];
  entries.forEach((e) => {
    if (e.ad_start_date) dates.push(new Date(e.ad_start_date));
    if (e.ad_end_date) dates.push(new Date(e.ad_end_date));
  });

  const minDate = dates.length > 0 ? new Date(Math.min(...dates)) : new Date();
  const maxDate = dates.length > 0 ? new Date(Math.max(...dates)) : new Date();

  const timeSpanMs = maxDate - minDate;
  const daysTotal = Math.max(15, Math.ceil(timeSpanMs / (1000 * 60 * 60 * 24)));

  const numColumns = 12;
  const daysPerCol = Math.ceil(daysTotal / numColumns);
  const columnData = [];
  const today = new Date();

  for (let i = 0; i < numColumns; i++) {
    const colStart = new Date(minDate);
    colStart.setDate(colStart.getDate() + i * daysPerCol);
    const colEnd = new Date(colStart);
    colEnd.setDate(colEnd.getDate() + daysPerCol);

    let activeCount = 0;
    let endedInCol = 0;
    let startedInCol = 0;

    entries.forEach((e) => {
      const eStart = new Date(e.ad_start_date);
      const eEnd = e.ad_end_date ? new Date(e.ad_end_date) : today;

      if (eStart <= colEnd && eEnd >= colStart) {
        activeCount++;
      }

      if (
        e.ad_end_date &&
        new Date(e.ad_end_date) >= colStart &&
        new Date(e.ad_end_date) <= colEnd
      ) {
        endedInCol++;
      }

      if (eStart >= colStart && eStart <= colEnd) {
        startedInCol++;
      }
    });

    columnData.push({
      label: `${colStart.getDate()} ${getMonthNameAr(colStart.getMonth())}`,
      activeCount,
      ended: endedInCol > 0,
      started: startedInCol > 0,
      start: colStart,
      end: colEnd,
    });
  }

  let reactivations = 0;
  let inGap = false;

  columnData.forEach((col, idx) => {
    if (col.activeCount === 0) {
      inGap = true;
    } else if (inGap && col.activeCount > 0) {
      reactivations++;
      inGap = false;
      col.isReactivation = true;
    }
  });

  const maxActive = Math.max(...columnData.map((c) => c.activeCount), 1);
  const chartContainer = document.getElementById("details-chart");

  let chartHtml = "";
  columnData.forEach((col) => {
    const heightPercent = (col.activeCount / maxActive) * 100;
    const isReact = col.isReactivation ? "reactivation" : "";
    const dotHtml = col.ended ? `<div class="details-chart-dot"></div>` : "";

    chartHtml += `
      <div class="details-chart-bar-wrapper">
        <div class="chart-tooltip">${col.label}: ${col.activeCount} إعلان نشط</div>
        <div class="details-chart-bar ${isReact}" style="height: ${Math.max(8, heightPercent)}%;">
          ${dotHtml}
        </div>
        <span class="chart-label" style="font-size:0.55rem; width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${col.label}</span>
      </div>
    `;
  });
  chartContainer.innerHTML = chartHtml;

  const uniqueVideos =
    [...new Set(entries.map((e) => e.ad_video_urls).filter(Boolean))].length ||
    1;
  const adsCount = product.ads_count || entries.length || 12;
  const avgCreatives = product.avg_creatives || 1;

  const viewsMinVal = (adsCount * 9.5 + uniqueVideos * 5) * 1000;
  const viewsMaxVal = viewsMinVal * 10;
  const formattedViews = formatMetricRange(viewsMinVal, viewsMaxVal);
  const formatEng = formatMetricRange(viewsMinVal * 0.07, viewsMaxVal * 0.07);

  document.getElementById("details-views").textContent = formattedViews;
  document.getElementById("details-engagement").textContent = formatEng;
  document.getElementById("details-first-seen").textContent =
    formatArDateString(minDate);
  document.getElementById("details-last-seen").textContent =
    formatArDateString(maxDate);
  document.getElementById("details-max-creatives").textContent =
    `${adsCount} كرياتيف`;
  document.getElementById("details-reactivations").textContent =
    `${reactivations} أحداث`;

  const analysisText = generateAdAnalysis(
    adsCount,
    reactivations,
    avgCreatives,
    product,
    columnData,
    minDate,
    maxDate,
  );
  document.getElementById("details-analysis-text").innerHTML = analysisText;

  // Build the complete database capsule containing fetched & computed metrics
  currentProductDetailsWithAnalysis = {
    ...product,
    activityEntries: entries,
    computed_metrics: {
      estimated_views: formattedViews,
      estimated_engagement: formatEng,
      first_seen: minDate.toISOString(),
      last_seen: maxDate.toISOString(),
      creatives_count: adsCount,
      unique_videos_count: uniqueVideos,
      reactivations_count: reactivations,
      marketing_analysis: analysisText.replace(/<[^>]*>/g, ""), // Strip HTML tags
    },
  };

  // Re-populate all properties in scrollable container to include computed fields
  const rawDataContainer = document.getElementById("details-raw-data-list");
  if (rawDataContainer) {
    let listHtml = "";
    // Display base properties
    for (const [key, value] of Object.entries(product)) {
      if (value !== null && value !== undefined && value !== "") {
        let valStr = String(value);
        if (valStr.length > 80) valStr = valStr.slice(0, 80) + "...";
        listHtml += `
          <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color); padding: 4px 0; font-family: sans-serif; gap: 10px;">
            <span style="color: var(--color-primary); font-weight: 600; text-transform: capitalize;">${key}:</span>
            <span style="word-break: break-all; text-align: right; color: var(--color-text-main); font-weight: 500;">${valStr}</span>
          </div>
        `;
      }
    }
    // Display computed metrics
    for (const [key, value] of Object.entries(
      currentProductDetailsWithAnalysis.computed_metrics,
    )) {
      if (value !== null && value !== undefined && value !== "") {
        let valStr = String(value);
        if (valStr.length > 80) valStr = valStr.slice(0, 80) + "...";
        listHtml += `
          <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color); padding: 4px 0; font-family: sans-serif; gap: 10px; background: var(--bg-card); opacity: 0.9;">
            <span style="color: var(--color-success); font-weight: 600; text-transform: capitalize;">computed_${key}:</span>
            <span style="word-break: break-all; text-align: right; color: var(--color-text-main); font-weight: 500;">${valStr}</span>
          </div>
        `;
      }
    }
    rawDataContainer.innerHTML =
      listHtml ||
      `<div style="text-align: center; padding: 10px; color: var(--color-text-muted);">لا توجد بيانات إضافية</div>`;
  }
}
