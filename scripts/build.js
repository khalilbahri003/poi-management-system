const { spawnSync } = require('node:child_process');
process.env.GENERATE_SOURCEMAP = 'false';
const result = spawnSync(process.execPath, [require.resolve('react-scripts/scripts/build')], {
  stdio: 'inherit',
  env: process.env,
});
if (result.error) throw result.error;
if (result.status !== 0) process.exit(result.status || 1);
require('./publish-static');
