// TerraLink CRM — small progressive enhancements, no framework
(() => {
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const csrf = $('meta[name=csrf]')?.content || '';
  const money = n => '$' + (Math.round(n * 100) / 100).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  // Theme: light / auto / dark (stored per browser)
  const applyTheme = t => {
    const dark = t === 'dark' || (t === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    $$('[data-theme-set]').forEach(b => b.classList.toggle('on', b.dataset.themeSet === t));
  };
  let theme = 'auto'; try { theme = localStorage.getItem('tl-theme') || 'auto'; } catch (e) {}
  applyTheme(theme);
  $$('[data-theme-set]').forEach(b => b.addEventListener('click', () => { theme = b.dataset.themeSet; try { localStorage.setItem('tl-theme', theme); } catch (e) {} applyTheme(theme); }));
  matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => theme === 'auto' && applyTheme('auto'));

  // Timeline: hide automatic entries (remembered)
  $$('[data-tl-toggle]').forEach(cb => {
    const tl = cb.closest('.card').querySelector('.timeline');
    let hide = false; try { hide = localStorage.getItem('tl-hide-system') === '1'; } catch (e) {}
    cb.checked = hide; tl?.classList.toggle('hide-system', hide);
    cb.addEventListener('change', () => { tl?.classList.toggle('hide-system', cb.checked); try { localStorage.setItem('tl-hide-system', cb.checked ? '1' : '0'); } catch (e) {} });
  });

  // Portal login presets <-> permission ticks
  $$('[data-preset]').forEach(sel => {
    const boxes = $$('.perms input[type=checkbox]:not([name="site_ids[]"])', sel.form);
    const apply = () => { const o = sel.selectedOptions[0]; if (!o?.dataset.perms) return; const on = o.dataset.perms.split(','); boxes.forEach(b => b.checked = on.includes(b.name)); };
    sel.addEventListener('change', apply); apply();
    boxes.forEach(b => b.addEventListener('change', () => { sel.value = ''; }));
  });

  // Mobile "More" drawer
  $$('[data-toggle-nav]').forEach(b => b.addEventListener('click', () => document.body.classList.toggle('nav-open')));

  // Confirm destructive actions
  document.addEventListener('submit', e => {
    const msg = e.target.dataset.confirm;
    if (msg && !confirm(msg)) e.preventDefault();
  });

  // Tabs (hash-driven so links like /companies/5#sites work)
  const tabs = $('.tabs[data-tabs]');
  if (tabs) {
    const show = name => {
      const link = $(`a[href="#${name}"]`, tabs) || $('a', tabs);
      name = link.getAttribute('href').slice(1);
      $$('a', tabs).forEach(a => a.classList.toggle('on', a === link));
      $$('[data-tabpanel]').forEach(p => p.classList.toggle('on', p.dataset.tabpanel === name));
    };
    tabs.addEventListener('click', e => {
      const a = e.target.closest('a'); if (!a) return;
      e.preventDefault(); history.replaceState(null, '', a.getAttribute('href')); show(a.getAttribute('href').slice(1));
    });
    show(location.hash.slice(1));
  }

  // Pipeline: drag & drop between columns (desktop), tap header to expand (mobile)
  const board = $('.board');
  if (board) {
    let dragged = null;
    $$('.dcard', board).forEach(c => {
      c.addEventListener('dragstart', e => { dragged = c; c.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; });
      c.addEventListener('dragend', () => { c.classList.remove('dragging'); $$('.col', board).forEach(x => x.classList.remove('drop')); });
    });
    $$('.col', board).forEach(col => {
      col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('drop'); });
      col.addEventListener('dragleave', e => { if (!col.contains(e.relatedTarget)) col.classList.remove('drop'); });
      col.addEventListener('drop', async e => {
        e.preventDefault(); col.classList.remove('drop');
        if (!dragged || dragged.closest('.col') === col) return;
        const from = dragged.closest('.col');
        $('.cards', col).prepend(dragged);
        const body = new URLSearchParams({ stage: col.dataset.stage, _csrf: csrf });
        const r = await fetch(`/deals/${dragged.dataset.id}/stage`, { method: 'POST', body, headers: { Accept: 'application/json' } });
        if (!r.ok) { $('.cards', from).prepend(dragged); alert('Could not move the deal. Reload and try again.'); return; }
        recount();
      });
      $('h3', col).addEventListener('click', () => col.classList.toggle('open'));
    });
    const recount = () => $$('.col', board).forEach(col => {
      const cards = $$('.dcard', col);
      const total = cards.reduce((s, c) => s + parseFloat(c.dataset.value || 0), 0);
      $('h3 small', col).textContent = `${cards.length} · $${Math.round(total).toLocaleString('en-CA')}`;
    });
    // Open the first non-empty column on phones
    if (matchMedia('(max-width:820px)').matches) ($$('.col', board).find(c => $('.dcard', c)) || $('.col', board))?.classList.add('open');
  }

  // Dependent dropdowns: picking a company reloads its contacts / sites / deals
  const company = $('form[data-dependent] select[name=company_id]');
  if (company) {
    company.addEventListener('change', async () => {
      const id = company.value;
      const map = { contact_id: 'contacts', site_id: 'sites', deal_id: 'deals' };
      const data = id ? await (await fetch(`/options/company/${id}`)).json() : { contacts: { '': '— None —' }, sites: { '': '— None —' }, deals: { '': '— None —' } };
      for (const [field, key] of Object.entries(map)) {
        const sel = $(`select[name=${field}]`, company.form); if (!sel) continue;
        sel.innerHTML = '';
        for (const [v, l] of Object.entries(data[key])) sel.add(new Option(l, v));
      }
    });
  }

  // Site form: "use my location" for GPS
  const geoBtn = $('[data-geo]');
  if (geoBtn && navigator.geolocation) {
    geoBtn.hidden = false;
    geoBtn.addEventListener('click', () => {
      geoBtn.textContent = 'Locating…';
      navigator.geolocation.getCurrentPosition(p => {
        $('#f_latitude').value = p.coords.latitude.toFixed(6);
        $('#f_longitude').value = p.coords.longitude.toFixed(6);
        geoBtn.textContent = 'Location set ✓';
      }, () => { geoBtn.textContent = 'Location unavailable'; }, { enableHighAccuracy: true, timeout: 15000 });
    });
  }

  // Quote line-item editor
  const items = $('#items');
  if (items) {
    const tbody = $('tbody', items);
    const tpl = $('#item-tpl');
    const gst = $('#f_gst_rate'), pst = $('#f_pst_rate');
    const calc = () => {
      let sub = 0, pstBase = 0;
      $$('tr', tbody).forEach(tr => {
        const q = parseFloat($('[name="items[qty][]"]', tr).value) || 0;
        const p = parseFloat($('[name="items[unit_price][]"]', tr).value) || 0;
        const line = Math.round(q * p * 100) / 100;
        $('.c-line', tr).textContent = money(line);
        sub += line;
        if ($('.pstbox', tr).checked) pstBase += line;
        $('[name="items[pst][]"]', tr).value = $('.pstbox', tr).checked ? '1' : '0';
      });
      const g = Math.round(sub * (parseFloat(gst.value) || 0)) / 100;
      const ps = Math.round(pstBase * (parseFloat(pst.value) || 0)) / 100;
      $('#t-sub').textContent = money(sub); $('#t-gst').textContent = money(g); $('#t-pst').textContent = money(ps);
      $('#t-total').textContent = money(sub + g + ps);
    };
    const addRow = (vals = {}) => {
      const tr = tpl.content.firstElementChild.cloneNode(true);
      for (const [k, v] of Object.entries(vals)) { const el = $(`[name="items[${k}][]"]`, tr); if (el) el.value = v; }
      if ('pst' in vals) $('.pstbox', tr).checked = !!vals.pst;
      tbody.append(tr); calc(); return tr;
    };
    items.addEventListener('input', calc);
    items.addEventListener('change', e => {
      if (e.target.name === 'items[kind][]') {
        const tr = e.target.closest('tr');
        $('.pstbox', tr).checked = ['hardware', 'software'].includes(e.target.value); // BC PST default: goods & software
        calc();
      }
    });
    items.addEventListener('click', e => { if (e.target.closest('.rm')) { e.target.closest('tr').remove(); calc(); } });
    [gst, pst].forEach(el => el.addEventListener('input', calc));
    $('#add-item').addEventListener('click', () => $('input', addRow()).focus());
    $('#add-travel')?.addEventListener('click', () => {
      const km = parseFloat(prompt('Round-trip kilometres?') || '');
      if (!km) return;
      const rate = parseFloat(items.dataset.travelRate) || 0;
      addRow({ kind: 'travel', description: `Travel — ${km} km round trip`, qty: km, unit: 'km', unit_price: rate.toFixed(2), pst: 0 });
      if (!rate) alert('No travel rate is set yet (Settings → Travel rate per km). Enter the price per km on the new line.');
    });
    JSON.parse(items.dataset.items || '[]').forEach(it => addRow({ ...it, pst: +it.pst_applies }));
    if (!$('tr', tbody)) addRow();
    calc();
  }
})();

