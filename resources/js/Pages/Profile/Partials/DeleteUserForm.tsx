import { Button, Field, Input, Modal } from '@/Components/ui';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

const FORM_ID = 'delete-user-form';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    return (
        <section className="space-y-5">
            <header>
                <h2 className="text-base font-semibold text-slate-900">
                    Hapus Akun
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                    Setelah akun dihapus, seluruh sumber daya dan datanya akan
                    dihapus permanen. Sebelum menghapus, unduh dulu data yang
                    ingin Anda simpan.
                </p>
            </header>

            <Button
                type="button"
                variant="destructive"
                onClick={() => setConfirmingUserDeletion(true)}
            >
                Hapus Akun
            </Button>

            <Modal
                open={confirmingUserDeletion}
                onClose={closeModal}
                title="Yakin ingin menghapus akun Anda?"
                description="Setelah akun dihapus, seluruh data akan hilang permanen. Masukkan password untuk mengonfirmasi."
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={closeModal}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form={FORM_ID}
                            variant="destructive"
                            size="sm"
                            disabled={processing}
                        >
                            {processing ? 'Menghapus…' : 'Hapus Akun'}
                        </Button>
                    </>
                }
            >
                <form id={FORM_ID} onSubmit={deleteUser}>
                    <Field label="Password" error={errors.password}>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            autoComplete="current-password"
                            placeholder="Masukkan password"
                        />
                    </Field>
                </form>
            </Modal>
        </section>
    );
}
