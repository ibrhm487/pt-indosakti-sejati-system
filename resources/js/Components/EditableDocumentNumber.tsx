import { Button, Input } from '@/Components/ui';
import { router } from '@inertiajs/react';
import { Check, Pencil, X } from 'lucide-react';
import { KeyboardEvent, useState } from 'react';

export default function EditableDocumentNumber({
    value,
    field,
    url,
    canEdit = false,
}: {
    value: string;
    field: string;
    url: string;
    canEdit?: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const [number, setNumber] = useState(value);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const save = () => {
        if (processing) {
            return;
        }

        setProcessing(true);
        setError(null);

        router.patch(
            url,
            { [field]: number },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(false),
                onError: (errors) => setError(errors[field] ?? 'Gagal menyimpan nomor.'),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const cancel = () => {
        setNumber(value);
        setError(null);
        setEditing(false);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            save();
        }

        if (event.key === 'Escape') {
            cancel();
        }
    };

    if (!editing) {
        return (
            <span className="inline-flex flex-wrap items-center gap-2">
                <span className="font-mono">{value}</span>
                {canEdit && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => {
                            setNumber(value);
                            setEditing(true);
                        }}
                    >
                        <Pencil className="h-3.5 w-3.5" /> Ubah nomor
                    </Button>
                )}
            </span>
        );
    }

    return (
        <span className="inline-flex flex-col gap-1">
            <span className="inline-flex flex-wrap items-center gap-2">
                <Input
                    value={number}
                    onChange={(event) => setNumber(event.target.value)}
                    onKeyDown={onKeyDown}
                    className="h-9 w-56 font-mono"
                    autoFocus
                />
                <Button type="button" size="sm" onClick={save} disabled={processing}>
                    <Check className="h-3.5 w-3.5" /> Simpan
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={cancel} disabled={processing}>
                    <X className="h-3.5 w-3.5" /> Batal
                </Button>
            </span>
            {error && <span className="text-xs text-red-600">{error}</span>}
        </span>
    );
}
