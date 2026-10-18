import UserForm, { RoleOption } from '@/Components/UserForm';

export default function AdminUsersForm({
    user,
    roles,
}: {
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        is_active: boolean;
        active_role_id: number | null;
        roles: number[];
    } | null;
    roles: RoleOption[];
}) {
    return <UserForm user={user} roles={roles} />;
}
