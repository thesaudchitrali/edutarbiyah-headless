const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

async function main() {
  const root = path.resolve(__dirname, '..');
  const logoPath = path.join(root, 'public', 'images', 'logo.webp');
  
  if (!fs.existsSync(logoPath)) {
    console.error('logo.webp not found at', logoPath);
    process.exit(1);
  }

  // Generate 32x32, 16x16, 180x180, 48x48
  await sharp(logoPath)
    .resize(32, 32, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .png()
    .toFile(path.join(root, 'public', 'favicon-32x32.png'));

  await sharp(logoPath)
    .resize(16, 16, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .png()
    .toFile(path.join(root, 'public', 'favicon-16x16.png'));

  await sharp(logoPath)
    .resize(180, 180, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .png()
    .toFile(path.join(root, 'public', 'apple-touch-icon.png'));

  await sharp(logoPath)
    .resize(48, 48, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .toFormat('png')
    .toFile(path.join(root, 'public', 'favicon.ico'));

  // SVG with embedded base64 of logo
  const b64 = fs.readFileSync(logoPath).toString('base64');
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128">
  <rect width="128" height="128" rx="16" fill="#ffffff"/>
  <image href="data:image/webp;base64,${b64}" x="4" y="38" width="120" height="52" preserveAspectRatio="xMidYMid meet"/>
</svg>`;
  fs.writeFileSync(path.join(root, 'public', 'favicon.svg'), svg, 'utf-8');

  console.log('All favicon formats (ico, svg, png, apple-touch-icon) generated from header logo successfully!');
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
