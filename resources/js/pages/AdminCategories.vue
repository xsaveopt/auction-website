<script setup>
import { ref, inject, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api } from "../api.js";

const router = useRouter();
const user = inject("user");
const categories = ref([]);
const loading = ref(true);
const newName = ref("");
const adding = ref(false);
const editingId = ref(null);
const editName = ref("");
const editOrder = ref(0);
const saving = ref(false);

onMounted(async () => {
    if (!user.value?.is_admin) {
        router.push("/");
        return;
    }
    await loadCategories();
});

async function loadCategories() {
    const data = await api("/categories");
    categories.value = data.categories;
    loading.value = false;
}

async function addCategory() {
    if (!newName.value.trim()) return;
    adding.value = true;
    await api("/categories", {
        method: "POST",
        body: JSON.stringify({
            name: newName.value.trim(),
            sort_order: categories.value.length,
        }),
    }).catch(() => null);
    newName.value = "";
    adding.value = false;
    await loadCategories();
}

function startEdit(cat) {
    editingId.value = cat.id;
    editName.value = cat.name;
    editOrder.value = cat.sort_order;
}

function cancelEdit() {
    editingId.value = null;
    editName.value = "";
    editOrder.value = 0;
}

async function saveEdit() {
    if (!editName.value.trim()) return;
    saving.value = true;
    await api(`/categories/${editingId.value}`, {
        method: "PUT",
        body: JSON.stringify({
            name: editName.value.trim(),
            sort_order: Number(editOrder.value),
        }),
    }).catch(() => null);
    saving.value = false;
    editingId.value = null;
    await loadCategories();
}

async function deleteCategory(cat) {
    if (!confirm(`Delete "${cat.name}"? Auctions in this category will become uncategorized.`)) {
        return;
    }
    await api(`/categories/${cat.id}`, { method: "DELETE" }).catch(() => null);
    await loadCategories();
}
</script>

<template>
    <div class="max-w-lg mx-auto">
        <h1 class="text-2xl font-bold mb-4">Manage Categories</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            Categories group auctions on the main page. Lower sort order appears first.
        </p>

        <div v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</div>
        <template v-else>
            <!-- Add new category -->
            <form @submit.prevent="addCategory" class="flex gap-2 mb-6">
                <input
                    v-model="newName"
                    type="text"
                    placeholder="New category name"
                    required
                    class="flex-1 border rounded px-3 py-2"
                />
                <button
                    type="submit"
                    :disabled="adding || !newName.trim()"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ adding ? "Adding..." : "Add" }}
                </button>
            </form>

            <!-- Category list -->
            <div v-if="categories.length === 0" class="text-gray-500 dark:text-gray-400">
                No categories yet.
            </div>
            <ul v-else class="space-y-2">
                <li
                    v-for="cat in categories"
                    :key="cat.id"
                    class="bg-white dark:bg-gray-800 rounded shadow p-4"
                >
                    <!-- View mode -->
                    <div v-if="editingId !== cat.id" class="flex items-center justify-between">
                        <div>
                            <span class="font-medium">{{ cat.name }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-2"
                                >order: {{ cat.sort_order }}</span
                            >
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="startEdit(cat)"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                            >
                                Edit
                            </button>
                            <button
                                @click="deleteCategory(cat)"
                                class="text-sm text-red-600 dark:text-red-400 hover:underline"
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Edit mode -->
                    <form v-else @submit.prevent="saveEdit" class="space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Name</label
                                >
                                <input
                                    v-model="editName"
                                    type="text"
                                    required
                                    class="w-full border rounded px-3 py-2"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1"
                                    >Sort Order</label
                                >
                                <input
                                    v-model="editOrder"
                                    type="number"
                                    class="w-full border rounded px-3 py-2"
                                />
                            </div>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button
                                type="button"
                                @click="cancelEdit"
                                class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="saving || !editName.trim()"
                                class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ saving ? "Saving..." : "Save" }}
                            </button>
                        </div>
                    </form>
                </li>
            </ul>
        </template>
    </div>
</template>
