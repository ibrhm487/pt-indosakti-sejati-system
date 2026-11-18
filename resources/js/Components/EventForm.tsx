import { Field, Input, Select, Textarea } from '@/Components/ui';
import { InertiaFormProps } from '@inertiajs/react';
import { ClipboardList, MapPin, Target } from 'lucide-react';
import { ReactNode } from 'react';

export type EventOption = { id: number; name: string };
export type EventTypeOption = {
    id: number;
    brand_id?: number | null;
    name: string;
    requires_school?: boolean;
    kitchen_default?: boolean;
};
export type VenueDetail = {
    name: string;
    address?: string | null;
    city?: string | null;
    province?: string | null;
};
export type VenueOption = {
    id: number;
    name: string;
    npsn?: string | null;
    is_school?: boolean;
    address?: string | null;
    city?: string | null;
    province?: string | null;
};
export type ConfirmedFjpOption = {
    id: number;
    fjp_number: string;
    venue_id: number | null;
    venue_name: string | null;
    venue_address: string | null;
    venue_city: string | null;
    venue_province: string | null;
    maps_url: string | null;
    brand_id: number | null;
    brand_name: string | null;
    event_type_id: number | null;
    event_type_name: string | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    sampling_target: number | null;
    jumlah_siswa_sekolah: number | null;
    jug_required: boolean;
    school_name: string | null;
};

export type EventFormData = {
    event_name: string;
    fjp_id: string;
    brand_id: string;
    event_type_id: string;
    venue_id: string;
    event_date: string;
    start_time: string;
    end_time: string;
    sampling_target: string;
    notes: string;
    jug_required: boolean;
    pic_id: string;
};

function Section({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">
                <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-page text-muted">
                    {icon}
                </span>
                {title}
            </div>
            {children}
        </div>
    );
}

