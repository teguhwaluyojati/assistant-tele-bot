export default {
    plugins: {
        // @tailwindcss/postcss dihapus — sudah ditangani @tailwindcss/vite di vite.config.js
        // Menjalankan keduanya sekaligus menyebabkan double-processing yang menghilangkan class responsive
        autoprefixer: {},
    },
};