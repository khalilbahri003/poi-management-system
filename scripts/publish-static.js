const fs = require('fs');
const path = require('path');
const build = path.resolve(__dirname, '..', 'build');
const target = path.resolve(__dirname, '..', 'public', 'site');
if (!fs.existsSync(path.join(build, 'index.html'))) {
  console.error('React build output is missing.');
  process.exit(1);
}
fs.rmSync(target, { recursive: true, force: true });
fs.cpSync(build, target, { recursive: true });
// CRA copies everything in public/; keep server-side PHP only in the real public root.
for (const name of ['index.php', 'router.php', 'proge']) {
  fs.rmSync(path.join(target, name), { recursive: true, force: true });
}
console.log('Built React files copied to public/site (server scripts excluded).');
