export function formatDocumentNumberPreview(
    number: string,
    date: string,
    brandCode: string | null,
): string {
    const segments = number.split('/');

    if (segments.length < 4) {
        return number;
    }

    const datePart = date ? `${date.slice(8, 10)}${date.slice(5, 7)}` : segments[1];
    segments[0] = 'JKT';
    segments[1] = datePart;
    segments[2] = brandCode?.toUpperCase() || 'GEN';

    return segments.join('/');
}
