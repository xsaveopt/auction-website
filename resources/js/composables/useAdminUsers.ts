import { ref } from "vue";
import { api } from "../lib/api";
import type { User } from "../lib/types";

export function useAdminUsers() {
    const users = ref<User[]>([]);
    const usersLoaded = ref(false);

    async function loadUsers() {
        if (usersLoaded.value) return;
        try {
            const data = await api<{ users: User[] }>("/admin/users");
            users.value = data.users;
            usersLoaded.value = true;
        } catch {}
    }

    return { users, usersLoaded, loadUsers };
}
