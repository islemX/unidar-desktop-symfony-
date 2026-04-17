/*
 * UNIDAR Voice Commander — Pro Edition
 * ─────────────────────────────────────────────────────────────────────────────
 *  Phase 1 : Welcome & Briefings        (page-aware welcome card + TTS)
 *  Phase 2 : STT core                   (Web Speech API + pageContext)
 *  Phase 3 : Intent execution           (FILL_FIELD, DICTATE, SELECT…)
 *             ├ Field Map module
 *             ├ Dictation module
 *             ├ Select / Click mode module
 *             └ Confirmation flow
 *  Phase 4 : Advanced responder         (pageContext builder)
 *  TTS (enhanced voice selection)
 *  Visualiser
 *  State + UI helpers (expanded)
 *  Command palette (per-page aware)
 *  Public API
 * ─────────────────────────────────────────────────────────────────────────────
 */
(function () {
  'use strict';

  // ── Config / Constants ────────────────────────────────────────────────────
  const ENDPOINTS = {
    intent: '/voice/intent',
    health: '/voice/health',
  };
  const STORAGE_KEY       = 'unidar.voice.prefs.v1';
  const DEFAULT_LOCALE    = (document.documentElement.lang || 'en').slice(0, 2);
  const SUPPORTED_LOCALES = ['en', 'fr', 'ar'];

  // ── Multilingual UI strings ───────────────────────────────────────────────
  const UI = {
    en: {
      tip:          'Hold Space or click to speak',
      listening:    'Listening…',
      thinking:     'Thinking…',
      speaking:     'Speaking…',
      idle:         'Ready',
      dictation:    'Dictation',
      confirm:      'Confirm?',
      select:       'Select',
      denied:       'Microphone permission denied',
      unsupported:  'Voice not supported in this browser — use the typed palette (Ctrl+K)',
      placeholder:  'Type a command — try "open listings" or "search Tunis"',
      hint:         'Try: "show me listings", "open messages", "under 500", "help"',
      help:         'Navigate, search, filter, switch language, read page, or open messages. Try: \'open listings\', \'under 500\', \'help\'.',
      unknown:      'I didn\'t catch that.',
      paletteTitle: 'Command Palette',
    },
    fr: {
      tip:          'Maintenez Espace ou cliquez pour parler',
      listening:    'Écoute…',
      thinking:     'Réflexion…',
      speaking:     'Parole…',
      idle:         'Prêt',
      dictation:    'Dictée',
      confirm:      'Confirmer?',
      select:       'Sélection',
      denied:       'Permission du microphone refusée',
      unsupported:  'Voix non supportée — utilisez la palette (Ctrl+K)',
      placeholder:  'Tapez une commande — « ouvrir logements », « chercher Tunis »',
      hint:         'Essayez : « voir les logements », « ouvrir messages », « moins de 500 »',
      help:         'Naviguer, chercher, filtrer, changer de langue, lire la page. Essayez : « ouvrir logements », « moins de 500 », « aide ».',
      unknown:      'Je n\'ai pas compris.',
      paletteTitle: 'Palette de commandes',
    },
    ar: {
      tip:          'اضغط مسافة مطولاً أو انقر للتحدث',
      listening:    'جاري الاستماع…',
      thinking:     'جاري التفكير…',
      speaking:     'جاري التحدث…',
      idle:         'جاهز',
      dictation:    'إملاء',
      confirm:      'تأكيد؟',
      select:       'تحديد',
      denied:       'تم رفض إذن الميكروفون',
      unsupported:  'الصوت غير مدعوم — استخدم Ctrl+K',
      placeholder:  'اكتب أمراً — «افتح الإعلانات» أو «ابحث عن تونس»',
      hint:         'جرب: «افتح الإعلانات»، «الرسائل»، «أقل من 500»، «مساعدة»',
      help:         'التنقل، البحث، التصفية، تغيير اللغة، قراءة الصفحة.',
      unknown:      'لم أفهم.',
      paletteTitle: 'لوحة الأوامر',
    },
  };

  // ── State management ──────────────────────────────────────────────────────
  // States: idle | listening | thinking | speaking | dictation | confirm | select
  const prefs = loadPrefs();
  let state           = 'idle';
  let stickyMode      = null;    // last active sticky state: dictation | select | confirm | null
  let confirmPending  = null;    // { reply, fn, timer }
  let dictationTarget = null;    // HTMLElement being dictated into
  let selectElements  = [];      // array of { el, badge, n } for overlay mode
  let dictationInterim = '';     // last interim transcript for live preview in field

  // STT / audio
  let recognition = null;
  let audioCtx = null, analyser = null, micStream = null, rafId = null;
  let premiumAvailable = false;

  // ── Bootstrap ─────────────────────────────────────────────────────────────
  // Use readyState check: 'defer' scripts run after parsing, but the
  // DOMContentLoaded event may have already fired by that time.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    // DOM already parsed — run immediately
    init();
  }

  function init() {
    if (document.getElementById('voice-orb-root')) return; // already injected
    injectDom();
    wireEvents();
    probeHealth();
    // Phase 1: briefing card after short delay
    setTimeout(readBriefing, 1400);
  }

  function tr(key) { return (UI[prefs.locale] || UI.en)[key] || UI.en[key] || key; }

  function loadPrefs() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) return { locale: DEFAULT_LOCALE, tts: true, ...JSON.parse(raw) };
    } catch (_) {}
    const locale = SUPPORTED_LOCALES.includes(DEFAULT_LOCALE) ? DEFAULT_LOCALE : 'en';
    return { locale, tts: true };
  }

  function savePrefs() {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs)); } catch (_) {}
  }

  // ── Phase 1: Welcome & Briefings ─────────────────────────────────────────

  // Browsers block speechSynthesis.speak() until a user gesture has occurred.
  // We keep the pending briefing text and play it on the first interaction.
  let _pendingBriefing = null;
  let _ttsUnlocked     = false;

  function unlockTts() {
    if (_ttsUnlocked) return;
    _ttsUnlocked = true;
    // Load voices early (some browsers need this before first speak)
    if ('speechSynthesis' in window) {
      window.speechSynthesis.getVoices();
    }
    // If a briefing was queued, speak it now
    if (_pendingBriefing) {
      speak(_pendingBriefing.text, _pendingBriefing.lang);
      _pendingBriefing = null;
    }
    // Remove the one-shot listeners
    ['click', 'keydown', 'touchstart', 'pointerdown'].forEach(ev =>
      document.removeEventListener(ev, unlockTts, { capture: true })
    );
  }

  // Register unlock listeners as early as possible
  ['click', 'keydown', 'touchstart', 'pointerdown'].forEach(ev =>
    document.addEventListener(ev, unlockTts, { capture: true, once: true })
  );

  function readBriefing() {
    const pg = window.__vcPage__;
    if (!pg || !pg.briefing) return;
    const text = pg.briefing[prefs.locale] || pg.briefing.en;
    if (!text) return;
    showBriefingCard(text);
    // If TTS is already unlocked (user interacted), speak immediately.
    // Otherwise queue it for playback on first gesture.
    if (_ttsUnlocked) {
      setTimeout(() => speak(text, prefs.locale), 300);
    } else {
      _pendingBriefing = { text, lang: prefs.locale };
    }
  }

  function showBriefingCard(text) {
    const existing = document.getElementById('vc-briefing-card');
    if (existing) existing.remove();
    const card = document.createElement('div');
    card.id = 'vc-briefing-card';
    card.innerHTML = `<span class="vc-briefing-icon">📢</span><span class="vc-briefing-text">${escapeHtml(text)}</span>`;
    document.body.appendChild(card);
    setTimeout(() => card.classList.add('show'), 50);
    setTimeout(() => {
      card.classList.remove('show');
      setTimeout(() => card.remove(), 400);
    }, 6000);
  }

  // ── DOM injection ─────────────────────────────────────────────────────────
  function injectDom() {
    const root = document.createElement('div');
    root.id = 'voice-orb-root';
    root.innerHTML = `
      <button id="voice-orb" type="button" aria-label="Voice commander" title="${escapeHtml(tr('tip'))}">
        <svg class="voice-orb-icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 2a3 3 0 0 1 3 3v7a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3Zm7 10a1 1 0 1 1 2 0 9 9 0 0 1-8 8.94V22h3a1 1 0 1 1 0 2H8a1 1 0 1 1 0-2h3v-1.06A9 9 0 0 1 3 12a1 1 0 1 1 2 0 7 7 0 0 0 14 0Z"/>
        </svg>
        <span class="voice-orb-ring r1"></span>
        <span class="voice-orb-ring r2"></span>
        <span class="voice-orb-ring r3"></span>
        <canvas class="voice-orb-wave" width="96" height="96" aria-hidden="true"></canvas>
      </button>
      <div id="vc-mode-indicator"></div>

      <div id="voice-ribbon" role="status" aria-live="polite">
        <div class="voice-ribbon-chip" data-role="state">${escapeHtml(tr('idle'))}</div>
        <div class="voice-ribbon-body">
          <div class="voice-ribbon-transcript" data-role="transcript">${escapeHtml(tr('hint'))}</div>
          <div class="voice-ribbon-intent" data-role="intent"></div>
        </div>
        <button class="voice-ribbon-close" type="button" aria-label="Close">×</button>
      </div>

      <dialog id="voice-palette" aria-label="${escapeHtml(tr('paletteTitle'))}">
        <form method="dialog" class="voice-palette-form">
          <div class="voice-palette-head">
            <svg viewBox="0 0 24 24" width="18" height="18"><path d="M21 21l-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <input id="voice-palette-input" type="text" autocomplete="off" placeholder="${escapeHtml(tr('placeholder'))}">
            <kbd>Esc</kbd>
          </div>
          <ul id="voice-palette-suggestions" role="listbox"></ul>
        </form>
      </dialog>
    `;
    document.body.appendChild(root);
  }

  // ── Events ────────────────────────────────────────────────────────────────
  function wireEvents() {
    const orb         = document.getElementById('voice-orb');
    const ribbonClose = document.querySelector('.voice-ribbon-close');
    const palette     = document.getElementById('voice-palette');
    const paletteInput = document.getElementById('voice-palette-input');

    orb.addEventListener('click', toggleListening);
    ribbonClose.addEventListener('click', hideRibbon);

    let spaceHeld = false;
    document.addEventListener('keydown', (e) => {
      if (isTyping(e.target)) return;
      if (e.code === 'Space' && !spaceHeld && !e.repeat) {
        spaceHeld = true;
        e.preventDefault();
        startListening();
      } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openPalette();
      } else if (e.altKey && e.key.toLowerCase() === 's') {
        e.preventDefault();
        if (state === 'select') exitSelectMode();
        else enterSelectMode();
      } else if (e.key === 'Escape' && state === 'listening') {
        stopListening();
      }
    });
    document.addEventListener('keyup', (e) => {
      if (e.code === 'Space' && spaceHeld) {
        spaceHeld = false;
        if (state === 'listening') stopListening();
      }
    });

    paletteInput.addEventListener('input', () => renderSuggestions(paletteInput.value));
    palette.addEventListener('close', () => { paletteInput.value = ''; });
    paletteInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const v = paletteInput.value.trim();
        if (v) { palette.close(); handleTranscript(v); }
      }
    });
  }

  function isTyping(el) {
    if (!el) return false;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || el.isContentEditable;
  }

  // ── Health probe ──────────────────────────────────────────────────────────
  async function probeHealth() {
    try {
      const r = await fetch(ENDPOINTS.health, { credentials: 'same-origin' });
      if (r.ok) {
        const j = await r.json();
        premiumAvailable = !!(j.premium && j.premium.available);
        if (premiumAvailable) document.getElementById('voice-orb').classList.add('premium');
      }
    } catch (_) {}
  }

  // ── Phase 2: STT core ─────────────────────────────────────────────────────
  function toggleListening() {
    if (state === 'listening') return stopListening();
    if (state !== 'idle' && !isStickyState(stickyMode)) return;
    startListening();
  }

  function startListening() {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) {
      setState('idle');
      showRibbon(tr('unsupported'), '', 'error');
      openPalette();
      return;
    }
    try {
      recognition = new SR();
      recognition.lang = localeToBcp47(prefs.locale);
      // Dictation: keep the mic open continuously so short pauses don't end the session.
      // Other modes: single-shot — more reliable intent handling.
      recognition.continuous     = (stickyMode === 'dictation');
      recognition.interimResults = true;
      recognition.maxAlternatives = 3;

      let finalText = '';
      recognition.onstart  = () => {
        setState('listening');
        const hint = isStickyState(stickyMode)
          ? tr('listening') + ' — say "stop" or "done" to exit'
          : tr('listening');
        showRibbon(hint, stickyMode ? stickyMode.toUpperCase() : '', 'listening');
        startVisualiser();
      };
      recognition.onresult = (e) => {
        let interim = '';
        let newFinal = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
          const res = e.results[i];
          if (res.isFinal) newFinal += res[0].transcript;
          else interim += res[0].transcript;
        }
        if (newFinal) finalText += newFinal;
        updateTranscript((finalText + ' ' + interim).trim());

        // Dictation: commit each final chunk immediately, keep session alive.
        if (stickyMode === 'dictation') {
          if (newFinal.trim()) {
            commitDictationChunk(newFinal.trim());
            finalText = ''; // don't double-handle when session ends
          } else if (interim && dictationTarget) {
            showDictationInterim(interim);
          }
        }
      };
      recognition.onerror  = (e) => {
        stopVisualiser();
        if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
          // Permission error — exit sticky mode too
          stickyMode = null;
          showRibbon(tr('denied'), '', 'error');
          setState('idle');
        } else if (e.error === 'no-speech') {
          // No speech — restart if sticky, otherwise idle
          if (isStickyState(stickyMode)) {
            setState(stickyMode); // restore from stale 'listening'
            scheduleRestart();
          } else {
            setState('idle');
          }
        } else if (e.error === 'aborted') {
          // Aborted manually — respect it
          if (isStickyState(stickyMode)) setState(stickyMode);
          else setState('idle');
        } else {
          showRibbon(e.error, '', 'error');
          if (isStickyState(stickyMode)) {
            setState(stickyMode);
            scheduleRestart();
          } else {
            setState('idle');
          }
        }
      };
      recognition.onend    = () => {
        stopVisualiser();
        if (finalText.trim()) {
          handleTranscript(finalText.trim());
        } else {
          // No speech — mic ended with empty result
          if (isStickyState(stickyMode)) {
            setState(stickyMode); // restore from stale 'listening' state
            scheduleRestart();
          } else {
            setState('idle');
          }
        }
      };
      recognition.start();
    } catch (err) {
      console.warn('[voice] recognition failed', err);
      setState('idle');
    }
  }

  function stopListening() {
    try { recognition && recognition.stop(); } catch (_) {}
  }

  function localeToBcp47(l) {
    return { en: 'en-US', fr: 'fr-FR', ar: 'ar-TN' }[l] || 'en-US';
  }

  // ── Continuous / sticky-mode helpers ─────────────────────────────────────

  /** Returns true for modes where the mic should stay open automatically. */
  function isStickyState(s) {
    return s === 'dictation' || s === 'select' || s === 'confirm';
  }

  /**
   * If currently in a sticky mode, restart listening after a short pause.
   * Called after every transcript is processed and after TTS ends.
   */
  function scheduleRestart() {
    if (!isStickyState(stickyMode)) return;
    // Shorter delay for select (we just clicked a badge, user is already on next word)
    // and confirm (yes/no follow-up needs immediate re-listen).
    // Dictation uses continuous=true so it usually doesn't need a restart; only on error/end.
    const delay = stickyMode === 'select' ? 120
                : stickyMode === 'confirm' ? 150
                : 250;
    clearTimeout(scheduleRestart._t);
    scheduleRestart._t = setTimeout(() => {
      if (!isStickyState(stickyMode)) return;
      if (state === 'listening' || state === 'thinking' || state === 'speaking') return;
      startListening();
    }, delay);
  }

  /**
   * Universal exit for whichever sticky mode is currently active.
   * Triggered by "quit", "stop", "done", "exit" while in any sticky mode.
   */
  function exitCurrentMode() {
    const was = stickyMode;
    stickyMode = null;
    if (was === 'dictation')  stopDictationMode();
    else if (was === 'select') exitSelectMode();
    else if (was === 'confirm') {
      if (confirmPending) clearTimeout(confirmPending.timer);
      confirmPending = null;
      setState('idle');
    } else {
      setState('idle');
    }
    const msg = { en: 'Mode stopped. Microphone off.', fr: 'Mode arrêté. Microphone désactivé.', ar: 'تم إيقاف الوضع. الميكروفون متوقف.' };
    showRibbon(msg[prefs.locale] || msg.en, 'Stopped', 'success');
    speak(msg[prefs.locale] || msg.en, prefs.locale);
  }

  // ── Transcript handling ───────────────────────────────────────────────────
  async function handleTranscript(text) {
    // ── Select mode: intercept bare numbers client-side — no server roundtrip ──
    if (stickyMode === 'select') {
      const num = parseSpokenNumber(text.trim());
      if (num !== null && num > 0) {
        clickSelectElement(num);
        setState('select');
        scheduleRestart();
        return;
      }
    }

    // ── Universal exit: "quit / stop / done / exit" from any sticky mode ──
    if (isStickyState(stickyMode) && /^(quit|stop|exit|done|cancel|finish|arrêter|arrêt|quitter|terminer|خروج|إيقاف|انتهى|كفى)$/i.test(text.trim())) {
      exitCurrentMode();
      return;
    }

    // ── Confirm flow: same stickyMode check ──
    if (stickyMode === 'confirm' || confirmPending) {
      handleConfirmResponse(text);
      if (stickyMode === 'confirm') setState('confirm');
      scheduleRestart();
      return;
    }

    setState('thinking');
    updateTranscript(text);

    const pageContext = buildPageContext();

    try {
      const r = await fetch(ENDPOINTS.intent, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          text,
          route:  window.location.pathname,
          locale: prefs.locale,
          pageContext,
        }),
      });
      if (!r.ok) throw new Error('intent ' + r.status);
      const j = await r.json();
      showRibbon(j.reply || '', humanIntent(j.intent), j.intent === 'UNKNOWN' ? 'error' : 'success');
      executeIntent(j);
      speak(j.reply || '', j.lang || prefs.locale);
      // speak() calls scheduleRestart() after TTS; also call here if no TTS
      if (!j.reply) scheduleRestart();
    } catch (err) {
      console.warn('[voice] intent failed', err);
      showRibbon(tr('unknown'), '', 'error');
      if (!isStickyState(stickyMode)) setState('idle');
      scheduleRestart();
    }
  }

  // ── Phase 4: pageContext builder ──────────────────────────────────────────
  function buildPageContext() {
    const pg = window.__vcPage__ || {};
    const inputs = Array.from(document.querySelectorAll('input[name], textarea[name], select[name]'))
      .map(el => el.getAttribute('data-voice-label') || el.getAttribute('placeholder') || el.name)
      .filter(Boolean).slice(0, 10);
    const buttons = Array.from(document.querySelectorAll('button:not([aria-hidden]), a.btn'))
      .map(el => el.textContent.trim()).filter(s => s.length > 0 && s.length < 40).slice(0, 8);
    return {
      page:         pg.page || document.title,
      itemCount:    pg.itemCount || document.querySelectorAll('[data-listing-id], .listing-card').length || null,
      userRole:     pg.role || null,
      currentMode:  state,
      focusedField: document.activeElement
        ? (document.activeElement.getAttribute('data-voice-label') || document.activeElement.getAttribute('placeholder') || null)
        : null,
      visibleInputs:  inputs,
      visibleButtons: buttons,
    };
  }

  // ── Phase 3: Intent execution ─────────────────────────────────────────────
  function executeIntent(result) {
    const { intent, params = {}, url } = result;
    switch (intent) {

      // ── Navigation ──
      case 'NAVIGATE_HOME':
      case 'NAVIGATE_LISTINGS':
      case 'NAVIGATE_MY_LISTINGS':
      case 'NAVIGATE_SAVED':
      case 'NAVIGATE_ROOMMATES':
      case 'NAVIGATE_MESSAGES':
      case 'NAVIGATE_CONTRACTS':
      case 'NAVIGATE_DASHBOARD':
      case 'NAVIGATE_PREMIUM':
      case 'NAVIGATE_VERIFICATION':
      case 'LOGIN':
      case 'REGISTER':
      case 'SEARCH':
      case 'FILTER_LISTINGS':
        if (url) delayedNavigate(url);
        break;

      case 'LOGOUT': {
        triggerConfirm(
          result.reply || (prefs.locale === 'fr' ? 'Déconnexion ? Dites oui pour confirmer.' : prefs.locale === 'ar' ? 'تسجيل الخروج؟ قل نعم للتأكيد.' : 'Log out? Say yes to confirm.'),
          () => { if (url) window.location.assign(url); }
        );
        break;
      }

      case 'REFRESH':
        setTimeout(() => window.location.reload(), 500);
        break;

      case 'GO_BACK':
        setTimeout(() => window.history.length > 1 ? window.history.back() : window.location.assign('/'), 500);
        break;

      // ── Scroll ──
      case 'SCROLL_DOWN':
        window.scrollBy({ top: window.innerHeight * 0.8, behavior: 'smooth' });
        break;
      case 'SCROLL_UP':
        window.scrollBy({ top: -window.innerHeight * 0.8, behavior: 'smooth' });
        break;

      // ── Open listing by index ──
      case 'OPEN_LISTING_N': {
        const n = Math.max(1, parseInt(params.n, 10) || 1);
        const cards = document.querySelectorAll('[data-listing-id], .listing-card a, a.listing-card, a[href*="/listings/"]');
        const target = cards[n - 1];
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          setTimeout(() => { if (target.click) target.click(); if (target.href) window.location.assign(target.href); }, 450);
        } else {
          showRibbon('No listing found at position ' + n, '', 'error');
        }
        break;
      }

      // ── Read page ──
      case 'READ_PAGE': {
        const main = document.querySelector('main, article, .container') || document.body;
        const text = (main.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 1200);
        speak(text, prefs.locale);
        break;
      }

      // ── Switch language ──
      case 'SWITCH_LANGUAGE':
        if (params.lang && SUPPORTED_LOCALES.includes(params.lang)) {
          prefs.locale = params.lang; savePrefs();
          if (window.i18n && typeof window.i18n.setLang === 'function') window.i18n.setLang(params.lang);
          else document.documentElement.lang = params.lang;
        }
        break;

      // ── Help ──
      case 'HELP':
        showRibbon(tr('help'), '', 'success');
        break;

      // ── Fill / Clear / Read Field ──
      case 'FILL_FIELD': {
        const field = (params.field || '').toLowerCase().trim();
        const value = params.value || '';
        const el = findFieldByName(field);
        if (el) {
          fillElement(el, value);
          highlightField(el);
          showRibbon(`Filled "${field}" with "${value}"`, 'Fill Field', 'success');
        } else {
          showRibbon(`Field "${field}" not found on this page.`, 'Fill Field', 'error');
        }
        break;
      }

      case 'CLEAR_FIELD': {
        const el = findFieldByName((params.field || '').toLowerCase());
        if (el) {
          el.value = '';
          el.dispatchEvent(new Event('input', { bubbles: true }));
          highlightField(el);
        }
        break;
      }

      case 'READ_FIELD': {
        const el = findFieldByName((params.field || '').toLowerCase());
        if (el) {
          const v = el.value || el.textContent || '(empty)';
          speak(`${params.field} contains: ${v}`, prefs.locale);
        }
        break;
      }

      // ── Dictation ──
      case 'DICTATION_START':
        startDictationMode();
        break;

      case 'DICTATION_STOP':
        stopDictationMode();
        break;

      case 'DICTATE_TEXT': {
        const target = dictationTarget || document.querySelector('input:focus, textarea:focus, select:focus');
        if (target) {
          if (target.tagName === 'SELECT') {
            selectDropdownOption(target, params.text || '');
          } else {
            target.value += (target.value ? ' ' : '') + (params.text || '');
            target.dispatchEvent(new Event('input', { bubbles: true }));
            triggerAutofill(target);
            highlightField(target);
          }
        }
        break;
      }

      // ── Pick dropdown option (explicit "choose X") ──
      case 'PICK_OPTION': {
        const sel = dictationTarget || document.querySelector('select:focus');
        if (sel && sel.tagName === 'SELECT') {
          selectDropdownOption(sel, params.option || '');
        } else {
          // No dropdown focused — try to find nearest <select> on page
          const nearest = document.querySelector('select:not([type=hidden])');
          if (nearest) selectDropdownOption(nearest, params.option || '');
          else showRibbon('No dropdown found on this page.', 'Pick', 'error');
        }
        break;
      }

      // ── Field navigation ──
      case 'NEXT_FIELD': {
        const fields = Array.from(document.querySelectorAll('input:not([type=hidden]), textarea, select'))
          .filter(el => !el.disabled && el.offsetParent !== null);
        const idx  = fields.indexOf(document.activeElement);
        const next = fields[Math.min(idx + 1, fields.length - 1)] || fields[0];
        if (next) {
          next.focus();
          if (stickyMode === 'dictation') {
            dictationTarget = next;
            if (next.tagName === 'SELECT') showDropdownOptions(next);
          }
          highlightField(next);
          const label = next.getAttribute('data-voice-label') || next.placeholder || next.name || 'next field';
          showRibbon(`Now in: ${label}`, 'Next Field', 'success');
        }
        break;
      }

      case 'PREV_FIELD': {
        const fields = Array.from(document.querySelectorAll('input:not([type=hidden]), textarea, select'))
          .filter(el => !el.disabled && el.offsetParent !== null);
        const idx  = fields.indexOf(document.activeElement);
        const prev = idx > 0 ? fields[idx - 1] : fields[fields.length - 1];
        if (prev) {
          prev.focus();
          if (stickyMode === 'dictation') {
            dictationTarget = prev;
            if (prev.tagName === 'SELECT') showDropdownOptions(prev);
          }
          highlightField(prev);
          const label = prev.getAttribute('data-voice-label') || prev.placeholder || prev.name || 'previous field';
          showRibbon(`Now in: ${label}`, 'Prev Field', 'success');
        }
        break;
      }

      // ── Form submit / clear ──
      case 'FORM_SUBMIT': {
        const btn = document.querySelector('[type=submit], button.btn-primary') || document.querySelector('form button');
        if (btn) { btn.click(); showRibbon('Submitting form…', 'Submit', 'success'); }
        break;
      }

      case 'FORM_CLEAR': {
        triggerConfirm(
          result.reply || 'Clear all form fields? Say yes to confirm.',
          () => {
            document.querySelectorAll('input:not([type=hidden]), textarea, select').forEach(el => {
              el.value = '';
              el.dispatchEvent(new Event('input', { bubbles: true }));
            });
            showRibbon('Form cleared.', 'Clear Form', 'success');
          }
        );
        break;
      }

      // ── Click by label ──
      case 'CLICK_ELEMENT': {
        const label = (params.label || '').toLowerCase();
        const el = findClickableByLabel(label);
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          setTimeout(() => { el.click(); }, 300);
          showRibbon(`Clicked "${params.label}"`, 'Click', 'success');
        } else {
          showRibbon(`Could not find "${params.label}" on this page.`, 'Click', 'error');
        }
        break;
      }

      // ── Select / overlay mode ──
      case 'SELECT_MODE_ON':
        enterSelectMode();
        break;

      case 'SELECT_MODE_OFF':
        exitSelectMode();
        break;

      case 'CLICK_N': {
        const n = parseInt(params.n, 10);
        clickSelectElement(n);
        break;
      }

      // ── Page info ──
      case 'COUNT_ITEMS': {
        const pg    = window.__vcPage__;
        const count = (pg && pg.itemCount) || document.querySelectorAll('[data-listing-id], .listing-card').length;
        speak(result.reply || `There are ${count} items on this page.`, prefs.locale);
        break;
      }

      case 'WHAT_PAGE': {
        const pg   = window.__vcPage__;
        const text = (pg && pg.briefing && (pg.briefing[prefs.locale] || pg.briefing.en)) || document.title;
        showRibbon(text, 'Page Info', 'success');
        speak(text, prefs.locale);
        break;
      }

      case 'WHAT_COMMANDS': {
        const pg   = window.__vcPage__;
        const cmds = (pg && pg.commandsHelp && (pg.commandsHelp[prefs.locale] || pg.commandsHelp.en)) || tr('help');
        showRibbon(cmds, 'Commands', 'success');
        speak(cmds, prefs.locale);
        break;
      }

      // ── Confirmation answers ──
      case 'CONFIRM_YES': {
        if (confirmPending) {
          clearTimeout(confirmPending.timer);
          confirmPending.fn();
          confirmPending = null;
          setState('idle');
        }
        break;
      }

      case 'CONFIRM_NO': {
        if (confirmPending) {
          clearTimeout(confirmPending.timer);
          confirmPending = null;
          setState('idle');
          showRibbon('Action cancelled.', 'Cancel', 'success');
          speak(
            prefs.locale === 'fr' ? 'Annulé.' : prefs.locale === 'ar' ? 'تم الإلغاء.' : 'Cancelled.',
            prefs.locale
          );
        }
        break;
      }

      // ── Listing actions ──
      case 'GENERATE_CONTRACT': {
        const btn = document.querySelector('[data-action="generate-contract"], a[href*="generate"], button[data-contract]');
        if (btn) {
          btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
          setTimeout(() => btn.click(), 400);
        } else if (url) {
          delayedNavigate(url);
        }
        break;
      }

      case 'SEND_MESSAGE': {
        const btn = document.querySelector('[data-action="send-message"], a[href*="message"], .btn-message');
        if (btn) { btn.click(); }
        else if (url) delayedNavigate(url);
        break;
      }

      case 'SAVE_LISTING': {
        const btn = document.querySelector('[data-action="save-listing"], .btn-save, form[action*="save"] button');
        if (btn) btn.click();
        break;
      }

      case 'NEW_LISTING': {
        if (url) delayedNavigate(url);
        break;
      }
    }
  }

  function delayedNavigate(url) {
    setTimeout(() => window.location.assign(url), 650);
  }

  // ── Field Map module ──────────────────────────────────────────────────────
  function buildFieldMap() {
    const map = new Map();

    // From <label for="...">
    document.querySelectorAll('label[for]').forEach(label => {
      const el = document.getElementById(label.htmlFor);
      if (!el) return;
      const name = normalizeFieldName(label.textContent);
      if (name) map.set(name, el);
    });

    // From data-voice-label
    document.querySelectorAll('[data-voice-label]').forEach(el => {
      const name = normalizeFieldName(el.dataset.voiceLabel);
      if (name) map.set(name, el);
    });

    // From placeholder
    document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(el => {
      const name = normalizeFieldName(el.placeholder);
      if (name && !map.has(name)) map.set(name, el);
    });

    // From name attribute
    document.querySelectorAll('input[name], textarea[name], select[name]').forEach(el => {
      const name = normalizeFieldName(el.name);
      if (name && !map.has(name)) map.set(name, el);
    });

    return map;
  }

  function normalizeFieldName(s) {
    return (s || '').toLowerCase().replace(/[^a-z0-9]/g, '').trim();
  }

  function findFieldByName(query) {
    const map = buildFieldMap();
    const q   = normalizeFieldName(query);
    if (!q) return null;

    // Exact match first
    if (map.has(q)) return map.get(q);

    // Prefix / substring match
    for (const [key, el] of map) {
      if (key.startsWith(q) || q.startsWith(key) || key.includes(q) || q.includes(key)) return el;
    }

    // Levenshtein <= 2
    for (const [key, el] of map) {
      if (levenshtein(q, key) <= 2) return el;
    }

    return null;
  }

  function fillElement(el, value) {
    if (el.tagName === 'SELECT') {
      const v = value.toLowerCase();
      for (const opt of el.options) {
        if (opt.text.toLowerCase().includes(v) || opt.value.toLowerCase().includes(v)) {
          el.value = opt.value;
          break;
        }
      }
    } else {
      el.value = value;
    }
    el.dispatchEvent(new Event('input',  { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function highlightField(el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('vc-field-highlight');
    setTimeout(() => el.classList.remove('vc-field-highlight'), 2000);
  }

  function levenshtein(a, b) {
    const m = a.length, n = b.length;
    const d = Array.from({ length: m + 1 }, (_, i) =>
      Array.from({ length: n + 1 }, (_, j) => i === 0 ? j : j === 0 ? i : 0)
    );
    for (let i = 1; i <= m; i++) {
      for (let j = 1; j <= n; j++) {
        d[i][j] = a[i - 1] === b[j - 1]
          ? d[i - 1][j - 1]
          : 1 + Math.min(d[i - 1][j], d[i][j - 1], d[i - 1][j - 1]);
      }
    }
    return d[m][n];
  }

  // ── Spoken number parser (for select mode) ───────────────────────────────
  const SPOKEN_NUMBERS = {
    // English cardinals
    'zero':0,'one':1,'two':2,'three':3,'four':4,'five':5,'six':6,'seven':7,'eight':8,'nine':9,
    'ten':10,'eleven':11,'twelve':12,'thirteen':13,'fourteen':14,'fifteen':15,
    'sixteen':16,'seventeen':17,'eighteen':18,'nineteen':19,'twenty':20,
    'twenty one':21,'twenty two':22,'twenty three':23,'twenty four':24,'twenty five':25,
    'thirty':30,'forty':40,'fifty':50,
    // English ordinals (users often say "the third")
    'first':1,'second':2,'third':3,'fourth':4,'fifth':5,'sixth':6,'seventh':7,'eighth':8,'ninth':9,'tenth':10,
    // Common homophones speech-to-text might emit
    'won':1,'too':2,'to':2,'tree':3,'for':4,'fore':4,'ate':8,
    // French
    'un':1,'une':1,'deux':2,'trois':3,'quatre':4,'cinq':5,'sept':7,'huit':8,'neuf':9,'dix':10,
    'onze':11,'douze':12,'treize':13,'quatorze':14,'quinze':15,'seize':16,'vingt':20,
    'premier':1,'première':1,'deuxième':2,'troisième':3,'quatrième':4,'cinquième':5,
    // Arabic
    'واحد':1,'اثنين':2,'اثنان':2,'ثلاثة':3,'أربعة':4,'خمسة':5,'ستة':6,'سبعة':7,'ثمانية':8,'تسعة':9,'عشرة':10,
    'الأول':1,'الثاني':2,'الثالث':3,'الرابع':4,'الخامس':5,
  };

  function parseSpokenNumber(text) {
    const t = text.toLowerCase().trim()
      .replace(/[.,!?؟،]/g, '')     // drop punctuation
      .replace(/\s+/g, ' ');
    if (!t) return null;
    // Pure digit(s)
    if (/^\d+$/.test(t)) return parseInt(t, 10);
    // Exact word match
    if (SPOKEN_NUMBERS[t] !== undefined) return SPOKEN_NUMBERS[t];
    // "click N", "select N", "go N", "hit N", "pick N", "number N", "element N", "numéro N", "رقم N"
    const m = t.match(/^(?:click|select|go|hit|pick|tap|press|choose|open|number|num|element|numéro|numero|sélection|selection|رقم|اختر|اضغط)\s+(\d+|[a-zà-ïа-я\u0600-\u06FF]+(?:\s+[a-zà-ïа-я\u0600-\u06FF]+)?)$/i);
    if (m) {
      const v = parseInt(m[1], 10);
      if (!isNaN(v)) return v;
      if (SPOKEN_NUMBERS[m[1]] !== undefined) return SPOKEN_NUMBERS[m[1]];
    }
    // Trailing digit "go to 5"
    const tail = t.match(/(\d+)$/);
    if (tail) return parseInt(tail[1], 10);
    return null;
  }

  // ── Dictation module ──────────────────────────────────────────────────────
  function startDictationMode() {
    stickyMode = 'dictation';
    setState('dictation');
    dictationTarget = (document.activeElement && isInputEl(document.activeElement))
      ? document.activeElement
      : document.querySelector('input:not([type=hidden]), textarea, select');
    if (dictationTarget) {
      dictationTarget.focus();
      highlightField(dictationTarget);
      dictationTarget.classList.add('vc-dictation-active');
      // If starting on a dropdown, show its options
      if (dictationTarget.tagName === 'SELECT') showDropdownOptions(dictationTarget);
    }
    showRibbon('Dictation on — mic stays open. Say "stop" or "done" to exit.', 'DICTATE', 'success');
    speak(
      prefs.locale === 'fr'  ? 'Mode dictée activé. Parlez pour écrire. Dites arrêt pour sortir.' :
      prefs.locale === 'ar'  ? 'وضع الإملاء نشط. تحدث للكتابة. قل خروج للإيقاف.'   :
      'Dictation on. Speak to type into the active field. Say stop or done to exit.',
      prefs.locale
    );
  }

  function stopDictationMode() {
    stickyMode = null;
    if (dictationTarget) {
      dictationTarget.classList.remove('vc-dictation-active');
      // Strip any stale interim preview suffix that didn't finalize.
      if (dictationInterim && dictationTarget.value.endsWith(dictationInterim)) {
        dictationTarget.value = dictationTarget.value.slice(0, -dictationInterim.length);
        dictationTarget.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }
    dictationInterim = '';
    dictationTarget = null;
    setState('idle');
    showRibbon('Dictation off. Microphone stopped.', 'Dictation', 'success');
  }

  /**
   * Append the confirmed chunk to the active dictation field.
   * Handles smart spacing (no double spaces, capitalize at start).
   */
  function commitDictationChunk(text) {
    const target = dictationTarget || document.querySelector('input:focus, textarea:focus, select:focus');
    if (!target) return;

    // ── SELECT dropdown: match spoken text to an option ──
    if (target.tagName === 'SELECT') {
      selectDropdownOption(target, text);
      return;
    }

    // Remove any stale interim preview first
    if (dictationInterim && target.value.endsWith(dictationInterim)) {
      target.value = target.value.slice(0, -dictationInterim.length);
    }
    dictationInterim = '';
    let chunk = text.trim();
    if (!chunk) return;
    // Capitalize first letter if field is empty or ends with sentence terminator.
    const prev = target.value;
    const needsCap = !prev || /[.!?]\s*$/.test(prev);
    if (needsCap) chunk = chunk.charAt(0).toUpperCase() + chunk.slice(1);
    const sep = prev && !/\s$/.test(prev) ? ' ' : '';
    target.value = prev + sep + chunk;
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));
    // Nudge browser auto-fill for credential fields (login page)
    triggerAutofill(target);
    highlightField(target);
    showRibbon(`✎ ${chunk}`, 'DICTATE', 'success');
  }

  /** Render live interim transcript inline in the field (de-committed each time). */
  function showDictationInterim(interim) {
    const target = dictationTarget;
    if (!target) return;
    const trimmed = interim.trim();
    if (!trimmed) return;
    // Remove previous interim suffix
    if (dictationInterim && target.value.endsWith(dictationInterim)) {
      target.value = target.value.slice(0, -dictationInterim.length);
    }
    const prev = target.value;
    const sep = prev && !/\s$/.test(prev) ? ' ' : '';
    dictationInterim = sep + trimmed;
    target.value = prev + dictationInterim;
  }

  function isInputEl(el) {
    return el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT');
  }

  // ── Dropdown option selection (for dictation mode) ────────────────────────

  /**
   * Match spoken text against a <select>'s <option> labels.
   * Tries: exact substring, Levenshtein ≤ 3, spoken-number index.
   */
  function selectDropdownOption(selectEl, spoken) {
    const raw = (spoken || '').trim();
    if (!raw) return;
    const q = raw.toLowerCase().replace(/[.,!?؟،]/g, '').trim();
    const opts = Array.from(selectEl.options).filter(o => o.value !== '' || o.textContent.trim());

    let bestOpt = null;
    let bestScore = Infinity;

    // 1. Exact substring match on option text
    for (const opt of opts) {
      const text = opt.textContent.trim().toLowerCase();
      if (text === q) { bestOpt = opt; bestScore = 0; break; }
      if (text.includes(q) || q.includes(text)) {
        const score = Math.abs(text.length - q.length);
        if (score < bestScore) { bestOpt = opt; bestScore = score; }
      }
    }

    // 2. Levenshtein fuzzy match
    if (!bestOpt || bestScore > 3) {
      for (const opt of opts) {
        const text = opt.textContent.trim().toLowerCase();
        const dist = levenshtein(q, text.length > q.length + 5 ? text.slice(0, q.length + 5) : text);
        if (dist < bestScore && dist <= 3) { bestOpt = opt; bestScore = dist; }
      }
    }

    // 3. Spoken number → pick by 1-based index ("one", "2", etc.)
    if (!bestOpt) {
      const num = parseSpokenNumber(q);
      if (num !== null && num > 0 && num <= opts.length) {
        bestOpt = opts[num - 1];
      }
    }

    if (bestOpt) {
      selectEl.value = bestOpt.value;
      selectEl.dispatchEvent(new Event('change', { bubbles: true }));
      selectEl.dispatchEvent(new Event('input', { bubbles: true }));
      highlightField(selectEl);
      const label = bestOpt.textContent.trim();
      showRibbon(`✓ Selected: ${label}`, 'DROPDOWN', 'success');
      speak(
        prefs.locale === 'fr' ? `Sélectionné : ${label}` :
        prefs.locale === 'ar' ? `تم اختيار: ${label}` :
        `Selected: ${label}`,
        prefs.locale
      );
    } else {
      showRibbon(`No matching option for "${raw}"`, 'DROPDOWN', 'error');
      // Re-show available options so user can try again
      showDropdownOptions(selectEl);
    }
  }

  /**
   * Announce the available <option>s in the ribbon so the user knows what to say.
   */
  function showDropdownOptions(selectEl) {
    const opts = Array.from(selectEl.options).filter(o => o.value !== '' || o.textContent.trim());
    if (opts.length === 0) return;
    const list = opts.slice(0, 12).map((o, i) => `${i + 1}) ${o.textContent.trim()}`).join('  ');
    const label = selectEl.getAttribute('data-voice-label') || selectEl.name || 'dropdown';
    showRibbon(`${label}: ${list} — say the name or number`, 'DROPDOWN', 'success');
    speak(
      prefs.locale === 'fr' ? `Menu déroulant avec ${opts.length} options. Dites le nom ou le numéro.` :
      prefs.locale === 'ar' ? `قائمة منسدلة بها ${opts.length} خيارات. قل الاسم أو الرقم.` :
      `Dropdown with ${opts.length} options. Say the name or number.`,
      prefs.locale
    );
  }

  /**
   * After filling a credential field (email/username on login), dispatch
   * focus → input → change events to nudge the browser into populating
   * the paired password field from saved credentials.
   */
  function triggerAutofill(field) {
    if (!field) return;
    const isCredential = field.type === 'email' || field.type === 'text' &&
      (field.name === '_username' || field.name === 'email' || field.autocomplete === 'username');
    if (!isCredential) return;
    // Give browser a moment to react to the value change, then re-focus to trigger autofill
    setTimeout(() => {
      field.dispatchEvent(new Event('focus', { bubbles: true }));
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
      // Also try to focus the password field to trigger browser's credential picker
      const form = field.closest('form');
      if (form) {
        const pwField = form.querySelector('input[type=password]');
        if (pwField) {
          setTimeout(() => {
            pwField.focus();
            pwField.dispatchEvent(new Event('focus', { bubbles: true }));
            // Move focus back if in dictation so the user can continue
            if (stickyMode === 'dictation' && dictationTarget === field) {
              setTimeout(() => field.focus(), 100);
            }
          }, 150);
        }
      }
    }, 80);
  }

  // ── Select / Click mode ───────────────────────────────────────────────────
  const CLICK_COLORS = {
    A:        '#6366f1',
    BUTTON:   '#10b981',
    INPUT:    '#f59e0b',
    TEXTAREA: '#f59e0b',
    SELECT:   '#f59e0b',
    default:  '#64748b',
  };

  function enterSelectMode() {
    exitSelectMode(); // clear any existing overlay
    stickyMode = 'select';
    setState('select');
    selectElements = [];

    const candidates = document.querySelectorAll(
      'a[href], button:not([aria-hidden]):not(.preview-remove), input:not([type=hidden]), select, textarea, [data-listing-id], .listing-card, .btn'
    );

    let n = 1;
    candidates.forEach(el => {
      if (!el.offsetParent) return; // skip hidden
      const rect = el.getBoundingClientRect();
      if (rect.width < 10 || rect.height < 10) return;

      const badge = document.createElement('div');
      badge.className   = 'vc-select-badge';
      badge.textContent = n;
      badge.dataset.n   = n;

      const color = CLICK_COLORS[el.tagName] || CLICK_COLORS.default;
      badge.style.cssText = `background:${color};`;
      badge.style.setProperty('--badge-top',  rect.top  + 'px');
      badge.style.setProperty('--badge-left', rect.left + 'px');

      document.body.appendChild(badge);
      badge.addEventListener('click', () => { el.focus(); el.click(); });

      selectElements.push({ el, badge, n });
      n++;
    });

    showRibbon(
      `Select on — ${selectElements.length} elements numbered. Say "click N". Say "stop" to exit.`,
      'SELECT', 'success'
    );
    speak(
      prefs.locale === 'fr' ? `Sélection active. ${selectElements.length} éléments numérotés. Dites arrêt pour sortir.` :
      prefs.locale === 'ar' ? `وضع التحديد. ${selectElements.length} عنصر. قل خروج للإيقاف.` :
      `Select mode on. ${selectElements.length} elements numbered. Say click N to click, or stop to exit.`,
      prefs.locale
    );
  }

  function exitSelectMode() {
    stickyMode = null;
    selectElements.forEach(({ badge }) => badge.remove());
    selectElements = [];
    if (state === 'select') setState('idle');
  }

  function clickSelectElement(n) {
    const item = selectElements.find(s => s.n === n);
    if (item) {
      item.badge.style.transform = 'scale(1.4)';
      // Keep in select mode after click — user might want to click another
      setTimeout(() => { item.el.focus(); item.el.click(); }, 300);
      showRibbon(`Clicking element #${n}`, 'Select', 'success');
    } else {
      showRibbon(`No element #${n} found.`, 'Select', 'error');
    }
  }

  function findClickableByLabel(label) {
    const q          = label.toLowerCase();
    const candidates = document.querySelectorAll('button, a, [role=button], input[type=submit], label');
    let best = null, bestScore = Infinity;

    candidates.forEach(el => {
      const text = (el.textContent || el.value || el.title || el.ariaLabel || '').toLowerCase().trim();
      if (!text) return;
      if (text.includes(q)) {
        best = el; bestScore = 0;
      } else {
        const score = levenshtein(q, text.slice(0, q.length + 5));
        if (score < bestScore && score <= 3) { best = el; bestScore = score; }
      }
    });

    return best;
  }

  // ── Confirmation flow ─────────────────────────────────────────────────────
  function triggerConfirm(message, fn) {
    stickyMode = 'confirm';
    confirmPending = {
      fn,
      timer: setTimeout(() => {
        stickyMode = null;
        confirmPending = null;
        setState('idle');
        showRibbon('Timed out.', 'Confirm', 'error');
      }, 8000),
    };
    setState('confirm');
    showRibbon(message + ' (Say yes or no)', 'CONFIRM?', 'warning');
    speak(message, prefs.locale);
  }

  function handleConfirmResponse(text) {
    const t = text.toLowerCase().trim();
    const positives = ['yes', 'yeah', 'yep', 'ok', 'okay', 'confirm', 'go ahead', 'sure', 'oui', 'نعم'];
    const negatives = ['no', 'nope', 'cancel', 'stop', 'abort', 'non', 'لا'];

    if (positives.some(p => t.includes(p))) {
      if (confirmPending) {
        clearTimeout(confirmPending.timer);
        const fn = confirmPending.fn;
        confirmPending = null;
        stickyMode = null;
        setState('idle');
        fn();
      }
    } else if (negatives.some(n => t.includes(n))) {
      if (confirmPending) {
        clearTimeout(confirmPending.timer);
        confirmPending = null;
        stickyMode = null;
        setState('idle');
        showRibbon('Action cancelled.', 'Cancel', 'success');
        speak(
          prefs.locale === 'fr' ? 'Annulé.' : prefs.locale === 'ar' ? 'تم الإلغاء.' : 'Cancelled.',
          prefs.locale
        );
      }
    } else {
      // Unclear response — re-prompt (mic will restart via scheduleRestart in handleTranscript)
      showRibbon('Please say yes or no.', 'CONFIRM?', 'warning');
    }
  }

  // ── Enhanced TTS ──────────────────────────────────────────────────────────
  function speak(text, lang) {
    if (!prefs.tts || !text || !('speechSynthesis' in window)) {
      if (!isStickyState(stickyMode)) setState('idle');
      scheduleRestart();
      return;
    }
    try {
      window.speechSynthesis.cancel();
      const u  = new SpeechSynthesisUtterance(text);
      u.lang   = localeToBcp47(lang);
      u.rate   = 0.95;
      u.pitch  = 1.05;

      // Prefer higher-quality voices
      const voices    = window.speechSynthesis.getVoices();
      const preferred = voices.find(v =>
        v.lang.startsWith(u.lang.slice(0, 2)) &&
        (v.name.includes('Google') || v.name.includes('Samantha') || v.name.includes('Alex') || v.name.includes('Aria'))
      );
      if (preferred) u.voice = preferred;

      // Remember sticky state — TTS transitions through 'speaking' but must restore
      const snap = stickyMode;
      u.onstart = () => setState('speaking');
      u.onend   = () => {
        // Restore sticky state after TTS, then restart mic
        if (snap) setState(snap);
        else setState('idle');
        scheduleRestart();
      };
      u.onerror = () => {
        if (snap) setState(snap);
        else setState('idle');
        scheduleRestart();
      };
      window.speechSynthesis.speak(u);
    } catch (_) {
      if (!isStickyState(stickyMode)) setState('idle');
      scheduleRestart();
    }
  }

  // ── Visualiser ────────────────────────────────────────────────────────────
  function startVisualiser() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;
    navigator.mediaDevices.getUserMedia({ audio: true }).then((stream) => {
      micStream = stream;
      audioCtx  = new (window.AudioContext || window.webkitAudioContext)();
      const src = audioCtx.createMediaStreamSource(stream);
      analyser  = audioCtx.createAnalyser();
      analyser.fftSize = 256;
      src.connect(analyser);
      drawWave();
    }).catch(() => {});
  }

  function drawWave() {
    const canvas = document.querySelector('.voice-orb-wave');
    if (!canvas || !analyser) return;
    const ctx = canvas.getContext('2d');
    const buf = new Uint8Array(analyser.frequencyBinCount);

    const render = () => {
      rafId = requestAnimationFrame(render);
      analyser.getByteFrequencyData(buf);
      let sum = 0;
      for (let i = 0; i < buf.length; i++) sum += buf[i];
      const level = Math.min(1, (sum / buf.length) / 96);
      document.getElementById('voice-orb').style.setProperty('--level', level.toFixed(2));

      const w = canvas.width, h = canvas.height, cx = w / 2, cy = h / 2;
      ctx.clearRect(0, 0, w, h);
      ctx.strokeStyle = 'rgba(255,255,255,.95)';
      ctx.lineWidth   = 2;
      ctx.lineCap     = 'round';
      const bars = 32;
      for (let i = 0; i < bars; i++) {
        const v  = buf[Math.floor(i * buf.length / bars)] / 255;
        const a  = (i / bars) * Math.PI * 2;
        const r1 = 32, r2 = 32 + 4 + v * 14;
        ctx.beginPath();
        ctx.moveTo(cx + Math.cos(a) * r1, cy + Math.sin(a) * r1);
        ctx.lineTo(cx + Math.cos(a) * r2, cy + Math.sin(a) * r2);
        ctx.stroke();
      }
    };
    render();
  }

  function stopVisualiser() {
    if (rafId)    { cancelAnimationFrame(rafId); rafId = null; }
    if (micStream){ micStream.getTracks().forEach(t => t.stop()); micStream = null; }
    if (audioCtx) { audioCtx.close().catch(() => {}); audioCtx = null; }
    const canvas = document.querySelector('.voice-orb-wave');
    if (canvas)   canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    const orb = document.getElementById('voice-orb');
    if (orb)      orb.style.removeProperty('--level');
  }

  // ── State + UI helpers ────────────────────────────────────────────────────
  function setState(s) {
    state = s;
    const orb = document.getElementById('voice-orb');
    if (!orb) return;
    orb.dataset.state = s;

    // State chip in ribbon
    const chip = document.querySelector('#voice-ribbon [data-role="state"]');
    if (chip) chip.textContent = tr(s);

    // Mode indicator pill
    const indicator = document.getElementById('vc-mode-indicator');
    if (indicator) {
      const labels = {
        idle:       '',
        listening:  '',
        thinking:   '',
        speaking:   '',
        dictation:  'DICTATE',
        confirm:    'CONFIRM?',
        select:     'SELECT',
      };
      indicator.textContent = labels[s] || '';
      indicator.className   = (s !== 'idle' && s !== 'listening' && s !== 'thinking' && s !== 'speaking') ? 'show' : '';
      indicator.dataset.mode = s;
    }
  }

  function showRibbon(text, intent, tone = 'success') {
    const r = document.getElementById('voice-ribbon');
    if (!r) return;
    r.classList.add('show');
    r.dataset.tone = tone;
    const t = r.querySelector('[data-role="transcript"]'); if (t) t.textContent = text || '';
    const i = r.querySelector('[data-role="intent"]');     if (i) i.textContent = intent ? '→ ' + intent : '';
    clearTimeout(showRibbon._t);
    showRibbon._t = setTimeout(hideRibbon, 6000);
  }

  function hideRibbon() {
    const r = document.getElementById('voice-ribbon');
    r && r.classList.remove('show');
  }

  function updateTranscript(text) {
    const t = document.querySelector('#voice-ribbon [data-role="transcript"]');
    const r = document.getElementById('voice-ribbon');
    if (r) r.classList.add('show');
    if (t) t.textContent = text;
  }

  function humanIntent(i) {
    if (!i || i === 'UNKNOWN') return '';
    return i.replace(/^NAVIGATE_/, '').replace(/_/g, ' ').toLowerCase().replace(/^\w/, c => c.toUpperCase());
  }

  // ── Command palette (per-page aware) ──────────────────────────────────────
  function getPageSuggestions() {
    const pg       = window.__vcPage__;
    const pageSugs = (pg && pg.suggestions) || [];
    const base     = [
      { label: 'Show listings',       cmd: 'open listings'      },
      { label: 'My listings',         cmd: 'my listings'         },
      { label: 'Open messages',       cmd: 'open messages'       },
      { label: 'My contracts',        cmd: 'open contracts'      },
      { label: 'Dashboard',           cmd: 'go to dashboard'     },
      { label: 'Search in Tunis',     cmd: 'search Tunis'        },
      { label: 'Under 500 TND',       cmd: 'under 500'           },
      { label: 'Read this page',      cmd: 'read this page'      },
      { label: 'What page is this?',  cmd: 'what page is this'   },
      { label: 'Start dictation',     cmd: 'start dictation'     },
      { label: 'Select mode',         cmd: 'select mode'         },
      { label: 'Next field',          cmd: 'next field'          },
      { label: 'Submit form',         cmd: 'submit form'         },
      { label: 'Switch to French',    cmd: 'switch to french'    },
      { label: 'Switch to Arabic',    cmd: 'switch to arabic'    },
      { label: 'Switch to English',   cmd: 'switch to english'   },
      { label: 'What can I say?',     cmd: 'what can I say'      },
      { label: 'Help',                cmd: 'help'                },
      { label: 'Log out',             cmd: 'logout'              },
    ];
    return [...pageSugs, ...base];
  }

  function openPalette() {
    const dlg = document.getElementById('voice-palette');
    if (!dlg) return;
    renderSuggestions('');
    if (typeof dlg.showModal === 'function') dlg.showModal();
    else dlg.setAttribute('open', '');
    setTimeout(() => document.getElementById('voice-palette-input').focus(), 30);
  }

  function renderSuggestions(q) {
    const ul = document.getElementById('voice-palette-suggestions');
    if (!ul) return;
    const qq    = (q || '').toLowerCase().trim();
    const all   = getPageSuggestions();
    const items = all.filter(s => !qq || s.label.toLowerCase().includes(qq) || s.cmd.toLowerCase().includes(qq)).slice(0, 8);
    ul.innerHTML = items.map(s =>
      `<li role="option" data-cmd="${escapeHtml(s.cmd)}"><span>${escapeHtml(s.label)}</span><kbd>${escapeHtml(s.cmd)}</kbd></li>`
    ).join('') || `<li class="voice-palette-empty">No matches — Enter to run as-is</li>`;
    ul.querySelectorAll('li[data-cmd]').forEach(li => {
      li.addEventListener('click', () => {
        document.getElementById('voice-palette').close();
        handleTranscript(li.dataset.cmd);
      });
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
  }

  // ── Public API ────────────────────────────────────────────────────────────
  window.unidarVoice = {
    open:           openPalette,
    run:            handleTranscript,
    setLocale:      (l) => { if (SUPPORTED_LOCALES.includes(l)) { prefs.locale = l; savePrefs(); } },
    toggleTTS:      () => { prefs.tts = !prefs.tts; savePrefs(); return prefs.tts; },
    startDictation: startDictationMode,
    stopDictation:  stopDictationMode,
    enterSelect:    enterSelectMode,
    exitSelect:     exitSelectMode,
    fillField:      (name, value) => {
      const el = findFieldByName(name);
      if (el) fillElement(el, value);
    },
  };

})();
