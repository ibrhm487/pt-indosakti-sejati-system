import { CardTable, EmptyState, PageHeader, SearchInput } from '@/Components/AppBits';
import {
    Button,
    Input,
    Modal,
    Select,
    Table,
    TBody,
    TD,
    TH,
    THead,
    TR,
    StatusBadge,
} from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Clock3, MapPin, Plus } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

type VenueRow = {
    id: number;
    name: string;
    npsn: string | null;
    is_school: boolean;
    city: string | null;
    province: string | null;
    status: string;
    status_label: string;
    approved_by: string | null;
    approved_at: string | null;
    fjps_count: number;
    events_count: number;
};

type VenueFormData = {
    name: string;
    is_school: boolean;
    npsn: string;
    address: string;
    city: string;
    province: string;
    contact_name: string;
    contact_phone: string;
    latitude: string;
    longitude: string;
};

const EMPTY_FORM: VenueFormData = {
    name: '', is_school: false, npsn: '', address: '', city: '', province: '',
    contact_name: '', contact_phone: '', latitude: '', longitude: '',
};

export default function VenuesIndex({
    venues,
    selectedDate,
    pendingCount,
    canApprove,
    canCreate,
}: {
    venues: VenueRow[];
    selectedDate: string;
    pendingCount: number;
    canApprove: boolean;
    canCreate: boolean;
}) {
    const [createOpen, setCreateOpen] = useState(false);
    const [search, setSearch] = useState('');
    const form = useForm<VenueFormData>(EMPTY_FORM);
    const filteredVenues = useMemo(() => {
        const normalizedSearch = search.trim().toLocaleLowerCase('id');

        return venues.filter((venue) => !normalizedSearch || [
            venue.name,
            venue.npsn ?? '',
            venue.city ?? '',
            venue.province ?? '',
        ].some((value) => value.toLocaleLowerCase('id').includes(normalizedSearch)));
    }, [venues, search]);

    const changeDate = (date: string) => {
        router.get(route('venues.index'), { date }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('venues.store'), {
            onSuccess: () => {
                setCreateOpen(false);
                form.reset();
            },
        });
    };

    return (
        <AppLayout title="Lokasi & Venue">
            <Head title="Lokasi & Venue" />
            <PageHeader
                title="Lokasi & Venue"
                subtitle="Tampilkan lokasi berdasarkan jadwal harian; kota dan provinsi hanya menjadi informasi."
            >
                {canCreate && <Button variant="primary" onClick={() => setCreateOpen(true)}><Plus className="h-4 w-4" /> Tambah Venue</Button>}
            </PageHeader>

            {pendingCount > 0 && (
                <div className="mb-4 flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3">
                    <Clock3 className="mt-0.5 h-4 w-4 shrink-0 text-warning" />
                    <p className="text-sm text-text">
                        <span className="font-semibold">{pendingCount} lokasi</span>{' '}
                        menunggu persetujuan. Setelah disetujui, lokasi langsung
                        tersedia di dropdown venue.
                    </p>
                </div>
            )}

            <div className="mb-4 grid gap-3 sm:grid-cols-[minmax(12rem,16rem)_minmax(0,1fr)]">
                <label className="space-y-1 text-xs font-medium text-muted">
                    Tanggal jadwal
                    <Input type="date" value={selectedDate} onChange={(event) => changeDate(event.target.value)} />
                </label>
                <div>
                    <SearchInput
                        placeholder="Cari nama venue, NPSN, kota, atau provinsi…"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>
            </div>

            <CardTable>
                {venues.length === 0 ? (
                    <EmptyState
                        title="Belum ada lokasi terjadwal"
                        description={`Tidak ada FJP atau event yang memakai venue pada ${selectedDate}.`}
                    />
                ) : filteredVenues.length === 0 ? (
                    <EmptyState title="Lokasi tidak ditemukan" description="Coba kata kunci lain." />
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <THead>
                                <TR>
                                    <TH>Nama</TH>
                                    <TH>NPSN</TH>
                                    <TH>Kota</TH>
                                    <TH>Provinsi</TH>
                                    <TH>FJP</TH>
                                    <TH>Event</TH>
                                    <TH>Status</TH>
                                    <TH className="text-right">Aksi</TH>
                                </TR>
                            </THead>
                            <TBody>
                                {filteredVenues.map((venue) => (
                                    <TR key={venue.id}>
                                        <TD>
                                            <div className="flex items-center gap-2">
                                                <MapPin className="h-4 w-4 shrink-0 text-primary" />
                                                <div>
                                                    <p className="font-medium text-text">{venue.name}</p>
                                                    <p className="text-xs text-muted">
                                                        {venue.is_school ? 'Sekolah' : 'Non-Sekolah'}
                                                    </p>
                                                </div>
                                            </div>
                                        </TD>
                                        <TD>{venue.npsn ?? '-'}</TD>
                                        <TD>{venue.city ?? '-'}</TD>
                                        <TD>{venue.province ?? '-'}</TD>
                                        <TD>{venue.fjps_count}</TD>
                                        <TD>{venue.events_count}</TD>
                                        <TD>
                                            <StatusBadge status={venue.status} label={venue.status_label} />
                                        </TD>
                                        <TD className="text-right">
                                            {canApprove && venue.status === 'pending' ? (
                                                <Link href={route('verifications.index', { type: 'venue', status: 'pending' })}>
                                                    <Button size="sm" variant="primary">Buka Verifikasi</Button>
                                                </Link>
                                            ) : venue.approved_by ? (
                                                <span className="text-xs text-muted">oleh {venue.approved_by}</span>
                                            ) : (
                                                <span className="text-xs text-muted">-</span>
                                            )}
                                        </TD>
                                    </TR>
                                ))}
                            </TBody>
                        </Table>
                    </div>
                )}
            </CardTable>
            <Modal
                open={createOpen}
                onClose={() => setCreateOpen(false)}
                size="xl"
                title="Tambah Venue"
                description="Tambahkan lokasi event baru. Venue yang dibuat di sini langsung disetujui."
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setCreateOpen(false)}>Batal</Button>
                        <Button type="submit" form="venue-create-form" variant="primary" size="sm" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan'}</Button>
                    </>
                }
            >
                <form id="venue-create-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <label className="space-y-1 text-xs font-medium text-muted sm:col-span-2">Nama Venue
                        <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                        {form.errors.name && <span className="block text-danger">{form.errors.name}</span>}
                    </label>
                    <label className="space-y-1 text-xs font-medium text-muted">Jenis Lokasi
                        <Select value={form.data.is_school ? 'school' : 'venue'} onChange={(event) => form.setData('is_school', event.target.value === 'school')}>
                            <option value="venue">Non-Sekolah</option><option value="school">Sekolah</option>
                        </Select>
                    </label>
                    {form.data.is_school && <label className="space-y-1 text-xs font-medium text-muted">NPSN
                        <Input value={form.data.npsn} onChange={(event) => form.setData('npsn', event.target.value)} />
                        {form.errors.npsn && <span className="block text-danger">{form.errors.npsn}</span>}
                    </label>}
                    <label className="space-y-1 text-xs font-medium text-muted sm:col-span-2">Alamat
                        <Input value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} />
                    </label>
                    <label className="space-y-1 text-xs font-medium text-muted">Kota
                        <Input value={form.data.city} onChange={(event) => form.setData('city', event.target.value)} />
                    </label>
                    <label className="space-y-1 text-xs font-medium text-muted">Provinsi
                        <Input value={form.data.province} onChange={(event) => form.setData('province', event.target.value)} />
                    </label>
                    <label className="space-y-1 text-xs font-medium text-muted">Nama Kontak
                        <Input value={form.data.contact_name} onChange={(event) => form.setData('contact_name', event.target.value)} />
                    </label>
                    <label className="space-y-1 text-xs font-medium text-muted">Telepon Kontak
                        <Input value={form.data.contact_phone} onChange={(event) => form.setData('contact_phone', event.target.value)} />
                    </label>
                    <label className="space-y-1 text-xs font-medium text-muted">Latitude
                        <Input type="number" step="any" value={form.data.latitude} onChange={(event) => form.setData('latitude', event.target.value)} />
                        {form.errors.latitude && <span className="block text-danger">{form.errors.latitude}</span>}
                    </label>
                    <label className="space-y-1 text-xs font-medium text-muted">Longitude
                        <Input type="number" step="any" value={form.data.longitude} onChange={(event) => form.setData('longitude', event.target.value)} />
                        {form.errors.longitude && <span className="block text-danger">{form.errors.longitude}</span>}
                    </label>
                </form>
            </Modal>
        </AppLayout>
    );
}
