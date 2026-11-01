// webpack >= 5.109 auto-enables built-in TypeScript support on Node >= 22.6, which makes it
// read every dependency's tsconfig.json (side-channel's extends an uninstalled package).
module.exports = {
    webpack: config => ({
        ...config,
        experiments: { ...config.experiments, typescript: false },
    }),
};
