(function () {
  var body = document.body;
  var toggle = document.getElementById("admin-sidebar-toggle");
  var closeBtn = document.getElementById("admin-sidebar-close");
  var scrim = document.getElementById("admin-sidebar-scrim");
  var sidebar = document.getElementById("admin-sidebar");

  function setOpen(open) {
    if (open) {
      body.classList.add("admin-sidebar-open");
      if (toggle) toggle.setAttribute("aria-expanded", "true");
      if (scrim) scrim.removeAttribute("hidden");
    } else {
      body.classList.remove("admin-sidebar-open");
      if (toggle) toggle.setAttribute("aria-expanded", "false");
      if (scrim) scrim.setAttribute("hidden", "hidden");
    }
  }

  if (toggle && sidebar) {
    toggle.addEventListener("click", function () {
      var open = !body.classList.contains("admin-sidebar-open");
      setOpen(open);
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener("click", function () {
      setOpen(false);
    });
  }

  if (scrim) {
    scrim.addEventListener("click", function () {
      setOpen(false);
    });
  }

  window.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      setOpen(false);
    }
  });

  window.addEventListener("resize", function () {
    if (window.matchMedia("(min-width: 901px)").matches) {
      setOpen(false);
    }
  });

  document.querySelectorAll(".admin-alert[data-dismissible] .admin-alert__dismiss").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var root = btn.closest(".admin-alert");
      if (root && root.parentElement) {
        root.parentElement.removeChild(root);
      }
    });
  });

  /** Keep the current sidebar item visible after full page navigation (desktop scrollable nav). */
  function scrollActiveSidebarLinkIntoView() {
    var nav = document.querySelector(".admin-sidebar__nav");
    var active = nav && nav.querySelector("a.admin-navlink.is-active");
    if (!nav || !active) {
      return;
    }
    requestAnimationFrame(function () {
      active.scrollIntoView({ block: "center", inline: "nearest", behavior: "auto" });
    });
  }

  scrollActiveSidebarLinkIntoView();
})();

/* ============================================================
   Theme switcher
   Source of truth: PHP session (cms-admin/actions/theme-update.php
   writes $_SESSION['wpm_theme']; includes/header.php reads it and
   renders data-theme on <html> server-side — no FOUC, and it follows
   the admin across every page navigation). This block:
     1. Applies the chosen theme to <html> immediately on change.
     2. Persists it to the session via fetch() so the next page load
        (any menu, any tab) keeps the same theme.
     3. Also mirrors it to localStorage as a same-tab fallback only.
   ============================================================ */
(function () {
  var THEME_KEY    = "wpm-theme";
  var DEFAULT      = "light-modern";
  var VALID_THEMES = ["dark-modern", "light-modern", "deep-purple"];
  var html         = document.documentElement;

  function persistToSession(theme, select) {
    var action = select.dataset.themeAction;
    var token  = select.dataset.csrfToken;
    if (!action) { return; }
    fetch(action, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-CSRF-Token": token || ""
      },
      body: "theme=" + encodeURIComponent(theme),
      credentials: "same-origin"
    }).catch(function () {
      /* Network hiccup: theme still applied for this page view via
         localStorage/dataset; it will just re-sync from session on the
         next successful navigation instead. */
    });
  }

  function applyTheme(theme, select) {
    if (VALID_THEMES.indexOf(theme) === -1) { theme = DEFAULT; }
    html.dataset.theme = theme;
    try { localStorage.setItem(THEME_KEY, theme); } catch (e) {}
    if (select) {
      if (select.value !== theme) { select.value = theme; }
      persistToSession(theme, select);
    }
  }

  function initSelect() {
    var select = document.getElementById("theme-switcher");
    if (!select) { return; }
    var current = html.dataset.theme || DEFAULT;
    select.value = current;
    select.addEventListener("change", function () {
      applyTheme(select.value, select);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSelect);
  } else {
    initSelect();
  }
}());

/* ============================================================
   Sidebar-menu search (navbar)
   Pure client-side filter — no request per keystroke. The menu list
   (already filtered to what this admin's role may open) is embedded by
   includes/navbar.php in data-menu-index as [{label, group, href}, …].
   Typing "banner", "seo", "media"… lists the matching sidebar entries
   instantly; Enter opens the first match, ArrowDown/Up moves between
   results.
   ============================================================ */
