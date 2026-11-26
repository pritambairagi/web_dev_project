<?php
$pageTitle = 'SalesPro | Technology for Everyone';
$pageDescription = 'Shop laptops, desktops, printers, monitors and accessories at SalesPro.';
$activeNav = 'home';

require_once __DIR__ . '/config/db.php';
$pdo = getDbConnection();

// Fetch categories from DB
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $catStmt->fetchAll();

// Fetch featured products from DB
$featStmt = $pdo->query("SELECT p.*, c.name AS category_name, c.slug AS category_slug 
                         FROM products p 
                         JOIN categories c ON p.category_id = c.id 
                         WHERE p.is_featured = 1 
                         ORDER BY p.id ASC 
                         LIMIT 4");
$featuredProducts = $featStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

  <main id="main">
    <section class="hero" aria-label="Featured products">
      <div class="hero-image" role="img" aria-label="Laptop on a modern desk"></div>
      <div class="hero-content">
        <p class="eyebrow">SALESPRO.</p>
        <h1 id="heroTitle">Make room for<br>what’s next.</h1>
        <p class="hero-copy" id="heroCopy">Meet the new generation of PCs, designed to help you create, connect, and do more.</p>
        <div class="hero-buttons">
          <a class="button button-dark" id="heroShop" href="products.php?cat=laptops">Shop PCs</a>
          <a class="text-link" href="products.php">Explore the lineup <span>→</span></a>
        </div>
      </div>
      <div class="hero-dots" role="group" aria-label="Choose slide">
        <button class="dot active" aria-label="Slide 1"></button>
        <button class="dot" aria-label="Slide 2"></button>
        <button class="dot" aria-label="Slide 3"></button>
      </div>
    </section>

    <section class="section" aria-label="Video">
      <div class="wrap">
        <iframe class="home-video"
          src="https://www.youtube-nocookie.com/embed/N2rfh81I3VQ"
          title="LG UltraFine evo 32U990A-S Monitor"
          loading="lazy"
          referrerpolicy="strict-origin-when-cross-origin"
          allow="encrypted-media; picture-in-picture"
          allowfullscreen></iframe>
      </div>
    </section>

    <!-- Categories Section -->
    <section class="section">
      <div class="wrap">
        <div class="section-heading">
          <p class="eyebrow">FIND YOUR FIT</p>
          <h2>Our products</h2>
          <p>Technology to help you work, create, learn, and play.</p>
        </div>
        <div class="grid g4">
          <?php foreach ($categories as $cat): 
            if ($cat['slug'] === 'accessories') continue; // keep 4 main on top grid
          ?>
            <a class="product-card" href="products.php?cat=<?= e($cat['slug']) ?>">
              <div class="product-image">
                <img loading="lazy" src="<?= e($cat['image_url']) ?>" alt="<?= e($cat['name']) ?>">
              </div>
              <h3><?= e($cat['name']) ?></h3>
              <p><?= e($cat['description']) ?></p>
              <span class="card-link">Shop <?= strtolower(e($cat['name'])) ?> →</span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- Popular Products Section -->
    <section class="section alt">
      <div class="wrap">
        <div class="section-heading">
          <h2>Popular right now</h2>
          <p>Our customers’ favourites, ready to ship.</p>
        </div>
        <div class="grid g4" id="featured">
          <?php foreach ($featuredProducts as $p): ?>
            <article class="product-card">
              <div class="product-image">
                <img loading="lazy" src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
              </div>
              <span class="tag"><?= e($p['category_name']) ?></span>
              <h3><?= e($p['name']) ?></h3>
              <p><?= e($p['description']) ?></p>
              <div class="price-row">
                <span class="price"><?= formatPrice($p['price']) ?></span>
                <button class="button button-dark button-sm" data-add="<?= (int)$p['id'] ?>">Add to cart</button>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <p style="text-align:center;margin-top:30px">
          <a class="button button-ghost" href="products.php">View all products</a>
        </p>
      </div>
    </section>

    <!-- Why Choose Us -->
    <section class="section">
      <div class="wrap">
        <div class="section-heading">
          <h2>Why shop with SalesPro</h2>
          <p>Straightforward service from people who know technology.</p>
        </div>
        <div class="grid g4">
          <div class="feature"><div class="ico">🚚</div><h3>Free shipping</h3><p>On every order over $500, delivered quickly.</p></div>
          <div class="feature"><div class="ico">🛡️</div><h3>3-year warranty</h3><p>Cover for peace of mind on all PCs.</p></div>
          <div class="feature"><div class="ico">↩️</div><h3>30-day returns</h3><p>Not right for you? Send it back, no fuss.</p></div>
          <div class="feature"><div class="ico">💬</div><h3>Real support</h3><p>Friendly experts, seven days a week.</p></div>
        </div>
      </div>
    </section>

    <!-- Stats -->
    <section class="stats">
      <div class="wrap grid g4">
        <div><strong>250K+</strong><span>Happy customers</span></div>
        <div><strong>12</strong><span>Years in business</span></div>
        <div><strong>4.8/5</strong><span>Average rating</span></div>
        <div><strong>24/7</strong><span>Online help</span></div>
      </div>
    </section>

    <!-- Newsletter CTA -->
    <section class="section">
      <div class="wrap">
        <div class="cta">
          <div>
            <h2>Get news and offers</h2>
            <p>Join our newsletter for new arrivals and member-only deals.</p>
          </div>
          <form class="news" id="newsletterForm" data-validate>
            <input type="email" name="email" required placeholder="Email address" aria-label="Email address">
            <button type="submit" class="button button-dark" style="background:#fff;color:var(--ink);border-color:#fff">Subscribe</button>
          </form>
        </div>
      </div>
    </section>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

