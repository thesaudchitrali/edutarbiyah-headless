# EduTarbiyah — Headless Static Frontend (Astro 5 + WordPress/WooCommerce)

Ultra-fast, modern headless web platform for **"EduTarbiyah"** (Islamic Parenting, Moral Education & Holistic Growth), powered by Astro 5 static site generation and an existing WordPress + WooCommerce backend.

---

## 🏛️ Architecture Overview

```
WordPress + WooCommerce (Backend)
  ├── WordPress REST API (/wp-json/wp/v2/)
  ├── Custom MU-Plugin Menu API (/wp-json/edutarbiyah/v1/menu/primary-menu)
  └── WooCommerce Store API (/wp-json/wc/store/v1/)
         ↓
Astro 5 (Frontend Static SSG Layer)
         ↓
Pure Static Output (HTML, CSS, JS in dist/)
         ↓
Shared Hosting (cPanel / Apache / Nginx via FTP)
```

- **Output Mode:** Pure static HTML/CSS/JS (`output: 'static'`).
- **No Node.js runtime required** on production shared hosting.
- **Single Source of Truth:** WordPress and WooCommerce manage all articles, products, inventory, payments, and navigation.

---

## 🌟 Key Features

1. **Dignified Modern Visual Identity:**
   - Deep Sage / Emerald Green (`#0F766E` / `#064E3B`), subtle Warm Ochre / Gold accents (`#F59E0B`), warm paper/cream backgrounds (`#FFFDF9`), and high-contrast charcoal typography.
   - Editorial typography pairing: **Playfair Display** (headings) + **Plus Jakarta Sans** (humanist sans-serif body) + **Noto Nastaliq Urdu** (for dual-language book titles and Urdu blog).

2. **Full Dynamic Route Coverage:**
   - **Homepage (`/`):** High-converting landing page showcasing diagnostic assessments, core pillars, WooCommerce bookstore, flagship courses (TPPC), and community invite.
   - **Bookstore Catalog (`/shop/`):** Clean filterable catalog with category pills, price display, and stock indicators.
   - **Category Pages (`/product-category/[slug]/`):** Collection views with sibling navigation pills.
   - **Product Detail (`/product/[slug]/`):** Live client-side freshness island, dynamic variation resolution, native WooCommerce cart handoff, and 1-click WhatsApp ordering.
   - **English Blog (`/english/` & `/english/[slug]/`):** Clean reading experience with metadata, sharing buttons, and cross-links to the Urdu portal.
   - **Parents WhatsApp Community (`/parents-whatsapp-community/`):** Dedicated community gateway for daily reminders and peer support.
   - **Dynamic WordPress Pages (`/[slug]/`):** Automatic rendering of pages with interactive script execution runner.

3. **Interactive Quizzes & Assessments (Script Runner):**
   - Interactive WordPress tools like `/audit-your-parenting/`, `/family-vision-maker/`, and `/childs-behavior-improvement-tracker/` execute smoothly in the browser using the client-side `<ScriptRunner />` component.

4. **1-Click WhatsApp & Native Cart Handoff:**
   - **Simple Products:** Links directly to `https://edutarbiyah.com/cart/?add-to-cart=PRODUCT_ID`.
   - **Variable Products:** Dynamically resolves selected attributes to variation ID: `https://edutarbiyah.com/cart/?add-to-cart=PARENT_ID&variation_id=VARIATION_ID&attribute_pa_x=val`.
   - **WhatsApp Ordering:** Direct button pre-populates item name, price, variation selection, and URL to WhatsApp number `+92 312 2370720`.

5. **Build-Time Resilience & Disk Caching:**
   - Resilient JSON parser extracts clean JSON starting from the first `{` or `[`, bypassing Elementor HTML buffer prefixes.
   - Local disk cache (`.cache/api/`) ensures static builds run in **under 5 seconds** without hitting shared hosting rate limits.

---

## 📁 Repository Structure

