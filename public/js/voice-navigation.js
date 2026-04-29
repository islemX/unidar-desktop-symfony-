/**
 * UNIDAR Voice-Guided Navigation
 * Multi-language TTS + Speech Recognition + Auto-step + Animated map cursor
 * Languages: English · Français · العربية
 */
(function (global) {
  'use strict';

  // ── Language packs ──────────────────────────────────────────────────────────
  var LANG = {
    en: {
      code: 'en-US',
      start:   'Voice navigation started. Follow the highlighted route on the map.',
      arrived: 'You have arrived at your destination. Well done!',
      stepOf:  'Step {n} of {total}',
      paused:  'Navigation paused. Say resume to continue.',
      resumed: 'Navigation resumed.',
      stopped: 'Navigation stopped.',
      repeated:'Repeating: ',
      commands: {
        pause:  ['pause', 'hold on', 'wait'],
        resume: ['resume', 'continue', 'go'],
        stop:   ['stop', 'cancel', 'exit'],
        repeat: ['repeat', 'say again', 'again'],
        next:   ['next', 'skip', 'advance']
      }
    },
    fr: {
      code: 'fr-FR',
      start:   'Navigation vocale démarrée. Suivez l\'itinéraire sur la carte.',
      arrived: 'Vous êtes arrivé à destination. Félicitations !',
      stepOf:  'Étape {n} sur {total}',
      paused:  'Navigation en pause. Dites reprendre pour continuer.',
      resumed: 'Navigation reprise.',
      stopped: 'Navigation arrêtée.',
      repeated:'Je répète : ',
      commands: {
        pause:  ['pause', 'attendre', 'arrête'],
        resume: ['reprendre', 'continuer', 'allez'],
        stop:   ['stop', 'annuler', 'quitter'],
        repeat: ['répéter', 'encore', 'redis'],
        next:   ['suivant', 'prochain', 'avancer']
      }
    },
    ar: {
      code: 'ar-SA',
      start:   'بدأ التنقل الصوتي. اتبع المسار المحدد على الخريطة.',
      arrived: 'لقد وصلت إلى وجهتك. أحسنت!',
      stepOf:  'الخطوة {n} من {total}',
      paused:  'التنقل متوقف مؤقتاً. قل استمر للمتابعة.',
      resumed: 'استُؤنف التنقل.',
      stopped: 'توقف التنقل.',
      repeated:'أكرر: ',
      commands: {
        pause:  ['توقف', 'انتظر', 'وقفة'],
        resume: ['استمر', 'تابع', 'اكمل'],
        stop:   ['إيقاف', 'إلغاء', 'انهاء'],
        repeat: ['كرر', 'أعد', 'مرة أخرى'],
        next:   ['التالي', 'تخطي', 'التالية']
      }
    }
  };

  // ── Helpers ─────────────────────────────────────────────────────────────────

  // Compass bearing (0° = north, clockwise) from point A → B
  function getBearing(lat1, lng1, lat2, lng2) {
    var rad  = Math.PI / 180;
    var dLng = (lng2 - lng1) * rad;
    var y    = Math.sin(dLng) * Math.cos(lat2 * rad);
    var x    = Math.cos(lat1 * rad) * Math.sin(lat2 * rad)
             - Math.sin(lat1 * rad) * Math.cos(lat2 * rad) * Math.cos(dLng);
    return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
  }

  function haversine(lat1, lng1, lat2, lng2) {
    var R   = 6371000;
    var rad = Math.PI / 180;
    var dLat = (lat2 - lat1) * rad;
    var dLng = (lng2 - lng1) * rad;
    var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
          + Math.cos(lat1 * rad) * Math.cos(lat2 * rad)
          * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function fmtDist(m) {
    return m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m';
  }

  function fmtTime(s) {
    var m = Math.round(s / 60);
    return m < 60 ? m + ' min' : Math.floor(m / 60) + 'h ' + (m % 60) + 'min';
  }

  // ── VoiceNav singleton ───────────────────────────────────────────────────────
  var VoiceNav = {
    _active:          false,
    _paused:          false,
    _lang:            'en',
    _steps:           [],
    _step:            0,
    _route:           null,
    _coords:          [],
    _geo:             null,
    _sim:             null,
    _anim:            null,   // segment interval
    _ringAnim:        null,   // pulse interval
    _rec:             null,
    _cursorArrow:     null,   // DOM div — directional arrow
    _cursorRing:      null,   // DOM div — pulsing ring
    _mapMoveHandler:  null,   // map move/zoom listener ref
    _useGeo:          false,
    _cursorIdx:       0,
    _bearing:         0,

    // ── Called from routesfound — pre-builds panel without starting audio ──
    prepare: function (routeObj) {
      if (!routeObj) return;
      this._route  = routeObj;
      this._steps  = (routeObj.instructions || []).filter(function (s) { return s && s.text; });
      this._coords = (routeObj.coordinates  || []).map(function (c) { return { lat: c.lat, lng: c.lng }; });
      this._step   = 0;
      this._buildPanel();
      this._refreshDisplay();
    },

    // ── Start navigation (called by the "Start Voice Navigation" button) ───
    start: function () {
      if (this._active) this._cleanup();
      if (!this._route)  return;

      this._active = true;
      this._paused = false;
      this._step   = 0;

      this._showPanel(true);
      this._updateLaunchBtn(false);
      this._refreshDisplay();

      // Scroll the nav panel into view so the user sees it immediately
      var panel = document.getElementById('voiceNavPanel');
      if (panel) {
        setTimeout(function () {
          panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 200);
      }

      var self = this;
      this.speak(this._t('start'));
      setTimeout(function () {
        if (self._steps[0]) self.speak(self._steps[0].text);
      }, 1700);

      this._startGeo();
      this._startRecognition();
      this._startCursor();
    },

    // ── TTS ────────────────────────────────────────────────────────────────────
    speak: function (text) {
      if (!global.speechSynthesis) return;
      global.speechSynthesis.cancel();
      var u  = new SpeechSynthesisUtterance(text);
      u.lang  = (LANG[this._lang] || LANG.en).code;
      u.rate  = 0.92;
      u.pitch = 1.0;
      global.speechSynthesis.speak(u);
    },

    // ── Geolocation ────────────────────────────────────────────────────────────
    _startGeo: function () {
      if (!navigator.geolocation) { this._startSim(); return; }
      var self = this;
      this._geo = navigator.geolocation.watchPosition(
        function (pos) {
          self._useGeo = true;
          var gpsEl = document.getElementById('vnavGps');
          if (gpsEl) gpsEl.textContent = '📡 GPS active';
          self._onPos(pos.coords.latitude, pos.coords.longitude);
        },
        function () {
          var gpsEl = document.getElementById('vnavGps');
          if (gpsEl) gpsEl.textContent = '📡 GPS unavailable';
          if (!self._useGeo) self._startSim();
        },
        { enableHighAccuracy: true, maximumAge: 2000, timeout: 8000 }
      );
    },

    _onPos: function (lat, lng) {
      if (!this._active || this._paused) return;
      // Move cursor to real GPS position
      this._positionCursorEl(lat, lng);
      // Auto-advance when close to next step waypoint
      var nextStep = this._steps[this._step + 1];
      if (nextStep && typeof nextStep.index === 'number' && this._coords[nextStep.index]) {
        var wp   = this._coords[nextStep.index];
        var dist = haversine(lat, lng, wp.lat, wp.lng);
        if (dist < 25) this.nextStep();
      }
    },

    // ── Simulation (fallback, no GPS) ──────────────────────────────────────────
    _startSim: function () {
      if (this._sim) return;
      var self = this;
      this._sim = setInterval(function () {
        if (!self._active || self._paused) return;
        if (self._step < self._steps.length - 1) {
          self.nextStep();
        } else {
          self._arrive();
        }
      }, 12000);
    },

    // ── Animated cursor on map (DOM overlay approach) ───────────────────────────
    // We append divs directly to the map container and position them in pixels
    // via map.latLngToContainerPoint(). This is 100% guaranteed to be visible
    // regardless of Leaflet layer ordering or SVG pane issues.
    _startCursor: function () {
      var M = global.MapCtl;
      if (!M || !M.map) return;

      this._destroyCursor();

      var startCoord = this._coords.length
        ? this._coords[0]
        : { lat: M.listingLat, lng: M.listingLng };

      var container = M.map.getContainer();
      // Ensure the container is a positioning context for absolute children
      if (container.style.position !== 'relative' && container.style.position !== 'absolute') {
        container.style.position = 'relative';
      }

      // ── Outer pulsing ring ──
      var ring = document.createElement('div');
      ring.style.cssText = [
        'position:absolute',
        'border-radius:50%',
        'background:rgba(99,102,241,0.15)',
        'border:3px solid rgba(99,102,241,0.65)',
        'pointer-events:none',
        'z-index:900',
        'transform:translate(-50%,-50%)',
        'width:56px',
        'height:56px',
        'transition:opacity 0.1s'
      ].join(';');
      container.appendChild(ring);
      this._cursorRing = ring;

      // ── Navigation arrow (points up = north; rotated via CSS transform) ──
      // SVG: a classic filled arrowhead pointing upward, white-outlined
      var arrow = document.createElement('div');
      arrow.style.cssText = [
        'position:absolute',
        'pointer-events:none',
        'z-index:901',
        'width:36px',
        'height:44px',
        'transform:translate(-50%,-55%) rotate(0deg)',
        'filter:drop-shadow(0 3px 8px rgba(99,102,241,0.85))',
        'transition:transform 0.25s ease'
      ].join(';');
      // The SVG arrow points UP by default (0° = north)
      arrow.innerHTML = [
        '<svg viewBox="0 0 36 44" width="36" height="44" xmlns="http://www.w3.org/2000/svg">',
        '  <polygon points="18,2 34,40 18,32 2,40" fill="#6366f1" stroke="#ffffff" stroke-width="3" stroke-linejoin="round"/>',
        '</svg>'
      ].join('');
      container.appendChild(arrow);
      this._cursorArrow = arrow;

      // ── Pulse ring by changing size via JS ──
      var self = this;
      var size = 56, expanding = true;
      this._ringAnim = setInterval(function () {
        if (!self._cursorRing) return;
        if (expanding) { size += 2; if (size >= 76) expanding = false; }
        else           { size -= 2; if (size <= 56) expanding = true;  }
        self._cursorRing.style.width   = size + 'px';
        self._cursorRing.style.height  = size + 'px';
        self._cursorRing.style.opacity = String(1 - (size - 56) / 40);
      }, 70);

      // ── Reposition cursor when map moves/zooms ──
      this._mapMoveHandler = function () {
        var c = self._coords[self._cursorIdx];
        if (c) self._positionCursorEl(c.lat, c.lng, self._bearing);
      };
      M.map.on('move zoom viewreset', this._mapMoveHandler);

      this._cursorIdx = 0;
      this._positionCursorEl(startCoord.lat, startCoord.lng);

      // Zoom into the route start so the cursor is obvious
      M.map.setView([startCoord.lat, startCoord.lng], Math.max(M.map.getZoom(), 15), { animate: true, duration: 0.9 });

      this._animateSegment();
    },

    // ── Convert lat/lng → container px, position + rotate both cursor elements ──
    _positionCursorEl: function (lat, lng, bearing) {
      var M = global.MapCtl;
      if (!M || !M.map) return;
      var pt = M.map.latLngToContainerPoint([lat, lng]);
      var deg = (bearing != null) ? bearing : this._bearing;
      if (this._cursorArrow) {
        this._cursorArrow.style.left      = pt.x + 'px';
        this._cursorArrow.style.top       = pt.y + 'px';
        // translate(-50%,-55%) keeps the arrow tip centred on the point;
        // rotate() faces the direction of travel
        this._cursorArrow.style.transform = 'translate(-50%,-55%) rotate(' + deg + 'deg)';
      }
      if (this._cursorRing) {
        this._cursorRing.style.left = pt.x + 'px';
        this._cursorRing.style.top  = pt.y + 'px';
      }
    },

    // ── Tear down cursor DOM elements and timers ───────────────────────────────
    _destroyCursor: function () {
      var M = global.MapCtl;
      if (this._ringAnim) { clearInterval(this._ringAnim); this._ringAnim = null; }
      if (this._mapMoveHandler && M && M.map) {
        M.map.off('move zoom viewreset', this._mapMoveHandler);
        this._mapMoveHandler = null;
      }
      if (this._cursorArrow && this._cursorArrow.parentNode) { this._cursorArrow.parentNode.removeChild(this._cursorArrow); this._cursorArrow = null; }
      if (this._cursorRing  && this._cursorRing.parentNode)  { this._cursorRing.parentNode.removeChild(this._cursorRing);   this._cursorRing  = null; }
    },

    // ── Animate cursor along coords for the current step segment ──────────────
    // Uses step.index (LRM instruction property) to know which slice of
    // this._coords belongs to each step, then traverses it at the right pace.
    _animateSegment: function () {
      if (this._anim) { clearInterval(this._anim); this._anim = null; }
      if (!this._active || !this._coords.length) return;

      var step     = this._steps[this._step];
      var nextStep = this._steps[this._step + 1];

      // Coord index range for this step
      var startIdx = (step     && step.index     != null) ? step.index     : 0;
      var endIdx   = (nextStep && nextStep.index != null) ? nextStep.index : this._coords.length - 1;

      // Clamp
      startIdx = Math.max(0, Math.min(startIdx, this._coords.length - 1));
      endIdx   = Math.max(startIdx, Math.min(endIdx, this._coords.length - 1));

      // Speed: spread the step's travel time across the coord count
      // step.time is in seconds; use at least 150 ms/pt, at most 800 ms/pt
      var count    = Math.max(1, endIdx - startIdx);
      var budget   = step && step.time ? step.time * 1000 : 10000; // ms
      var interval = Math.max(150, Math.min(800, Math.round(budget / count)));

      var self = this;
      var idx  = startIdx;

      // Jump cursor to start of this step immediately
      this._moveCursor(idx);

      this._anim = setInterval(function () {
        if (!self._active || self._paused || self._useGeo) return;
        if (idx >= endIdx) {
          clearInterval(self._anim);
          self._anim = null;
          return;
        }
        idx++;
        self._cursorIdx = idx;
        self._moveCursor(idx);
      }, interval);
    },

    // ── Move cursor to coords[idx]; rotate arrow; pan map to keep visible ────────
    _moveCursor: function (idx) {
      var c = this._coords[idx];
      if (!c) return;

      // Calculate bearing to the NEXT coord so the arrow faces travel direction
      var next = this._coords[idx + 1];
      if (next) {
        this._bearing = getBearing(c.lat, c.lng, next.lat, next.lng);
      }

      // Position + rotate the DOM cursor elements
      this._positionCursorEl(c.lat, c.lng, this._bearing);

      var M = global.MapCtl;
      if (!M || !M.map) return;

      var latlng = L.latLng(c.lat, c.lng);
      var zoom   = M.map.getZoom();
      var bounds = M.map.getBounds();

      // At street-level zoom (≥15) keep the cursor in the central 50% of the
      // viewport; at lower zooms only pan when cursor leaves the viewport entirely.
      if (zoom >= 15) {
        var ne = bounds.getNorthEast();
        var sw = bounds.getSouthWest();
        var latMargin = (ne.lat - sw.lat) * 0.25;
        var lngMargin = (ne.lng - sw.lng) * 0.25;
        var inner = L.latLngBounds(
          [sw.lat + latMargin, sw.lng + lngMargin],
          [ne.lat - latMargin, ne.lng - lngMargin]
        );
        if (!inner.contains(latlng)) {
          M.map.panTo(latlng, { animate: true, duration: 0.5, easeLinearity: 0.3 });
        }
      } else {
        if (!bounds.contains(latlng)) {
          M.map.panTo(latlng, { animate: true, duration: 0.7, easeLinearity: 0.4 });
        }
      }
    },

    // ── Speech recognition ──────────────────────────────────────────────────────
    _startRecognition: function () {
      var SR = global.SpeechRecognition || global.webkitSpeechRecognition;
      if (!SR) return;
      var self = this;
      try {
        this._rec = new SR();
        this._rec.continuous      = true;
        this._rec.interimResults  = false;
        this._rec.lang            = (LANG[this._lang] || LANG.en).code;

        this._rec.onstart = function () {
          var micEl = document.getElementById('vnavMic');
          if (micEl) micEl.style.display = 'flex';
        };
        this._rec.onend = function () {
          if (self._active && !self._paused) {
            try { self._rec.start(); } catch (e) {}
          } else {
            var micEl = document.getElementById('vnavMic');
            if (micEl) micEl.style.display = 'none';
          }
        };
        this._rec.onerror   = function () {};
        this._rec.onresult  = function (e) {
          var t = e.results[e.results.length - 1][0].transcript.toLowerCase().trim();
          self._handleCmd(t);
        };
        this._rec.start();
      } catch (e) {}
    },

    _handleCmd: function (t) {
      var c = (LANG[this._lang] || LANG.en).commands;
      var self = this;
      function has(list) { return list.some(function (w) { return t.indexOf(w) !== -1; }); }
      if (has(c.stop))   { self.stop();                                    return; }
      if (has(c.pause))  { if (!self._paused) self.togglePause();          return; }
      if (has(c.resume)) { if (self._paused)  self.togglePause();          return; }
      if (has(c.repeat)) { self.repeatStep();                              return; }
      if (has(c.next))   { self.nextStep();                                return; }
    },

    // ── User controls ────────────────────────────────────────────────────────────
    togglePause: function () {
      this._paused = !this._paused;
      var btn = document.getElementById('vnavPauseBtn');
      if (this._paused) {
        if (global.speechSynthesis) global.speechSynthesis.cancel();
        this.speak(this._t('paused'));
        if (btn) btn.innerHTML = '▶ Resume';
        if (this._rec) try { this._rec.stop(); } catch (e) {}
      } else {
        if (btn) btn.innerHTML = '⏸ Pause';
        this.speak(this._t('resumed'));
        this._animateSegment();   // resume cursor movement from where it stopped
        var self = this;
        setTimeout(function () {
          if (self._steps[self._step]) self.speak(self._steps[self._step].text);
        }, 900);
        try { if (this._rec) this._rec.start(); } catch (e) {}
      }
    },

    // ── Focus map on cursor at street level ───────────────────────────────────
    focusMap: function () {
      var M = global.MapCtl;
      if (!M || !M.map) return;
      var c = this._coords[this._cursorIdx];
      if (!c) return;
      M.map.setView([c.lat, c.lng], 16, { animate: true });
    },

    repeatStep: function () {
      if (!this._active) return;
      var s = this._steps[this._step];
      if (s) this.speak(this._t('repeated') + s.text);
    },

    nextStep: function () {
      if (!this._active) return;
      if (this._step < this._steps.length - 1) {
        this._step++;
        this._refreshDisplay();
        this._animateSegment();   // move cursor to new step's coords
        var self = this;
        setTimeout(function () {
          if (self._steps[self._step]) self.speak(self._steps[self._step].text);
        }, 300);
      } else {
        this._arrive();
      }
    },

    _arrive: function () {
      var fillEl = document.getElementById('vnavFill');
      if (fillEl) fillEl.style.width = '100%';
      var numEl  = document.getElementById('vnavStepNum');
      if (numEl)  numEl.textContent = '✅ Arrived!';
      var txtEl  = document.getElementById('vnavStepTxt');
      if (txtEl)  txtEl.textContent = this._t('arrived');
      this.speak(this._t('arrived'));
      this._cleanup();
    },

    stop: function () {
      this.speak(this._t('stopped'));
      this._cleanup();
      this._showPanel(false);
      this._updateLaunchBtn(true);
    },

    // ── Reset — hide everything when route is recalculated ─────────────────────
    reset: function () {
      if (this._active) this._cleanup();
      this._showPanel(false);
      this._updateLaunchBtn(false);
      // Remove the built panel so it gets rebuilt fresh on next prepare()
      var p = document.getElementById('voiceNavPanel');
      if (p) p.remove();
    },

    _cleanup: function () {
      this._active = false;
      this._paused = false;
      if (this._geo !== null && navigator.geolocation) {
        navigator.geolocation.clearWatch(this._geo);
        this._geo = null;
      }
      if (this._sim)  { clearInterval(this._sim);  this._sim  = null; }
      if (this._anim) { clearInterval(this._anim); this._anim = null; }
      if (this._rec)  { try { this._rec.stop(); } catch (e) {} this._rec = null; }
      this._destroyCursor();
      this._useGeo = false;
      var micEl = document.getElementById('vnavMic');
      if (micEl) micEl.style.display = 'none';
    },

    // ── Panel builder (runs once; subsequent calls update content only) ─────────
    _buildPanel: function () {
      if (document.getElementById('voiceNavPanel')) return; // already built
      var html =
        '<div id="voiceNavPanel" class="vnav-panel" style="display:none;">' +
          '<div class="vnav-header">' +
            '<span class="vnav-title">🎙️ Voice Navigation</span>' +
            '<div class="vnav-langs">' +
              '<button class="vnav-lang active" data-l="en">🇬🇧 EN</button>' +
              '<button class="vnav-lang" data-l="fr">🇫🇷 FR</button>' +
              '<button class="vnav-lang" data-l="ar">🇹🇳 AR</button>' +
            '</div>' +
          '</div>' +
          '<div class="vnav-bar"><div class="vnav-fill" id="vnavFill"></div></div>' +
          '<div class="vnav-step">' +
            '<div class="vnav-step-meta">' +
              '<span id="vnavStepNum" class="vnav-step-badge">Step 1</span>' +
              '<span id="vnavStepDist" class="vnav-step-dist"></span>' +
            '</div>' +
            '<div id="vnavStepTxt" class="vnav-step-text">—</div>' +
          '</div>' +
          '<div class="vnav-info">' +
            '<span>📏 <b id="vnavTotDist">—</b></span>' +
            '<span>⏱ <b id="vnavTotTime">—</b></span>' +
            '<span id="vnavGps" class="vnav-gps-tag">📡 GPS off</span>' +
          '</div>' +
          '<div class="vnav-btns">' +
            '<button class="vnav-btn vnav-sec" onclick="VoiceNav.repeatStep()" title="Repeat current instruction">🔁 Repeat</button>' +
            '<button class="vnav-btn vnav-pri" id="vnavPauseBtn" onclick="VoiceNav.togglePause()">⏸ Pause</button>' +
            '<button class="vnav-btn vnav-sec" onclick="VoiceNav.nextStep()" title="Skip to next step">⏭ Next</button>' +
            '<button class="vnav-btn vnav-sec" onclick="VoiceNav.focusMap()" title="Zoom to cursor on map">🗺️</button>' +
            '<button class="vnav-btn vnav-dan" onclick="VoiceNav.stop()">⏹ Stop</button>' +
          '</div>' +
          '<div id="vnavMic" class="vnav-mic" style="display:none;">🎤 Listening for voice commands…</div>' +
        '</div>';

      var anchor = document.getElementById('routeStepsPanel');
      if (anchor) {
        anchor.insertAdjacentHTML('afterend', html);
      } else {
        var map = document.getElementById('listingMap');
        if (map) map.insertAdjacentHTML('afterend', html);
      }

      // Language switcher
      var self = this;
      document.querySelectorAll('.vnav-lang').forEach(function (btn) {
        btn.addEventListener('click', function () {
          self._lang = this.getAttribute('data-l');
          document.querySelectorAll('.vnav-lang').forEach(function (b) { b.classList.remove('active'); });
          this.classList.add('active');
          // Restart recognition in new language
          if (self._rec) { try { self._rec.stop(); } catch (e) {} }
          if (self._active && !self._paused) {
            setTimeout(function () { try { if (self._rec) self._rec.start(); } catch (e) {} }, 400);
          }
        });
      });
    },

    _showPanel: function (show) {
      var p = document.getElementById('voiceNavPanel');
      if (p) p.style.display = show ? 'block' : 'none';
    },

    _updateLaunchBtn: function (show) {
      var btn = document.getElementById('startVoiceNavBtn');
      if (btn) btn.style.display = show ? 'inline-block' : 'none';
    },

    _t: function (key, vars) {
      var pack = LANG[this._lang] || LANG.en;
      var s    = pack[key] || key;
      if (vars) {
        Object.keys(vars).forEach(function (k) {
          s = s.replace('{' + k + '}', vars[k]);
        });
      }
      return s;
    },

    _refreshDisplay: function () {
      if (!this._steps.length) return;
      var step  = this._steps[this._step];
      var total = this._steps.length;
      var pct   = Math.round(this._step / Math.max(total - 1, 1) * 100);

      var numEl  = document.getElementById('vnavStepNum');
      var txtEl  = document.getElementById('vnavStepTxt');
      var dstEl  = document.getElementById('vnavStepDist');
      var fillEl = document.getElementById('vnavFill');
      var tdEl   = document.getElementById('vnavTotDist');
      var ttEl   = document.getElementById('vnavTotTime');

      if (numEl)  numEl.textContent  = this._t('stepOf', { n: this._step + 1, total: total });
      if (txtEl && step) txtEl.textContent = step.text;
      if (dstEl && step) dstEl.textContent = step.distance ? fmtDist(step.distance) : '';
      if (fillEl) fillEl.style.width = pct + '%';
      if (tdEl && this._route) tdEl.textContent = fmtDist(this._route.summary.totalDistance);
      if (ttEl && this._route) ttEl.textContent  = fmtTime(this._route.summary.totalTime);
    }
  };

  global.VoiceNav = VoiceNav;

})(window);
