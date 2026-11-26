/**
 * SalesPro Frontend Script
 * Integrated with PHP backend & MySQL database (salespro)
 */
(function () {
  "use strict";

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* Format currency helper */
  function money(n) {
    return "$" + Number(n).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /* Toast Notification */
  function toast(msg) {
    var t = $(".toast");
    if (!t) return;
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(toast.t);
    toast.t = setTimeout(function () {
      t.classList.remove("show");
    }, 2400);
  }

  /* Update Cart Badge */
  function updateBadge(count) {
    $$(".badge").forEach(function (b) {
      var n = parseInt(count, 10) || 0;
      b.textContent = n > 0 ? n : "";
      b.dataset.n = n;
      b.style.display = n > 0 ? "grid" : "none";
    });
  }

  /* Header scroll effect & Mobile Menu Toggle */
  var header = $(".site-header"),
      toggle = $(".menu-toggle"),
      nav = $(".main-nav");

  if (header) {
    window.addEventListener("scroll", function () {
      header.classList.toggle("scrolled", window.scrollY > 8);
    }, { passive: true });
  }

  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") nav.classList.remove("open");
    });
  }

  /* Dynamic Year */
  $$(".yr").forEach(function (y) {
    y.textContent = new Date().getFullYear();
  });

  /* API Helper */
  function apiCart(action, data, callback) {
    var csrf = $('meta[name="csrf-token"]');
    if (!csrf || !csrf.content) {
      callback({ success: false, message: "Refresh the page and try again." });
      return;
    }
    var formData = new FormData();
    formData.append("action", action);
    formData.append("csrf_token", csrf.content);
    for (var k in data) {
      if (data.hasOwnProperty(k)) formData.append(k, data[k]);
    }
    fetch("api/cart.php", {
      method: "POST",
      body: formData
    })
      .then(function (res) { return res.json(); })
      .then(function (res) {
        if (callback) callback(res);
      })
      .catch(function (err) {
        console.error("Cart API error:", err);
        callback({ success: false, message: "Could not update your cart. Please try again." });
      });
  }

  /* Add to Cart Handler (Event Delegation) */
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-add]");
    if (btn) {
      e.preventDefault();
      var id = btn.dataset.add;
      var originalText = btn.textContent;
      btn.textContent = "Adding...";
      btn.disabled = true;

      apiCart("add", { id: id }, function (res) {
        btn.textContent = originalText;
        btn.disabled = false;
        if (res.success) {
          updateBadge(res.count);
          toast(res.message || "Product added to cart!");
        } else {
          toast(res.message || "Could not add product.");
        }
      });
    }
  });

  /* Hero Slider (Homepage) */
  var slides = [
    ["Make room for<br>what’s next.", "Meet the new generation of PCs, designed to help you create, connect, and do more.", "laptops"],
    ["Power for<br>every project.", "Desktops with the performance to handle work, study and play without slowing down.", "desktops"],
    ["Set up a space<br>you love.", "Monitors and accessories that make long days more comfortable and productive.", "monitors"]
  ];
  var dots = $$(".hero .dot");
  if (dots.length) {
    var cur = 0, timer;
    var show = function (i) {
      cur = i;
      var hTitle = $("#heroTitle"), hCopy = $("#heroCopy"), hShop = $("#heroShop");
      if (hTitle) hTitle.innerHTML = slides[i][0];
      if (hCopy) hCopy.textContent = slides[i][1];
      if (hShop) hShop.href = "products.php?cat=" + slides[i][2];
      var hc = $(".hero-content");
      if (hc) {
        hc.classList.remove("fade");
        void hc.offsetWidth;
        hc.classList.add("fade");
      }
      dots.forEach(function (d, k) {
        d.classList.toggle("active", k === i);
        d.setAttribute("aria-current", k === i);
      });
    };
    var run = function () {
      clearInterval(timer);
      timer = setInterval(function () {
        show((cur + 1) % slides.length);
      }, 6500);
    };
    dots.forEach(function (d, i) {
      d.addEventListener("click", function () {
        show(i);
        run();
      });
    });
    run();
  }

  /* Cart Page Interactive Quantity & Removal */
  var cartList = $("#cartList");
  if (cartList) {
    cartList.addEventListener("click", function (e) {
      var qBtn = e.target.closest("[data-q]");
      var rmBtn = e.target.closest("[data-rm]");

      if (qBtn) {
        var id = qBtn.dataset.id;
        var change = parseInt(qBtn.dataset.q, 10);
        apiCart("update", { id: id, change: change }, function (res) {
          if (res.success) {
            updateBadge(res.count);
            // Refresh cart display or reload to reflect new totals cleanly
            location.reload();
          } else {
            toast(res.message);
          }
        });
      }

      if (rmBtn) {
        var rId = rmBtn.dataset.rm;
        apiCart("remove", { id: rId }, function (res) {
          if (res.success) {
            updateBadge(res.count);
            toast("Item removed from cart");
            location.reload();
          } else {
            toast(res.message);
          }
        });
      }
    });
  }

  var savedAddress = $("#checkoutForm #address_id");
  if (savedAddress) {
    var addressFields = $$("#checkoutForm #name, #checkoutForm #phone, #checkoutForm #address");
    var manualAddress = {};
    addressFields.forEach(function (field) {
      manualAddress[field.id] = field.value;
      field.addEventListener("input", function () { manualAddress[field.id] = field.value; });
    });
    function syncAddressFields() {
      addressFields.forEach(function (field) {
        var saved = savedAddress.value ? savedAddress.selectedOptions[0].dataset[field.id] : "";
        var useSaved = !!savedAddress.value && (field.id !== "phone" || !!saved);
        field.required = !useSaved;
        field.readOnly = useSaved;
        field.value = useSaved ? saved : manualAddress[field.id];
      });
    }
    savedAddress.addEventListener("change", syncAddressFields);
    syncAddressFields();
  }

  /* Newsletter AJAX Subscription */
  var newsForm = $("#newsletterForm");
  if (newsForm) {
    newsForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var input = newsForm.querySelector('input[name="email"]');
      if (!input || !input.value.trim()) return;

      var formData = new FormData();
      formData.append("email", input.value.trim());

      fetch("api/subscribe.php", {
        method: "POST",
        body: formData
      })
        .then(function (res) { return res.json(); })
        .then(function (res) {
          toast(res.message);
          if (res.success) {
            input.value = "";
          }
        })
        .catch(function () {
          toast("Subscription error. Please try again.");
        });
    });
  }

  /* Forms: client-side validation */
  $$("form[data-validate]").forEach(function (f) {
    f.setAttribute("novalidate", "");
    f.addEventListener("submit", function (e) {
      var ok = true;
      $$("[required]", f).forEach(function (el) {
        var field = el.closest(".field"),
            msg = "",
            v = el.value.trim();
        if (!v) {
          msg = "This field is required.";
        } else if (el.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
          msg = "Enter a valid email address.";
        }
        if (field) {
          field.classList.toggle("bad", !!msg);
          var errSpan = $(".err", field);
          if (errSpan) errSpan.textContent = msg;
        }
        if (msg) ok = false;
      });
      if (!ok) {
        e.preventDefault();
      }
    });
  });

})();
