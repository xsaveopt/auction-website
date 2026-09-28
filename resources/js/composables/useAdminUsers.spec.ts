import { describe, it, expect, vi, beforeEach } from "vitest";
import { alice, bob } from "../testing";

const { apiMock } = vi.hoisted(() => ({ apiMock: vi.fn() }));

vi.mock("../lib/api", () => ({ api: apiMock }));

import { useAdminUsers } from "./useAdminUsers";

describe("useAdminUsers", () => {
    beforeEach(() => {
        apiMock.mockReset();
    });

    it("loads users only once", async () => {
        const { users, usersLoaded, loadUsers } = useAdminUsers();
        apiMock.mockResolvedValueOnce({ users: [alice, bob] });

        await loadUsers();
        await loadUsers();

        expect(users.value).toHaveLength(2);
        expect(usersLoaded.value).toBe(true);
        expect(apiMock).toHaveBeenCalledTimes(1);
        expect(apiMock).toHaveBeenCalledWith("/admin/users");
    });

    it("retries after a failed load", async () => {
        const { users, usersLoaded, loadUsers } = useAdminUsers();
        apiMock.mockRejectedValueOnce(new Error("offline"));
        await loadUsers();
        expect(usersLoaded.value).toBe(false);

        apiMock.mockResolvedValueOnce({ users: [alice] });
        await loadUsers();
        expect(users.value).toEqual([alice]);
    });
});
