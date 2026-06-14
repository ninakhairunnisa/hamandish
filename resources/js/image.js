// Client-side image downscaling so uploads stay well under server limits
// (PHP upload_max_filesize / post_max_size) and Laravel's image rule.
//
// Reads an image File, draws it onto a canvas capped at `maxDim` on the long
// edge, and re-encodes it as a JPEG at `quality`. Falls back to the original
// file if anything goes wrong (e.g. unsupported format).

export async function compressImage(file, maxDim = 1280, quality = 0.8) {
    if (!file || !file.type?.startsWith('image/')) return file;

    try {
        const dataUrl = await readAsDataURL(file);
        const img = await loadImage(dataUrl);

        let { width, height } = img;
        if (width > maxDim || height > maxDim) {
            if (width >= height) {
                height = Math.round((height / width) * maxDim);
                width = maxDim;
            } else {
                width = Math.round((width / height) * maxDim);
                height = maxDim;
            }
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(img, 0, 0, width, height);

        const blob = await new Promise((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', quality),
        );
        if (!blob) return file;

        // Keep it a File so FormData sends a proper filename.
        const name = (file.name || 'image').replace(/\.[^.]+$/, '') + '.jpg';
        return new File([blob], name, { type: 'image/jpeg' });
    } catch (_) {
        return file;
    }
}

function readAsDataURL(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}
