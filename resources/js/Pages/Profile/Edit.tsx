import { Card } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AppLayout title="Profil">
            <Head title="Profil" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div>
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">
                        Profil
                    </h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Kelola informasi akun, password, dan keamanan akun Anda.
                    </p>
                </div>

                <Card className="p-5 sm:p-6">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                    />
                </Card>

                <Card className="p-5 sm:p-6">
                    <UpdatePasswordForm />
                </Card>

                <Card className="p-5 sm:p-6">
                    <DeleteUserForm />
                </Card>
            </div>
        </AppLayout>
    );
}
