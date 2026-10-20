import fs from 'node:fs/promises';
import path from 'node:path';
import crypto from 'node:crypto';

export const WP_BASE_URL = ((typeof import.meta !== 'undefined' && import.meta.env?.PUBLIC_WP_URL) || process.env?.PUBLIC_WP_URL || 'https://edutarbiyah.com').replace(/\/+$/, '');
export const WHATSAPP_NUMBER = '923122370720';

const CACHE_DIR = path.resolve(process.cwd(), '.cache', 'api');

// Resilient JSON parser that handles Elementor buffer junk or HTML prefix/suffix
export function parseResilientJson<T>(rawText: string): T {
  const trimmed = rawText.trim();
  try {
    return JSON.parse(trimmed) as T;
  } catch (initialErr) {
    const firstBrace = trimmed.indexOf('{');
    const firstBracket = trimmed.indexOf('[');
    let startIdx = -1;
    if (firstBrace !== -1 && firstBracket !== -1) {
      startIdx = Math.min(firstBrace, firstBracket);
    } else if (firstBrace !== -1) {
      startIdx = firstBrace;
    } else if (firstBracket !== -1) {
      startIdx = firstBracket;
    }

    if (startIdx === -1) {
      throw new Error(`No JSON opening token found in response: ${trimmed.slice(0, 100)}...`);
    }

    const isObject = trimmed[startIdx] === '{';
    const lastIdx = isObject ? trimmed.lastIndexOf('}') : trimmed.lastIndexOf(']');
    if (lastIdx === -1 || lastIdx <= startIdx) {
      throw new Error(`No matching JSON closing token found in response: ${trimmed.slice(0, 100)}...`);
    }

    const extracted = trimmed.substring(startIdx, lastIdx + 1);
    try {
      return JSON.parse(extracted) as T;
    } catch (err) {
      throw new Error(`Failed to parse extracted JSON string: ${(err as Error).message}`);
    }
  }
}

/**
 * Cached fetch with retries and exponential backoff
 */
export async function cachedFetch<T>(
  endpoint: string,
  options: {
    ttlSeconds?: number;
    retries?: number;
    fallbackData?: T;
  } = {}
): Promise<T> {
  const { ttlSeconds = 86400, retries = 3, fallbackData } = options;
  const url = endpoint.startsWith('http') ? endpoint : `${WP_BASE_URL}${endpoint}`;
  
  // Safe cache key based on URL hash
  const hash = crypto.createHash('sha1').update(url).digest('hex');
  const cacheFilePath = path.join(CACHE_DIR, `${hash}.json`);

  // 1. Try reading from disk cache
  try {
    const fileStat = await fs.stat(cacheFilePath);
    const ageSeconds = (Date.now() - fileStat.mtimeMs) / 1000;
    if (ageSeconds < ttlSeconds) {
      const data = await fs.readFile(cacheFilePath, 'utf-8');
      return JSON.parse(data) as T;
    }
  } catch {
    // Cache miss or error reading cache, proceed to fetch
  }

  // 2. Fetch with retries
  let lastError: any = null;
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 15000);

      const res = await fetch(url, {
        signal: controller.signal,
        headers: {
          'Accept': 'application/json',
          'User-Agent': 'EduTarbiyah-Headless-Static-Builder/1.0',
        },
      });
      clearTimeout(timeoutId);

      if (!res.ok) {
        throw new Error(`HTTP ${res.status} ${res.statusText} on ${url}`);
      }

      const text = await res.text();
      const parsed = parseResilientJson<T>(text);

      // Save to cache
      try {
        await fs.mkdir(CACHE_DIR, { recursive: true });
        await fs.writeFile(cacheFilePath, JSON.stringify(parsed, null, 2), 'utf-8');
      } catch (cacheErr) {
        console.warn(`[Cache Warning] Could not write cache file: ${(cacheErr as Error).message}`);
      }

      return parsed;
    } catch (err) {
      lastError = err;
      console.warn(`[Fetch Retry ${attempt}/${retries}] for ${url} - ${(err as Error).message}`);
      if (attempt < retries) {
        // Exponential backoff
        await new Promise((resolve) => setTimeout(resolve, attempt * 1200));
      }
    }
  }

  // 3. Fallback: try reading stale cache if available
  try {
    const staleData = await fs.readFile(cacheFilePath, 'utf-8');
    console.warn(`[Cache Warning] Using stale cache for ${url} after fetch failure.`);
    return JSON.parse(staleData) as T;
  } catch {
    // No stale cache available
  }

  if (fallbackData !== undefined) {
    console.warn(`[Fallback] Using provided fallback data for ${url}`);
    return fallbackData;
  }

  throw lastError || new Error(`Failed to fetch from ${url}`);
}

