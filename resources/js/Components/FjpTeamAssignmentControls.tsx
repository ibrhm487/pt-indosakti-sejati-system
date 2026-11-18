import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Pencil, Trash2 } from 'lucide-react';
import { ConfirmButton } from './AppBits';
import { Button, Field, Modal, Select } from './ui';

export type FjpTeamMember = {
    personnel_id: number | null;
    user_id: number | null;
    position: string;
    name: string;
    phone: string | null;
};

export type TeamPersonnelOption = {
    id: number;
    user_id: number;
    name: string;
    role: string;
    phone: string | null;
};

function personnelIdForMember(member: FjpTeamMember, personnel: TeamPersonnelOption[]): string {
    const personnelOption = personnel.find((person) => (
        person.id === member.personnel_id || person.user_id === member.user_id
    ));

    return personnelOption ? String(personnelOption.id) : '';
}

export function PermitTeamAssignment({
    fjpId,
    teamGroupLabel,
}: {
    fjpId: number;
    teamGroupLabel: string | null;
}) {
    const currentTeamNumber = teamGroupLabel?.match(/\d+/)?.[0] ?? '';
    const [open, setOpen] = useState(false);
    const [selectedTeamNumber, setSelectedTeamNumber] = useState(currentTeamNumber);
    const [saving, setSaving] = useState(false);
    const [feedback, setFeedback] = useState('');

    useEffect(() => {
        setSelectedTeamNumber(currentTeamNumber);
    }, [currentTeamNumber]);

    const save = () => {
        if (saving || selectedTeamNumber === '' || selectedTeamNumber === currentTeamNumber) {
            return;
        }

        setSaving(true);
        setFeedback('');
        router.patch(
            route('fjp.inline', fjpId),
            { team_number: Number(selectedTeamNumber) },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setOpen(false),
                onError: () => setFeedback('Tim gagal disimpan. Coba lagi.'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Button
                type="button"
                variant={currentTeamNumber ? 'outline' : 'primary'}
                size="sm"
                onClick={() => setOpen(true)}
            >
                {currentTeamNumber ? 'Ubah Tim' : 'Pembagian Tim'}
            </Button>
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="Pembagian Tim"
                description="Pilih kelompok tim untuk FJP ini. PIC hanya dapat membagikan anggota setelah tim ditetapkan."
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={saving || selectedTeamNumber === '' || selectedTeamNumber === currentTeamNumber}
                            onClick={save}
                        >
                            {saving ? 'Menyimpan...' : 'Simpan tim'}
                        </Button>
                    </>
                }
            >
                <Field label="Tim yang ditugaskan">
                    <Select
                        value={selectedTeamNumber}
                        disabled={saving}
                        onChange={(event) => setSelectedTeamNumber(event.target.value)}
                    >
                        <option value="">Pilih tim</option>
                        {Array.from({ length: 25 }, (_, index) => String(index + 1)).map((teamNumber) => (
                            <option key={teamNumber} value={teamNumber}>Tim {teamNumber}</option>
                        ))}
                    </Select>
                </Field>
                {feedback && <p role="alert" className="mt-3 text-sm text-danger">{feedback}</p>}
            </Modal>
        </>
    );
}

export function TeamGroupRoster({
    teamGroupId,
    fjpIds,
    teamGroupLabel,
    members,
    personnel,
    editable,
    hasExistingEvent,
    onSaved,
}: {
    teamGroupId: string;
    fjpIds: number[];
    teamGroupLabel: string;
    members: FjpTeamMember[];
    personnel: TeamPersonnelOption[];
    editable: boolean;
    hasExistingEvent: boolean;
    onSaved?: () => void;
}) {
    const [teamLeaderId, setTeamLeaderId] = useState('');
    const [spgPersonnelId, setSpgPersonnelId] = useState('');
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [feedback, setFeedback] = useState('');
    const hasRoster = members.length > 0;

    useEffect(() => {
        const leader = members.find((member) => member.position === 'Team Leader');
        setTeamLeaderId(leader ? personnelIdForMember(leader, personnel) : '');
        const spg = members.find((member) => member.position === 'SPG');
        setSpgPersonnelId(spg ? personnelIdForMember(spg, personnel) : '');
        setFeedback('');
    }, [members, personnel]);

    const save = () => {
        if (saving || !teamLeaderId || !spgPersonnelId || spgPersonnelId === teamLeaderId) {
            return;
        }

        setSaving(true);
        setFeedback('');
        const payload = {
            team_leader_personnel_id: Number(teamLeaderId),
            spg_personnel_ids: [Number(spgPersonnelId)],
        };
        const options = {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setOpen(false);
                onSaved?.();
            },
            onError: () => setFeedback('Gagal menyimpan pembagian anggota tim.'),
            onFinish: () => setSaving(false),
        };

        if (hasExistingEvent) {
            router.patch(route('fjp.team-group.update', teamGroupId), payload, options);
        } else {
            router.post(route('fjp.team-group'), { fjp_ids: fjpIds, ...payload }, options);
        }
    };

    return (
        <div className="space-y-2">
            <table className="w-full min-w-[560px] border-collapse text-xs [&_td]:border [&_td]:border-border [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-border [&_th]:bg-page [&_th]:px-2 [&_th]:py-2 [&_th]:text-left">
                <thead>
                    <tr>
                        <th>Kelompok tim</th>
                        <th>Nama personel</th>
                        <th>Jabatan</th>
                        <th>Kontak</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    {hasRoster ? members.map((member, index) => {
                        const personnelId = personnelIdForMember(member, personnel);

                        return (
                            <tr key={`${member.position}-${member.personnel_id ?? member.user_id ?? index}`}>
                                <td>{teamGroupLabel}</td>
                                <td className="font-medium text-text">{member.name}</td>
                                <td>{member.position}</td>
                                <td>{member.phone || '—'}</td>
                                <td>
                                    {editable && (
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                aria-label={`Ubah pembagian ${member.name}`}
                                                onClick={() => setOpen(true)}
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                            </Button>
                                            {personnelId !== '' && (
                                                <ConfirmButton
                                                    title="Hapus Personel"
                                                    description={`Hapus ${member.name} dari ${teamGroupLabel}?`}
                                                    onConfirm={() => router.delete(
                                                        route('fjp.team-group.members.destroy', {
                                                            teamGroupId,
                                                            personnel: personnelId,
                                                        }),
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () => onSaved?.(),
                                                            onError: (errors) => setFeedback(Object.values(errors)[0] ?? 'Gagal menghapus personel.'),
                                                        },
                                                    )}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </ConfirmButton>
                                            )}
                                        </div>
                                    )}
                                </td>
                            </tr>
                        );
                    }) : (
                        <tr>
                            <td colSpan={5} className="text-center text-muted">
                                Belum ada personel yang ditugaskan.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
            <Button type="button" size="sm" disabled={!editable} onClick={() => setOpen(true)}>
                {hasRoster ? 'Ubah Anggota Tim' : 'Pembagian Anggota Tim'}
            </Button>
            {personnel.length === 0 && (
                <p role="alert" className="text-xs text-warning">
                    Belum ada personel aktif yang terhubung ke akun role Tim. Hubungkan akun melalui menu Personel sebelum membagikan anggota.
                </p>
            )}
            {feedback && <p role="alert" className="text-xs text-danger">{feedback}</p>}
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="Pembagian Anggota Tim"
                description={`${teamGroupLabel} · ${fjpIds.length} FJP`}
                footer={
                    <>
                        <Button type="button" variant="outline" size="sm" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            disabled={saving || !teamLeaderId || !spgPersonnelId || spgPersonnelId === teamLeaderId}
                            onClick={save}
                        >
                            {saving ? 'Menyimpan...' : 'Simpan pembagian'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    {personnel.length === 0 && (
                        <p role="alert" className="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                            Belum ada personel aktif yang terhubung ke akun role Tim. Hubungkan akun melalui menu Personel sebelum membagikan anggota.
                        </p>
                    )}
                    <Field label="Team Leader">
                        <Select
                            value={teamLeaderId}
                            disabled={saving}
                            aria-label="Pilih Team Leader"
                            onChange={(event) => setTeamLeaderId(event.target.value)}
                        >
                            <option value="">Pilih Team Leader</option>
                            {personnel.filter((person) => person.role.trim().toLowerCase() === 'team leader').map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}{person.phone ? ` · ${person.phone}` : ''}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="SPG">
                        <Select
                            value={spgPersonnelId}
                            disabled={saving}
                            aria-label="Pilih SPG"
                            onChange={(event) => setSpgPersonnelId(event.target.value)}
                        >
                            <option value="">Pilih SPG</option>
                            {personnel.filter((person) => person.role.trim().toLowerCase() === 'spg').map((person) => (
                                <option key={person.id} value={person.id} disabled={String(person.id) === teamLeaderId}>
                                    {person.name}{person.phone ? ` · ${person.phone}` : ''}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    {feedback && <p role="alert" className="text-sm text-danger">{feedback}</p>}
                </div>
            </Modal>
        </div>
    );
}
