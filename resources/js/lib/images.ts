const MAX_DIMENSION = 1600;
const JPEG_QUALITY = 0.8;
const SKIP_BELOW_BYTES = 500 * 1024;

const loadImage = (file: File): Promise<HTMLImageElement> =>
    new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error(`Gagal membaca ${file.name}`));
        };
        image.src = url;
    });

const jpegName = (name: string): string =>
    `${name.replace(/\.[^.]+$/, '') || 'foto'}.jpg`;

const compressImage = async (file: File): Promise<File> => {
    if (!file.type.startsWith('image/') || file.size <= SKIP_BELOW_BYTES) {
        return file;
    }

    try {
        const image = await loadImage(file);
        const longestSide = Math.max(image.naturalWidth, image.naturalHeight);
        const scale = Math.min(1, MAX_DIMENSION / longestSide);
        const width = Math.max(1, Math.round(image.naturalWidth * scale));
        const height = Math.max(1, Math.round(image.naturalHeight * scale));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');

        if (!context) {
            return file;
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(image, 0, 0, width, height);

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY),
        );

        if (!blob || blob.size >= file.size) {
            return file;
        }

        return new File([blob], jpegName(file.name), {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    } catch {
        return file;
    }
};

export const compressImageFiles = async (files: File[]): Promise<File[]> =>
    Promise.all(files.map(compressImage));

export const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