```text
├── .github/workflows/
│   └── deploy.yml                       # GitHub Actions CI/CD (Build & FTP upload)
├── public/
│   ├── .htaccess                        # Apache configuration (DirectoryIndex & Trailing Slashes)
│   ├── favicon.svg                      # EduTarbiyah brand mark
│   └── images/
│       └── book-placeholder.svg         # Fallback product graphic
├── src/
│   ├── components/
│   │   ├── Header.astro                 # Hierarchical navigation & mobile menu
│   │   ├── Footer.astro                 # Multi-column footer & contact details
│   │   ├── ProductCard.astro            # Book / toolkit card component
│   │   ├── ProductFreshness.astro       # Client freshness check & variation selector
│   │   ├── ScriptRunner.astro           # Client-side script runner for interactive quizzes
│   │   └── WhatsAppButton.astro         # Floating WhatsApp assistance widget
│   ├── layouts/
│   │   └── Layout.astro                 # Base HTML wrapper with Google Fonts & SEO
│   ├── lib/
│   │   └── api.ts                       # WordPress/WooCommerce API client with caching
│   ├── pages/
│   │   ├── index.astro                  # Homepage
│   │   ├── [slug].astro                 # Dynamic WordPress page renderer
│   │   ├── shop/index.astro             # Bookstore catalog
│   │   ├── product-category/[slug].astro# Product category pages
│   │   ├── product/[slug].astro         # Product detail page
│   │   ├── english/index.astro          # English blog list
│   │   ├── english/[slug].astro         # English blog single article
│   │   └── parents-whatsapp-community.astro # WhatsApp community gateway
│   └── styles/
│       └── global.css                   # Tailwind v4, typography & WordPress styles
├── wordpress/
│   └── mu-plugins/
│       ├── edutarbiyah-menu-api.php     # Custom hierarchical menu endpoint
│       └── edutarbiyah-auto-deploy.php  # Content change webhook to GitHub Actions
├── astro.config.mjs                     # Astro 5 configuration
├── package.json
└── tsconfig.json
```

---

## 🚀 Local Development

1. **Install Dependencies:**
   ```bash
   npm install
   ```

2. **Start Dev Server:**
   ```bash
   npm run dev
   ```
   Open [http://localhost:4321](http://localhost:4321) in your browser.

3. **Build Static Production Site:**
   ```bash
   npm run build
   ```
   Generates production-ready static assets in the `./dist/` directory.

4. **Preview Static Production Build:**
   ```bash
   npm run preview
   ```

---

## 🔌 WordPress MU-Plugins Setup

Copy the files from `wordpress/mu-plugins/` directly into your WordPress server's `wp-content/mu-plugins/` folder (create the directory if it does not exist):

1. **`edutarbiyah-menu-api.php`:**
   - Exposes `/wp-json/edutarbiyah/v1/menu/primary-menu`.
   - Returns a structured hierarchical tree of your primary menu with relative links.
2. **`edutarbiyah-auto-deploy.php`:**
   - Automatically pings GitHub Actions when a Post, Page, or WooCommerce Product is published or updated.
   - Adds an admin management page under **WP Admin → Tools → Deploy Frontend** where you can enter your GitHub repository name and Personal Access Token (PAT), or trigger a manual deploy with one click.

---

## 🚢 Deployment Methods

### Option A: 1-Command Local Automated Deploy (`npm run deploy`)
Never copy/paste files manually again! You can deploy directly from your local terminal with one command:

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
2. Enter your hosting FTP credentials inside `.env`:
   ```env
   FTP_HOST=ftp.edutarbiyah.com
   FTP_USER=your_cpanel_username
   FTP_PASSWORD=your_ftp_password
   FTP_REMOTE_DIR=public_html/
   ```
3. Run:
   ```bash
   npm run deploy
   ```
   This automatically builds the static site and syncs all files from `dist/` directly to your hosting folder!

---

### Option B: Automated GitHub Actions CI/CD
Add the following secrets to your GitHub repository (**Settings → Secrets and variables → Actions**):
- `FTP_SERVER`: Your shared hosting FTP host (e.g., `ftp.edutarbiyah.com`)
- `FTP_USERNAME`: Your cPanel FTP username
- `FTP_PASSWORD`: Your FTP password
- `FTP_REMOTE_DIR`: Target folder on hosting (e.g., `public_html/`)

Whenever content is updated in WordPress, the `edutarbiyah-auto-deploy.php` MU-plugin triggers the `.github/workflows/deploy.yml` workflow, rebuilding and uploading the site within minutes.

### Option B: Manual FTP / cPanel File Manager Upload
1. Run `npm run build`.
2. Upload the entire contents of `./dist/` (including `.htaccess`) to your server's `public_html/` directory.
