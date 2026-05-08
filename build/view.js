!function(){
  var items = document.querySelectorAll("[data-post-title]");
  if (!items.length) return;

  var popover = null;
  var hideTimer = null;

  function hidePopover() {
    hideTimer = setTimeout(function() {
      if (popover) { popover.remove(); popover = null; }
    }, 120);
  }

  function cancelHide() {
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
  }

  function showPopover(el) {
    cancelHide();
    if (popover) { popover.remove(); popover = null; }

    var title = el.dataset.postTitle;
    var url = el.dataset.postUrl;
    if (!title) return;

    popover = document.createElement("div");
    popover.className = "lexical-lode-popover";
    popover.setAttribute("role", "tooltip");
    popover.id = "lexical-lode-tooltip";

    var link = document.createElement("a");
    link.href = url || "#";
    link.textContent = title;
    if (url) {
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      var srHint = document.createElement("span");
      srHint.className = "screen-reader-text";
      srHint.textContent = " (opens in a new tab)";
      link.appendChild(srHint);
    }
    popover.appendChild(link);

    popover.addEventListener("mouseenter", cancelHide);
    popover.addEventListener("mouseleave", hidePopover);

    document.body.appendChild(popover);

    var rect = el.getBoundingClientRect();
    popover.style.left = (rect.left + window.scrollX) + "px";
    popover.style.top = (rect.top + window.scrollY - popover.offsetHeight - 4) + "px";

    el.setAttribute("aria-describedby", "lexical-lode-tooltip");
  }

  function removeDescribedBy(el) {
    el.removeAttribute("aria-describedby");
  }

  items.forEach(function(el) {
    // Make focusable for keyboard users
    if (!el.getAttribute("tabindex")) {
      el.setAttribute("tabindex", "0");
    }

    // Mouse
    el.addEventListener("mouseenter", function() { showPopover(el); });
    el.addEventListener("mouseleave", function() { removeDescribedBy(el); hidePopover(); });

    // Keyboard
    el.addEventListener("focusin", function() { showPopover(el); });
    el.addEventListener("focusout", function() { removeDescribedBy(el); hidePopover(); });

    // Escape to dismiss
    el.addEventListener("keydown", function(e) {
      if (e.key === "Escape" && popover) {
        removeDescribedBy(el);
        popover.remove();
        popover = null;
      }
    });
  });

  // Also handle Escape globally
  document.addEventListener("keydown", function(e) {
    if (e.key === "Escape" && popover) {
      popover.remove();
      popover = null;
    }
  });
}();