// v1.7 uploads: pick files → upload right away
document.querySelectorAll('form[data-upload]').forEach(f => {
  const inp = f.querySelector('input[type=file]'), btn = f.querySelector('[data-upload-btn]'), msg = f.querySelector('[data-upload-msg]');
  if (btn) btn.hidden = true;
  inp.addEventListener('change', () => {
    if (!inp.files.length) return;
    const mb = [...inp.files].reduce((s, x) => s + x.size, 0) / 1048576;
    msg.textContent = `Uploading ${inp.files.length} file${inp.files.length > 1 ? 's' : ''} (${mb.toFixed(1)} MB)…`;
    f.querySelector('label.btn').classList.add('disabled');
    f.submit();
  });
});

// v1.9 price list picker on the quote editor
(() => {
  const box = document.getElementById('items'), pick = document.getElementById('cat-pick');
  if (!box || !pick) return;
  const cat = JSON.parse(box.dataset.catalog || '[]'), list = document.getElementById('cat-list');
  const fmt = n => '$' + (+n).toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const label = c => `${c.name}${c.sku ? ' · ' + c.sku : ''} — ${fmt(c.unit_price)}${c.unit ? '/' + c.unit : ''}${c.stock !== null ? ` (${+c.stock} free)` : ''}`;
  cat.forEach(c => { const o = document.createElement('option'); o.value = label(c); list.append(o); });
  const hint = (tr, id) => {
    const c = cat.find(x => x.id === +id), h = tr.querySelector('.stockhint');
    if (!h) return;
    if (!c || c.stock === null) { h.textContent = ''; return; }
    const need = parseFloat(tr.querySelector('[name="items[qty][]"]').value) || 0;
    h.textContent = `${+c.stock} free in stock` + (need > c.stock ? ' — not enough' : '');
    h.className = 'stockhint ' + (need > c.stock ? 'short' : 'ok');
  };
  const refresh = () => box.querySelectorAll('tbody tr').forEach(tr => hint(tr, tr.querySelector('[name="items[catalog_id][]"]').value));
  pick.addEventListener('change', () => {
    const c = cat.find(x => label(x) === pick.value);
    if (!c) return;
    let tr = [...box.querySelectorAll('tbody tr')].find(r => !r.querySelector('[name="items[description][]"]').value.trim());
    if (!tr) { box.querySelector('#add-item').click(); tr = [...box.querySelectorAll('tbody tr')].pop(); }
    const set = (n, v) => { const el = tr.querySelector(`[name="items[${n}][]"]`); if (el) el.value = v; };
    set('kind', c.kind); set('description', c.description); set('unit', c.unit); set('unit_price', c.unit_price); set('catalog_id', c.id);
    tr.querySelector('.pstbox').checked = !!c.pst;
    pick.value = '';
    box.dispatchEvent(new Event('input', { bubbles: true }));
    refresh();
    tr.querySelector('[name="items[qty][]"]').focus();
  });
  box.addEventListener('input', refresh);
  refresh();
})();