export default function EventForm({
    form,
    brands,
    eventTypes,
    venues,
    confirmedFjps,
    currentVenue,
    pics,
}: {
    form: InertiaFormProps<EventFormData>;
    brands: EventOption[];
    eventTypes: EventTypeOption[];
    venues: VenueOption[];
    confirmedFjps: ConfirmedFjpOption[];
    currentVenue?: VenueDetail | null;
    pics: EventOption[];
}) {
    const { data, setData, errors } = form;

    const typeOptionsFor = (brandId: string) => {
        if (brandId === '') {
            return eventTypes.filter((t) => !t.brand_id);
        }

        const scoped = eventTypes.filter((t) => String(t.brand_id) === brandId);

        return scoped.length ? scoped : eventTypes.filter((t) => !t.brand_id);
    };

    const typeOptions = (() => {
        const options = typeOptionsFor(data.brand_id);
        const current = eventTypes.find((t) => String(t.id) === data.event_type_id);

        return current && !options.some((t) => t.id === current.id)
            ? [...options, current]
            : options;
    })();

    const selectedFjp = confirmedFjps.find((f) => String(f.id) === data.fjp_id);
    const selectedVenue = venues.find((v) => String(v.id) === data.venue_id);
    const fjpLocked = data.fjp_id !== '';
    const scheduleSource =
        data.fjp_id !== ''
            ? `fjp:${data.fjp_id}`
            : data.venue_id !== ''
              ? `venue:${data.venue_id}`
              : '';

    const venueName = (fjpId: string) =>
        confirmedFjps.find((f) => String(f.id) === fjpId)?.venue_name ??
        currentVenue?.name ??
        '';

    const composeName = (brandId: string, typeId: string, venue: string) => {
        const brand = brands.find((b) => String(b.id) === brandId)?.name ?? '';
        const type = eventTypes.find((t) => String(t.id) === typeId)?.name ?? '';

        if (brand === '' && type === '' && venue === '') {
            return '';
        }

        let base = type || brand || 'Event';

        if (brand !== '' && !base.toUpperCase().includes(brand.toUpperCase())) {
            base = `${brand} ${base}`;
        }

        return venue !== '' ? `${base} - ${venue}` : base;
    };

    const nextName = (brandId: string, typeId: string, fjpId: string) => {
        const currentAuto = composeName(
            data.brand_id,
            data.event_type_id,
            venueName(data.fjp_id),
        );

        return data.event_name === '' || data.event_name === currentAuto
            ? composeName(brandId, typeId, venueName(fjpId))
            : data.event_name;
    };

    const changeBrand = (brandId: string) => {
        const options = typeOptionsFor(brandId);
        const typeId = options.some((t) => String(t.id) === data.event_type_id)
            ? data.event_type_id
            : '';

        setData({
            ...data,
            brand_id: brandId,
            event_type_id: typeId,
            event_name: nextName(brandId, typeId, data.fjp_id),
        });
    };

    const changeType = (typeId: string) => {
        setData({
            ...data,
            event_type_id: typeId,
            event_name: nextName(data.brand_id, typeId, data.fjp_id),
        });
    };

    const changeVenue = (venueId: string) => {
        const name = venues.find((v) => String(v.id) === venueId)?.name ?? '';

        setData({
            ...data,
            venue_id: venueId,
            event_name: name
                ? composeName(data.brand_id, data.event_type_id, name)
                : data.event_name,
        });
    };

    const changeFjp = (fjpId: string) => {
        const fjp = confirmedFjps.find((f) => String(f.id) === fjpId);

        if (!fjp) {
            setData({
                ...data,
                fjp_id: '',
                venue_id: currentVenue ? data.venue_id : '',
            });
            return;
        }

        const brandId = fjp.brand_id != null ? String(fjp.brand_id) : data.brand_id;
        const typeId =
            fjp.event_type_id != null ? String(fjp.event_type_id) : data.event_type_id;

        setData({
            ...data,
            fjp_id: String(fjp.id),
            venue_id: fjp.venue_id != null ? String(fjp.venue_id) : '',
            brand_id: brandId,
            event_type_id: typeId,
            event_date: fjp.event_date ?? data.event_date,
            start_time: fjp.start_time ?? data.start_time,
            end_time: fjp.end_time ?? data.end_time,
            sampling_target:
                fjp.jumlah_siswa_sekolah != null
                    ? String(fjp.jumlah_siswa_sekolah)
                    : fjp.sampling_target != null
                      ? String(fjp.sampling_target)
                      : data.sampling_target,
            jug_required: !!fjp.jug_required,
            event_name: nextName(brandId, typeId, String(fjp.id)),
        });
    };

    const changeScheduleSource = (value: string) => {
        if (value === '') {
            setData({ ...data, fjp_id: '', venue_id: '' });
            return;
        }

        const [type, rawId] = value.split(':');
        const id = rawId ?? '';

        if (type === 'fjp') {
            changeFjp(id);
            return;
        }

        const venueFjps = confirmedFjps.filter(
            (fjp) => String(fjp.venue_id) === id,
        );

        if (venueFjps.length === 1) {
            changeFjp(String(venueFjps[0].id));
            return;
        }

        const name = venues.find((v) => String(v.id) === id)?.name ?? '';

        setData({
            ...data,
            fjp_id: '',
            venue_id: id,
            ...(data.fjp_id !== '' && {
                event_date: '',
                start_time: '',
                end_time: '',
                sampling_target: '',
            }),
            event_name: name
                ? composeName(data.brand_id, data.event_type_id, name)
                : data.event_name,
        });
    };

    const venue =
        selectedFjp?.venue_id != null
            ? {
                  name: selectedFjp.venue_name ?? '-',
                  address: selectedFjp.venue_address,
                  city: selectedFjp.venue_city,
                  province: selectedFjp.venue_province,
                  mapsUrl: selectedFjp.maps_url,
              }
            : selectedVenue
              ? {
                    name: selectedVenue.name,
                    address: selectedVenue.address,
                    city: selectedVenue.city,
                    province: selectedVenue.province,
                    mapsUrl: null,
                }
              : currentVenue
                ? { ...currentVenue, mapsUrl: null }
                : null;

    return (
        <div className="space-y-6">
            <Section icon={<ClipboardList className="h-3.5 w-3.5" />} title="Informasi Event">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Nama Event"
                        error={errors.event_name}
                        className="sm:col-span-2"
                    >
                        <Input
                            value={data.event_name}
                            onChange={(e) => setData('event_name', e.target.value)}
                            placeholder="Otomatis dari brand, jenis event, dan lokasi"
                        />
                    </Field>
                    <Field label="Brand" error={errors.brand_id}>
                        <Select
                            value={data.brand_id}
                            onChange={(e) => changeBrand(e.target.value)}
                            disabled={fjpLocked}
                        >
                            <option value="">- Pilih -</option>
                            {brands.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Jenis Event" error={errors.event_type_id}>
                        <Select
                            value={data.event_type_id}
                            onChange={(e) => changeType(e.target.value)}
                            disabled={fjpLocked}
                        >
                            <option value="">- Pilih -</option>
                            {typeOptions.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="PIC" error={errors.pic_id}>
                        <Select
                            value={data.pic_id}
                            onChange={(e) => setData('pic_id', e.target.value)}
                        >
                            <option value="">- Pilih -</option>
                            {pics.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field
                        label="Lokasi dari FJP / Venue (disetujui)"
                        error={errors.fjp_id ?? errors.venue_id}
                        className="sm:col-span-2"
                    >
                        <Select
                            value={scheduleSource}
                            onChange={(e) => changeScheduleSource(e.target.value)}
                        >
                            <option value="">- Pilih FJP atau venue yang disetujui -</option>
                            {confirmedFjps.length > 0 && (
                                <optgroup label="FJP disetujui">
                                    {confirmedFjps.map((f) => (
                                        <option key={`fjp-${f.id}`} value={`fjp:${f.id}`}>
                                            {f.fjp_number} — {f.venue_name ?? f.school_name ?? '-'}
                                            {f.brand_name ? ` · ${f.brand_name}` : ''}
                                            {f.event_type_name ? ` · ${f.event_type_name}` : ''}
                                            {f.event_date ? ` · ${f.event_date}` : ''}
                                        </option>
                                    ))}
                                </optgroup>
                            )}
                            {venues.length > 0 && (
                                <optgroup label="Venue disetujui">
                                    {venues.map((v) => (
                                        <option key={`venue-${v.id}`} value={`venue:${v.id}`}>
                                            {v.name}
                                            {v.city ? ` · ${v.city}` : ''}
                                        </option>
                                    ))}
                                </optgroup>
                            )}
                        </Select>
                        <p className="mt-1 text-xs text-muted">
                            Memilih FJP atau venue dengan satu FJP yang disetujui
                            dan belum dijadwalkan akan mengisi jadwal dan target
                            otomatis. Jika venue memiliki beberapa FJP, pilih FJP
                            yang sesuai secara langsung.
                        </p>
                    </Field>
                    {selectedFjp && selectedFjp.venue_id == null && (
                        <Field
                            label="Venue untuk FJP ini"
                            error={errors.venue_id}
                            className="sm:col-span-2"
                        >
                            <Select
                                value={data.venue_id}
                                onChange={(e) => changeVenue(e.target.value)}
                            >
                                <option value="">- Pilih venue -</option>
                                {venues.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </Select>
                            <p className="mt-1 text-xs text-muted">
                                FJP ini belum memiliki lokasi. Venue yang dipilih akan
                                disimpan ke FJP.
                            </p>
                        </Field>
                    )}
                </div>

                {venue ? (
                    <div className="rounded-xl border border-border bg-page px-4 py-3">
                        <div className="flex items-start gap-2.5">
                            <MapPin className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <div className="space-y-1">
                                <p className="text-sm font-semibold text-text">
                                    {venue.name}
                                </p>
                                {venue.address && (
                                    <p className="text-xs text-muted">{venue.address}</p>
                                )}
                                {(venue.city || venue.province) && (
                                    <p className="text-xs text-muted">
                                        {[venue.city, venue.province]
                                            .filter(Boolean)
                                            .join(', ')}
                                    </p>
                                )}
                                {venue.mapsUrl && (
                                    <a
                                        href={venue.mapsUrl}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex text-xs font-medium text-primary hover:underline"
                                    >
                                        Buka lokasi di Maps
                                    </a>
                                )}
                            </div>
                        </div>
                    </div>
                ) : (
                    <p className="text-xs text-muted">
                        Pilih FJP yang sudah disetujui agar brand dan jadwal terisi
                        otomatis, atau pilih venue yang sudah disetujui PIC untuk
                        menjadwalkan event langsung.
                    </p>
                )}

                <p className="text-xs text-muted">
                    Nama event terisi otomatis dari brand, jenis event, dan lokasi.
                    Ubah manual bila perlu.
                    {fjpLocked && ' Brand dan jenis event mengikuti FJP yang dipilih.'}
                </p>
            </Section>

            <Section icon={<Target className="h-3.5 w-3.5" />} title="Jadwal & Target">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Tanggal" error={errors.event_date}>
                        <Input
                            type="date"
                            value={data.event_date}
                            onChange={(e) => setData('event_date', e.target.value)}
                        />
                    </Field>
                    <Field label="Target Sampling" error={errors.sampling_target}>
                        <Input
                            type="number"
                            min={0}
                            value={data.sampling_target}
                            onChange={(e) => setData('sampling_target', e.target.value)}
                        />
                        {selectedFjp && (
                            <p className="mt-1 text-xs text-muted">
                                Terisi otomatis dari Jumlah Siswa (versi Sekolah)
                                FJP
                                {selectedFjp.jumlah_siswa_sekolah != null
                                    ? `: ${selectedFjp.jumlah_siswa_sekolah}`
                                    : selectedFjp.sampling_target != null
                                      ? ` (target FJP): ${selectedFjp.sampling_target}`
                                      : ''}
                                . PIC dapat mengubahnya.
                            </p>
                        )}
                    </Field>
                    <Field label="Jam Mulai" error={errors.start_time}>
                        <Input
                            type="time"
                            value={data.start_time}
                            onChange={(e) => setData('start_time', e.target.value)}
                        />
                    </Field>
                    <Field label="Jam Selesai" error={errors.end_time}>
                        <Input
                            type="time"
                            value={data.end_time}
                            onChange={(e) => setData('end_time', e.target.value)}
                        />
                    </Field>
                    <label className="flex cursor-pointer items-center gap-2.5 rounded-xl border border-border bg-page px-3.5 py-2.5 sm:col-span-2">
                        <input
                            type="checkbox"
                            className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                            checked={data.jug_required}
                            onChange={(e) => setData('jug_required', e.target.checked)}
                        />
                        <span className="text-sm text-text">
                            Butuh JUG (cuci gelas)
                        </span>
                    </label>
                </div>
            </Section>

            <Section icon={<MapPin className="h-3.5 w-3.5" />} title="Catatan">
                <Field error={errors.notes}>
                    <Textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="Catatan tambahan (opsional)"
                    />
                </Field>
            </Section>
        </div>
    );
}