/**
 * Cached text/HTML fetch with retries and exponential backoff
 */
export async function cachedFetchText(
  endpoint: string,
  options: {
    ttlSeconds?: number;
    retries?: number;
    fallbackData?: string;
  } = {}
): Promise<string> {
  const { ttlSeconds = 86400, retries = 3, fallbackData } = options;
  const url = endpoint.startsWith('http') ? endpoint : `${WP_BASE_URL}${endpoint}`;

  const hash = crypto.createHash('sha1').update(url).digest('hex');
  const cacheFilePath = path.join(CACHE_DIR, `${hash}.txt`);

  // 1. Try reading from disk cache
  try {
    const fileStat = await fs.stat(cacheFilePath);
    const ageSeconds = (Date.now() - fileStat.mtimeMs) / 1000;
    if (ageSeconds < ttlSeconds) {
      return await fs.readFile(cacheFilePath, 'utf-8');
    }
  } catch {
    // Cache miss or error reading cache, proceed to fetch
  }

  // 2. Fetch with retries
  let lastError: any = null;
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 15000);

      const res = await fetch(url, {
        signal: controller.signal,
        headers: {
          'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
          'User-Agent': 'EduTarbiyah-Headless-Static-Builder/1.0',
        },
      });
      clearTimeout(timeoutId);

      if (!res.ok) {
        throw new Error(`HTTP ${res.status} ${res.statusText} on ${url}`);
      }

      const text = await res.text();

      // Save to cache
      try {
        await fs.mkdir(CACHE_DIR, { recursive: true });
        await fs.writeFile(cacheFilePath, text, 'utf-8');
      } catch (cacheErr) {
        console.warn(`[Cache Warning] Could not write cache file: ${(cacheErr as Error).message}`);
      }

      return text;
    } catch (err) {
      lastError = err;
      console.warn(`[Fetch Retry ${attempt}/${retries}] for ${url} - ${(err as Error).message}`);
      if (attempt < retries) {
        await new Promise((resolve) => setTimeout(resolve, attempt * 1200));
      }
    }
  }

  // 3. Fallback to stale cache
  try {
    const staleData = await fs.readFile(cacheFilePath, 'utf-8');
    console.warn(`[Cache Warning] Using stale cache for ${url} after fetch failure.`);
    return staleData;
  } catch {
    // No stale cache
  }

  if (fallbackData !== undefined) {
    console.warn(`[Fallback] Using provided fallback data for ${url}`);
    return fallbackData;
  }

  throw lastError || new Error(`Failed to fetch text from ${url}`);
}

/* -------------------------------------------------------------
 * Data Interfaces
 * ------------------------------------------------------------- */

export interface ProductPrice {
  price: string;
  regular_price: string;
  sale_price: string;
  currency_code: string;
  currency_symbol: string;
  currency_prefix: string;
  currency_suffix: string;
  currency_minor_unit: number;
}

export interface ProductImage {
  id: number;
  src: string;
  alt: string;
  thumbnail?: string;
}

export interface ProductAttribute {
  id: number;
  name: string;
  taxonomy?: string;
  has_variations: boolean;
  terms: Array<{ id: number; name: string; slug: string }>;
}

export interface ProductVariation {
  id: number;
  attributes: Array<{ name: string; value: string }>;
  prices?: ProductPrice;
  is_in_stock?: boolean;
}

