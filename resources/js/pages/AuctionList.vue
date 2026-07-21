<script setup lang="ts">
import { getItemLabel } from "../auctionPresentation";
import { useAuctionList } from "../composables/useAuctionList";

const {
    auctions,
    allRounds,
    loading,
    currencySymbol,
    user,
    now,
    currentRound,
    selectedRoundId,
    announcement,
    editingAnnouncement,
    announcementDraft,
    announcementSaving,
    saveAnnouncement,
    removeAnnouncement,
    startEditAnnouncement,
    watchingText,
    roundClosed,
    isLeftoverSale,
    priceLabel,
    priceValue,
    leftoverDiscountText,
    statusText,
    showSoldOut,
    selectedLocation,
    hiddenEndedCount,
    availableLocations,
    groupedAuctions,
    timeLeft,
} = useAuctionList();
</script>

<template>
    <div>
        <p v-if="loading" class="text-gray-500 dark:text-gray-400">Loading...</p>
        <template v-else>
            <!-- Round ended banner -->
            <div
                v-if="currentRound && !currentRound.active && currentRound.ended?.length > 0"
                class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-4 flex items-start gap-3"
            >
                <svg
                    class="w-5 h-5 text-amber-500 dark:text-amber-400 mt-0.5 shrink-0"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <div>
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                        The "{{ currentRound.ended[0].name }}" auction round has ended.
                    </p>
                </div>
            </div>

            <!-- Round selector -->
            <div v-if="allRounds.length > 0" class="flex items-center gap-2 mb-4">
                <label class="text-sm text-gray-500 dark:text-gray-400 shrink-0">Round:</label>
                <select
                    v-model="selectedRoundId"
                    class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200"
                >
                    <option :value="null">All rounds</option>
                    <option v-for="r in allRounds" :key="r.id" :value="r.id">
                        {{ r.name }}
                    </option>
                </select>
            </div>

            <!-- Announcement Board -->
            <div
                v-if="announcement && !editingAnnouncement"
                class="mb-6 bg-white dark:bg-gray-800 rounded shadow border-l-4 border-blue-500 dark:border-blue-400 p-4"
            >
                <div class="flex items-start gap-3">
                    <svg
                        class="w-5 h-5 text-blue-500 dark:text-blue-400 mt-0.5 shrink-0"
                        fill="currentColor"
                        viewBox="0 0 20 20"
                    >
                        <path
                            d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"
                        />
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">
                            {{ announcement.message }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Posted by {{ announcement.author }}
                        </p>
                    </div>
                    <div v-if="user?.is_admin" class="flex gap-1 shrink-0">
                        <button
                            @click="startEditAnnouncement"
                            class="text-gray-400 dark:text-gray-500 hover:text-blue-600 dark:hover:text-blue-400 p-1"
                            title="Edit"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                />
                            </svg>
                        </button>
                        <button
                            @click="removeAnnouncement"
                            class="text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 p-1"
                            title="Remove"
                        >
                            <svg
                                class="w-4 h-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Announcement Editor (admin) -->
            <div
                v-if="editingAnnouncement && user?.is_admin"
                class="mb-6 bg-white dark:bg-gray-800 rounded shadow border-l-4 border-blue-500 dark:border-blue-400 p-4"
            >
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                    >Announcement</label
                >
                <textarea
                    v-model="announcementDraft"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Write an announcement for all users..."
                ></textarea>
                <div class="flex items-center justify-between mt-2">
                    <span class="text-xs text-gray-400 dark:text-gray-500"
                        >{{ announcementDraft.length }}/1000</span
                    >
                    <div class="flex gap-2">
                        <button
                            @click="editingAnnouncement = false"
                            class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200"
                        >
                            Cancel
                        </button>
                        <button
                            @click="saveAnnouncement"
                            :disabled="!announcementDraft.trim() || announcementSaving"
                            class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ announcementSaving ? "Saving..." : "Publish" }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Toolbar: admin announcement link (left) + filters (right) -->
            <div
                class="mb-4 flex items-center justify-between gap-3 flex-wrap"
                v-if="
                    (!announcement && !editingAnnouncement && user?.is_admin) ||
                    hiddenEndedCount > 0 ||
                    showSoldOut ||
                    availableLocations.length > 0
                "
            >
                <button
                    v-if="!announcement && !editingAnnouncement && user?.is_admin"
                    @click="startEditAnnouncement"
                    class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center gap-1"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    Add announcement
                </button>
                <span v-else />

                <div class="flex items-center gap-2 ml-auto flex-wrap">
                    <select
                        v-if="availableLocations.length > 0"
                        v-model="selectedLocation"
                        class="text-sm border border-gray-300 dark:border-gray-600 rounded px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option :value="null">All locations</option>
                        <option v-for="loc in availableLocations" :key="loc" :value="loc">
                            {{ loc }}
                        </option>
                    </select>

                    <button
                        v-if="hiddenEndedCount > 0 || showSoldOut"
                        @click="showSoldOut = !showSoldOut"
                        class="text-sm font-medium border border-gray-300 dark:border-gray-600 rounded px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                    >
                        <span v-if="!showSoldOut">Show {{ hiddenEndedCount }} ended</span>
                        <span v-else>Hide ended</span>
                    </button>
                </div>
            </div>

            <!-- Auction groups by category — 3-column pane layout -->
            <div v-if="groupedAuctions.length === 0" class="text-gray-500 dark:text-gray-400">
                No auctions yet{{ selectedRoundId !== null ? " for this round" : "" }}.
            </div>
            <div v-else class="grid gap-6 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
                <section v-for="group in groupedAuctions" :key="group.slug" class="min-w-0">
                    <h2
                        class="text-lg font-bold mb-3 pb-2 border-b border-gray-200 dark:border-gray-700"
                    >
                        {{ group.name }}
                    </h2>
                    <div class="space-y-4">
                        <router-link
                            v-for="auction in group.auctions"
                            :key="auction.id"
                            :to="`/auctions/${auction.id}`"
                            class="block bg-white dark:bg-gray-800 rounded shadow overflow-hidden hover:shadow-md dark:hover:shadow-gray-900 transition-shadow"
                        >
                            <img
                                v-if="auction.images.length"
                                :src="auction.images[0].url"
                                :alt="auction.title"
                                class="w-full h-36 object-cover"
                            />
                            <div class="p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-semibold truncate">
                                        {{ auction.title }}
                                    </h3>
                                    <span
                                        class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                        :class="
                                            auction.is_active
                                                ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                                : isLeftoverSale(auction)
                                                  ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300'
                                                  : auction.leftover_enabled &&
                                                      auction.leftover_quantity === 0 &&
                                                      !roundClosed(auction)
                                                    ? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                        "
                                    >
                                        {{ statusText(auction) }}
                                    </span>
                                </div>
                                <p
                                    class="text-gray-600 dark:text-gray-400 text-sm mt-1 line-clamp-2"
                                >
                                    {{ auction.description }}
                                </p>
                                <div
                                    class="mt-2 text-sm font-medium text-amber-600 dark:text-amber-400"
                                >
                                    {{ watchingText(auction.watcher_count ?? 0) }}
                                </div>
                                <div
                                    class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"
                                        >
                                            {{ priceLabel(auction) }}
                                        </p>
                                        <p class="mt-1 font-bold text-gray-800 dark:text-gray-100">
                                            {{ currencySymbol
                                            }}{{ Number(priceValue(auction)).toFixed(2) }}
                                            <span
                                                class="text-sm font-medium text-gray-500 dark:text-gray-400"
                                            >
                                                / item
                                            </span>
                                        </p>
                                        <p
                                            v-if="isLeftoverSale(auction)"
                                            class="mt-1 text-xs text-green-700 dark:text-green-300"
                                        >
                                            <span class="font-semibold">
                                                {{ getItemLabel(auction.leftover_quantity) }} left
                                            </span>
                                            <span v-if="leftoverDiscountText(auction)">
                                                · {{ leftoverDiscountText(auction) }} off the
                                                original price
                                            </span>
                                        </p>
                                    </div>
                                    <span
                                        v-if="auction.is_active || isLeftoverSale(auction)"
                                        class="text-sm"
                                    >
                                        <template v-if="auction.is_active">
                                            <span class="text-gray-600 dark:text-gray-300">{{
                                                timeLeft(auction.ends_at)
                                            }}</span>
                                        </template>
                                        <template v-else>
                                            <span
                                                class="font-medium text-green-600 dark:text-green-400"
                                                >Buy now available</span
                                            >
                                        </template>
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    <span
                                        v-if="auction.quantity > 1"
                                        class="text-gray-500 dark:text-gray-400 font-medium"
                                        >{{ auction.quantity }} items ·
                                    </span>
                                    {{ auction.bid_count }}
                                    bid{{ auction.bid_count !== 1 ? "s" : "" }}
                                </div>
                            </div>
                        </router-link>
                    </div>
                </section>
            </div>
        </template>
    </div>
</template>
