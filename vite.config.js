import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { constants as zlibConstants, createBrotliCompress, createGzip, createZstdCompress } from 'zlib';
import { createReadStream, createWriteStream } from 'fs';
import { readdir } from 'fs/promises';
import { pipeline } from 'stream/promises';
import { extname, join, resolve } from 'path';

const COMPRESSIBLE = new Set(['.js', '.css', '.json', '.svg', '.html']);

function precompress() {
    let outDir;
    return {
        name: 'precompress',
        apply: 'build',
        configResolved(config) {
            outDir = config.build.outDir;
        },
        async closeBundle() {
            const entries = await readdir(resolve(outDir), { recursive: true, withFileTypes: true });
            const files = entries
                .filter((e) => e.isFile() && COMPRESSIBLE.has(extname(e.name)))
                .map((e) => join(e.parentPath, e.name));
            await Promise.all(
                files.flatMap((file) => [
                    pipeline(
                        createReadStream(file),
                        createGzip({ level: 9 }),
                        createWriteStream(`${file}.gz`),
                    ),
                    pipeline(
                        createReadStream(file),
                        createBrotliCompress({
                            params: { [zlibConstants.BROTLI_PARAM_QUALITY]: 11 },
                        }),
                        createWriteStream(`${file}.br`),
                    ),
                    pipeline(
                        createReadStream(file),
                        createZstdCompress({ level: 11 }),
                        createWriteStream(`${file}.zst`),
                    ),
                ]),
            );
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        precompress(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
