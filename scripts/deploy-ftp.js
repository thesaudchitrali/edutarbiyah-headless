import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import * as ftp from 'basic-ftp';
import dotenv from 'dotenv';

// Load .env
dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const distDir = path.resolve(__dirname, '..', 'dist');

async function deploy() {
  console.log('\n🚀 [EduTarbiyah] Automated Local -> Hosting FTP Deployer');
  console.log('----------------------------------------------------');

  const host = process.env.FTP_HOST;
  const user = process.env.FTP_USER;
  const password = process.env.FTP_PASSWORD;
  const port = parseInt(process.env.FTP_PORT || '21', 10);
  const secure = process.env.FTP_SECURE === 'true';
  const remoteDir = process.env.FTP_REMOTE_DIR || 'public_html/';

  if (!host || !user || !password) {
    console.error('\n❌ ERROR: Missing FTP credentials in .env file!');
    console.error('Please create a .env file in the root directory with:');
    console.error(`
FTP_HOST=ftp.edutarbiyah.com
FTP_USER=your_cpanel_username
FTP_PASSWORD=your_ftp_password
FTP_REMOTE_DIR=public_html/
    `);
    console.error('See .env.example for full details.\n');
    process.exit(1);
  }

  if (!fs.existsSync(distDir)) {
    console.error('\n❌ ERROR: "dist" folder not found. Please run "npm run build" first.\n');
    process.exit(1);
  }

  const client = new ftp.Client();
  client.ftp.verbose = false; // set to true if deep debugging needed

  try {
    console.log(`📡 Connecting to FTP Server: ${host}:${port} as ${user}...`);
    
    await client.access({
      host,
      user,
      password,
      port,
      secure,
      secureOptions: {
        rejectUnauthorized: false, // allows self-signed cPanel certificates if any
      },
    });

    console.log(`✅ Connected successfully!`);

    let targetDir = remoteDir.trim();
    const initialPwd = await client.pwd();

    // If the cPanel FTP user was created specifically for this domain, its root is already chrooted at '/'
    if ((targetDir.startsWith('/home') || targetDir.startsWith('home/')) && initialPwd === '/') {
      console.log(`ℹ️ [Auto-Detect] FTP account is chrooted to domain root.`);
      console.log(`ℹ️ Target directory set to root '/' (preventing nested subfolder '${targetDir}')`);
      targetDir = '/';
    }

    if (targetDir && targetDir !== '/' && targetDir !== '.') {
      console.log(`📁 Navigating to target remote directory: ${targetDir}`);
      await client.ensureDir(targetDir);
      console.log(`📦 Uploading all static files from "dist/" to "${targetDir}"...`);
    } else {
      console.log(`📁 Navigating to domain root directory: /`);
      await client.cd('/');
      console.log(`📦 Uploading all static files from "dist/" to root "/"...`);
    }

    const startTime = Date.now();

    // Track upload progress
    let lastBytes = 0;
    client.trackProgress((info) => {
      const mb = (info.bytesOverall / (1024 * 1024)).toFixed(2);
      if (info.bytesOverall - lastBytes > 500000 || info.bytesOverall === info.bytes) {
        lastBytes = info.bytesOverall;
        process.stdout.write(`\r📤 Uploading: ${info.name} (${mb} MB transferred)   `);
      }
    });

    if (targetDir && targetDir !== '/' && targetDir !== '.') {
      await client.uploadFromDir(distDir, targetDir);
    } else {
      await client.uploadFromDir(distDir);
    }

    const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
    console.log(`\n\n🎉 Deployment Complete in ${elapsed}s!`);
    console.log(`🌐 Your changes are now LIVE on your hosting server.\n`);
  } catch (err) {
    console.error('\n❌ Deployment failed:', err.message);
    process.exit(1);
  } finally {
    client.close();
  }
}

deploy();