export interface Product {
  id: number;
  name: string;
  slug: string;
  parent: number;
  type: 'simple' | 'variable' | 'grouped' | string;
  sku: string;
  short_description: string;
  description: string;
  on_sale: boolean;
  prices: ProductPrice;
  images: ProductImage[];
  categories: Array<{ id: number; name: string; slug: string }>;
  attributes: ProductAttribute[];
  variations: ProductVariation[];
  is_in_stock: boolean;
  permalink: string;
}

export interface ProductCategory {
  id: number;
  name: string;
  slug: string;
  description: string;
  parent: number;
  count: number;
  image?: {
    id: number;
    src: string;
    alt: string;
  };
}

export interface WPPage {
  id: number;
  slug: string;
  date: string;
  modified: string;
  title: { rendered: string };
  content: { rendered: string };
  excerpt: { rendered: string };
  link: string;
}

export interface WPPost {
  id: number;
  slug: string;
  date: string;
  modified: string;
  title: { rendered: string };
  content: { rendered: string };
  excerpt: { rendered: string };
  categories: number[];
  _embedded?: {
    'wp:featuredmedia'?: Array<{ source_url: string; alt_text?: string }>;
    'author'?: Array<{ name: string; avatar_urls?: Record<string, string> }>;
  };
}

export interface MenuItem {
  id: number;
  title: string;
  url: string;
  target?: string;
  children?: MenuItem[];
  type?: string;
}

/* -------------------------------------------------------------
 * API Query Methods
 * ------------------------------------------------------------- */

