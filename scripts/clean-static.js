const fs = require('fs');
const path = require('path');
const target = path.resolve(__dirname, '..', 'public', 'site');
fs.rmSync(target, { recursive: true, force: true });
