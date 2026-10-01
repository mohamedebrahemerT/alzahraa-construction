import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { copyFile, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

function publishStaticAssets() {
    return {
        name: 'publish-static-assets',
        apply: 'build',
        async closeBundle() {
            const publicDirectory = path.resolve('public');
            const buildDirectory = path.join(publicDirectory, 'build');
            const manifest = JSON.parse(await readFile(path.join(buildDirectory, 'manifest.json'), 'utf8'));
            const stylesheet = manifest['resources/css/app.css']?.file;
            const script = manifest['resources/js/app.js']?.file;

            if (!stylesheet || !script) {
                throw new Error('The Vite build is missing the application CSS or JavaScript entry.');
            }

            const staticDirectory = path.join(publicDirectory, 'assets', 'site');
            await mkdir(staticDirectory, { recursive: true });

            for (const entry of await readdir(staticDirectory)) {
                await rm(path.join(staticDirectory, entry), { recursive: true, force: true });
            }

            const compiledStylesheet = await readFile(path.join(buildDirectory, stylesheet), 'utf8');
            await writeFile(path.join(staticDirectory, 'site.css'), compiledStylesheet.replaceAll('/build/assets/', '/assets/site/'));
            await copyFile(path.join(buildDirectory, script), path.join(staticDirectory, 'site.js'));

            const builtAssetsDirectory = path.join(buildDirectory, 'assets');
            for (const asset of await readdir(builtAssetsDirectory)) {
                if (/\.(woff2?|ttf|otf)$/i.test(asset)) {
                    await copyFile(path.join(builtAssetsDirectory, asset), path.join(staticDirectory, asset));
                }
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        publishStaticAssets(),
    ],
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
});