function decodeHtmlEntities(str: string): string {
  return str
    .replace(/&#038;/g, '&')
    .replace(/&amp;/g, '&')
    .replace(/&#8217;/g, "'")
    .replace(/&#8216;/g, "'")
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .trim();
}

/**
 * Robustly parses WordPress nav menu from rendered HTML
 */
export function parseWordPressNavHtml(html: string, baseUrl: string = WP_BASE_URL): MenuItem[] {
  const match = html.match(/<ul[^>]*class="[^"]*elementor-nav-menu[^"]*"[^>]*>/i);
  if (!match || match.index === undefined) return [];

  const startIdx = match.index + match[0].length;

  let ulDepth = 1;
  let pos = startIdx;
  let endIdx = -1;

  while (pos < html.length && ulDepth > 0) {
    const nextOpen = html.indexOf('<ul', pos);
    const nextClose = html.indexOf('</ul>', pos);

    if (nextClose === -1) break;

    if (nextOpen !== -1 && nextOpen < nextClose) {
      ulDepth++;
      pos = nextOpen + 3;
    } else {
      ulDepth--;
      if (ulDepth === 0) {
        endIdx = nextClose;
        break;
      }
      pos = nextClose + 5;
    }
  }

  if (endIdx === -1) endIdx = html.lastIndexOf('</ul>');
  const innerMenuHtml = html.substring(startIdx, endIdx);

  function parseItems(fragment: string): MenuItem[] {
    const items: MenuItem[] = [];
    let i = 0;

    while (i < fragment.length) {
      const liOpenIdx = fragment.indexOf('<li', i);
      if (liOpenIdx === -1) break;

      const liTagClose = fragment.indexOf('>', liOpenIdx);
      if (liTagClose === -1) break;

      const openTag = fragment.substring(liOpenIdx, liTagClose + 1);
      const idMatch = openTag.match(/menu-item-(\d+)/);
      const id = idMatch ? parseInt(idMatch[1], 10) : Math.floor(Math.random() * 100000);

      let liDepth = 1;
      let cur = liTagClose + 1;
      let liCloseIdx = -1;

      while (cur < fragment.length && liDepth > 0) {
        const nextLi = fragment.indexOf('<li', cur);
        const nextLiEnd = fragment.indexOf('</li>', cur);

        if (nextLiEnd === -1) break;

        if (nextLi !== -1 && nextLi < nextLiEnd) {
          liDepth++;
          cur = nextLi + 3;
        } else {
          liDepth--;
          if (liDepth === 0) {
            liCloseIdx = nextLiEnd;
            break;
          }
          cur = nextLiEnd + 5;
        }
      }

      if (liCloseIdx === -1) break;

      const liInner = fragment.substring(liTagClose + 1, liCloseIdx);

      const subUlStart = liInner.search(/<ul[^>]*>/i);
      const aHtml = subUlStart !== -1 ? liInner.substring(0, subUlStart) : liInner;

      const aMatch = aHtml.match(/<a\s+[^>]*href="([^"]*)"[^>]*>([\s\S]*?)<\/a>/i);
      let href = '#';
      let title = '';

      if (aMatch) {
        href = aMatch[1].trim();
        const rawText = aMatch[2]
          .replace(/<svg[\s\S]*?<\/svg>/gi, '')
          .replace(/<[^>]*>/g, '')
          .trim();
        title = decodeHtmlEntities(rawText);
      }

      let cleanUrl = href;
      let target: string | undefined = undefined;

      if (href.startsWith(baseUrl)) {
        cleanUrl = href.replace(baseUrl, '');
        if (!cleanUrl || cleanUrl === '') cleanUrl = '/';
      } else if (href.startsWith('http://') || href.startsWith('https://')) {
        target = '_blank';
      }

      // Check if file asset link
      if (/\.(png|jpe?g|gif|webp|svg|pdf|docx?|xlsx?)$/i.test(cleanUrl)) {
        if (cleanUrl.startsWith('/wp-content')) {
          cleanUrl = `${baseUrl}${cleanUrl}`;
        }
        target = '_blank';
      }

      // Ensure trailing slash for internal non-anchor directory routes
      if (
        cleanUrl.startsWith('/') &&
        cleanUrl !== '/' &&
        !cleanUrl.includes('#') &&
        !cleanUrl.includes('?') &&
        !/\.[a-z0-9]+$/i.test(cleanUrl)
      ) {
        cleanUrl = cleanUrl.replace(/\/+$/, '') + '/';
      }

      let children: MenuItem[] = [];
      if (subUlStart !== -1) {
        const subUlTagEnd = liInner.indexOf('>', subUlStart);
        const subUlEndIdx = liInner.lastIndexOf('</ul>');
        if (subUlTagEnd !== -1 && subUlEndIdx > subUlTagEnd) {
          const subInner = liInner.substring(subUlTagEnd + 1, subUlEndIdx);
          children = parseItems(subInner);
        }
      }

      if (title) {
        const item: MenuItem = {
          id,
          title,
          url: cleanUrl,
        };
        if (target) item.target = target;
        if (children.length > 0) item.children = children;
        items.push(item);
      }

      i = liCloseIdx + 5;
    }

    return items;
  }

  return parseItems(innerMenuHtml);
}

let memoryMenuCache: MenuItem[] | null = null;

export async function getPrimaryMenu(): Promise<MenuItem[]> {
  if (memoryMenuCache) {
    return memoryMenuCache;
  }

  const fallbackMenu: MenuItem[] = [
    { id: 1, title: 'Home', url: '/' },
    {
      id: 2,
      title: 'Programs & Courses',
      url: '/tarbiyah-parenting-mastery-program/',
      children: [
        { id: 21, title: 'Parenting Mastery Program', url: '/tarbiyah-parenting-mastery-program/' },
        { id: 22, title: 'TPPC Recorded Course', url: '/tppc-self-paced-recorded-course/' },
        { id: 23, title: 'Parent Coaching Program', url: '/edutarbiyah-parent-coaching-program/' },
        { id: 24, title: 'Coaching Readiness Form', url: '/parent-coaching-readiness-form/' },
      ],
    },
    {
      id: 3,
      title: 'Self-Assessments',
      url: '/audit-your-parenting/',
      children: [
        { id: 31, title: 'Audit Your Parenting', url: '/audit-your-parenting/' },
        { id: 32, title: 'Family Vision Maker', url: '/family-vision-maker/' },
        { id: 33, title: 'Know Your Personality Type', url: '/know-your-personality-type/' },
        { id: 34, title: 'Child Behavior Tracker', url: '/childs-behavior-improvement-tracker/' },
        { id: 35, title: 'Islamic Tarbiyah Routine Maker', url: '/islamic-tarbiyah-home-routine-chart/' },
        { id: 36, title: 'Marriage Audit', url: '/audit-your-marriage-relationships/' },
      ],
    },
    {
      id: 4,
      title: 'Bookstore',
      url: '/shop/',
      children: [
        { id: 41, title: 'All Books & Toolkits', url: '/shop/' },
        { id: 42, title: 'EduTarbiyah Publications', url: '/product-category/edutarbiyah-publications/' },
        { id: 43, title: 'Parenting Books', url: '/product-category/parenting-books/' },
        { id: 44, title: 'Islamic Books', url: '/product-category/islamic-books/' },
        { id: 45, title: 'Self-Help Books', url: '/product-category/selfhelp-books/' },
      ],
    },
    {
      id: 5,
      title: 'Blog',
      url: '/english/',
      children: [
        { id: 51, title: 'English Blog', url: '/english/' },
        { id: 52, title: 'Urdu Blog (اردو)', url: 'https://blog.edutarbiyah.com', target: '_blank' },
      ],
    },
    { id: 6, title: 'WhatsApp Community', url: '/parents-whatsapp-community/' },
  ];

  // Strategy 1: Try REST API endpoint (MU-plugin)
  try {
    const data = await cachedFetch<{ success: boolean; items: MenuItem[] }>(
      '/wp-json/edutarbiyah/v1/menu/primary-menu',
      { retries: 1, ttlSeconds: 60, fallbackData: { success: false, items: [] } }
    );
    if (data?.success && Array.isArray(data?.items) && data.items.length > 0) {
      memoryMenuCache = data.items;
      return memoryMenuCache;
    }
  } catch (err) {
    // Continue to strategy 2
  }

  // Strategy 2: Fetch and dynamically parse live WordPress navigation from homepage HTML
  try {
    const html = await cachedFetchText('/', { retries: 2, ttlSeconds: 60 });
    const parsedItems = parseWordPressNavHtml(html, WP_BASE_URL);
    if (parsedItems && parsedItems.length > 0) {
      memoryMenuCache = parsedItems;
      return memoryMenuCache;
    }
  } catch (err) {
    console.warn(`[Menu Dynamic] Could not parse live menu HTML: ${(err as Error).message}`);
  }

  // Strategy 3: Built-in reliable static fallback
  memoryMenuCache = fallbackMenu;
  return memoryMenuCache;
}

/**
 * Fetch all WooCommerce store products
 */
export async function getAllProducts(): Promise<Product[]> {
  try {
    const products = await cachedFetch<Product[]>(
      '/wp-json/wc/store/v1/products?per_page=100',
      { retries: 3 }
    );
    return Array.isArray(products) ? products : [];
  } catch (err) {
    console.error('Error fetching all products:', err);
    return [];
  }
}

/**
 * Fetch single product by slug
 */
export async function getProductBySlug(slug: string): Promise<Product | null> {
  const allProducts = await getAllProducts();
  const matched = allProducts.find((p) => p.slug === slug || decodeURIComponent(p.slug) === slug);
  if (matched) return matched;

  try {
    const directFetch = await cachedFetch<Product[]>(
      `/wp-json/wc/store/v1/products?slug=${encodeURIComponent(slug)}`,
      { retries: 2 }
    );
    if (Array.isArray(directFetch) && directFetch.length > 0) {
      return directFetch[0];
    }
  } catch {
    // Handled below
  }

  return null;
}

/**
 * Fetch all product categories
 */
export async function getAllProductCategories(): Promise<ProductCategory[]> {
  try {
    const categories = await cachedFetch<ProductCategory[]>(
      '/wp-json/wc/store/v1/products/categories?per_page=100',
      { retries: 3 }
    );
    return Array.isArray(categories) ? categories : [];
  } catch (err) {
    console.error('Error fetching product categories:', err);
    return [];
  }
}

/**
 * Fetch product category by slug
 */
export async function getProductCategoryBySlug(slug: string): Promise<ProductCategory | null> {
  const allCats = await getAllProductCategories();
  const matched = allCats.find((c) => c.slug === slug || decodeURIComponent(c.slug) === slug);
  return matched || null;
}

/**
 * Fetch WordPress pages
 */
export async function getAllPages(): Promise<WPPage[]> {
  try {
    const pages = await cachedFetch<WPPage[]>(
      '/wp-json/wp/v2/pages?per_page=100',
      { retries: 3 }
    );
    return Array.isArray(pages) ? pages : [];
  } catch (err) {
    console.error('Error fetching pages:', err);
    return [];
  }
}

/**
 * Fetch single page by slug
 */
export async function getPageBySlug(slug: string): Promise<WPPage | null> {
  try {
    const pages = await cachedFetch<WPPage[]>(
      `/wp-json/wp/v2/pages?slug=${encodeURIComponent(slug)}`,
      { retries: 3 }
    );
    if (Array.isArray(pages) && pages.length > 0) {
      return pages[0];
    }
  } catch (err) {
    console.error(`Error fetching page ${slug}:`, err);
  }
  return null;
}

/**
 * Fetch English blog posts (Category ID 11 = 'english-blog')
 */
export async function getEnglishPosts(perPage = 20): Promise<WPPost[]> {
  try {
    const posts = await cachedFetch<WPPost[]>(
      `/wp-json/wp/v2/posts?categories=11&per_page=${perPage}&_embed=1`,
      { retries: 3 }
    );
    return Array.isArray(posts) ? posts : [];
  } catch (err) {
    console.error('Error fetching English posts:', err);
    return [];
  }
}

/**
 * Fetch single English post by slug
 */
export async function getEnglishPostBySlug(slug: string): Promise<WPPost | null> {
  try {
    const posts = await cachedFetch<WPPost[]>(
      `/wp-json/wp/v2/posts?slug=${encodeURIComponent(slug)}&_embed=1`,
      { retries: 3 }
    );
    if (Array.isArray(posts) && posts.length > 0) {
      return posts[0];
    }
  } catch (err) {
    console.error(`Error fetching post ${slug}:`, err);
  }
  return null;
}

/**
 * Format price in PKR or specified currency
 */
export function formatPrice(prices: ProductPrice | undefined): string {
  if (!prices) return '';
  const prefix = prices.currency_prefix || '₨ ';
  const priceVal = prices.price || '0';
  return `${prefix}${priceVal}`;
}

/**
 * Helper to build 1-click WhatsApp order link with pre-filled message
 */
export function buildWhatsAppOrderLink(
  productName: string,
  price: string,
  productUrl?: string,
  variationSummary?: string
): string {
  const cleanTitle = productName.replace(/<[^>]*>?/gm, '').trim();
  let message = `السلام علیکم EduTarbiyah!\n\nI would like to place an order for:\n📚 *${cleanTitle}*\n💰 Price: ${price}`;
  
  if (variationSummary) {
    message += `\n⚙️ Options: ${variationSummary}`;
  }
  
  if (productUrl) {
    message += `\n🔗 Link: ${productUrl}`;
  }

  message += `\n\nPlease let me know the payment details and estimated delivery time. JazakAllah Khair!`;

  return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
}

/**
 * Helper to build native WooCommerce add-to-cart link
 */
export function buildWooCommerceCartLink(
  productId: number,
  variationId?: number,
  attributes?: Record<string, string>
): string {
  const base = `${WP_BASE_URL}/cart/?add-to-cart=${productId}`;
  if (!variationId) {
    return base;
  }

  const params = new URLSearchParams();
  params.set('add-to-cart', productId.toString());
  params.set('variation_id', variationId.toString());

  if (attributes) {
    for (const [key, value] of Object.entries(attributes)) {
      const paramKey = key.startsWith('attribute_') ? key : `attribute_${key}`;
      params.set(paramKey, value);
    }
  }

  return `${WP_BASE_URL}/cart/?${params.toString()}`;
}