// v1.14 portal: show how many files are picked (forms that don't auto-upload)
document.querySelectorAll('input[type=file][data-count]').forEach(inp => inp.addEventListener('change', () => {
  const msg = inp.closest('form').querySelector('.upmsg');
  if (msg) msg.textContent = inp.files.length ? `${inp.files.length} file${inp.files.length > 1 ? 's' : ''} ready to send` : '';
}));

// v1.18 installable app: service worker + "Install app" button (Android/Chrome/Edge prompt, iPhone/iPad instructions)
(() => {
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
  }
  const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (standalone) return; // already running as an installed app
  const btns = [...document.querySelectorAll('[data-install]')];
  if (!btns.length) return;
  const show = () => btns.forEach(b => { b.hidden = false; b.closest('[data-install-wrap]')?.removeAttribute('hidden'); });
  const ua = navigator.userAgent;
  const ios = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  let deferred = null;

  addEventListener('beforeinstallprompt', e => { e.preventDefault(); deferred = e; show(); });
  addEventListener('appinstalled', () => { deferred = null; btns.forEach(b => { b.hidden = true; b.closest('[data-install-wrap]')?.setAttribute('hidden', ''); }); });
  if (ios) show();

  const iosHelp = () => {
    let d = document.getElementById('tl-install-help');
    if (!d) {
      d = document.createElement('dialog');
      d.id = 'tl-install-help'; d.className = 'installdlg';
      
      d.innerHTML = `<h3>Add to your Home Screen</h3>
        <ol><li>Tap the <b>Share</b> button <span aria-hidden="true" class="shareico">⬆︎</span> (bottom of the screen on iPhone, top right on iPad).</li>
        <li>Scroll down and tap <b>Add to Home Screen</b>.</li><li>Tap <b>Add</b>. The app opens full screen from its new icon.</li></ol>
        <p class="muted">Works in Safari. In Chrome on iPhone, the Share button is in the address bar.</p>
        <form method="dialog"><button class="btn primary">Got it</button></form>`;
      document.body.appendChild(d);
    }
    d.showModal ? d.showModal() : alert('Tap the Share button, then "Add to Home Screen".');
  };

  btns.forEach(b => b.addEventListener('click', async () => {
    b.closest('details')?.removeAttribute('open');
    if (deferred) { deferred.prompt(); await deferred.userChoice.catch(() => {}); deferred = null; return; }
    if (ios) return iosHelp();
    alert('Open your browser menu and choose "Install app" or "Add to Home screen".');
  }));
})();