(function () {
  var input = document.getElementById("admin-search-input");
  var resultsBox = document.getElementById("admin-search-results");
  if (!input || !resultsBox) { return; }

  var wrapper = input.closest(".admin-search");
  var menuItems = [];
  try {
    menuItems = JSON.parse((wrapper && wrapper.dataset.menuIndex) || "[]");
  } catch (e) {
    menuItems = [];
  }
  menuItems.forEach(function (item, i) {
    item._order = i;
    item._label = String(item.label || "").toLowerCase();
    item._hay = (item._label + " " + String(item.group || "").toLowerCase());
  });

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function hideResults() {
    resultsBox.setAttribute("hidden", "hidden");
    resultsBox.innerHTML = "";
  }

  /* Every whitespace-separated term must appear in "label + group";
     entries whose own label matches the whole query rank first, rest
     keep sidebar order. */
  function filterMenu(query) {
    var q = query.toLowerCase();
    var terms = q.split(/\s+/).filter(Boolean);
    return menuItems
      .filter(function (item) {
        return terms.every(function (t) { return item._hay.indexOf(t) !== -1; });
      })
      .sort(function (a, b) {
        var ra = a._label.indexOf(q) !== -1 ? 0 : 1;
        var rb = b._label.indexOf(q) !== -1 ? 0 : 1;
        return ra - rb || a._order - b._order;
      });
  }

  function renderResults(items, query) {
    if (!items.length) {
      resultsBox.innerHTML =
        '<div class="admin-search__empty">Menu “' + escapeHtml(query) + '” tidak ditemukan.</div>';
      resultsBox.removeAttribute("hidden");
      return;
    }
    var html = "";
    items.forEach(function (item) {
      html +=
        '<a class="admin-search__item" href="' + escapeHtml(item.href) + '">' +
        '<span class="admin-search__item-title">' + escapeHtml(item.label || "(untitled)") + "</span>" +
        '<span class="admin-search__item-subtitle">' + escapeHtml(item.group || "Menu utama") + "</span>" +
        "</a>";
    });
    resultsBox.innerHTML = html;
    resultsBox.removeAttribute("hidden");
  }

  function update() {
    var query = input.value.trim();
    if (!query) {
      hideResults();
      return;
    }
    renderResults(filterMenu(query), query);
  }

  input.addEventListener("input", update);

  input.addEventListener("focus", function () {
    if (input.value.trim()) { update(); }
  });

  document.addEventListener("click", function (e) {
    if (!wrapper.contains(e.target)) {
      hideResults();
    }
  });

  input.addEventListener("keydown", function (e) {
    var links = resultsBox.querySelectorAll(".admin-search__item");
    if (e.key === "Escape") {
      hideResults();
      input.blur();
    } else if (e.key === "Enter" && links.length) {
      e.preventDefault();
      window.location.href = links[0].getAttribute("href");
    } else if (e.key === "ArrowDown" && links.length) {
      e.preventDefault();
      links[0].focus();
    }
  });

  resultsBox.addEventListener("keydown", function (e) {
    var links = Array.prototype.slice.call(resultsBox.querySelectorAll(".admin-search__item"));
    var idx = links.indexOf(document.activeElement);
    if (idx === -1) { return; }
    if (e.key === "ArrowDown") {
      e.preventDefault();
      if (links[idx + 1]) { links[idx + 1].focus(); }
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      if (idx === 0) { input.focus(); } else { links[idx - 1].focus(); }
    } else if (e.key === "Escape") {
      hideResults();
      input.focus();
    }
  });
}());

/* ============================================================
   Notification bell — Growth Agent jobs needing attention
   (failed generations, SEO recommendations awaiting review).
   Server-rendered dropdown, just a plain show/hide toggle —
   same click-outside-to-close pattern as the search box above.
   ============================================================ */
(function () {
  var toggle = document.getElementById("admin-notif-toggle");
  var panel = document.getElementById("admin-notif-panel");
  var wrapper = document.getElementById("admin-notif");
  if (!toggle || !panel || !wrapper) { return; }

  function isOpen() {
    return !panel.hasAttribute("hidden");
  }

  function setOpen(open) {
    if (open) {
      panel.removeAttribute("hidden");
      toggle.setAttribute("aria-expanded", "true");
    } else {
      panel.setAttribute("hidden", "hidden");
      toggle.setAttribute("aria-expanded", "false");
    }
  }

  toggle.addEventListener("click", function (e) {
    e.stopPropagation();
    setOpen(!isOpen());
  });

  document.addEventListener("click", function (e) {
    if (!wrapper.contains(e.target)) {
      setOpen(false);
    }
  });

  window.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      setOpen(false);
    }
  });
}());
