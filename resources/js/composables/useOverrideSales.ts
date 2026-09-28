import { computed, ref } from "vue";
import { api } from "../lib/api";
import { apiError } from "../lib/apiError";
import { injectNotifyOptional } from "../lib/injection";
import type { Auction, ConfirmDialogState, Id, OverrideSale } from "../lib/types";

export interface OverrideSaleLine {
    auction: Auction;
    quantity: number;
    price: string;
}

export function overrideSaleQuoteUrl(id: Id) {
    return `/api/override-sales/${id}/quotes`;
}

export function useOverrideSales(options: {
    confirm: (dialog: ConfirmDialogState) => void;
    onChange: () => void | Promise<void>;
}) {
    const notify = injectNotifyOptional();
    const sales = ref<OverrideSale[]>([]);
    const lines = ref<Record<Id, OverrideSaleLine>>({});
    const username = ref("");
    const error = ref("");
    const saving = ref(false);

    const selectedLines = computed(() => Object.values(lines.value));
    const total = computed(() =>
        selectedLines.value.reduce(
            (sum, line) => sum + Number(line.quantity || 0) * Number(line.price || 0),
            0,
        ),
    );

    function isSelected(auction: Auction) {
        return auction.id in lines.value;
    }

    function toggle(auction: Auction) {
        if (isSelected(auction)) {
            delete lines.value[auction.id];
            return;
        }
        lines.value[auction.id] = {
            auction,
            quantity: 1,
            price: String(auction.leftover_price ?? auction.starting_price ?? ""),
        };
    }

    function clear() {
        lines.value = {};
        username.value = "";
        error.value = "";
    }

    async function loadSales() {
        try {
            const data = await api<{ override_sales: OverrideSale[] }>("/admin/override-sales");
            sales.value = data.override_sales;
        } catch {
            notify?.("Failed to load override sales.", "error");
        }
    }

    async function submit() {
        error.value = "";
        saving.value = true;
        try {
            const data = await api<{ override_sale: OverrideSale }>("/admin/override-sales", {
                method: "POST",
                body: JSON.stringify({
                    username: username.value,
                    items: selectedLines.value.map((line) => ({
                        auction_id: line.auction.id,
                        quantity: Number(line.quantity),
                        price_per_item: line.price,
                    })),
                }),
            });
            sales.value = [data.override_sale, ...sales.value];
            clear();
            notify?.("Override sale created.", "success");
            await options.onChange();
        } catch (e) {
            error.value = apiError(e, "username", "items") || "Failed to create override sale.";
        } finally {
            saving.value = false;
        }
    }

    function remove(sale: OverrideSale) {
        options.confirm({
            message: `Delete override sale #${sale.id} to ${sale.user.username}? Its items return to the leftovers.`,
            confirmLabel: "Delete",
            danger: true,
            onConfirm: async () => {
                try {
                    await api(`/admin/override-sales/${sale.id}`, { method: "DELETE" });
                    sales.value = sales.value.filter((s) => s.id !== sale.id);
                    notify?.("Override sale deleted.", "success");
                    await options.onChange();
                } catch {
                    notify?.("Failed to delete override sale.", "error");
                }
            },
        });
    }

    return {
        sales,
        lines,
        selectedLines,
        username,
        error,
        saving,
        total,
        isSelected,
        toggle,
        clear,
        loadSales,
        submit,
        remove,
    };
}