// v1.19 sign out after inactivity (minutes set in Settings → Security). Activity = typing, tapping, scrolling.
// Shared across tabs via localStorage so an idle tab doesn't sign out someone working in another tab.
(() => {
  const limit = parseInt(document.body?.dataset.idle || '0', 10) * 1000;
  if (!limit) return;
  const KEY = 'tl_last_active', WARN = Math.min(60000, limit / 3);
  const store = { get() { try { return parseInt(localStorage.getItem(KEY) || '0', 10); } catch { return 0; } }, set(v) { try { localStorage.setItem(KEY, String(v)); } catch {} } };
  let local = Date.now(), lastPing = Date.now(), dlg = null, done = false;
  const lastActive = () => Math.max(local, store.get());
  const mark = (e) => { if (done) return; local = Date.now(); store.set(local); if (dlg?.open && !(e && dlg.contains(e.target))) dlg.close(); };
  ['pointerdown', 'keydown', 'input', 'scroll', 'touchstart', 'wheel'].forEach(ev => addEventListener(ev, mark, { passive: true, capture: true }));
  store.set(local);

  const ping = async () => {
    lastPing = Date.now();
    try {
      const r = await fetch('/session/ping', { credentials: 'same-origin', redirect: 'manual', cache: 'no-store' });
      if (r.status !== 204) signOut(false);   // already signed out on the server
    } catch {}   // offline: try again later
  };
  const signOut = async (tellServer = true, idle = true) => {
    if (done) return; done = true;
    if (tellServer) {
      const body = new FormData(); if (idle) body.append('idle', '1'); body.append('_csrf', document.querySelector('meta[name=csrf]')?.content || document.querySelector('input[name=_csrf]')?.value || '');
      try { await fetch('/logout', { method: 'POST', body, credentials: 'same-origin', redirect: 'manual' }); } catch {}
    }
    location.href = '/login';
  };
  const warn = () => {
    if (!dlg) {
      dlg = document.createElement('dialog'); dlg.className = 'installdlg';
      dlg.innerHTML = '<h3>Still there?</h3><p>You\'ll be signed out in <b data-left>60</b> seconds because nothing has happened for a while.</p><form method="dialog" style="display:flex;gap:8px;justify-content:flex-end"><button class="btn" value="out" type="submit">Sign out</button><button class="btn primary" value="stay" type="submit" autofocus>Stay signed in</button></form>';
      document.body.appendChild(dlg);
      dlg.addEventListener('close', () => { if (dlg.returnValue === 'out') signOut(true, false); else { mark(); ping(); } dlg.returnValue = ''; });
      dlg.addEventListener('cancel', () => { dlg.returnValue = 'stay'; });
    }
    if (!dlg.open) { dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', ''); }
  };
  const tick = () => {
    if (done) return;
    const idle = Date.now() - lastActive();
    if (idle >= limit) return signOut();
    if (idle >= limit - WARN) { warn(); const b = dlg?.querySelector('[data-left]'); if (b) b.textContent = Math.max(1, Math.ceil((limit - idle) / 1000)); }
    else if (dlg?.open) dlg.close();
    // Someone is working on this page: tell the server now and then so the sign-in stays alive
    const every = Math.min(5 * 60000, limit / 3);
    if (lastActive() > lastPing && Date.now() - lastPing > every) ping();
  };
  setInterval(tick, 1000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
})();
