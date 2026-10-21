const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

async function main() {
  const root = path.resolve(__dirname, '..');
  const logoPath = path.join(root, 'public', 'images', 'logo.webp');

  // Copy uploaded file directly to public/images/logo.webp
  const uploadedPath = 'C:/Users/REHAN/.gemini/antigravity/brain/2339f7b2-e555-42d2-85c1-9d209fe14ca3/.user_uploaded/media_1792581057301_02255642.webp';
  if (fs.existsSync(uploadedPath)) {
    fs.copyFileSync(uploadedPath, logoPath);
  }

  // 1. Trim excess whitespace around the logo for maximum legibility in small tabs
  const trimmedBuffer = await sharp(logoPath).trim().toBuffer();

  // 2. Generate crisp favicons centered in square canvases (with white background so text is readable in dark tabs)
  // 48x48 for favicon.ico
  const ico48 = await sharp(trimmedBuffer)
    .resize(48, 48, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .toFormat('png')
    .toBuffer();
  fs.writeFileSync(path.join(root, 'public', 'favicon.ico'), ico48);

  // 32x32 png
  await sharp(trimmedBuffer)
    .resize(32, 32, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .png()
    .toFile(path.join(root, 'public', 'favicon-32x32.png'));

  // 16x16 png
  await sharp(trimmedBuffer)
    .resize(16, 16, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .png()
    .toFile(path.join(root, 'public', 'favicon-16x16.png'));

  // 180x180 apple touch icon
  await sharp(trimmedBuffer)
    .resize(180, 180, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .png()
    .toFile(path.join(root, 'public', 'apple-touch-icon.png'));

  // 192x192 & 512x512 standard PWA icons
  await sharp(trimmedBuffer)
    .resize(192, 192, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .png()
    .toFile(path.join(root, 'public', 'icon-192.png'));

  // SVG favicon embedding the trimmed logo cleanly
  const b64 = (await sharp(trimmedBuffer).png().toBuffer()).toString('base64');
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128">
  <rect width="128" height="128" rx="20" fill="#ffffff"/>
  <image href="data:image/png;base64,${b64}" x="6" y="24" width="116" height="80" preserveAspectRatio="xMidYMid meet"/>
</svg>`;
  fs.writeFileSync(path.join(root, 'public', 'favicon.svg'), svg, 'utf-8');

  console.log('All favicons regenerated successfully from uploaded logo!');
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
