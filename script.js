(function () {
  "use strict";
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var IMG = {
    laptops: "https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=85",
    desktops: "https://images.unsplash.com/photo-1587831990711-23ca6441447b?auto=format&fit=crop&w=900&q=85",
    printers: "https://images.unsplash.com/photo-1612815154858-60aa4c59eaa6?auto=format&fit=crop&w=900&q=85",
    monitors: "https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=900&q=85",
    accessories: "https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=900&q=85"
  };
  var CATS = { all: "All", laptops: "Laptops", desktops: "Desktops", printers: "Printers", monitors: "Monitors", accessories: "Accessories" };
  var PRODUCTS = [
    { id: 1, n: "SalesPro Book 14", c: "laptops", p: 799, d: "Light, fast and built for all-day battery life." },
    { id: 2, n: "SalesPro Book 15 Pro", c: "laptops", p: 1199, d: "Creator-ready display and a powerful processor." },
    { id: 3, n: "SalesPro Flip 13", c: "laptops", p: 999, d: "2-in-1 touchscreen that adapts to how you work." },
    { id: 4, n: "SalesPro Tower Plus", c: "desktops", p: 949, d: "Expandable performance for the home office." },
    { id: 5, n: "SalesPro All-in-One 24", c: "desktops", p: 879, d: "Clean, space-saving design with a 24\" display." },
    { id: 6, n: "SalesPro Mini Desktop", c: "desktops", p: 549, d: "Compact power that fits almost anywhere." },
    { id: 7, n: "SalesPro LaserJet 200", c: "printers", p: 229, d: "Fast, sharp black and white printing." },
    { id: 8, n: "SalesPro Ink Tank Wireless", c: "printers", p: 279, d: "Print, scan and copy at very low cost per page." },
    { id: 9, n: "SalesPro 27\" QHD Monitor", c: "monitors", p: 329, d: "Crisp colour and thin bezels for focused work." },
    { id: 10, n: "SalesPro 32\" 4K Monitor", c: "monitors", p: 549, d: "Sharp 4K detail with USB-C charging." },
    { id: 11, n: "SalesPro Wireless Keyboard & Mouse", c: "accessories", p: 59, d: "Quiet keys and a comfortable, precise mouse." },
    { id: 12, n: "SalesPro USB-C Dock", c: "accessories", p: 149, d: "One cable for power, display and everything else." }
  ];
  var money = function (n) { return "$" + n.toLocaleString("en-US"); };
  var byId = function (id) { return PRODUCTS.filter(function (p) { return p.id === id; })[0]; };

  /* Storage helpers (safe if blocked) */
  function load() { try { return JSON.parse(localStorage.getItem("sp_cart")) || {}; } catch (e) { return {}; } }
  function save(c) { try { localStorage.setItem("sp_cart", JSON.stringify(c)); } catch (e) {} }
  var cart = load();
  function count() { return Object.keys(cart).reduce(function (a, k) { return a + cart[k]; }, 0); }
  function updateBadge() { $$(".badge").forEach(function (b) { b.textContent = count(); b.dataset.n = count(); }); }
  function toast(msg) {
    var t = $(".toast"); if (!t) return;
    t.textContent = msg; t.classList.add("show");
    clearTimeout(toast.t); toast.t = setTimeout(function () { t.classList.remove("show"); }, 2200);
  }
  function add(id) { cart[id] = (cart[id] || 0) + 1; save(cart); updateBadge(); toast(byId(id).n + " added to cart"); }

  /* Header */
  var header = $(".site-header"), toggle = $(".menu-toggle"), nav = $(".main-nav");
  window.addEventListener("scroll", function () { header.classList.toggle("scrolled", window.scrollY > 8); }, { passive: true });
  toggle.addEventListener("click", function () {
    var open = nav.classList.toggle("open"); toggle.setAttribute("aria-expanded", open);
  });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") nav.classList.remove("open"); });
  $$(".yr").forEach(function (y) { y.textContent = new Date().getFullYear(); });
  updateBadge();

  /* Product card markup */
  function card(p) {
    return '<article class="product-card"><div class="product-image"><img loading="lazy" src="' + IMG[p.c] + '" alt="' + p.n + '"></div>' +
      '<span class="tag">' + CATS[p.c] + '</span><h3>' + p.n + '</h3><p>' + p.d + '</p>' +
      '<div class="price-row"><span class="price">' + money(p.p) + '</span>' +
      '<button class="button button-dark button-sm" data-add="' + p.id + '">Add to cart</button></div></article>';
  }
  document.addEventListener("click", function (e) {
    var b = e.target.closest("[data-add]"); if (b) add(+b.dataset.add);
  });

  /* Home: featured */
  var feat = $("#featured");
  if (feat) feat.innerHTML = [1, 4, 9, 8].map(function (i) { return card(byId(i)); }).join("");

  /* Home: hero slides */
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
      $("#heroTitle").innerHTML = slides[i][0]; $("#heroCopy").textContent = slides[i][1];
      $("#heroShop").href = "products.html?cat=" + slides[i][2];
      $(".hero-content").classList.remove("fade"); void $(".hero-content").offsetWidth; $(".hero-content").classList.add("fade");
      dots.forEach(function (d, k) { d.classList.toggle("active", k === i); d.setAttribute("aria-current", k === i); });
    };
    var run = function () { clearInterval(timer); timer = setInterval(function () { show((cur + 1) % slides.length); }, 6500); };
    dots.forEach(function (d, i) { d.addEventListener("click", function () { show(i); run(); }); });
    run();
  }

  /* Products page: filter, search, sort */
  var grid = $("#productGrid");
  if (grid) {
    var params = new URLSearchParams(location.search);
    var state = { cat: CATS[params.get("cat")] ? params.get("cat") : "all", q: params.get("q") || "", sort: "featured" };
    var chips = $("#chips"), q = $("#search"), sort = $("#sort");
    chips.innerHTML = Object.keys(CATS).map(function (k) { return '<button class="chip" data-cat="' + k + '">' + CATS[k] + "</button>"; }).join("");
    q.value = state.q;
    var render = function () {
      var list = PRODUCTS.filter(function (p) {
        return (state.cat === "all" || p.c === state.cat) && (p.n + " " + p.d).toLowerCase().indexOf(state.q.toLowerCase()) > -1;
      });
      if (state.sort === "low") list.sort(function (a, b) { return a.p - b.p; });
      if (state.sort === "high") list.sort(function (a, b) { return b.p - a.p; });
      grid.innerHTML = list.length ? list.map(card).join("") : '<p class="empty">No products match your search. Try another keyword or category.</p>';
      $$(".chip", chips).forEach(function (c) { c.classList.toggle("active", c.dataset.cat === state.cat); });
      $("#resultCount").textContent = list.length + (list.length === 1 ? " product" : " products");
    };
    chips.addEventListener("click", function (e) { var c = e.target.closest(".chip"); if (c) { state.cat = c.dataset.cat; render(); } });
    q.addEventListener("input", function () { state.q = q.value; render(); });
    sort.addEventListener("change", function () { state.sort = sort.value; render(); });
    render();
  }

  /* Cart page */
  var list = $("#cartList");
  if (list) {
    var draw = function () {
      var ids = Object.keys(cart), sub = 0;
      if (!ids.length) {
        list.innerHTML = '<p class="empty">Your cart is empty. <a class="text-link" href="products.html">Browse products</a></p>';
        $("#cartSummary").hidden = true; return;
      }
      $("#cartSummary").hidden = false;
      list.innerHTML = ids.map(function (id) {
        var p = byId(+id); sub += p.p * cart[id];
        return '<div class="cart-row"><img src="' + IMG[p.c] + '" alt=""><div><strong>' + p.n + '</strong><br><span class="qty">' +
          '<button data-q="-1" data-id="' + id + '" aria-label="Decrease quantity">−</button><span>' + cart[id] + '</span>' +
          '<button data-q="1" data-id="' + id + '" aria-label="Increase quantity">+</button></span>' +
          '<button class="link-btn" data-rm="' + id + '">Remove</button></div><div><strong>' + money(p.p * cart[id]) + "</strong></div></div>";
      }).join("");
      var ship = sub > 500 ? 0 : 25;
      $("#sub").textContent = money(sub); $("#ship").textContent = ship ? money(ship) : "Free"; $("#tot").textContent = money(sub + ship);
    };
    list.addEventListener("click", function (e) {
      var q = e.target.closest("[data-q]"), r = e.target.closest("[data-rm]");
      if (q) { var id = q.dataset.id; cart[id] += +q.dataset.q; if (cart[id] < 1) delete cart[id]; }
      if (r) delete cart[r.dataset.rm];
      if (q || r) { save(cart); updateBadge(); draw(); }
    });
    $("#checkout").addEventListener("click", function () {
      cart = {}; save(cart); updateBadge(); draw();
      toast("Thanks! This is a demo, so no order was placed.");
    });
    draw();
  }

  /* Forms: validation */
  $$("form[data-validate]").forEach(function (f) {
    f.setAttribute("novalidate", "");
    f.addEventListener("submit", function (e) {
      e.preventDefault(); var ok = true;
      $$("[required]", f).forEach(function (el) {
        var field = el.closest(".field"), msg = "", v = el.value.trim();
        if (!v) msg = "This field is required.";
        else if (el.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) msg = "Enter a valid email address.";
        if (field) { field.classList.toggle("bad", !!msg); $(".err", field).textContent = msg; }
        if (msg) ok = false;
      });
      if (ok) {
        f.reset();
        var n = $(".notice", f.parentNode); if (n) n.hidden = false; else toast("Thanks! We’ll be in touch soon.");
      }
    });
  });
})();
